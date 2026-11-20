<?php

namespace App\Concerns;

/**
 * Turns a `name[]=1&name[]=2`-style request value into a clean list of int
 * ids, for filters built with <x-multi-select-filter> (checkboxes posting
 * as an array) instead of a single-value <select>. Shared by every
 * controller with a multi-select filter so the parsing rule - and its
 * single-value backward-compat allowance - stays in exactly one place.
 */
trait FiltersByMultipleIds
{
    /**
     * @return list<int>
     */
    private function idList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Same as idList() but keeps values as strings - for filters whose
     * underlying column isn't numeric (e.g. an enum-like string column).
     *
     * @return list<string>
     */
    private function valueList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();
    }
}
