package main

import (
	"strings"
	"syscall"
	"unsafe"
)

// Installed programs from the uninstall entries in the registry (64-bit, 32-bit and per-user).
// Only name, version and vendor are read. System components and updates are skipped.
func installedSoftware() []Software {
	type src struct {
		root syscall.Handle
		path string
	}
	sources := []src{
		{syscall.HKEY_LOCAL_MACHINE, `SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall`},
		{syscall.HKEY_LOCAL_MACHINE, `SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall`},
		{syscall.HKEY_CURRENT_USER, `SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall`},
	}
	var out []Software
	for _, s := range sources {
		out = append(out, readUninstall(s.root, s.path)...)
	}
	return out
}

func readUninstall(root syscall.Handle, path string) []Software {
	var k syscall.Handle
	p, _ := syscall.UTF16PtrFromString(path)
	if syscall.RegOpenKeyEx(root, p, 0, keyRead|keyWow6464Key, &k) != nil {
		return nil
	}
	defer syscall.RegCloseKey(k)
	var out []Software
	for i := uint32(0); ; i++ {
		name := make([]uint16, 512)
		n := uint32(len(name))
		if syscall.RegEnumKeyEx(k, i, &name[0], &n, nil, nil, nil, nil) != nil {
			break
		}
		var sub syscall.Handle
		if syscall.RegOpenKeyEx(k, &name[0], 0, keyRead|keyWow6464Key, &sub) != nil {
			continue
		}
		display := regValue(sub, "DisplayName")
		skip := display == "" || regValue(sub, "SystemComponent") == "1" || regValue(sub, "ParentKeyName") != ""
		release := strings.ToLower(regValue(sub, "ReleaseType"))
		if strings.Contains(release, "update") || strings.Contains(release, "hotfix") {
			skip = true
		}
		if !skip {
			out = append(out, Software{Name: display, Version: regValue(sub, "DisplayVersion"), Vendor: regValue(sub, "Publisher")})
		}
		syscall.RegCloseKey(sub)
	}
	return out
}

var (
	user32                   = syscall.NewLazyDLL("user32.dll")
	procOpenClipboard        = user32.NewProc("OpenClipboard")
	procCloseClipboard       = user32.NewProc("CloseClipboard")
	procEmptyClipboard       = user32.NewProc("EmptyClipboard")
	procSetClipboardData     = user32.NewProc("SetClipboardData")
	procGlobalAlloc          = kernel32.NewProc("GlobalAlloc")
	procGlobalLock           = kernel32.NewProc("GlobalLock")
	procGlobalUnlock         = kernel32.NewProc("GlobalUnlock")
	procGlobalFree           = kernel32.NewProc("GlobalFree")
	procGetUserDefaultUILang = kernel32.NewProc("GetUserDefaultUILanguage")
)

// copyToClipboard puts text on the Windows clipboard as CF_UNICODETEXT.
func copyToClipboard(s string) bool {
	const cfUnicodeText = 13
	const gmemMoveable = 0x0002
	u, err := syscall.UTF16FromString(s)
	if err != nil {
		return false
	}
	if r, _, _ := procOpenClipboard.Call(0); r == 0 {
		return false
	}
	defer procCloseClipboard.Call()
	procEmptyClipboard.Call()
	size := uintptr(len(u) * 2)
	mem, _, _ := procGlobalAlloc.Call(gmemMoveable, size)
	if mem == 0 {
		return false
	}
	ptr, _, _ := procGlobalLock.Call(mem)
	if ptr == 0 {
		procGlobalFree.Call(mem)
		return false
	}
	// Convert the locked handle address without a direct uintptr→Pointer cast (go vet).
	dst := *(*unsafe.Pointer)(unsafe.Pointer(&ptr))
	copy(unsafe.Slice((*uint16)(dst), len(u)), u)
	procGlobalUnlock.Call(mem)
	if r, _, _ := procSetClipboardData.Call(cfUnicodeText, mem); r == 0 {
		procGlobalFree.Call(mem)
		return false
	}
	return true
}

// uiLanguage maps the Windows display language to de, tr or en.
func uiLanguage() string {
	id, _, _ := procGetUserDefaultUILang.Call()
	switch id & 0x3ff { // primary language id
	case 0x07:
		return "de"
	case 0x1f:
		return "tr"
	}
	return "en"
}
