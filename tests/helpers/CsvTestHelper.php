<?php

namespace samuelreichor\genesis\tests\helpers;

/**
 * CSV Test Helper
 *
 * Provides utilities for parsing CSV strings in tests.
 */
class CsvTestHelper
{
    /**
     * Parse a CSV string into columns and rows.
     *
     * @param string $csv The CSV string
     * @return array{columns: array, rows: array}
     */
    public static function parse(string $csv): array
    {
        $lines = array_filter(array_map('trim', explode("\n", trim($csv))));

        if (empty($lines)) {
            return ['columns' => [], 'rows' => []];
        }

        $columns = str_getcsv(array_shift($lines));
        $columns = array_map('trim', $columns);

        $rows = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $rows[] = array_map('trim', $row);
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * Create a simple CSV string from an array of associative arrays.
     *
     * @param array $data Array of associative arrays
     * @return string CSV string
     */
    public static function fromArray(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $columns = array_keys($data[0]);
        $lines = [implode(',', $columns)];

        foreach ($data as $row) {
            $values = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                // Escape values with commas or quotes
                if (str_contains($value, ',') || str_contains($value, '"')) {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }
                $values[] = $value;
            }
            $lines[] = implode(',', $values);
        }

        return implode("\n", $lines);
    }
}
