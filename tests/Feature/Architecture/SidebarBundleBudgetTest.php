<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Fase 4 · paso 4.1 — **el peso del JS público tiene techo, y Vue no viaja con la landing**
 * (`docs/specs/sidebar-spa.md` §4.7, criterio CE-7).
 *
 * Antes de este paso el repo **no tenía ningún gate de tamaño de bundle**, y la fase que empieza
 * mete Vue, Pinia y once pasos de asistente. El riesgo no es teórico: la landing sirve hoy ~16 kB de
 * JS propio, y montar el motor nuevo en el bundle de todas las páginas públicas es un orden de
 * magnitud más — **con el flag activo se enviarían los DOS motores a la vez**.
 *
 * Es un test-presupuesto, hermano de `ApiOverheadTest` y de `SidebarTokenBudgetTest`: no persigue un
 * ideal, impide que empeore sin que nadie lo vea.
 *
 * ⚠️ Depende de `public/build`, que **no está versionado**. El `pre-push` corre `npm run build`
 * ANTES de la suite justo por esto (`PrePushGateTest`), así que en el gate siempre existe; si un
 * agente corre la suite a mano sin haber construido, el test lo dice en vez de fallar en falso.
 */
class SidebarBundleBudgetTest extends TestCase
{
    /**
     * Techo del entry que SÍ carga toda página pública. Medido tras enganchar el motor SPA:
     * 16,27 kB — el coste del enganche fue **medio kB**, porque lo único que entra es el `import()`
     * diferido y su manejo de errores.
     *
     * El margen es corto a propósito: este número solo debe subir cuando alguien decida que la
     * landing haga algo más, no por arrastre de una dependencia que se coló.
     */
    private const LANDING_ENTRY_MAX_KB = 20;

    /**
     * Techo del chunk del cajón, que se descarga en la PRIMERA apertura. Los once pasos llegan a
     * partir de 4.2, así que este número sube; lo que no puede es subir **sin que nadie lo decida**.
     *
     * Consumo medido, para que el margen se lea de un vistazo y no haya que reconstruir para saberlo:
     *   · 4.1 (Vue 3 + Pinia + andamio, sin negocio) ....... 69,13 kB
     *   · 4.2 (catálogo, calendario, hora y complementos) ... 90,29 kB
     *   · 4.3·1 (armazón + módulos de texto e importes) ..... 95,54 kB
     * Quedan ~24 kB para la cesta, la identificación, el pago y las tres pantallas de desenlace. Si el
     * paso que los meta se pasa, la decisión es SUBIR el techo con su motivo escrito — no dejar que lo
     * empuje el arrastre, que es lo que este test existe para impedir.
     */
    private const SIDEBAR_CHUNK_MAX_KB = 120;

    /**
     * Firmas del runtime que NO pueden aparecer en el entry de la landing. Es la guarda de verdad: un
     * techo en kB se puede satisfacer por casualidad, pero encontrar el runtime de Vue dentro del
     * bundle que carga la home significa que el `import()` dejó de ser dinámico —basta un `import`
     * estático en `app.js` para que Rollup lo funda— y eso no se ve en el diff.
     *
     * ⚠️ **Son marcadores INTERNOS de Vue y Pinia, no rutas de `node_modules`.** La primera versión
     * de esta guarda buscaba `node_modules/vue/` y pasaba **sin mirar nada**: en un build de
     * producción Vite no conserva las rutas de origen, así que la cadena no está ni en el chunk que
     * sí lleva Vue. Se comprobó contra el bundle real antes de fijarlas — un test verde que no puede
     * fallar es peor que no tenerlo.
     *
     * @var list<string>
     */
    private const NEVER_IN_LANDING = ['__v_isRef', '__v_skip', '__vue_app__', 'pinia'];

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = public_path('build/manifest.json');

        $this->assertFileExists(
            $path,
            'No hay `public/build`: corre `npm run build` antes de la suite. El `pre-push` ya lo hace.'
        );

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function sizeKb(string $file): float
    {
        $path = public_path('build/'.$file);
        $this->assertFileExists($path, "el manifiesto declara «{$file}» y no está construido");

        return filesize($path) / 1024;
    }

