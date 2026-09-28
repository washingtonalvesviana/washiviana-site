package httpapi

import (
	"encoding/csv"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"time"
)

// writeRadarBackupCSV grava um backup CSV dos itens em /tmp (paridade com o PHP).
func writeRadarBackupCSV(rows []map[string]any) (string, error) {
	if len(rows) == 0 {
		return "", nil
	}
	keys := make([]string, 0, len(rows[0]))
	for k := range rows[0] {
		keys = append(keys, k)
	}
	sort.Strings(keys)

	name := fmt.Sprintf("radar_items_delete_backup_%s.csv", time.Now().Format("20060102150405"))
	path := filepath.Join(os.TempDir(), name)
	f, err := os.Create(path)
	if err != nil {
		return "", err
	}
	defer f.Close()

	w := csv.NewWriter(f)
	if err := w.Write(keys); err != nil {
		return "", err
	}
	for _, r := range rows {
		rec := make([]string, len(keys))
		for i, k := range keys {
			if r[k] == nil {
				rec[i] = ""
			} else {
				rec[i] = fmt.Sprintf("%v", r[k])
			}
		}
		if err := w.Write(rec); err != nil {
			return "", err
		}
	}
	w.Flush()
	if err := w.Error(); err != nil {
		return "", err
	}
	return path, nil
}
