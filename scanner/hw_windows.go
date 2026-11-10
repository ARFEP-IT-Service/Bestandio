package main

import (
	"encoding/binary"
	"fmt"
	"strconv"
	"strings"
	"syscall"
	"unsafe"
)

// Hardware is read with documented Windows APIs only, without WMI/COM or external programs:
//   - SMBIOS table via GetSystemFirmwareTable('RSMB') for manufacturer, model, serial number,
//   - registry for CPU name and Windows version,
//   - GetPhysicallyInstalledSystemMemory for RAM,
//   - DeviceIoControl on \\.\PhysicalDriveN (query-only access, no admin rights) for disks.

var (
	kernel32                               = syscall.NewLazyDLL("kernel32.dll")
	procGetSystemFirmwareTable             = kernel32.NewProc("GetSystemFirmwareTable")
	procGetPhysicallyInstalledSystemMemory = kernel32.NewProc("GetPhysicallyInstalledSystemMemory")
	procGlobalMemoryStatusEx               = kernel32.NewProc("GlobalMemoryStatusEx")
	procDeviceIoControl                    = kernel32.NewProc("DeviceIoControl")
)

const (
	keyQueryValue       = 0x0001
	keyEnumerateSubKeys = 0x0008
	keyWow6464Key       = 0x0100
	keyRead             = 0x20019

	ioctlStorageQueryProperty   = 0x2D1400
	ioctlDiskGetDriveGeometryEx = 0x700A0
	storageDeviceProperty       = 0
	storageDeviceSeekPenalty    = 7
)

func readSMBIOS() []smbiosStruct {
	const rsmb = 'R'<<24 | 'S'<<16 | 'M'<<8 | 'B'
	n, _, _ := procGetSystemFirmwareTable.Call(rsmb, 0, 0, 0)
	if n == 0 {
		return nil
	}
	buf := make([]byte, n)
	got, _, _ := procGetSystemFirmwareTable.Call(rsmb, 0, uintptr(unsafe.Pointer(&buf[0])), n)
	if got == 0 || got > n {
		return nil
	}
	return parseSMBIOS(buf[:got])
}

// regString reads a string value; view64 selects the 64-bit registry view.
func regString(root syscall.Handle, path, name string) string {
	var k syscall.Handle
	p, _ := syscall.UTF16PtrFromString(path)
	if syscall.RegOpenKeyEx(root, p, 0, keyRead|keyWow6464Key, &k) != nil {
		return ""
	}
	defer syscall.RegCloseKey(k)
	return regValue(k, name)
}

func regValue(k syscall.Handle, name string) string {
	n, _ := syscall.UTF16PtrFromString(name)
	var typ, size uint32
	if syscall.RegQueryValueEx(k, n, nil, &typ, nil, &size) != nil || size == 0 {
		return ""
	}
	buf := make([]uint16, size/2+1)
	if syscall.RegQueryValueEx(k, n, nil, &typ, (*byte)(unsafe.Pointer(&buf[0])), &size) != nil {
		return ""
	}
	switch typ {
	case syscall.REG_SZ, syscall.REG_EXPAND_SZ:
		return strings.TrimSpace(syscall.UTF16ToString(buf))
	case syscall.REG_DWORD:
		return strconv.FormatUint(uint64(binary.LittleEndian.Uint32((*[4]byte)(unsafe.Pointer(&buf[0]))[:])), 10)
	}
	return ""
}

func cpuName() string {
	return regString(syscall.HKEY_LOCAL_MACHINE, `HARDWARE\DESCRIPTION\System\CentralProcessor\0`, "ProcessorNameString")
}

// ramMB returns installed RAM; falls back to the memory visible to Windows.
func ramMB() int64 {
	var kb uint64
	if r, _, _ := procGetPhysicallyInstalledSystemMemory.Call(uintptr(unsafe.Pointer(&kb))); r != 0 && kb > 0 {
		return int64(kb / 1024)
	}
	var ms struct {
		Length               uint32
		MemoryLoad           uint32
		TotalPhys            uint64
		AvailPhys            uint64
		TotalPageFile        uint64
		AvailPageFile        uint64
		TotalVirtual         uint64
		AvailVirtual         uint64
		AvailExtendedVirtual uint64
	}
	ms.Length = uint32(unsafe.Sizeof(ms))
	if r, _, _ := procGlobalMemoryStatusEx.Call(uintptr(unsafe.Pointer(&ms))); r != 0 {
		return int64(ms.TotalPhys / (1024 * 1024))
	}
	return 0
}

