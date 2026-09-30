<?php

use App\Domain\Content\Services\CookiePolicyContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * **La política de cookies de PRODUCCIÓN** (`docs/specs/politica-de-cookies.md`; el encargo del owner, 30-09) — el texto
 * reescrito sin listas que envejecen: el LISTADO lo compone ahora `CookieInventory` y lo pintan las vistas.
 *
 * ⚠️⚠️ **La página `/cookies` vive en la BD**: cambiar `CookiePolicyContent` solo alcanza a las instalaciones NUEVAS.
 *
 * ⚠️ **Sustituye un idioma SOLO si su texto es EXACTAMENTE el que dejaba el producto** —la «v4», la de `#592` con la T3a
 * y la T3b encima, que es lo que dejan las migraciones anteriores de esta página en una BD sin editar—, reconocido por su
 * HUELLA (sha256 del JSON de sus secciones). Un idioma que la clienta reescribió desde el panel NO se toca, y queda en el
 * registro para revisarlo a mano; el listado de cookies se pinta igual debajo, porque no sale del texto.
 * ▶ Escribe el texto VIGENTE del producto (`CookiePolicyContent::body()`): es una sustitución entera por huella, y lo
 * correcto es llevar el texto sin editar al día. Idempotente: con el texto nuevo la huella ya no casa.
 * La huella la ata `CookiePolicyContentTest` a la copia congelada de la v4 (`tests/Support/CookiePolicyV4.php`).
 */
return new class extends Migration
{
    /** La huella de cada idioma de la v4, tal como la sembraba el producto (`f8771e1c`). */
    public const V4_FINGERPRINTS = [
        'es' => 'c6f46795b47a509a2b3e42af292fe26c6d963a0bb09feb6de2951d6ed80212df',
        'en' => '02b8c5c6d719d209fba5ba5b2bbf81b0c3735fff7d1e515d57f187c5344df211',
        'fr' => '38bd8dd140f979fd50349e3d13fc2bb169283c73a449dca38d61e509b1ff7222',
    ];

    /** @param array<int, mixed> $sections */
    public static function fingerprint(array $sections): string
    {
        return hash('sha256', (string) json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $row = DB::table('pages')->where('slug', 'cookies')->first(['id', 'body']);
        $body = $row === null ? null : json_decode((string) $row->body, true);

        if (! is_array($body)) {
            return;
        }

        $nuevo = CookiePolicyContent::body();
        $cambiados = [];
        $editados = [];

        foreach (self::V4_FINGERPRINTS as $locale => $huella) {
            if (! is_array($body[$locale] ?? null) || ! isset($nuevo[$locale])) {
                continue;
            }
            if (self::fingerprint($body[$locale]) === $huella) {
                $body[$locale] = $nuevo[$locale];
                $cambiados[] = $locale;
            } elseif ($body[$locale] !== $nuevo[$locale]) {
                $editados[] = $locale;
            }
        }

        if ($cambiados !== []) {
            DB::table('pages')->where('id', $row->id)->update([
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }

        if ($editados !== []) {
            Log::warning('cookies.policy_not_updated', ['locales' => $editados, 'reason' => 'edited_in_the_panel']);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: el texto viejo afirmaba cosas que ya no son (la «v4» está en `tests/Support/CookiePolicyV4.php`).
    }
};
