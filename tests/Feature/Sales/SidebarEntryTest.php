<?php

namespace Tests\Feature\Sales;

use App\Http\Sidebar\SidebarEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.0a — **el desenlace del pago tiene UN solo dueño**
 * (`docs/specs/sidebar-spa.md` §4.1).
 *
 * Cuando el cliente vuelve de la pasarela aterriza en una ruta WEB, y lo que hay que enseñarle
 * —confirmado · denegado · verificando— viaja al cajón por la sesión. Hasta este paso, esas tres
 * claves se nombraban a mano en cinco ficheros y **solo `Purchase::mount()` las olvidaba**. Con
 * otro motor nadie lo haría: el cajón se auto-abriría en cada página hasta que caducara la sesión.
 *
 * Dos cosas se prueban aquí, y la segunda es la que evita que esto vuelva a pasar:
 *  1. la CONDUCTA de `SidebarEntry` (mirar sin consumir, consumir olvidando todo, precedencia);
 *  2. que **nadie más nombre las claves** en el código de producción.
 *
 * ⚠️ Los literales de las claves se fijan aquí a propósito. `SidebarEntry` es quien escribe y quien
 * lee, así que un renombrado suyo sería invisible para cualquier test que usara solo su API: el
 * valor se guardaría y se leería igual de mal. Estos tests siembran y leen la sesión CRUDA, que es
 * lo que de verdad comparte con los dos controladores que escriben el desenlace.
 */
class SidebarEntryTest extends TestCase
{
    use RefreshDatabase;

    /** Las claves reales. Cambiarlas es romper la sesión de cualquiera que esté comprando. */
    private const KEYS = [
        'purchase.confirmed_code',
        'purchase.failed_code',
        'purchase.verifying_code',
    ];

    public function test_each_outcome_writes_its_own_session_key(): void
    {
        SidebarEntry::confirmed('R-AAA');
        $this->assertSame('R-AAA', session('purchase.confirmed_code'));

        SidebarEntry::failed('R-BBB');
        $this->assertSame('R-BBB', session('purchase.failed_code'));

        SidebarEntry::verifying('R-CCC');
        $this->assertSame('R-CCC', session('purchase.verifying_code'));
    }

    /**
     * `peek()` es lo que usa el layout, y **no puede consumir**: el componente de compra es `lazy`
     * y su `mount()` corre en una petición POSTERIOR. Si el layout consumiera, el cliente volvería
     * de pagar a un cajón que se abre vacío.
     */
    public function test_peek_reports_the_outcome_without_consuming_it(): void
    {
        session(['purchase.confirmed_code' => 'R-AAA']);

        $entry = SidebarEntry::peek();

        $this->assertTrue($entry->pending());
        $this->assertSame(SidebarEntry::OUTCOME_CONFIRMED, $entry->outcome);
        $this->assertSame('R-AAA', $entry->orderCode);
        $this->assertSame('R-AAA', session('purchase.confirmed_code'), 'peek() no puede consumir');
    }

    public function test_peek_on_a_clean_session_reports_nothing(): void
    {
        $entry = SidebarEntry::peek();

        $this->assertFalse($entry->pending());
        $this->assertNull($entry->outcome);
        $this->assertNull($entry->orderCode);
    }

    /**
     * `consume()` olvida **las tres** claves, no solo la que devuelve. Una que sobreviviera volvería
     * a abrir el cajón en la página siguiente, que es exactamente el fallo que este paso cierra.
     */
    public function test_consume_returns_the_outcome_and_forgets_every_key(): void
    {
        session([
            'purchase.confirmed_code' => 'R-AAA',
            'purchase.failed_code' => 'R-BBB',
        ]);

        $entry = SidebarEntry::consume();

        $this->assertTrue($entry->pending());

        foreach (self::KEYS as $key) {
            $this->assertNull(session($key), "«{$key}» ha sobrevivido al consumo");
        }
    }

    /**
     * La precedencia reproduce la que tenía `Purchase::mount()` por el orden de sus tres bloques:
     * el último ganaba. Puede darse si una vuelta no llegó a consumirse antes de la siguiente.
     */
    public function test_verifying_wins_over_the_other_two(): void
    {
        session([
            'purchase.confirmed_code' => 'R-AAA',
            'purchase.failed_code' => 'R-BBB',
            'purchase.verifying_code' => 'R-CCC',
        ]);

        $entry = SidebarEntry::peek();

        $this->assertSame(SidebarEntry::OUTCOME_VERIFYING, $entry->outcome);
        $this->assertSame('R-CCC', $entry->orderCode);
    }

