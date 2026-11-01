<?php

namespace App\Support;

/** CSV helpers shared by every export. */
final class Csv
{
    /**
     * Neutralise spreadsheet formula injection: text starting with = + - @ TAB or CR would be
     * executed by Excel/Sheets, so it is prefixed with an apostrophe.
     */
    public static function cell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    /** Write a CSV document (UTF-8 BOM so Excel reads Indonesian text) to a stream. */
    public static function begin($out, array $header): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
    }
}