    public function test_the_landing_entry_stays_under_its_budget(): void
    {
        $manifest = $this->manifest();
        $entry = $manifest['resources/js/app.js']['file'] ?? null;

        $this->assertNotNull($entry, 'el entry de la landing no está en el manifiesto');

        $kb = $this->sizeKb($entry);

        $this->assertLessThanOrEqual(
            self::LANDING_ENTRY_MAX_KB, $kb,
            sprintf(
                "El JS que carga TODA página pública pesa %.1f kB (techo: %d kB).\n".
                'Si es por el cajón, el `import()` ha dejado de ser diferido. Si es trabajo nuevo de '.
                'la landing, sube el techo a propósito — es un presupuesto, no un objetivo.',
                $kb, self::LANDING_ENTRY_MAX_KB
            )
        );
    }

    /**
     * **La guarda que sostiene la decisión de §4.7.** El chunk existe ⇔ el entry lo importa de forma
     * DINÁMICA. En cuanto alguien escriba `import Sidebar from './sidebar'` arriba de `app.js`,
     * Rollup lo funde con el entry, este chunk desaparece del manifiesto y la landing engorda un
     * orden de magnitud sin que el diff lo enseñe.
     */
    public function test_the_sidebar_engine_is_a_separate_chunk_loaded_on_demand(): void
    {
        $manifest = $this->manifest();

        $this->assertArrayHasKey(
            'resources/js/sidebar/index.js', $manifest,
            'El motor SPA ya no es un chunk propio: alguien lo ha importado de forma ESTÁTICA en el '.
            'entry, así que Vue y Pinia viajan ahora con todas las páginas públicas.'
        );

        $this->assertContains(
            'resources/js/sidebar/index.js',
            $manifest['resources/js/app.js']['dynamicImports'] ?? [],
            'El entry de la landing ya no declara el motor como importación dinámica.'
        );

        $kb = $this->sizeKb($manifest['resources/js/sidebar/index.js']['file']);

        $this->assertLessThanOrEqual(
            self::SIDEBAR_CHUNK_MAX_KB, $kb,
            sprintf(
                'El chunk del cajón pesa %.1f kB (techo: %d kB). Se descarga en la primera apertura, '.
                'así que su peso es tiempo de espera del cliente justo cuando quiere comprar.',
                $kb, self::SIDEBAR_CHUNK_MAX_KB
            )
        );
    }

    public function test_vue_never_travels_with_the_landing(): void
    {
        $manifest = $this->manifest();
        $entry = (string) $manifest['resources/js/app.js']['file'];
        $js = (string) file_get_contents(public_path('build/'.$entry));

        foreach (self::NEVER_IN_LANDING as $signature) {
            $this->assertStringNotContainsString(
                $signature, $js,
                "La firma «{$signature}» ha acabado dentro del JS que carga toda página pública: el ".
                'motor del cajón ha dejado de traerse con `import()` en la primera apertura (§4.7).'
            );
        }
    }

    /**
     * **La guarda de la guarda.** El test de arriba solo significa algo si esas firmas están de
     * verdad en el bundle del motor: si Vue cambiara sus marcadores internos, aquélla seguiría verde
     * para siempre sin mirar nada — que es exactamente lo que hacía su primera versión.
     */
    public function test_the_runtime_signatures_actually_exist_in_the_engine_chunk(): void
    {
        $manifest = $this->manifest();
        $chunk = (string) $manifest['resources/js/sidebar/index.js']['file'];
        $js = (string) file_get_contents(public_path('build/'.$chunk));

        foreach (self::NEVER_IN_LANDING as $signature) {
            $this->assertStringContainsString(
                $signature, $js,
                "«{$signature}» ya no aparece en el chunk del motor, así que buscarla en el entry de ".
                'la landing no demuestra nada. Vuelve a medirla contra el bundle real.'
            );
        }
    }
}
