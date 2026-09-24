<?php

namespace App\Domain\Platform\Services\Analytics\Reports;

use Illuminate\Support\Facades\DB;

/**
 * **Leer una clave de un JSON en SQL sin saber de motores** (`docs/specs/analitica.md` §4.5, T2b).
 *
 * `analytics_events.props` es JSON, y el cuadro agrupa por una de sus claves (`user_registered.props.method`).
 * El operador `->>` que MySQL y SQLite entienden **no existe en MariaDB** —que es el hosting, y donde `json`
 * es `LONGTEXT`—, así que se escribe la forma larga en cada motor: `JSON_UNQUOTE(JSON_EXTRACT(…))` para
 * MySQL/MariaDB y `json_extract(…)` para SQLite, que ya devuelve el texto sin comillas.
 *
 * ⚠️ `$column` y `$path` son literales de confianza escritos en el informe, nunca una entrada.
 */
final class SqlJson
{
    public static function string(string $column, string $path): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "json_extract({$column}, '{$path}')",
            default => "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))",
        };
    }
}
