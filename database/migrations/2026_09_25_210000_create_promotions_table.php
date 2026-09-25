<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **Las PROMOCIONES** (`docs/specs/promociones.md` §4.1, `DECISIONES #684` y `#770`): un TEXTO en es/en/fr vinculado a
 * un producto, una zona o toda la instalación, que sale solo en los sitios de su objetivo.
 *
 *  · `kind`: `offer` (con fecha de fin OBLIGATORIA; la etiqueta de oferta, encima del precio) o `gift` (lo que el
 *    parque da sin cobrar; sin fecha, o con ella si es de temporada).
 *  · El objetivo son DOS claves foráneas anulables y no una pareja polimórfica: así la base de datos borra las
 *    promociones de un producto o una zona que se borra (`cascadeOnDelete`), y una promoción no puede apuntar a una
 *    fila que no existe. Las dos vacías = toda la instalación; las dos llenas no se admite (lo impide el modelo).
 *  · `starts_on`/`ends_on`: DÍAS del parque, no instantes: «hasta el 30 de septiembre» es el 30 entero en el
 *    calendario del parque (`DisplayTime::today()`), y un `timestamp` lo haría depender de la zona horaria del
 *    contenedor.
 *
 * ▶ **Los REGALOS pasan aquí** (`#770`, el owner): cada línea de `ticket_types.gifts` (`#589`) se copia a una promoción
 * `gift` de su producto, con sus tres idiomas emparejados por posición, y la columna se va. `TicketType::giftLines()`
 * las lee, así que `gifts` de la API, el post-form y la invitación no cambian de forma. `down()` la reconstruye.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table): void {
                $table->id();
                $table->string('kind', 16);
                $table->json('text');
                $table->foreignId('zone_id')->nullable()->constrained('zones')->cascadeOnDelete();
                $table->foreignId('ticket_type_id')->nullable()->constrained('ticket_types')->cascadeOnDelete();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
                $table->index(['kind', 'is_active']);
            });
        }

        if (! Schema::hasColumn('ticket_types', 'gifts')) {
            return;
        }

        $ahora = Carbon::now();
        foreach (DB::table('ticket_types')->whereNotNull('gifts')->orderBy('id')->get(['id', 'gifts']) as $producto) {
            foreach (self::lineas(json_decode((string) $producto->gifts, true)) as $posicion => $texto) {
                DB::table('promotions')->insert([
                    'kind' => 'gift',
                    'text' => json_encode($texto, JSON_UNESCAPED_UNICODE),
                    'ticket_type_id' => $producto->id,
                    'is_active' => true,
                    'position' => $posicion,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn('gifts');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ticket_types', 'gifts')) {
            Schema::table('ticket_types', function (Blueprint $table): void {
                $table->json('gifts')->nullable()->after('features');
            });
        }

        if (! Schema::hasTable('promotions')) {
            return;
        }

        $porProducto = [];
        foreach (DB::table('promotions')->where('kind', 'gift')->whereNotNull('ticket_type_id')->orderBy('position')->orderBy('id')->get() as $regalo) {
            foreach ((array) json_decode((string) $regalo->text, true) as $idioma => $texto) {
                $porProducto[$regalo->ticket_type_id][$idioma][] = $texto;
            }
        }
        foreach ($porProducto as $id => $gifts) {
            DB::table('ticket_types')->where('id', $id)->update(['gifts' => json_encode($gifts, JSON_UNESCAPED_UNICODE)]);
        }

        Schema::dropIfExists('promotions');
    }

    /**
     * Las líneas de un `gifts` de antes, una por regalo, con sus idiomas emparejados por POSICIÓN: la primera línea en
     * español con la primera en inglés. Admite las dos formas que la columna tuvo —una lista por idioma o un texto suelto
     * (`TicketType::giftLines()`, «la trampa de `features`»)— y descarta lo vacío, como hacía la lectura.
     *
     * @return list<array<string, string>>
     */
    public static function lineas(mixed $gifts): array
    {
        if (! is_array($gifts) || $gifts === []) {
            return [];
        }
        if (array_is_list($gifts)) {
            $gifts = ['es' => $gifts];
        }

        $porIdioma = [];
        foreach ($gifts as $idioma => $valor) {
            $valor = is_array($valor) ? $valor : [$valor];
            $porIdioma[$idioma] = array_values(array_filter(
                array_map(fn (mixed $g): string => is_scalar($g) ? trim((string) $g) : '', $valor),
                fn (string $g): bool => $g !== '',
            ));
        }

        $lineas = [];
        $cuantas = max(array_map('count', $porIdioma));
        for ($i = 0; $i < $cuantas; $i++) {
            $lineas[] = array_filter(array_map(fn (array $l): ?string => $l[$i] ?? null, $porIdioma), fn (?string $t): bool => $t !== null);
        }

        return $lineas;
    }
};