func osInfo() *OS {
	const cv = `SOFTWARE\Microsoft\Windows NT\CurrentVersion`
	name := regString(syscall.HKEY_LOCAL_MACHINE, cv, "ProductName")
	build := regString(syscall.HKEY_LOCAL_MACHINE, cv, "CurrentBuild")
	ubr := regString(syscall.HKEY_LOCAL_MACHINE, cv, "UBR")
	display := regString(syscall.HKEY_LOCAL_MACHINE, cv, "DisplayVersion")
	// The registry still says "Windows 10" on Windows 11 (build 22000 and later).
	if b, err := strconv.Atoi(build); err == nil && b >= 22000 {
		name = strings.Replace(name, "Windows 10", "Windows 11", 1)
	}
	version := "10.0." + build
	if ubr != "" {
		version += "." + ubr
	}
	if display != "" {
		version = display + " (" + version + ")"
	}
	if build == "" {
		version = ""
	}
	return &OS{Name: name, Version: version}
}

func ioctl(h syscall.Handle, code uint32, in []byte, out []byte) bool {
	var ret uint32
	var inPtr uintptr
	if len(in) > 0 {
		inPtr = uintptr(unsafe.Pointer(&in[0]))
	}
	r, _, _ := procDeviceIoControl.Call(uintptr(h), uintptr(code), inPtr, uintptr(len(in)),
		uintptr(unsafe.Pointer(&out[0])), uintptr(len(out)), uintptr(unsafe.Pointer(&ret)), 0)
	return r != 0
}

// disks lists internal physical drives. Removable, USB, SD and virtual disks are skipped.
func disks() []Disk {
	var out []Disk
	for i := 0; i < 32; i++ {
		path, _ := syscall.UTF16PtrFromString(fmt.Sprintf(`\\.\PhysicalDrive%d`, i))
		h, err := syscall.CreateFile(path, 0, syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE, nil, syscall.OPEN_EXISTING, 0, 0)
		if err != nil {
			continue
		}
		d, ok := describeDisk(h)
		syscall.CloseHandle(h)
		if ok {
			out = append(out, d)
		}
	}
	return out
}

func describeDisk(h syscall.Handle) (Disk, bool) {
	query := make([]byte, 12) // STORAGE_PROPERTY_QUERY: PropertyId, QueryType=0, padding
	desc := make([]byte, 1024)
	binary.LittleEndian.PutUint32(query[0:], storageDeviceProperty)
	if !ioctl(h, ioctlStorageQueryProperty, query, desc) {
		return Disk{}, false
	}
	removable := desc[10] != 0
	bus := binary.LittleEndian.Uint32(desc[28:])
	switch bus {
	case 7, 12, 14, 15, 16: // USB, SD, virtual, file-backed virtual, storage spaces
		return Disk{}, false
	}
	if removable {
		return Disk{}, false
	}
	seek := make([]byte, 12)
	binary.LittleEndian.PutUint32(query[0:], storageDeviceSeekPenalty)
	known := ioctl(h, ioctlStorageQueryProperty, query, seek)
	geo := make([]byte, 256)
	if !ioctl(h, ioctlDiskGetDriveGeometryEx, nil, geo) {
		return Disk{}, false
	}
	size := int64(binary.LittleEndian.Uint64(geo[24:]))
	if size <= 0 {
		return Disk{}, false
	}
	return Disk{Type: diskType(bus, known && seek[8] != 0, known), SizeGB: sizeGB(size)}, true
}

// collectHardware gathers everything except software.
func collectHardware() (Hardware, *OS) {
	structs := readSMBIOS()
	manufacturer, product, version, serial := systemInfo(structs)
	if isPlaceholder(manufacturer) {
		manufacturer = regString(syscall.HKEY_LOCAL_MACHINE, `HARDWARE\DESCRIPTION\System\BIOS`, "SystemManufacturer")
	}
	if isPlaceholder(product) {
		product = regString(syscall.HKEY_LOCAL_MACHINE, `HARDWARE\DESCRIPTION\System\BIOS`, "SystemProductName")
	}
	model := modelName(manufacturer, product, version)
	if model == "" {
		// Self-built PCs: use the mainboard (e.g. "ROG STRIX X870E-E GAMING WIFI").
		boardMaker, boardProduct := boardInfo(structs)
		model = boardProduct
		if isPlaceholder(manufacturer) {
			manufacturer = boardMaker
		}
	}
	return Hardware{
		Manufacturer: manufacturer,
		Model:        model,
		SerialNumber: serial,
		CPU:          cpuName(),
		RAMMB:        ramMB(),
		Disks:        disks(),
	}, osInfo()
}

// modelName: Lenovo stores the readable name in the version field ("ThinkPad T14 Gen 5")
// and the machine type in the product field ("21MC0012GE").
func modelName(manufacturer, product, version string) string {
	product, version = clean(product), clean(version)
	if strings.Contains(strings.ToUpper(manufacturer), "LENOVO") && version != "" && product != "" {
		return version + " (" + product + ")"
	}
	return product
}
