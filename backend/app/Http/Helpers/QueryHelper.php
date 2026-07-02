<?php

namespace App\Http\Helpers;

class QueryHelper
{
    /**
     * Escape LIKE wildcard characters to prevent wildcard injection.
     *
     * Without this, a user sending '%' or '_' as a search term could
     * bypass filtering or cause expensive full-table scans.
     *
     * @param  string  $value  Raw user input
     * @return string          Safe value for use inside a LIKE clause
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
