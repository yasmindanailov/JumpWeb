<?php

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4 · T5 — **el paquete se pide por una ruta que no cambia** (`docs/specs/cajon-empaquetable.md` §4.1).
 *
 * La promesa del paquete es que una landing que no es del producto escriba DOS líneas y no vuelva a
 * tocarlas: la hoja (`/css/cajon.css`, fichero estático) y el cargador. El cargador lo construye Vite con
 * hash en el nombre —`paquete-CpMV1uT2.js`—, así que sin esta ruta la landing de cada instancia habría que
 * editarla en cada despliegue, que es exactamente la clase de trabajo manual que el programa existe para
 * quitar (`#610`).
 *
 * ⚠️⚠️ **Y tiene que ser una REDIRECCIÓN, no el contenido.** El módulo construido trae sus `import()` en
 * relativo, y un módulo los resuelve contra SU propia URL: sirviendo los bytes desde `/cajon/paquete.js` el
 * navegador pediría `/cajon/sidebar-<hash>.js`, que es 404, y **el cajón no abriría nunca** — sin que
 * fallara la carga del cargador ni ningún test de rutas. Por eso el caso de abajo mira el 302 y su destino.
 */
class PaqueteDelCajonTest extends TestCase
{
    // La ruta vive en el grupo `web`, y ese grupo lee ajustes del sitio antes de llegar al controlador.
    use RefreshDatabase;

    private const RUTA = '/cajon/paquete.js';

    public function test_the_stable_route_redirects_to_the_built_loader(): void
    {
        $manifiesto = $this->manifiesto();

        $this->assertArrayHasKey(
            'resources/js/cajon/paquete.js', $manifiesto,
            'el cargador del paquete no está construido: falta su entrada en `vite.config.js` o falta `npm run build`',
        );

        $esperado = (string) $manifiesto['resources/js/cajon/paquete.js']['file'];

        $respuesta = $this->get(self::RUTA);

        $respuesta->assertStatus(302);
        $respuesta->assertRedirectContains('/build/'.$esperado);
    }

    /**
     * **No sirve el contenido.** Es la mitad que no se ve del caso de arriba: un 200 con el JavaScript
     * dentro pasaría por «funciona» —el cargador se ejecuta— y el cajón moriría al abrirse, cuando pide
     * su motor por una ruta relativa que desde aquí no existe.
     */
    public function test_the_route_does_not_serve_the_bytes(): void
    {
        $respuesta = $this->get(self::RUTA);

        $this->assertNotSame(200, $respuesta->getStatusCode(), 'la ruta del paquete sirve el módulo en vez de redirigir');
        $this->assertStringNotContainsString('import(', (string) $respuesta->getContent());
    }

    /**
     * **Sin construir, un 503 que se explica solo.**
     *
     * Quien lea esto lo lee en la consola del navegador de una landing que NO es nuestra, probablemente
     * sin acceso al servidor. Un 404 diría «esta ruta no existe» y mandaría a buscar donde no es; un 500,
     * nada. El motivo va en el cuerpo, y el cuerpo es JavaScript válido (un comentario) para que el
     * navegador no añada encima un error de sintaxis que despiste más.
     */
    public function test_without_a_build_it_says_so(): void
    {
        $ruta = public_path('build/manifest.json');
        $copia = $ruta.'.prueba-paquete';

        $this->assertFileExists($ruta, 'este caso necesita el manifiesto construido para poder esconderlo');
        rename($ruta, $copia);

        try {
            $respuesta = $this->get(self::RUTA);

            $respuesta->assertStatus(503);
            $respuesta->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
            $this->assertStringContainsString('npm run build', (string) $respuesta->getContent());
            $this->assertStringStartsWith('/*', (string) $respuesta->getContent(), 'el cuerpo no es JavaScript válido');
        } finally {
            rename($copia, $ruta);
        }
    }

    /**
     * **La hoja es la otra línea, y su ruta también es estable.**
     *
     * Aquí no hay nada que construir —`public/css/cajon.css` está versionada— pero sí algo que puede
     * romperse sin ruido: que alguien la mueva o la empiece a servir por Vite con hash. Entonces las dos
     * líneas del contrato dejarían de ser dos líneas fijas.
     */
    public function test_the_sheet_is_served_from_its_stable_path(): void
    {
        $this->assertFileExists(
            public_path('css/cajon.css'),
            'la hoja del paquete ya no está en `/css/cajon.css`, que es la ruta que escriben las landings',
        );
    }

    /** @return array<string, array{file?: string}> */
    private function manifiesto(): array
    {
        $ruta = public_path('build/manifest.json');

        $this->assertFileExists($ruta, 'falta `npm run build`: sin manifiesto no hay nada que comprobar');

        return json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
    }
}
