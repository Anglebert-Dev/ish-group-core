<?php

namespace App\Support\Printing;

/**
 * Anything printable, in one shape (docs/branding-print-build-guide.md §4):
 * a module turns its record into a Document; the print engine gives it the
 * business's brand and design, as PDF, a print view or Excel.
 *
 *   Document::make('sales_invoice', 'Invoice')
 *       ->number('INV-2026-00012')
 *       ->meta('Date', '02 Nov 2026')
 *       ->party('Bill to', 'Acme Ltd', ['KG 7 Ave', 'TIN 1234'])
 *       ->table([['Item'], ['Qty', 'right']], $rows)
 *       ->total('Total', 'RWF 118,000', strong: true)
 *       ->withPayment()->withSignature();
 */
final class Document
{
    public ?string $number = null;

    /** @var list<array{label: string, value: string}> */
    public array $meta = [];

    /** @var list<array{label: string, name: string, lines: list<string>}> */
    public array $parties = [];

    /** @var list<array{label: string, align: string}> */
    public array $columns = [];

    /** @var list<list<string>> */
    public array $rows = [];

    /** @var list<array{label: string, value: string, strong: bool}> */
    public array $totals = [];

    /** @var list<array{title: string, lines: list<string>}> */
    public array $sections = [];

    public ?string $notes = null;

    public ?string $watermark = null;

    public bool $payment = false;

    public bool $signature = false;

    public string $orientation = 'portrait';

    public ?string $emptyText = null;

    public function __construct(public string $type, public string $title)
    {
    }

    public static function make(string $type, string $title): self
    {
        return new self($type, $title);
    }

    public function number(?string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function meta(string $label, ?string $value): self
    {
        if ($value !== null && $value !== '') {
            $this->meta[] = ['label' => $label, 'value' => $value];
        }

        return $this;
    }

    /**
     * @param  list<string|null>  $lines
     */
    public function party(string $label, ?string $name, array $lines = []): self
    {
        if ($name !== null && $name !== '') {
            $this->parties[] = ['label' => $label, 'name' => $name, 'lines' => array_values(array_filter($lines, fn ($line) => $line !== null && $line !== ''))];
        }

        return $this;
    }

    /**
     * @param  list<array{0: string, 1?: string}|string>  $columns  label, or [label, align]
     * @param  list<list<mixed>>  $rows
     */
    public function table(array $columns, array $rows): self
    {
        $this->columns = array_map(fn ($column) => is_array($column)
            ? ['label' => (string) $column[0], 'align' => in_array($column[1] ?? 'left', ['left', 'right', 'center'], true) ? ($column[1] ?? 'left') : 'left']
            : ['label' => (string) $column, 'align' => 'left'], $columns);
        $this->rows = array_map(fn (array $row) => array_map(fn ($cell) => (string) $cell, array_values($row)), $rows);

        return $this;
    }

    public function total(string $label, ?string $value, bool $strong = false): self
    {
        if ($value !== null) {
            $this->totals[] = ['label' => $label, 'value' => $value, 'strong' => $strong];
        }

        return $this;
    }

    /**
     * @param  list<string|null>  $lines
     */
    public function section(string $title, array $lines): self
    {
        $lines = array_values(array_filter($lines, fn ($line) => $line !== null && $line !== ''));

        if ($lines !== []) {
            $this->sections[] = ['title' => $title, 'lines' => $lines];
        }

        return $this;
    }

    public function notes(?string $notes): self
    {
        $this->notes = $notes ?: null;

        return $this;
    }

    /** Large faint word across the page: PAID, DRAFT, CANCELLED… */
    public function watermark(?string $text): self
    {
        $this->watermark = $text ?: null;

        return $this;
    }

    public function withPayment(bool $show = true): self
    {
        $this->payment = $show;

        return $this;
    }

    public function withSignature(bool $show = true): self
    {
        $this->signature = $show;

        return $this;
    }

    public function landscape(bool $landscape = true): self
    {
        $this->orientation = $landscape ? 'landscape' : 'portrait';

        return $this;
    }

    public function whenEmpty(string $text): self
    {
        $this->emptyText = $text;

        return $this;
    }
}
