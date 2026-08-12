<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 2 — **frontera de módulo EJECUTABLE** (`docs/specs/modulos-dominio.md` §4, `DECISIONES #13`).
 *
 * El namespace plano de `app/Support` + `app/Models` esconde las llamadas cruzadas: no aparecen
 * como imports, así que las fronteras entre contextos son invisibles e inejecutables. Este test
 * las hace visibles Y las impone sobre lo que ya vive en `app/Domain`.
 *
 * Tres guardas:
 *  1. **Grafo permitido** entre módulos (`ALLOWED`): quién puede mirar a quién.
 *  2. **Baselines explícitas** (`SEAM` y `LEGACY`) para lo que aún no cumple el grafo, con el paso
 *     del spec que las retira. **Solo pueden ENCOGER**: una entrada que deja de usarse hace fallar
 *     el test, así que borrarla es obligatorio y nadie puede colar una flecha nueva ahí dentro.
 *  3. **Puerta de entrada**: desde fuera de `app/Domain` solo se puede tocar `Contracts` — nunca
 *     los `Services`/`Models` de un módulo.
 *
 * El escaneo usa el TOKENIZADOR de PHP, no expresiones regulares sobre el texto: los docblocks de
 * los contratos citan clases legacy a propósito y una regex las contaría como dependencias reales.
 */
class ModuleBoundariesTest extends TestCase
{
    /**
     * Grafo permitido (spec §4): todos→Platform; Content/Identity→`Contracts` de Booking/Payments;
     * Booking↔Payments solo por `SEAM`. Cada módulo puede mirarse a sí mismo siempre.
     *
     * Prefijos relativos a `App\Domain\`.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'Platform' => [],
        'Content' => ['Platform', 'Booking\Contracts', 'Payments\Contracts'],
        'Identity' => ['Platform', 'Booking\Contracts', 'Payments\Contracts'],
        'Booking' => ['Platform'],
        'Payments' => ['Platform'],
    ];

    /**
     * Costura Booking↔Payments: el dinero. Explícita fichero a fichero — no es un permiso de
     * módulo, es una lista de flechas concretas que la revisión aceptó (spec §4 y §6).
     *
     * Hoy vacía: la llamada real (`Order` → `RefundGateway`) sigue en `app/Models/Order.php` y
     * entra aquí cuando Booking mude en el paso 6.
     *
     * @var array<string, list<string>>
     */
    private const SEAM = [];

    /**
     * Baseline LEGACY: referencias desde `app/Domain` a código que aún no se ha modularizado
     * (`App\Models\*`, `App\Support\*`). Cada entrada dice qué paso del spec la retira.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY = [
        // El contrato tipa contra el modelo del PROPIO módulo Payments; muere en el paso 5.
        'Payments/Contracts/RefundGateway.php' => ['App\Models\Payment'],
        // Bindings a las implementaciones legacy: es exactamente su razón de ser hasta que muden.
        'Payments/PaymentsServiceProvider.php' => ['App\Support\Redsys'],
        'Booking/BookingServiceProvider.php' => [
            'App\Support\CustomerReservationsReader',
            'App\Support\PublishableCatalogReader',
        ],
    ];

    /** El escaneo nunca puede pasar en vacío (un glob roto lo volvería un test decorativo). */
    public function test_the_scan_actually_sees_the_domain_modules(): void
    {
        $files = $this->domainFiles();

        $this->assertNotEmpty($files, 'no se ha escaneado ningún fichero de app/Domain');
        $this->assertNotEmpty(
            array_filter($files, fn (string $f): bool => str_contains($f, '/Contracts/')),
            'no se ha escaneado ningún contrato'
        );
    }

    /** Cada carpeta de `app/Domain` debe declarar sus flechas: módulo nuevo sin grafo = fallo. */
    public function test_every_module_declares_its_allowed_arrows(): void
    {
        foreach ($this->modules() as $module) {
            $this->assertArrayHasKey(
                $module, self::ALLOWED,
                "el módulo «{$module}» no declara su grafo en ModuleBoundariesTest::ALLOWED"
            );
        }
    }

    /** La guarda principal: ninguna flecha fuera del grafo ni de las baselines. */
    public function test_domain_modules_only_depend_on_what_the_graph_allows(): void
    {
        $violations = [];

        foreach ($this->domainFiles() as $file) {
            $relative = $this->relative($file);
            $module = explode('/', $relative)[0];

            foreach ($this->appReferences($file) as $reference) {
                if ($this->isAllowed($module, $relative, $reference)) {
                    continue;
                }

                $violations[] = "  {$relative}  →  {$reference}";
            }
        }

        $this->assertSame([], $violations, "Flechas fuera del grafo de módulos:\n".implode("\n", $violations));
    }

