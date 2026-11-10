package main

import (
	"bytes"
	"encoding/json"
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
	"time"

	"bestandio/lang"
	"bestandio/scanner/internal/rsrc"
)

// smbiosTable builds a raw RSMB buffer from structures.
func smbiosTable(structs ...[]byte) []byte {
	var body []byte
	for _, s := range structs {
		body = append(body, s...)
	}
	head := []byte{0, 3, 4, 0, 0, 0, 0, 0}
	head[4] = byte(len(body))
	head[5] = byte(len(body) >> 8)
	return append(head, body...)
}

func structure(typ byte, formatted []byte, strs ...string) []byte {
	b := append([]byte{typ, byte(4 + len(formatted)), 0, 0}, formatted...)
	if len(strs) == 0 {
		return append(b, 0, 0)
	}
	for _, s := range strs {
		b = append(append(b, s...), 0)
	}
	return append(b, 0)
}

func TestParseSMBIOS(t *testing.T) {
	sys := structure(1, []byte{1, 2, 3, 4}, "LENOVO", "21MC0012GE", "ThinkPad T14 Gen 5", "PF4ABC12")
	board := structure(2, []byte{1, 0, 0, 2}, "LENOVO", "BOARD123")
	end := structure(127, nil)
	s := parseSMBIOS(smbiosTable(sys, board, end))
	if len(s) != 3 {
		t.Fatalf("structures: %d", len(s))
	}
	m, p, v, sn := systemInfo(s)
	if m != "LENOVO" || p != "21MC0012GE" || v != "ThinkPad T14 Gen 5" || sn != "PF4ABC12" {
		t.Fatalf("got %q %q %q %q", m, p, v, sn)
	}
	if got := modelName(m, p, v); got != "ThinkPad T14 Gen 5 (21MC0012GE)" {
		t.Fatalf("model %q", got)
	}
	if got := modelName("Dell Inc.", "Latitude 5440", "Not Specified"); got != "Latitude 5440" {
		t.Fatalf("model %q", got)
	}
}

func TestSerialFallbackAndPlaceholders(t *testing.T) {
	sys := structure(1, []byte{1, 2, 0, 3}, "System manufacturer", "System Product Name", "To be filled by O.E.M.")
	chassis := structure(3, []byte{1, 0, 0, 2}, "x", "Default string")
	board := structure(2, []byte{1, 0, 0, 2}, "ASUS", "MB-123456789AB")
	_, _, _, sn := systemInfo(parseSMBIOS(smbiosTable(sys, chassis, board, structure(127, nil))))
	if sn != "MB-123456789AB" {
		t.Fatalf("fallback serial %q", sn)
	}
	for _, p := range []string{"To be filled by O.E.M.", "Default string", "System Serial Number", "0", "00000000", "FFFFFFFF",
		"None", "N/A", "Not Specified", "123456789", "  ", "...", "Chassis Serial Number", "to be filled by oem"} {
		if !isPlaceholder(p) {
			t.Errorf("placeholder not detected: %q", p)
		}
	}
	for _, real := range []string{"PF4ABC12", "5CG1234XYZ", "C02XYZ", "A1"} {
		if isPlaceholder(real) {
			t.Errorf("real value treated as placeholder: %q", real)
		}
	}
}

