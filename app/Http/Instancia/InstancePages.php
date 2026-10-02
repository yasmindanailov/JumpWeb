<?php

namespace App\Http\Instancia;

use App\Http\Controllers\InstancePageController;
use App\Http\Middleware\RedirectToInstancePage;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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
 *
 * ▶ **La PORTADA declarada** (`'portada' => true`, T6a de §4.17): `/` es una ruta del producto —y la sirven también sus
 * puertas (`/login`, `/mi-cuenta`…) y la vuelta del banco—, así que una página no puede tomarla por su slug. La que se
 * marca como portada no tiene ruta propia: la pinta `HomeController`, con la misma vista y los mismos hechos que
 * cualquier página ({@see InstancePageController::pintar()}). Una sola: la segunda se descarta con aviso.
 * ▶▶ **Y cualquier RUTA DEL PRODUCTO que ceda su sitio** (`'ocupa' => 'cumpleanos'`, `#832`, T6b de §4.18): la misma
 * mano para las páginas del producto que la landing nueva sustituye conservando su dirección (la de más valor en
 * Google). Solo las de {@see self::OCUPABLES}, porque solo sus controladores preguntan (`queOcupa`); la portada es el
 * caso `home`. Una por ruta: la segunda se descarta con aviso.
 * ▶▶▶ **Y las RUTAS VIEJAS que una página SUSTITUYE** (`'sustituye' => ['contacto', 'bar']`, `#843`, T6f de §4.22): esas
 * responden **301** a la página, con su `?query` ({@see RedirectToInstancePage}), y salen del sitemap. Qué ruta va adónde es
 * del cliente —lo afina con lo que Google tenga indexado—; el producto solo pone la lista de las que puede soltar
 * ({@see self::SUSTITUIBLES}) y la regla, una vez ({@see self::redireccionDe()}). Una por ruta, como `ocupa`.
 */
final class InstancePages
{
    public const FICHERO = 'config'.DIRECTORY_SEPARATOR.'paginas.php';

    /**
     * Las rutas del producto que una página del paquete puede ocupar: las que su controlador sabe cederle. `normas`, desde la
     * T6e (`#842`): `PageController::rules`. `legal`, desde la T6h (`#844`): las CINCO rutas legales a la vez
     * (`PageController::show`), cada una con su texto en el hecho `legal`. `404`, desde la L4 de `#876`: la página de «no
     * encontrada» de la web, que no es una ruta sino la respuesta a lo que no lo es (`InstanceNotFound`); sin dirección propia,
     * tampoco sale en el sitemap.
     */
    public const OCUPABLES = ['home', 'cumpleanos', 'normas', 'legal', '404'];

    /**
     * Las rutas del producto que una página puede SUSTITUIR con un 301 (`#843`): las páginas viejas de la landing, solo en GET
     * (el `POST /contacto` del formulario sigue en su sitio). Cada una lleva {@see RedirectToInstancePage} en
     * `routes/web.php`, y `InstancePagesTest` lo exige: una ruta en la lista sin el middleware no redirigiría nunca.
     * ⚠️ `entradas` NO: es el enlace profundo que abre la compra sobre la portada, no una página. Y ninguna es ocupable: una
     * ruta, o conserva la dirección, o la suelta.
     */
    public const SUSTITUIBLES = ['precios', 'atracciones', 'servicios', 'bar', 'contacto'];

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

    /** La página que ocupa la portada, o `null` si el paquete no declara ninguna (y `/` pinta la vista de siempre). */
    public function portada(): ?InstancePage
    {
        return $this->queOcupa('home');
    }

    /** La página que ocupa esa ruta del producto, o `null` si ninguna (y la ruta pinta lo de siempre). */
    public function queOcupa(string $ruta): ?InstancePage
    {
        foreach ($this->todas() as $pagina) {
            if ($pagina->ocupa === $ruta) {
                return $pagina;
            }
        }

        return null;
    }

