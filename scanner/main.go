// Command bestandio-scanner reads the hardware of this Windows PC and writes a
// "bestandio-scan" JSON file (docs/import-format.md) for the Bestandio import.
//
// It needs no installation and no admin rights, never uses the network and never
// records user names, computer names, domains, IP or MAC addresses.
package main

//go:generate go run ./internal/mksyso -manifest manifest.xml -out rsrc_windows_amd64.syso

import (
	"bufio"
	"flag"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"time"
)

func main() {
	os.Exit(run(os.Args[1:], os.Stdin, os.Stdout))
}

func run(args []string, stdin io.Reader, stdout io.Writer) int {
	t := loadTexts(uiLanguage())
	fs := flag.NewFlagSet("bestandio-scanner", flag.ContinueOnError)
	fs.SetOutput(stdout)
	withSoftware := fs.Bool("software", false, t.get("scanner.flag_software"))
	out := fs.String("out", "", t.get("scanner.flag_out"))
	noClipboard := fs.Bool("no-clipboard", false, t.get("scanner.flag_no_clipboard"))
	language := fs.String("lang", "", t.get("scanner.flag_lang"))
	if err := fs.Parse(args); err != nil {
		return 2
	}
	if *language == "de" || *language == "en" || *language == "tr" {
		t = loadTexts(*language)
	}
	interactive := isConsole(stdin)
	in := bufio.NewReader(stdin)
	defer func() {
		if interactive {
			fmt.Fprintln(stdout)
			fmt.Fprint(stdout, t.get("scanner.press_enter"))
			_, _ = in.ReadString('\n')
		}
	}()

	fmt.Fprintln(stdout, t.get("scanner.title"))
	fmt.Fprintln(stdout, t.get("scanner.privacy"))
	fmt.Fprintln(stdout)

	software := *withSoftware
	if !software && !flagSet(fs, "software") {
		fmt.Fprint(stdout, t.get("scanner.ask_software"))
		answer, _ := in.ReadString('\n')
		software = t.isYes(answer)
		if !interactive {
			fmt.Fprintln(stdout)
		}
	}

	fmt.Fprintln(stdout, t.get("scanner.collecting"))
	hw, osv := collectHardware()
	var sw []Software
	if software {
		sw = installedSoftware()
	}
	// These values are only read to remove them from the report, never to record them.
	report := newReport(time.Now(), hw, osv, sw,
		os.Getenv("USERNAME"), os.Getenv("COMPUTERNAME"), os.Getenv("USERDOMAIN"), os.Getenv("USERDNSDOMAIN"))
	data, err := report.marshal(software)
	if err != nil {
		fmt.Fprintln(stdout, t.get("scanner.error", "msg", err.Error()))
		return 1
	}

	fmt.Fprintln(stdout, t.get("scanner.summary", "manufacturer", dash(report.Hardware.Manufacturer),
		"model", dash(report.Hardware.Model), "serial", dash(report.Hardware.SerialNumber)))
	if report.Hardware.SerialNumber == "" {
		fmt.Fprintln(stdout, t.get("scanner.no_serial"))
	}
	if software {
		fmt.Fprintln(stdout, t.get("scanner.software_count", "n", fmt.Sprint(len(report.Software))))
	}

	name := "bestandio-scan-" + time.Now().Format("20060102-150405") + ".json"
	exeDir := "."
	if exe, e := os.Executable(); e == nil {
		exeDir = filepath.Dir(exe)
	}
	path, fallback, err := writeReport(*out, name, data, exeDir, desktopDir)
	if err != nil {
		fmt.Fprintln(stdout, t.get("scanner.error", "msg", err.Error()))
		return 1
	}
	if fallback {
		fmt.Fprintln(stdout, t.get("scanner.saved_fallback", "path", path))
	} else {
		fmt.Fprintln(stdout, t.get("scanner.saved", "path", path))
	}
	if !*noClipboard {
		if copyToClipboard(string(data)) {
			fmt.Fprintln(stdout, t.get("scanner.clipboard_ok"))
		} else {
			fmt.Fprintln(stdout, t.get("scanner.clipboard_failed"))
		}
	}
	return 0
}

// writeReport writes next to the program (or to --out). If the program folder is not
// writable and no --out was given, the file goes to the desktop instead.
func writeReport(out, name string, data []byte, exeDir string, desktop func() string) (path string, fallback bool, err error) {
	if out != "" {
		if st, e := os.Stat(out); e == nil && st.IsDir() {
			out = filepath.Join(out, name)
		}
		return out, false, os.WriteFile(out, data, 0o644)
	}
	path = filepath.Join(exeDir, name)
	if err = os.WriteFile(path, data, 0o644); err == nil {
		return path, false, nil
	}
	dir := desktop()
	if dir == "" {
		return path, false, err
	}
	path = filepath.Join(dir, name)
	return path, true, os.WriteFile(path, data, 0o644)
}

func flagSet(fs *flag.FlagSet, name string) bool {
	found := false
	fs.Visit(func(f *flag.Flag) {
		if f.Name == name {
			found = true
		}
	})
	return found
}

func dash(s string) string {
	if s == "" {
		return "–"
	}
	return s
}