func TestReportFormat(t *testing.T) {
	now := time.Date(2026, 11, 10, 20, 30, 0, 0, time.FixedZone("CET", 3600))
	hw := Hardware{Manufacturer: " LENOVO ", Model: "T14", SerialNumber: "Default string", CPU: "Intel\x00 Core", RAMMB: 32768,
		Disks: []Disk{{Type: "nvme", SizeGB: 1024}}}
	r := newReport(now, hw, &OS{Name: "Windows 11 Pro", Version: "24H2 (10.0.26100.1)"},
		[]Software{{Name: "b"}, {Name: "A", Version: "1"}, {Name: "a", Version: "1"}, {Name: " "}, {Name: "<script>", Vendor: strings.Repeat("v", 300)}})
	data, err := r.marshal(true)
	if err != nil {
		t.Fatal(err)
	}
	var m map[string]any
	if err := json.Unmarshal(data, &m); err != nil {
		t.Fatal(err)
	}
	if m["format"] != "bestandio-scan" || m["version"] != float64(1) || m["scanned_at"] != "2026-11-10T20:30:00+01:00" {
		t.Fatalf("header %v", m)
	}
	h := m["hardware"].(map[string]any)
	if h["manufacturer"] != "LENOVO" || h["serial_number"] != "" || h["cpu"] != "Intel Core" {
		t.Fatalf("hardware %v", h)
	}
	if _, ok := h["serial_number"]; !ok {
		t.Fatal("serial_number key must always be present")
	}
	sw := m["software"].([]any)
	if len(sw) != 3 {
		t.Fatalf("software %d: %v", len(sw), sw)
	}
	if v := sw[0].(map[string]any)["vendor"].(string); len([]rune(v)) != maxSWVendor {
		t.Fatalf("vendor not cut: %d", len(v))
	}
	// Only fields of the format, nothing personal.
	allowed := regexp.MustCompile(`^"(format|version|scanned_at|hardware|os|software|manufacturer|model|serial_number|uuid|cpu|ram_mb|disks|type|size_gb|name|vendor)"$`)
	for _, k := range regexp.MustCompile(`"[a-z_]+":`).FindAll(data, -1) {
		if !allowed.Match(bytes.TrimSuffix(k, []byte(":"))) {
			t.Errorf("unexpected key %s", k)
		}
	}
	// Without software the key is absent; an empty request yields [].
	none, _ := newReport(now, hw, nil, nil).marshal(false)
	if bytes.Contains(none, []byte(`"software"`)) || bytes.Contains(none, []byte(`"os"`)) {
		t.Fatal("software/os must be omitted")
	}
	empty, _ := newReport(now, hw, nil, []Software{}).marshal(true)
	if !bytes.Contains(empty, []byte(`"software": []`)) {
		t.Fatalf("empty software list missing: %s", empty)
	}
}

func TestDiskTypeAndSize(t *testing.T) {
	cases := []struct {
		bus        uint32
		seek, know bool
		want       string
	}{{17, false, true, "nvme"}, {13, false, true, "emmc"}, {11, false, true, "ssd"}, {11, true, true, "hdd"}, {11, false, false, "other"}}
	for _, c := range cases {
		if got := diskType(c.bus, c.seek, c.know); got != c.want {
			t.Errorf("bus %d: %s, want %s", c.bus, got, c.want)
		}
	}
	if sizeGB(1_024_209_543_168) != 1024 || sizeGB(500_107_862_016) != 500 {
		t.Fatal("size")
	}
}

func TestTextsComplete(t *testing.T) {
	var all map[string]map[string]string
	if err := json.Unmarshal(lang.JSON, &all); err != nil {
		t.Fatal(err)
	}
	src, _ := os.ReadFile("main.go")
	used := regexp.MustCompile(`"(scanner\.[a-z_]+)"`).FindAllSubmatch(src, -1)
	if len(used) < 10 {
		t.Fatalf("too few keys found: %d", len(used))
	}
	for _, l := range []string{"de", "en", "tr"} {
		for _, u := range used {
			if all[l][string(u[1])] == "" {
				t.Errorf("%s: missing %s", l, u[1])
			}
		}
		if all[l]["scanner.yes_words"] == "" {
			t.Errorf("%s: yes words missing", l)
		}
	}
	de := texts(all["de"])
	if !de.isYes("j") || !de.isYes(" Ja ") || de.isYes("") || de.isYes("n") {
		t.Fatal("yes words de")
	}
	if !texts(all["tr"]).isYes("e") || !texts(all["en"]).isYes("y") {
		t.Fatal("yes words tr/en")
	}
}

// No network: no networking package (net and below, TLS) and no external programs.
func TestNoNetworkImports(t *testing.T) {
	files, _ := filepath.Glob("*.go")
	sub, _ := filepath.Glob("internal/*/*.go")
	for _, f := range append(files, sub...) {
		af, err := parser.ParseFile(token.NewFileSet(), f, nil, parser.ImportsOnly)
		if err != nil {
			t.Fatal(err)
		}
		for _, imp := range af.Imports {
			p := strings.Trim(imp.Path.Value, `"`)
			if p == "net" || strings.HasPrefix(p, "net/") || p == "crypto/tls" || p == "os/exec" {
				t.Errorf("%s imports %s", f, p)
			}
		}
	}
}

