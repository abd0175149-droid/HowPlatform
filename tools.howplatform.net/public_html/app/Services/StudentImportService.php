<?php

namespace App\Services;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentImportService
{
    /**
     * Reads a file (CSV, TXT, XLSX, XLS) and returns its rows.
     */
    public function readFileRows(string $localPath, string $ext): array
    {
        $ext = Str::lower($ext);

        // CSV / TXT
        if (in_array($ext, ['csv','txt'])) {
            $rows = [];
            if (($h = fopen($localPath, 'r')) !== false) {
                while (($data = fgetcsv($h, 0, ',')) !== false) {
                    $row = array_map(static fn($v) => trim((string)$v), $data);
                    if (implode('', $row) !== '') {
                        $rows[] = $row;
                    }
                }
                fclose($h);
            }
            return $rows;
        }

        // Excel (PhpSpreadsheet)
        if (in_array($ext, ['xlsx','xls']) && class_exists(IOFactory::class)) {
            try {
                $sheet      = IOFactory::load($localPath)->getActiveSheet();
                $highestRow = $sheet->getHighestRow();
                $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

                $rows = [];
                for ($r = 1; $r <= $highestRow; $r++) {
                    $row = [];
                    for ($c = 1; $c <= $highestCol; $c++) {
                        $colLetter = Coordinate::stringFromColumnIndex($c);
                        $val = $sheet->getCell($colLetter . $r)->getCalculatedValue();
                        $row[] = trim((string) $val);
                    }
                    if (implode('', $row) !== '') {
                        $rows[] = $row;
                    }
                }
                return $rows;
            } catch (\Throwable $e) {
                return [];
            }
        }

        return [];
    }

    /**
     * Builds column headings from the first row.
     */
    public function headingsFromFirstRow(array $rows): array
    {
        if (empty($rows)) return [];
        $first = $rows[0];
        $heads = [];
        foreach ($first as $i => $v) {
            $title = $v !== '' ? $v : ('عمود '.($i+1));
            $heads[$i] = $title;
        }
        return $heads;
    }

    /**
     * Extracts Name + Major pairs from rows based on column indices.
     */
    public function extractColumnsAsStudentPairs(array $rows, int $nameColIndex, ?int $majorColIndex): array
    {
        $pairs = [];
        foreach ($rows as $ri => $row) {
            if ($ri === 0) continue; // Skip headers
            $name  = trim((string)($row[$nameColIndex]  ?? ''));
            $major = $majorColIndex !== null ? trim((string)($row[$majorColIndex] ?? '')) : '';
            if ($name === '') continue;
            $pairs[] = ['name' => $name, 'major' => ($major === '' ? null : $major)];
        }
        
        // Remove duplicates by name+major
        $seen = [];
        $unique = [];
        foreach ($pairs as $p) {
            $key = mb_strtolower($p['name']).'|'.mb_strtolower((string)$p['major']);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $p;
        }
        return $unique;
    }

    /**
     * Parses two parallel texts or delimited text into Name + Major pairs.
     */
    public function parseTextareaPairs(?string $namesText, ?string $majorsText = null): array
    {
        $names = $this->normalizeNames(preg_split('/\r\n|\r|\n/u', (string)$namesText) ?: []);
        $majorsLines = preg_split('/\r\n|\r|\n/u', (string)$majorsText) ?: [];

        $pairs = [];
        if (!empty($majorsText)) {
            foreach ($names as $i => $nm) {
                $rawMajor = isset($majorsLines[$i]) ? trim((string)$majorsLines[$i]) : '';
                $pairs[] = ['name' => $nm, 'major' => ($rawMajor === '' ? null : $rawMajor)];
            }
            return $pairs;
        }

        $sep = '/\s*[|,\-]\s*/u';
        foreach ($names as $rawLine) {
            if (!preg_match($sep, $rawLine)) {
                $pairs[] = ['name' => $rawLine, 'major' => null];
                continue;
            }
            [$nm, $mg] = preg_split($sep, $rawLine, 2) + [null,null];
            $nm = trim((string)$nm);
            $mg = trim((string)$mg);
            if ($nm === '') continue;
            $pairs[] = ['name' => $nm, 'major' => ($mg === '' ? null : $mg)];
        }
        return $pairs;
    }

    public function parseTextarea(?string $text): array
    {
        if (!$text) return [];
        $lines = preg_split('/\r\n|\r|\n/u', $text) ?: [];
        return $this->normalizeNames($lines);
    }

    private function normalizeNames(array $names): array
    {
        $out = [];
        foreach ($names as $n) {
            $n = trim((string)$n);
            $n = preg_replace('/\s+/u', ' ', $n);
            if ($n !== '' && !in_array($n, $out, true)) $out[] = $n;
        }
        return $out;
    }
}