    public function test_a_declined_payment_wins_over_a_stale_confirmation(): void
    {
        session(['purchase.confirmed_code' => 'R-AAA', 'purchase.failed_code' => 'R-BBB']);

        $entry = SidebarEntry::peek();

        $this->assertSame(SidebarEntry::OUTCOME_FAILED, $entry->outcome);
        $this->assertSame('R-BBB', $entry->orderCode);
    }

    /**
     * Memoizado por PETICIÓN: si un segundo lector llega, recibe lo mismo que el primero en vez de
     * nada. Sin esto, añadir un lector nuevo rompería en silencio al que ya estaba — que es como se
     * rompen los flujos de pago.
     */
    public function test_a_second_reader_in_the_same_request_gets_the_same_outcome(): void
    {
        session(['purchase.failed_code' => 'R-BBB']);

        $first = SidebarEntry::consume();
        $second = SidebarEntry::consume();

        $this->assertSame('R-BBB', $first->orderCode);
        $this->assertSame('R-BBB', $second->orderCode, 'el segundo lector se ha quedado sin el desenlace');
    }

    /** `clear()` es lo que usa el login al cambiar de titular en un dispositivo compartido. */
    public function test_clear_discards_every_pending_outcome(): void
    {
        session([
            'purchase.confirmed_code' => 'R-AAA',
            'purchase.failed_code' => 'R-BBB',
            'purchase.verifying_code' => 'R-CCC',
        ]);

        SidebarEntry::clear();

        foreach (self::KEYS as $key) {
            $this->assertNull(session($key));
        }
    }

    /**
     * **La guarda**: nadie más nombra las claves. Es la versión ejecutable de «un solo dueño», el
     * mismo trato que `AccessRevocationTest` da a las credenciales (`RGPD-06`).
     *
     * Se escanea el código de PRODUCCIÓN. Los tests sí pueden nombrarlas —y este mismo lo hace— a
     * propósito: son el contrapunto que detecta un renombrado silencioso.
     */
    public function test_no_other_production_file_names_the_session_keys(): void
    {
        $owner = app_path('Http/Sidebar/SidebarEntry.php');
        $violations = [];

        foreach ([app_path(), resource_path('views'), base_path('routes')] as $root) {
            foreach ($this->phpFiles($root) as $file) {
                if ($file === $owner) {
                    continue;
                }

                foreach ($this->keysNamedIn($file) as $key) {
                    $violations[] = '  '.mb_substr($file, mb_strlen(base_path()) + 1).'  →  '.$key;
                }
            }
        }

        $this->assertSame(
            [], $violations,
            "El desenlace del pago se está manipulando fuera de su dueño:\n".implode("\n", $violations)."\n\n".
            "Usa `App\\Http\\Sidebar\\SidebarEntry`: `confirmed()`/`failed()`/`verifying()` para\n".
            "escribir, `peek()` para mirar sin consumir (layout) y `consume()` para tomarlo (el\n".
            'motor del cajón). Quien nombra la clave a mano es quien se olvida de olvidarla.'
        );
    }

    /**
     * Claves que el fichero nombra DE VERDAD, ignorando comentarios.
     *
     * Tres docblocks del sistema citan `purchase.failed_code`/`verifying_code` a propósito, para
     * explicar por dónde viajaba antes el motivo del rechazo. Una búsqueda de texto los contaría
     * como infracciones — es la misma lección que `ModuleBoundariesTest` ya tenía escrita.
     *
     * En `.php` se usa el tokenizador y solo se miran los literales de cadena. En `.blade.php` no
     * sirve —lo de fuera de `<?php` es HTML para el tokenizador—, así que se quitan los comentarios
     * de Blade y se busca en lo que queda.
     *
     * @return list<string>
     */
    private function keysNamedIn(string $file): array
    {
        $source = (string) file_get_contents($file);

        if (str_ends_with($file, '.blade.php')) {
            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);

            return array_values(array_filter(self::KEYS, fn (string $k): bool => str_contains($source, $k)));
        }

        $literals = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $literals .= $token[1]."\n";
            }
        }

        return array_values(array_filter(self::KEYS, fn (string $k): bool => str_contains($literals, $k)));
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
