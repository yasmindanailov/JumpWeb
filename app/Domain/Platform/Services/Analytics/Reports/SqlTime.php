<?php

namespace App\Domain\Platform\Services\Analytics\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * **Cómo agrupa el SQL por tiempo sin saber de zonas** (`docs/specs/analitica.md` §4.5, «El tiempo»).
 *
 * La base guarda instantes en UTC y el parque vive en su zona. Las dos salidas obvias son malas: `CONVERT_TZ`
 * exige las tablas de zona cargadas en MariaDB —y en el hosting no se controlan—, y un desplazamiento fijo
 * miente en el cambio de hora. Así que el SQL agrupa por **HORA UTC** —un cubo de una hora cae entero en un
 * solo día del parque mientras el desplazamiento sea de horas enteras— y PHP asigna cada cubo a su día, su
 * semana o su mes con {@see Window::bucketKey()}. Portable a SQLite, que es donde corre la suite.
 *
 * ⚠️ Una zona con desplazamiento de media hora (India, Terranova) partiría un cubo entre dos días: si un día se
 * instala el producto allí, el cubo baja a MINUTOS aquí y en ningún otro sitio.
 *
 * ⚠️ `$column` es un identificador de confianza escrito en el código del informe, nunca una entrada.
 */
final class SqlTime
{
    public const BUCKET_FORMAT = '!Y-m-d H';

    /** La expresión SQL que agrupa una columna de instante (UTC) por hora, como `YYYY-MM-DD HH`. */
    public static function hourBucket(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d %H')",
        };
    }

    /** El instante UTC con el que EMPIEZA un cubo de hora devuelto por {@see hourBucket()}. */
    public static function bucketStart(string $bucket): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(self::BUCKET_FORMAT, $bucket, 'UTC');
    }
}
