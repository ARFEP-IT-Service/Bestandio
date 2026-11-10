// Package rsrc builds a COFF object (.syso) with a Windows manifest resource
// (RT_MANIFEST, ID 1, language neutral) for the Go linker. Standard library only.
package rsrc

import (
	"bytes"
	"encoding/binary"
)

// Build returns the COFF object bytes for amd64.
func Build(manifest []byte) []byte {
	const (
		rtManifest = 24
		dirSize    = 16
		entrySize  = 8
		highBit    = 0x80000000
	)
	// Layout of the .rsrc section: root dir, type dir, name dir, data entry, manifest bytes.
	rootOff := 0
	typeOff := rootOff + dirSize + entrySize
	nameOff := typeOff + dirSize + entrySize
	dataEntryOff := nameOff + dirSize + entrySize
	manifestOff := dataEntryOff + 16

	sec := new(bytes.Buffer)
	w := func(v any) { _ = binary.Write(sec, binary.LittleEndian, v) }
	dir := func(ids uint16) {
		w(uint32(0))
		w(uint32(0))
		w(uint16(0))
		w(uint16(0))
		w(uint16(0))
		w(ids)
	}

	dir(1) // root: one ID entry (type)
	w(uint32(rtManifest))
	w(uint32(highBit | typeOff))
	dir(1) // type: one ID entry (resource ID 1)
	w(uint32(1))
	w(uint32(highBit | nameOff))
	dir(1) // name: one ID entry (language neutral)
	w(uint32(0))
	w(uint32(dataEntryOff))
	w(uint32(manifestOff)) // data entry: RVA (relocated), size, code page, reserved
	w(uint32(len(manifest)))
	w(uint32(0))
	w(uint32(0))
	sec.Write(manifest)
	for sec.Len()%8 != 0 {
		sec.WriteByte(0)
	}

	const fileHeader, sectionHeader = 20, 40
	rawOff := fileHeader + sectionHeader
	relocOff := rawOff + sec.Len()
	symOff := relocOff + 10

	obj := new(bytes.Buffer)
	o := func(v any) { _ = binary.Write(obj, binary.LittleEndian, v) }
	// IMAGE_FILE_HEADER
	o(uint16(0x8664)) // AMD64
	o(uint16(1))      // sections
	o(uint32(0))      // time stamp (reproducible)
	o(uint32(symOff)) // symbol table
	o(uint32(1))      // symbols
	o(uint16(0))      // optional header size
	o(uint16(0))      // characteristics
	// IMAGE_SECTION_HEADER
	obj.WriteString(".rsrc\x00\x00\x00")
	o(uint32(0)) // virtual size
	o(uint32(0)) // virtual address
	o(uint32(sec.Len()))
	o(uint32(rawOff))
	o(uint32(relocOff))
	o(uint32(0)) // line numbers
	o(uint16(1)) // relocations
	o(uint16(0))
	o(uint32(0x40000040)) // initialized data, readable
	obj.Write(sec.Bytes())
	// Relocation: the data entry's RVA field, relative to the section symbol.
	o(uint32(dataEntryOff))
	o(uint32(0)) // symbol index 0 = .rsrc
	o(uint16(3)) // IMAGE_REL_AMD64_ADDR32NB
	// Symbol table: ".rsrc", section 1, static.
	obj.WriteString(".rsrc\x00\x00\x00")
	o(uint32(0)) // value
	o(int16(1))  // section number
	o(uint16(0)) // type
	o(uint8(3))  // IMAGE_SYM_CLASS_STATIC
	o(uint8(0))  // aux symbols
	o(uint32(4)) // empty string table
	return obj.Bytes()
}
