package main

import (
	"encoding/json"
	"strings"

	"bestandio/lang"
)

// texts holds the "scanner.*" entries of lang/i18n.json for one language.
type texts map[string]string

func loadTexts(language string) texts {
	var all map[string]map[string]string
	if err := json.Unmarshal(lang.JSON, &all); err != nil {
		return texts{}
	}
	t, ok := all[language]
	if !ok {
		t = all["en"]
	}
	return t
}

// get returns the text for key with {name} placeholders replaced.
func (t texts) get(key string, params ...string) string {
	s, ok := t[key]
	if !ok {
		s = key
	}
	for i := 0; i+1 < len(params); i += 2 {
		s = strings.ReplaceAll(s, "{"+params[i]+"}", params[i+1])
	}
	return s
}

// isYes checks an answer against the comma-separated yes-words of the language.
func (t texts) isYes(answer string) bool {
	answer = strings.ToLower(strings.TrimSpace(answer))
	if answer == "" {
		return false
	}
	for _, w := range strings.Split(t.get("scanner.yes_words"), ",") {
		if answer == strings.TrimSpace(w) {
			return true
		}
	}
	return false
}
