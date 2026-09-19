<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use JsonException;

/**
 * **LA RUTA ESTABLE DEL CARGADOR DEL PAQUETE** (F4 · T5, `docs/specs/cajon-empaquetable.md` §4.1).
 *
 * §4.1 pide tres piezas «servidas por el PRODUCTO desde rutas estables (no con hash, o con un manifiesto
 * público)». La hoja ya la tiene (`/css/cajon.css`, fichero estático). El cargador no: Vite le pone hash al
 * nombre —y hace bien, porque así el navegador puede cachearlo para siempre—, así que una landing no puede
 * escribir su URL a mano sin volver a tocarla en cada despliegue. Esta ruta es esa estabilidad.
 *
 * ⚠️⚠️ **Redirige, NO sirve los bytes, y el motivo es técnico**: el fichero construido trae sus `import()`
 * escritos en RELATIVO (`import("./sidebar-BEDAuaES.js")`). Un módulo resuelve sus imports contra SU PROPIA
 * URL, así que devolver el contenido desde `/cajon/paquete.js` haría al navegador pedir
 * `/cajon/sidebar-BEDAuaES.js` — un 404, y el cajón no abriría nunca. Con un 302, el módulo acaba cargado
 * desde `/build/assets/…` y sus chunks resuelven solos.
 *
 * **La caché: la redirección NO se cachea, y es a propósito.** `NoStoreWebResponses` pone `no-store` en toda
 * respuesta de la web (`RGPD-04`, invariante heredada) y su única excepción es la superficie de la API. Se
 * probó a poner aquí un `max-age=300` y el middleware lo pisa, que es lo que tiene que hacer: **una
 * excepción a una invariante de privacidad no se abre para ahorrar un salto**. El coste medido es una
 * petición de más por carga —un 302 sin cuerpo, del mismo dominio— y el fichero con hash al que apunta sí
 * se cachea largo, que es donde están los kilobytes. Si algún día pesa, se mide y se decide con su número
 * delante; de momento no hay ninguno que lo justifique.
 */
class PaqueteDelCajonController extends Controller
{
    private const ENTRADA = 'resources/js/cajon/paquete.js';

    public function __invoke(): RedirectResponse|Response
    {
        $manifiesto = public_path('build/manifest.json');

        if (! is_file($manifiesto)) {
            return $this->noConstruido('no hay `public/build/manifest.json`');
        }

        try {
            /** @var array<string, array{file?: string}> $datos */
            $datos = json_decode((string) file_get_contents($manifiesto), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->noConstruido('el manifiesto de Vite no es JSON válido');
        }

        $fichero = $datos[self::ENTRADA]['file'] ?? null;

        if (! is_string($fichero) || $fichero === '') {
            return $this->noConstruido('el manifiesto no declara `'.self::ENTRADA.'`');
        }

        return redirect(asset('build/'.$fichero), 302);
    }

    /**
     * ⚠️ **Un 503 con el motivo escrito, no un 404 ni un 500.** Esto solo pasa si el sitio se sirve sin
     * haber construido los assets, y entonces lo que falla no es la petición: es el despliegue. Quien lo
     * lea —en la consola del navegador de una landing que no es nuestra— tiene que poder saber qué mirar
     * sin acceso al servidor.
     */
    private function noConstruido(string $motivo): Response
    {
        return response(
            "/* El paquete del cajón no está construido en este servidor: {$motivo}. ".
            "Falta `npm run build`. */\n",
            503,
        )->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-store');
    }
}
