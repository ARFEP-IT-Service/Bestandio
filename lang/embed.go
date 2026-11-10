// Package lang makes lang/i18n.json — the single source of all texts — available to Go programs.
package lang

import _ "embed"

// JSON holds lang/i18n.json: {"de": {...}, "en": {...}, "tr": {...}}.
//
//go:embed i18n.json
var JSON []byte
