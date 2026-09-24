<?php

namespace App\Http\Instancia;

use App\Http\Controllers\InstancePageController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * **Las páginas que declara el paquete de la instancia** (T4b de `isla-y-landing-nueva.md` §4.2 y §4.12;
 * `DECISIONES #681` y `#761`).
 *
 * «Kids» o «Jump» no son rutas del producto: son nombres de un cliente. Por eso las declara su paquete, en
 * `config/paginas.php` —slug, vista, hechos que consume y cómo la trata el sitemap—, y el producto las sirve con
 * un controlador genérico ({@see InstancePageController}) que le pasa a la vista los hechos que pidió
 * ({@see PageFacts}).
 *
 * ⚠️⚠️ **`SEC-12`: el fichero se EJECUTA**, igual que las vistas del paquete (Blade compila a PHP). Por eso se lee
 * SOLO desde la raíz que {@see InstanceViews::rutaDelPaquete()} ya validó —absoluta, real y fuera del árbol del
 * producto—, y nunca de una petición.
 *
 * ⚠️ **Lo que no cuadra NO se registra y se AVISA en el log, pero no tumba la web**: un slug mal escrito, una vista
 * que no existe o un hecho que no está en la lista blanca dejan esa página fuera, y las demás siguen en pie (la
 * misma doctrina que el contrato del paquete, `InstanceViews::avisarSiElContratoNoCuadra()`). Y una página nunca
 * pisa una ruta del producto: si su slug ya existe, se descarta.
 *
 * ⚠️ **Las rutas se cachean al desplegar** (`artisan optimize`): una página nueva en el paquete necesita volver a
 * construir esa caché, o no existe.
 */
final class InstancePages
{
    public const FICHERO = 'config'.DIRECTORY_SEPARATOR.'paginas.php';

    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const VISTA = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';

    private const FRECUENCIAS = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /** @var array<string, InstancePage>|null */
    private ?array $paginas = null;

    /** @return array<string, InstancePage> slug => página */
    public function todas(): array
    {
        return $this->paginas ??= $this->leer();
    }

    public function una(string $slug): ?InstancePage
    {
        return $this->todas()[$slug] ?? null;
    }

    /**
     * Registra una ruta GET por página, **después** de las del producto: una página cuyo slug ya sea una ruta se
     * descarta con aviso, para que un paquete no pueda tapar `/admin`, `/api` ni ninguna página del producto.
     */
    public function registrarRutas(Router $router): void
    {
        // El PRIMER segmento de cada ruta del producto queda reservado: una página es de un solo segmento, así que
        // con esto tampoco puede ser `api`, `admin` ni `livewire`, aunque ninguno sea una ruta por sí solo.
        $ocupadas = [];
        foreach ($router->getRoutes()->getRoutes() as $ruta) {
            $ocupadas[explode('/', trim($ruta->uri(), '/'))[0]] = true;
        }

        foreach ($this->todas() as $pagina) {
            if (isset($ocupadas[$pagina->slug])) {
                Log::warning('instancia: la página pisa una ruta del producto y no se registra', ['slug' => $pagina->slug]);

                continue;
            }

            $router->get('/'.$pagina->slug, InstancePageController::class)
                ->defaults('pagina', $pagina->slug)
                ->name($pagina->ruta());
        }
    }

    /** @return array<string, InstancePage> */
    private function leer(): array
    {
        $raiz = InstanceViews::rutaDelPaquete();
        $fichero = $raiz === null ? null : $raiz.DIRECTORY_SEPARATOR.self::FICHERO;

        if ($fichero === null || ! is_file($fichero)) {
            return [];
        }

        try {
            $declaradas = require $fichero;
        } catch (Throwable $e) {
            Log::error('instancia: config/paginas.php no se puede leer', ['error' => $e->getMessage()]);

            return [];
        }

        if (! is_array($declaradas)) {
            Log::warning('instancia: config/paginas.php no devuelve una lista de páginas');

            return [];
        }

        $paginas = [];
        foreach ($declaradas as $slug => $declarada) {
            $pagina = $this->validar($slug, $declarada);
            if ($pagina !== null) {
                $paginas[$pagina->slug] = $pagina;
            }
        }

        return $paginas;
    }

    private function validar(mixed $slug, mixed $declarada): ?InstancePage
    {
        $motivo = match (true) {
            ! is_string($slug) || preg_match(self::SLUG, $slug) !== 1 => 'el slug no es válido',
            ! is_array($declarada) => 'la página no es una lista de claves',
            ! is_string($declarada['vista'] ?? null) || preg_match(self::VISTA, $declarada['vista']) !== 1 => 'la vista no es válida',
            ! view()->exists(InstanceViews::NAMESPACE.'::'.$declarada['vista']) => 'la vista no existe en el paquete',
            ! is_array($declarada['hechos'] ?? []) || array_diff($declarada['hechos'] ?? [], array_keys(PageFacts::HECHOS)) !== [] => 'pide un hecho que no está en la lista blanca',
            ! in_array($declarada['frecuencia'] ?? 'weekly', self::FRECUENCIAS, true) => 'la frecuencia del sitemap no es válida',
            ! is_numeric($declarada['prioridad'] ?? '0.5') || (float) ($declarada['prioridad'] ?? 0.5) < 0 || (float) ($declarada['prioridad'] ?? 0.5) > 1 => 'la prioridad del sitemap no es válida',
            default => null,
        };

        if ($motivo !== null) {
            Log::warning('instancia: la página no se registra: '.$motivo, ['slug' => is_string($slug) ? $slug : null]);

            return null;
        }

        /** @var string $slug */
        /** @var array{vista: string, hechos?: list<string>, prioridad?: string|float, frecuencia?: string} $declarada */
        return new InstancePage(
            slug: $slug,
            vista: $declarada['vista'],
            hechos: array_values(array_unique($declarada['hechos'] ?? [])),
            prioridad: number_format((float) ($declarada['prioridad'] ?? 0.5), 1, '.', ''),
            frecuencia: $declarada['frecuencia'] ?? 'weekly',
        );
    }
}
