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
 * ⚠️ **Un `null` de JSON no es un NULL de SQL en MySQL**: `JSON_UNQUOTE(JSON_EXTRACT(…))` devuelve la CADENA
 * `'null'`, y la campaña de un pedido sin campaña salía escrita «null» en el cuadro (visto en la captura del
 * 24-09; SQLite devuelve NULL y la suite no lo ve). Se devuelve a NULL; una campaña que se llame literalmente
 * «null» perdería el nombre, y es un precio que se paga a sabiendas.
 * ❗ **Con `IF`, nunca con `NULLIF` ni `CASE`** (03-10, medido en el MariaDB 11.4 del hosting): los informes AGRUPAN
 * por estas expresiones, y con `ONLY_FULL_GROUP_BY` MariaDB no reconoce un `NULLIF` ni un `CASE` del SELECT como el
 * mismo del GROUP BY —el cuadro daba error 1055 («'props' isn't in GROUP BY») en staging y lo habría dado en
 * producción—; un `IF` repetido, sí. MySQL 8 (el local) acepta los tres, y SQLite (la suite) no tiene `IF`: de ahí
 * el motor por parámetro, para que la suite pruebe la forma del hosting.
 * ⚠️ `$column` y `$path` son literales de confianza escritos en el informe, nunca una entrada.
 */
final class SqlJson
{
    public static function string(string $column, string $path, ?string $driver = null): string
    {
        return match ($driver ?? DB::connection()->getDriverName()) {
            'sqlite' => "json_extract({$column}, '{$path}')",
            default => "IF(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')) = 'null', NULL, JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')))",
        };
    }

    /** 1 si la clave tiene valor y 0 si no (o es un `null` de JSON): para agrupar por «lleva gclid» sin `CASE`. */
    public static function present(string $column, string $path, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();
        $valor = self::string($column, $path, $driver);

        return $driver === 'sqlite' ? "CASE WHEN {$valor} IS NULL THEN 0 ELSE 1 END" : "IF({$valor} IS NULL, 0, 1)";
    }
}
