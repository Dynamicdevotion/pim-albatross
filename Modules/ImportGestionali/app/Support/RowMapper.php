<?php

namespace Modules\ImportGestionali\Support;

/**
 * Applies a saved mapping (column index => field) to one positional data row,
 * yielding [field => trimmed value] for the columns the user actually mapped.
 */
final class RowMapper
{
    /**
     * @param  array<int|string, string|null>  $mapping
     * @param  list<string>  $row
     * @return array<string, string>
     */
    public static function apply(array $mapping, array $row): array
    {
        $mapped = [];

        foreach ($mapping as $index => $field) {
            if ($field === null || $field === '') {
                continue;
            }

            $mapped[$field] = trim($row[(int) $index] ?? '');
        }

        return $mapped;
    }
}
