<?php

namespace App\Support\Printing;

use App\Models\Business\Business;
use App\Services\Setting\SettingService;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of record that can be printed (docs/branding-print-build-guide.md §4).
 * A module registers its printables through DataController::printables()
 * (type => class); the core's /app/print/{type}/{id} routes do the rest.
 */
abstract class Printable
{
    /** What it is called, e.g. "Invoice" (also the design page's label). */
    abstract public function label(): string;

    /** Permission needed to print it, e.g. "read invoicing.invoice". */
    abstract public function permission(): string;

    /**
     * The record, by its route key, within the current business (the model's
     * business scope keeps other businesses out).
     */
    abstract public function find(string $key): Model;

    abstract public function document(Model $record): Document;

    /**
     * A record of $class by its route key (uuid or id), with relations loaded.
     *
     * @param  class-string<Model>  $class
     * @param  list<string>  $with
     */
    protected function lookup(string $class, string $key, array $with = []): Model
    {
        return $class::query()->where((new $class)->getRouteKeyName(), $key)->with($with)->firstOrFail();
    }

    public function filename(Model $record): string
    {
        return $this->document($record)->number ?? $this->label();
    }

    /** Whose brand goes on it: the record's business. */
    public function business(Model $record): Business
    {
        return $record->business ?? app(SettingService::class)->current();
    }
}