// The committed .syso must match the manifest (regenerate with go generate).
func TestManifestResourceUpToDate(t *testing.T) {
	m, err := os.ReadFile("manifest.xml")
	if err != nil {
		t.Fatal(err)
	}
	if !bytes.Contains(m, []byte(`level="asInvoker"`)) {
		t.Fatal("manifest must request asInvoker")
	}
	syso, err := os.ReadFile("rsrc_windows_amd64.syso")
	if err != nil {
		t.Fatal(err)
	}
	if !bytes.Equal(syso, rsrc.Build(m)) {
		t.Fatal("rsrc_windows_amd64.syso is outdated: run go generate ./scanner")
	}
}

func TestWriteReportFallsBackToDesktop(t *testing.T) {
	desk := t.TempDir()
	data := []byte(`{"format":"bestandio-scan"}`)
	// Program folder not writable (here: does not exist) → desktop.
	p, fb, err := writeReport("", "a.json", data, filepath.Join(t.TempDir(), "missing"), func() string { return desk })
	if err != nil || !fb || p != filepath.Join(desk, "a.json") {
		t.Fatalf("fallback: %v %v %v", p, fb, err)
	}
	// Writable program folder → next to the program.
	exe := t.TempDir()
	p, fb, err = writeReport("", "b.json", data, exe, func() string { return desk })
	if err != nil || fb || p != filepath.Join(exe, "b.json") {
		t.Fatalf("normal: %v %v %v", p, fb, err)
	}
	// --out with a folder.
	p, _, err = writeReport(exe, "c.json", data, "", nil)
	if err != nil || p != filepath.Join(exe, "c.json") {
		t.Fatalf("out dir: %v %v", p, err)
	}
	if got, _ := os.ReadFile(p); !bytes.Equal(got, data) {
		t.Fatal("content")
	}
}

func TestUserAndComputerNamesAreRedacted(t *testing.T) {
	now := time.Now()
	r := newReport(now, Hardware{Manufacturer: "ACME", SerialNumber: "SN1"}, &OS{Name: "Windows 11 Pro"},
		[]Software{{Name: "Roblox Studio for MMuster"}, {Name: "Tool", Vendor: "PC-BUCHHALTUNG Inc"}, {Name: "Editor", Version: "corp.example"}},
		"mmuster", "PC-BUCHHALTUNG", "CORP", "corp.example")
	data, _ := r.marshal(true)
	low := strings.ToLower(string(data))
	for _, s := range []string{"mmuster", "pc-buchhaltung", "corp.example"} {
		if strings.Contains(low, s) {
			t.Fatalf("%q still in report: %s", s, data)
		}
	}
	if !strings.Contains(string(data), "Roblox Studio for [user]") {
		t.Fatalf("redaction marker missing: %s", data)
	}
}

func TestSystemUUID(t *testing.T) {
	// Raw SMBIOS bytes of 4C4C4544-0042-3510-8051-B4C04F4A3533 (first three fields little-endian).
	raw := []byte{0x44, 0x45, 0x4C, 0x4C, 0x42, 0x00, 0x10, 0x35, 0x80, 0x51, 0xB4, 0xC0, 0x4F, 0x4A, 0x35, 0x33}
	formatted := append([]byte{1, 2, 0, 3}, raw...)
	sys := structure(1, formatted, "Dell Inc.", "Latitude 5440", "H7K2LM3")
	s := parseSMBIOS(smbiosTable(sys, structure(127, nil)))
	if got := systemUUID(s, 3, 4); got != "4C4C4544-0042-3510-8051-B4C04F4A3533" {
		t.Fatalf("uuid %q", got)
	}
	if got := systemUUID(s, 2, 4); got != "44454C4C-4200-1035-8051-B4C04F4A3533" {
		t.Fatalf("uuid before SMBIOS 2.6 %q", got)
	}
	for _, p := range []string{"00000000-0000-0000-0000-000000000000", "FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF",
		"03000200-0400-0500-0006-000700080009", "00020003-0004-0005-0006-000700080009", "x"} {
		if cleanUUID(p) != "" {
			t.Errorf("placeholder kept: %s", p)
		}
	}
	if cleanUUID("4c4c4544-0042-3510-8051-b4c04f4a3533") != "4C4C4544-0042-3510-8051-B4C04F4A3533" {
		t.Fatal("upper case")
	}
	r := newReport(time.Now(), Hardware{Manufacturer: "Dell", UUID: "00000000-0000-0000-0000-000000000000"}, nil, nil)
	if b, _ := r.marshal(false); bytes.Contains(b, []byte(`"uuid"`)) {
		t.Fatal("placeholder uuid must be omitted")
	}
}