    /**
     * Las baselines SOLO ENCOGEN: una entrada que ya no se usa debe borrarse en el mismo commit
     * que la deja de usar. Sin esta guarda la lista se convierte en un cajón de sastre que
     * legitima flechas nuevas escondidas entre las viejas.
     */
    #[DataProvider('baselineProvider')]
    public function test_baseline_entries_are_still_in_use(string $baseline, string $relative, string $reference): void
    {
        $path = app_path('Domain/'.$relative);

        $this->assertFileExists($path, "{$baseline}: «{$relative}» ya no existe — quita su entrada");
        $this->assertContains(
            $reference,
            $this->appReferences($path),
            "{$baseline}: «{$relative}» ya no referencia «{$reference}» — quita la entrada (la baseline solo encoge)"
        );
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function baselineProvider(): iterable
    {
        foreach (['SEAM' => self::SEAM, 'LEGACY' => self::LEGACY] as $name => $entries) {
            foreach ($entries as $relative => $references) {
                foreach ($references as $reference) {
                    yield "{$name}: {$relative} → {$reference}" => [$name, $relative, $reference];
                }
            }
        }

        // Con las dos baselines vacías el proveedor no puede quedarse sin casos (PHPUnit lo
        // trataría como error): un caso trivial mantiene el test verde y honesto.
        if (self::SEAM === [] && self::LEGACY === []) {
            yield 'baselines vacías' => ['-', '-', '-'];
        }
    }

    /**
     * Puerta de entrada: el resto de `app/` (capa de entrega y código aún sin modularizar) solo
     * puede hablar con los `Contracts` de un módulo. Es la mitad que impide que la modularización
     * se convierta en «mover carpetas y seguir llamando a lo de dentro».
     */
    public function test_code_outside_the_modules_only_touches_contracts(): void
    {
        $violations = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            if (str_starts_with($file, app_path('Domain'))) {
                continue;
            }

            foreach ($this->appReferences($file) as $reference) {
                if (! str_starts_with($reference, 'App\\Domain\\')) {
                    continue;
                }
                // App\Domain\<Módulo>\Contracts\<Símbolo>
                if (preg_match('/^App\\\\Domain\\\\[A-Za-z]+\\\\Contracts\\\\/', $reference) === 1) {
                    continue;
                }

                $violations[] = '  '.mb_substr($file, mb_strlen(base_path()) + 1)."  →  {$reference}";
            }
        }

        $this->assertSame(
            [], $violations,
            "Fuera de app/Domain solo se pueden usar los Contracts de un módulo:\n".implode("\n", $violations)
        );
    }

    private function isAllowed(string $module, string $relative, string $reference): bool
    {
        if (in_array($reference, self::SEAM[$relative] ?? [], true)) {
            return true;
        }
        if (in_array($reference, self::LEGACY[$relative] ?? [], true)) {
            return true;
        }
        if (! str_starts_with($reference, 'App\\Domain\\')) {
            return false;   // legacy sin baseline explícita
        }

        $target = mb_substr($reference, mb_strlen('App\\Domain\\'));

        // El propio módulo, siempre.
        if (str_starts_with($target, $module.'\\') || $target === $module) {
            return true;
        }

        foreach (self::ALLOWED[$module] ?? [] as $prefix) {
            if (str_starts_with($target, $prefix.'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Símbolos `App\…` REALMENTE referenciados por el fichero (imports y FQCN en línea),
     * ignorando comentarios y docblocks. Se excluye la propia declaración `namespace`.
     *
     * @return list<string>
     */
    private function appReferences(string $file): array
    {
        $tokens = token_get_all((string) file_get_contents($file));
        $references = [];
        $skipNext = false;

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $skipNext = true;

                continue;
            }

            if ($token[0] !== T_NAME_QUALIFIED && $token[0] !== T_NAME_FULLY_QUALIFIED) {
                continue;
            }

            if ($skipNext) {
                $skipNext = false;   // el nombre que sigue a `namespace` no es una dependencia

                continue;
            }

            $name = ltrim($token[1], '\\');
            if (str_starts_with($name, 'App\\')) {
                $references[$name] = true;
            }
        }

        return array_keys($references);
    }

    /** @return list<string> */
    private function domainFiles(): array
    {
        return is_dir(app_path('Domain')) ? $this->phpFiles(app_path('Domain')) : [];
    }

    /** @return list<string> */
    private function modules(): array
    {
        $modules = [];
        foreach ($this->domainFiles() as $file) {
            $modules[explode('/', $this->relative($file))[0]] = true;
        }

        return array_keys($modules);
    }

    /** Ruta relativa a `app/Domain`, con `/` — la clave de las baselines. */
    private function relative(string $file): string
    {
        return mb_substr($file, mb_strlen(app_path('Domain')) + 1);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
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
