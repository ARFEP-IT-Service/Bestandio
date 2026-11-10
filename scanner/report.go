package main

import (
	"encoding/json"
	"regexp"
	"sort"
	"strings"
	"time"
	"unicode/utf8"
)

// Report is the import format "bestandio-scan" version 1 (docs/import-format.md).
// It deliberately has no fields for user names, computer names, domains, IP or MAC addresses.
type Report struct {
	Format    string     `json:"format"`
	Version   int        `json:"version"`
	ScannedAt string     `json:"scanned_at"`
	Hardware  Hardware   `json:"hardware"`
	OS        *OS        `json:"os,omitempty"`
	Software  []Software `json:"software,omitempty"`
}

type Hardware struct {
	Manufacturer string `json:"manufacturer"`
	Model        string `json:"model,omitempty"`
	SerialNumber string `json:"serial_number"`
	CPU          string `json:"cpu,omitempty"`
	RAMMB        int64  `json:"ram_mb,omitempty"`
	Disks        []Disk `json:"disks,omitempty"`
}

type Disk struct {
	Type   string `json:"type"` // ssd, hdd, nvme, emmc, other
	SizeGB int64  `json:"size_gb"`
}

type OS struct {
	Name    string `json:"name,omitempty"`
	Version string `json:"version,omitempty"`
}

type Software struct {
	Name    string `json:"name"`
	Version string `json:"version,omitempty"`
	Vendor  string `json:"vendor,omitempty"`
}

// Limits of the import format.
const (
	maxManufacturer = 100
	maxModel        = 100
	maxSerial       = 100
	maxCPU          = 190
	maxOSName       = 100
	maxOSVersion    = 64
	maxSWName       = 190
	maxSWVersion    = 64
	maxSWVendor     = 190
	maxSoftware     = 5000
	maxDisks        = 32
)

// text removes control characters, trims and cuts to max runes.
func text(s string, max int) string {
	s = strings.Map(func(r rune) rune {
		if r < 0x20 || r == 0x7f || r == utf8.RuneError {
			return -1
		}
		return r
	}, s)
	s = strings.TrimSpace(s)
	if utf8.RuneCountInString(s) > max {
		s = strings.TrimSpace(string([]rune(s)[:max]))
	}
	return s
}

// newReport builds a report with all strings sanitised and limits applied.
func newReport(now time.Time, hw Hardware, os *OS, sw []Software, secrets ...string) Report {
	red := redactor(secrets)
	hw.Manufacturer = text(clean(hw.Manufacturer), maxManufacturer)
	hw.Model = text(clean(hw.Model), maxModel)
	hw.SerialNumber = text(clean(hw.SerialNumber), maxSerial)
	hw.CPU = text(hw.CPU, maxCPU)
	if len(hw.Disks) > maxDisks {
		hw.Disks = hw.Disks[:maxDisks]
	}
	if os != nil {
		os.Name = text(os.Name, maxOSName)
		os.Version = text(os.Version, maxOSVersion)
		if os.Name == "" && os.Version == "" {
			os = nil
		}
	}
	r := Report{Format: "bestandio-scan", Version: 1, ScannedAt: now.Format(time.RFC3339), Hardware: hw, OS: os}
	if sw != nil {
		for i := range sw {
			sw[i] = Software{Name: red(sw[i].Name), Version: red(sw[i].Version), Vendor: red(sw[i].Vendor)}
		}
		r.Software = normaliseSoftware(sw)
	}
	r.Hardware.Manufacturer, r.Hardware.Model = red(r.Hardware.Manufacturer), red(r.Hardware.Model)
	r.Hardware.SerialNumber, r.Hardware.CPU = red(r.Hardware.SerialNumber), red(r.Hardware.CPU)
	if r.OS != nil {
		r.OS.Name, r.OS.Version = red(r.OS.Name), red(r.OS.Version)
	}
	return r
}

// redactor returns a function that replaces the given values (user name, computer name,
// domain) case-insensitively with "[user]". Some per-user programs carry the user name in
// their display name ("… for <user>"); this keeps such values out of the report.
func redactor(secrets []string) func(string) string {
	var parts []string
	for _, s := range secrets {
		if s = strings.TrimSpace(s); utf8.RuneCountInString(s) >= 2 {
			parts = append(parts, regexp.QuoteMeta(s))
		}
	}
	if len(parts) == 0 {
		return func(s string) string { return s }
	}
	re := regexp.MustCompile(`(?i)` + strings.Join(parts, "|"))
	return func(s string) string { return re.ReplaceAllString(s, "[user]") }
}

// normaliseSoftware cleans, drops empty names, removes duplicates and sorts by name.
func normaliseSoftware(in []Software) []Software {
	seen := map[string]bool{}
	out := make([]Software, 0, len(in))
	for _, s := range in {
		s.Name = text(s.Name, maxSWName)
		s.Version = text(s.Version, maxSWVersion)
		s.Vendor = text(s.Vendor, maxSWVendor)
		key := strings.ToLower(s.Name + "\x00" + s.Version)
		if s.Name == "" || seen[key] {
			continue
		}
		seen[key] = true
		out = append(out, s)
	}
	sort.Slice(out, func(i, j int) bool {
		a, b := strings.ToLower(out[i].Name), strings.ToLower(out[j].Name)
		if a != b {
			return a < b
		}
		return out[i].Version < out[j].Version
	})
	if len(out) > maxSoftware {
		out = out[:maxSoftware]
	}
	return out
}

// marshal returns indented JSON. Software is always an array when it was requested.
func (r Report) marshal(withSoftware bool) ([]byte, error) {
	if withSoftware && r.Software == nil {
		r.Software = []Software{}
	}
	type alias Report
	var v any = alias(r)
	if withSoftware && len(r.Software) == 0 {
		// omitempty would drop an empty list; keep it explicit.
		m := map[string]any{}
		b, err := json.Marshal(alias(r))
		if err != nil {
			return nil, err
		}
		if err := json.Unmarshal(b, &m); err != nil {
			return nil, err
		}
		m["software"] = []Software{}
		v = m
	}
	return json.MarshalIndent(v, "", "  ")
}

// diskType maps a storage bus type and seek-penalty flag to the format's disk types.
func diskType(busType uint32, seekPenalty, known bool) string {
	switch busType {
	case 17: // BusTypeNvme
		return "nvme"
	case 13: // BusTypeMmc
		return "emmc"
	}
	if !known {
		return "other"
	}
	if seekPenalty {
		return "hdd"
	}
	return "ssd"
}

// sizeGB converts bytes to whole decimal gigabytes (as printed on drives).
func sizeGB(bytes int64) int64 {
	return (bytes + 500_000_000) / 1_000_000_000
}
