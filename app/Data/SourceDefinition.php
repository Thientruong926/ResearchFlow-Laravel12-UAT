<?php

namespace App\Data;

use InvalidArgumentException;

/** In-memory integration contract v1. URL and assignment details are server-only. */
final readonly class SourceDefinition
{
    public function __construct(public array $definition)
    {
        foreach (['source_id', 'revision', 'transport', 'spreadsheet_id', 'sheet_id', 'range', 'header_row', 'timestamp_format', 'timezone', 'columns', 'assignment_ids', 'assignments', 'active'] as $key) {
            if (! array_key_exists($key, $definition)) {
                throw new InvalidArgumentException('Missing source field: '.$key);
            }
        }
        if (! in_array($definition['transport'], ['demo_csv', 'sheets_api'], true)
            || ! is_int($definition['header_row']) || $definition['header_row'] < 1) {
            throw new InvalidArgumentException('Invalid source transport/header row');
        }
        new \DateTimeZone($definition['timezone']);
    }
}
