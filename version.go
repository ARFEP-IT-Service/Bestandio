// Package bestandio holds the project version, read from the VERSION file (the only place it is stored).
package bestandio

import (
	_ "embed"
	"strings"
)

//go:embed VERSION
var versionFile string

// Version returns the release version, e.g. "0.1.0".
func Version() string {
	return strings.TrimSpace(versionFile)
}
