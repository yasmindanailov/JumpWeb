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
     *   · 4.3·2 (pie + cesta en memoria + paso 4) ........... 105,72 kB
     *   · 4.3·3 (aviso de reservas en pausa) ................ 107,29 kB
     *   · 4.3·4 (persistencia de la cesta) .................. 111,01 kB
     *   · 4.4a·1 (elegibilidad al pasar al pago) ............ 112,59 kB
     *   · 4.4a·2 (paso de identificación + login) ........... 122,56 kB
     *
     * ⚠️ **El techo sube a 135 en 4.4a·2, y es una decisión, no un arrastre.** El paso costó **9,97 kB**
     * (3,13 kB comprimidos) y se midió aislado construyendo con y sin él: casi todo es runtime de Vue
     * que hasta ahora no entraba —`vModelText`, `vModelCheckbox` y `withDirectives`—, porque este es el
     * **primer formulario** del cajón. Es coste de una vez: el registro de 4.4b y el pago de 4.5
     * reutilizan ese runtime. Con 120 el margen quedaba en 0,30 kB, que no es un presupuesto sino un
     * accidente esperando.
     *
     *   · 4.4b·1 (alta embebida + paso 7) .................. 131,70 kB
     *   · 4.5·1 (la cesta pide lo que le falta) ............ 133,12 kB
     *   · 4.5·2 (pasos 8 y 9: pagar y salir) ............... 139,36 kB
     *
     * ⚠️ **CUIDADO AL RESTAR: las dos cifras no están en la misma unidad.** Vite imprime en base 1000
     * («139,36 kB») y este assert divide entre 1024, así que el chunk mide **136,09 KiB**. Restar el
     * número de Vite del techo da un margen bastante menor del real; se hizo al cerrar 4.5·1 y salió
     * menos de la mitad del verdadero.
     *
     * ⚠️ **El techo sube a 150 en 4.5·2, y otra vez es una decisión medida.** Los pasos 8 y 9 costaron
     * **5,51 KiB**, aislados construyendo con y sin ellos — mucho menos que los 9,97 del primer
     * formulario, porque el runtime que aquel trajo ya estaba dentro. Con 135 el chunk se pasaba por
     * 1,09 KiB, así que subirlo era inevitable; lo que se decide es CUÁNTO: 150 deja **13,9 KiB** para
     * las tres pantallas de desenlace (4.6) y el widget de Turnstile (4.4b·2), que es lo único que
     * queda de la fase. Un techo más holgado dejaría de vigilar.
     *
     *   · 4.6·1 (paso 6: la reserva creada) ............... 144,17 kB = **140,79 KiB**
     *   · 4.6·2 (pasos 10 y 11: denegado y verificando) ... 149,34 kB = **145,85 KiB**
     *   · ⚠️ **el ledger se quedó ahí y siguió creciendo sin anotarse** durante 4.7·2b·2·B/C: medido
     *     el 2026-08-19, ANTES de tocar nada, el chunk estaba en **146,48 KiB** — o sea que el margen
     *     real no eran los 4,15 de abajo sino **3,52**. Que una cifra de presupuesto envejezca en
     *     silencio es exactamente lo que un presupuesto no puede permitirse.
     *   · 4.4b·2 (el widget de Turnstile: `turnstile.js` + el nodo y sus bindings) ... **148,24 KiB**,
     *     o sea **1,76 KiB** de coste real. Margen que queda: **1,76 KiB**.
     *
     * ⚠️ **El techo NO sube en 4.6, y el margen es hoy el dato que importa.** El primer tramo costó
     * **4,70 KiB** —menos de lo que ocupa su marcado, porque la fila del resumen se EXTRAJO a
     * `SummaryLine.vue` y la pantalla de pagar dejó de tener la suya— y el segundo **5,06 KiB**, con lo
     * que quedan **1,76 KiB** de los 150 (re-medido en 4.4b·2; ver el ledger de arriba).
     * ⚠️ **El siguiente que añada algo al cajón tiene el margen muy corto**: con 1,76 KiB, la decisión
     * escrita de este proyecto sigue siendo SUBIR el techo con su motivo, no adelgazar a ciegas.
     *
     * Con eso **la fase está transcrita entera** y lo único que falta es el widget de Turnstile
     * (4.4b·2), que es un `<div>` contenedor y una llamada a un script EXTERNO —no viaja en el bundle—,
     * así que el margen basta. Si aun así se pasa, la decisión vuelve a ser SUBIR el techo con su
     * motivo escrito, no dejar que lo empuje el arrastre. Y en 4.7, al retirar el motor Livewire, este
     * número debería BAJAR: se van con él los dos pasos que hoy conviven.
     */
    private const SIDEBAR_CHUNK_MAX_KB = 150;

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
     * **Lo que el chunk que se SIRVE tiene que saber hacer** (Fase 4 · paso 4.3·3).
     *
     * ⚠️ Nace de un hueco real: `Sidebar.vue` es el único sitio que pide `GET /booking/status`, y
     * **ningún test lo ejecuta**. No puede: lee `window.Alpine` y el idioma del documento, así que el
     * renderizador SSR del gate monta `Shell` directamente y nunca pasa por él. Se verificó por
     * mutación que borrar esa llamada dejaba la suite ENTERA en verde — y con ella el cajón volvía a
     * vender durante la pausa, que es justo la divergencia que 4.3·3 cierra.
     *
     * Es una guarda barata y del mismo tipo que la de arriba: mirar el artefacto construido. No dice
     * que el cableado sea correcto —eso lo dicen las paridades—, dice que **está**.
     *
     * @var array<string, string>
     */
    private const ENGINE_MUST_KNOW = [
        'jw.cart.v1' => 'persistir la cesta con su clave versionada',
        '/booking/status' => 'preguntar si las reservas están en pausa',
        'purchase__maint' => 'pintar el aviso de pausa',
        '/orders/quote' => 'presupuestar la cesta',
        '/cart/validate-line' => 'preguntar si una línea entra en la cesta',
        // ⚠️ 4.4a·1: el aviso TEMPRANO de admisión. Sin esta llamada el cajón lleva a la pantalla de
        // pago a quien el servidor va a rechazar —tope de pendientes, frecuencia— y, con las reservas
        // pausadas, no se entera de que ya no puede vender. `runCheckout()` tiene su propia red en
        // `node --test`; lo que esto dice es que sigue CABLEADO al clic de «Ir a pagar».
        '/me/reservation-eligibility' => 'preguntar si el titular puede reservar antes de llevarlo a pagar',
        // ⚠️ 4.4a·2: identificarse sin salir del cajón. La comprobación de credenciales y los dos
        // limitadores de `SEC-06` viven en `Identity\Services\PasswordLogin`, que es el MISMO servicio
        // que usa el modal de la web: el cajón no puede tener su propia copia de nada de eso.
        '/auth/login' => 'identificar al cliente contra el servicio que también usa la web',
        // ⚠️ Y el aviso al resto de la página. Fuera del cajón, `account-context` es un componente
        // Livewire que escucha `logged-in` para repintar «Hola, saltador/a»; sin el puente, el panel
        // seguiría ofreciendo «Entrar» a quien acaba de entrar — y ningún test del repo lo vería,
        // porque el cambio ocurre en OTRO componente y en el navegador.
        'logged-in' => 'avisar a Livewire de que ya hay sesión',
        // ⚠️ 4.4b·1: el alta embebida. `context` es lo que activa el **pay-first** en el servidor —sin
        // él manda un correo de verificación y no abre sesión—, así que su ausencia no rompería nada
        // visible: dejaría al cliente esperando un correo en mitad de una compra.
        '/auth/register' => 'dar de alta desde el cajón',
        'purchase' => 'declarar el contexto de compra, que es lo que activa el pay-first',
        // ⚠️ 4.4b·2: el widget anti-bot. Va UNO, y el porqué de que no vayan dos está medido.
        // Verificado mutando: quitar el montaje del widget y reconstruir deja esta cadena en CERO y
        // pone rojo este caso. Discrimina de verdad.
        'challenges.cloudflare.com' => 'cargar el widget del anti-bot en el propio cajón',
        // ⚠️ **`turnstile_token` NO sirve de centinela, y probarlo costó una reconstrucción.** Parece
        // el candidato natural para «el token viaja al servidor», pero al quitarlo del payload de
        // `runRegister()` las ocurrencias bajan de 6 a **4**, no a 0: el mismo identificador vive en
        // el cableado del formulario (`emptyForm`, el `v-model` del paso, el vaciado tras un fallo),
        // así que el caso seguía VERDE con el token sin mandarse. Es la misma trampa que `/payment`
        // vs `/payment-status` documentada abajo, y no hay substring que distinga «está en el
        // payload» de «está en el formulario». Ese cableado lo cubre `register.test.js` («manda el
        // token del anti-bot»), que sí muere al quitar la línea.
        // ⚠️ 4.5·2: el paso 9. `purchase__redirecting` es la clase de la pantalla que monta el
        // formulario firmado; sin ella el cajón crearía el pedido —reteniendo aforo— y no llevaría a
        // ninguna parte. El `/orders` del checkout no sirve de centinela: `/orders/quote` ya lo contiene.
        'purchase__redirecting' => 'sacar al cliente hacia la pasarela con el formulario firmado',
        // ⚠️ 4.6·1: la vuelta. `purchase__confirm` es la clase de la pantalla de reserva creada, y
        // `/event-data` la SEGUNDA petición que la sostiene: las respuestas del pack no viajan en
        // `GET orders/{code}` —son datos de un menor y del art. 9— así que sin esta llamada el resumen
        // saldría sin lo que el cliente contestó, con el resto de la pantalla intacto.
        'purchase__confirm' => 'pintar la reserva creada al volver de la pasarela',
        '/event-data' => 'traer las respuestas del pack que el pedido no lleva',
        // ⚠️ 4.6·2: los otros dos desenlaces. Las dos primeras son las clases de sus pantallas; las dos
        // últimas son DISCRIMINANTES y por eso están elegidas así:
        //  · `/payment-status` solo lo escribe `loadPaymentStatus()`, así que si el sondeo del paso 11
        //    dejara de llamarse, Rollup podaría el import y la cadena desaparecería del bundle;
        //  · `order_not_retryable` solo vive en `RETRY_ERROR_KEYS`, que solo alcanza `runRetry()`. Un
        //    `/payment` a secas NO valdría: es subcadena de `/payment-status` y estaría igual.
        'purchase__failed' => 'pintar el pago denegado con su motivo',
        'purchase__verifying' => 'pintar la espera del desenlace',
        '/payment-status' => 'preguntar en qué ha quedado el pago',
        'order_not_retryable' => 'saber cuándo ya no hay nada que reintentar y hay que rehacer la reserva',
        // ⚠️ **`/payment-status` NO basta para el SONDEO, y está medido**: el paso 10 pide ese mismo
        // endpoint para su motivo de rechazo, así que la cadena sigue en el bundle aunque el bucle del
        // paso 11 no se arranque nunca — y el cliente se quedaría mirando «verificando» para siempre.
        // `setInterval` sí discrimina: se midió que aparece **una sola vez** en el chunk y que
        // desaparece al desconectar `startPolling()`. Ni Vue ni Pinia lo usan hoy.
        'setInterval' => 'sondear EN BUCLE mientras espera la notificación de la pasarela',
    ];

    public function test_the_engine_chunk_asks_the_server_what_it_must_not_decide(): void
    {
        $manifest = $this->manifest();
        $chunk = (string) $manifest['resources/js/sidebar/index.js']['file'];
        $js = (string) file_get_contents(public_path('build/'.$chunk));

        foreach (self::ENGINE_MUST_KNOW as $needle => $what) {
            $this->assertStringContainsString(
                $needle, $js,
                "El chunk del cajón ya no contiene «{$needle}», así que ha dejado de {$what}.
".
                'Es la única red de ese cableado: `Sidebar.vue` no lo ejecuta ningún test —lee '.
                '`window.Alpine`, así que el renderizador del gate monta `Shell` directamente—, y '.
                'borrar una de estas llamadas dejaba la suite entera en verde.'
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
