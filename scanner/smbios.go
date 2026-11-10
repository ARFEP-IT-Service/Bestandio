package main

import (
	"bytes"
	"encoding/binary"
	"strings"
)

// smbiosStruct is one structure of the SMBIOS table (DMTF DSP0134).
type smbiosStruct struct {
	Type      byte
	Formatted []byte   // formatted area including the 4-byte header
	Strings   []string // string set, index 1 = Strings[0]
}

// str returns the string referenced by the byte at offset off of the formatted area.
func (s smbiosStruct) str(off int) string {
	if off >= len(s.Formatted) {
		return ""
	}
	idx := int(s.Formatted[off])
	if idx == 0 || idx > len(s.Strings) {
		return ""
	}
	return strings.TrimSpace(s.Strings[idx-1])
}

// parseSMBIOS parses the raw table as returned by GetSystemFirmwareTable('RSMB'):
// an 8-byte RawSMBIOSData header followed by the structure table.
func parseSMBIOS(raw []byte) []smbiosStruct {
	if len(raw) < 8 {
		return nil
	}
	length := int(binary.LittleEndian.Uint32(raw[4:8]))
	data := raw[8:]
	if length < len(data) {
		data = data[:length]
	}
	var out []smbiosStruct
	for len(data) >= 4 {
		typ := data[0]
		flen := int(data[1])
		if flen < 4 || flen > len(data) {
			break
		}
		s := smbiosStruct{Type: typ, Formatted: data[:flen]}
		rest := data[flen:]
		// String set: NUL-terminated strings, the set ends with a double NUL.
		end := bytes.Index(rest, []byte{0, 0})
		if end < 0 {
			out = append(out, s)
			break
		}
		if end > 0 {
			s.Strings = strings.Split(string(rest[:end]), "\x00")
		}
		out = append(out, s)
		if typ == 127 { // end-of-table
			break
		}
		data = rest[end+2:]
	}
	return out
}

// systemInfo extracts manufacturer, product, version and serial from type 1 (system),
// falling back to type 2 (baseboard) and type 3 (chassis) for the serial number.
func systemInfo(structs []smbiosStruct) (manufacturer, product, version, serial string) {
	var boardSerial, chassisSerial string
	for _, s := range structs {
		switch s.Type {
		case 1:
			manufacturer, product, version, serial = s.str(4), s.str(5), s.str(6), s.str(7)
		case 2:
			if boardSerial == "" {
				boardSerial = s.str(7)
			}
		case 3:
			if chassisSerial == "" {
				chassisSerial = s.str(7)
			}
		}
	}
	for _, candidate := range []string{serial, chassisSerial, boardSerial} {
		if !isPlaceholder(candidate) {
			return manufacturer, product, version, candidate
		}
	}
	return manufacturer, product, version, ""
}

// boardInfo returns manufacturer and product of the baseboard (type 2), used as model
// for self-built PCs whose system fields only contain placeholders.
func boardInfo(structs []smbiosStruct) (manufacturer, product string) {
	for _, s := range structs {
		if s.Type == 2 {
			return clean(s.str(4)), clean(s.str(5))
		}
	}
	return "", ""
}

var placeholders = map[string]bool{
	"to be filled by o.e.m.": true, "to be filled by oem": true, "default string": true,
	"system serial number": true, "system product name": true, "system manufacturer": true,
	"system version": true, "not specified": true, "not applicable": true, "not available": true,
	"none": true, "n/a": true, "na": true, "oem": true, "o.e.m.": true, "invalid": true,
	"chassis serial number": true, "base board serial number": true, "serial number": true,
	"123456789": true, "1234567890": true, "0123456789": true, "empty": true, "unknown": true,
	"default": true, "type1productconfigid": true, "sku": true, "undefined": true, "null": true,
}

// isPlaceholder reports values firmware vendors leave in unfilled fields.
func isPlaceholder(v string) bool {
	v = strings.TrimSpace(v)
	if v == "" || placeholders[strings.ToLower(v)] {
		return true
	}
	// Only one repeated character (00000000, FFFFFFFF, ........) or only punctuation.
	same := true
	alnum := false
	for i := 0; i < len(v); i++ {
		if v[i] != v[0] {
			same = false
		}
		c := v[i]
		if (c >= '0' && c <= '9') || (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || c >= 0x80 {
			alnum = true
		}
	}
	return same || !alnum // a single repeated character is never a real value
}

// clean returns "" for placeholders, otherwise the trimmed value.
func clean(v string) string {
	if isPlaceholder(v) {
		return ""
	}
	return strings.TrimSpace(v)
}
