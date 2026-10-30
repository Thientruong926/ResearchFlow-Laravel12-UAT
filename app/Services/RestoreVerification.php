<?php

namespace App\Services;

use Illuminate\Database\Connection;

class RestoreVerification
{
    /** Read-only comparison. Run with source writes paused, immediately after restoring its backup. */
    public function compare(Connection $source, Connection $restored): array
    {
        $left = $this->fingerprints($source);
        $right = $this->fingerprints($restored);
        return ['passed' => $left === $right, 'source_tables' => $left, 'restored_tables' => $right,
            'checked_at' => now()->toIso8601String(),
            'scope' => 'Table names, row counts, canonical content SHA-256. Schema and file attachments need separate verification.'];
    }

    private function fingerprints(Connection $connection): array
    {
        $results = [];
        foreach ($connection->getSchemaBuilder()->getTables() as $table) {
            $name = $table['name'];
            // Quote identifiers through the database grammar, never concatenate values into SQL.
            $rows = $connection->table($name)->get()->map(function ($row) {
                $columns = (array) $row;
                ksort($columns);
                return json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            })->all();
            sort($rows, SORT_STRING);
            $results[$name] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
        }
        ksort($results);
        return $results;
    }
}
