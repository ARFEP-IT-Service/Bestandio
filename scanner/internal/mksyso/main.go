// Command mksyso writes scanner/rsrc_windows_amd64.syso from scanner/manifest.xml.
package main

import (
	"flag"
	"fmt"
	"os"

	"bestandio/scanner/internal/rsrc"
)

func main() {
	manifest := flag.String("manifest", "manifest.xml", "manifest file")
	out := flag.String("out", "rsrc_windows_amd64.syso", "output .syso")
	flag.Parse()
	m, err := os.ReadFile(*manifest)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	if err := os.WriteFile(*out, rsrc.Build(m), 0o644); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}
