<?php

namespace App\Libraries;

class ListExport
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public string $title,
        public string $filename,
        public array $headings,
        public array $rows,
    ) {
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public static function make(string $title, string $filename, array $headings, array $rows): self
    {
        $maxRows = 5000;

        if (count($rows) > $maxRows) {
            $rows = array_slice($rows, 0, $maxRows);
        }

        return new self($title, $filename, $headings, $rows);
    }

    public function downloadName(string $extension): string
    {
        return $this->filename.'-'.now()->format('Y-m-d').'.'.$extension;
    }
}