    /**
     * **Adónde responde 301 esa ruta vieja del producto** (`#843`), o `null` si ninguna página la sustituye y sigue pintando
     * lo suyo. La URL de la página: la de la ruta que ocupa (la portada, `/`) o la suya. ⚠️ Solo si esa URL EXISTE: una
     * página que pisaba una ruta del producto no se registró, y redirigir a ella sería mandar a Google a un 404. La leen el
     * middleware y el sitemap: una sola regla para lo que se redirige y lo que se deja de anunciar.
     */
    public function redireccionDe(string $ruta): ?string
    {
        foreach ($this->todas() as $pagina) {
            if (! in_array($ruta, $pagina->sustituye, true)) {
                continue;
            }
            $suya = $pagina->ocupa ?? $pagina->ruta();

            return Route::has($suya) ? route($suya) : null;
        }

        return null;
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
            // La que ocupa una ruta del producto no tiene ruta propia: la sirve esa ruta (la portada, `/`).
            if ($pagina->ocupa !== null) {
                continue;
            }

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
        $ocupadas = [];
        $sustituidas = [];
        foreach ($declaradas as $slug => $declarada) {
            $pagina = $this->validar($slug, $declarada);
            if ($pagina === null) {
                continue;
            }
            // Una por ruta ocupada: la primera. La segunda se descarta ENTERA (no se degrada a página suelta, que sería
            // publicar en su slug lo que el paquete quería en esa ruta).
            if ($pagina->ocupa !== null && isset($ocupadas[$pagina->ocupa])) {
                Log::warning('instancia: la página no se registra: ya hay otra en la ruta que ocupa', ['slug' => $pagina->slug, 'ocupa' => $pagina->ocupa]);

                continue;
            }
            // Y una por ruta sustituida (`#843`), con la misma doctrina: dos destinos para un 301 es un paquete mal escrito.
            if (($repetidas = array_values(array_intersect($pagina->sustituye, array_keys($sustituidas)))) !== []) {
                Log::warning('instancia: la página no se registra: otra ya sustituye esa ruta', ['slug' => $pagina->slug, 'sustituye' => $repetidas]);

                continue;
            }
            if ($pagina->ocupa !== null) {
                $ocupadas[$pagina->ocupa] = true;
            }
            $sustituidas += array_fill_keys($pagina->sustituye, true);
            $paginas[$pagina->slug] = $pagina;
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
            ! is_bool($declarada['portada'] ?? false) => 'la marca de portada no es verdadero o falso',
            isset($declarada['ocupa']) && ! in_array($declarada['ocupa'], self::OCUPABLES, true) => 'la ruta que ocupa no es una que el producto ceda',
            ($declarada['portada'] ?? false) === true && isset($declarada['ocupa']) && $declarada['ocupa'] !== 'home' => 'es la portada y ocupa otra ruta',
            ! self::sustituyeBien($declarada['sustituye'] ?? []) => 'sustituye una ruta que el producto no suelta con un 301',
            default => null,
        };

        if ($motivo !== null) {
            Log::warning('instancia: la página no se registra: '.$motivo, ['slug' => is_string($slug) ? $slug : null]);

            return null;
        }

        /** @var string $slug */
        /** @var array{vista: string, hechos?: list<string>, prioridad?: string|float, frecuencia?: string, portada?: bool, ocupa?: string, sitemap?: bool, sustituye?: list<string>} $declarada */
        return new InstancePage(
            slug: $slug,
            vista: $declarada['vista'],
            hechos: array_values(array_unique($declarada['hechos'] ?? [])),
            prioridad: number_format((float) ($declarada['prioridad'] ?? 0.5), 1, '.', ''),
            frecuencia: $declarada['frecuencia'] ?? 'weekly',
            // `'portada' => true` es el caso `home` de `ocupa` (`#827` antes que `#832`).
            ocupa: ($declarada['portada'] ?? false) ? 'home' : ($declarada['ocupa'] ?? null),
            // Fuera del sitemap solo si lo dice con un `false` de verdad (T6c·4b): cualquier otra cosa, dentro.
            sitemap: ($declarada['sitemap'] ?? true) !== false,
            sustituye: array_values(array_unique($declarada['sustituye'] ?? [])),
        );
    }

    /** `'sustituye'` es una LISTA de nombres de {@see self::SUSTITUIBLES}; una cadena suelta o un nombre ajeno, no. */
    private static function sustituyeBien(mixed $rutas): bool
    {
        return is_array($rutas) && array_is_list($rutas)
            && array_filter($rutas, fn (mixed $r): bool => ! is_string($r) || ! in_array($r, self::SUSTITUIBLES, true)) === [];
    }
}
