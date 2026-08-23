<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Services\SelfSignup;
use Tests\TestCase;

/**
 * **La pantalla de «revisa tu correo» del cajón** (`docs/specs/auth-en-cajon.md` §4.10·2).
 *
 * Es la segunda cara de la zona de ALTA: el alta suelta manda correo de verificación y **no abre
 * sesión**, así que después de ella no hay a dónde navegar y el cliente se queda aquí. Lo que esta
 * pantalla ofrece —reenviar y salir— es lo único que le queda si el correo no llega.
 *
 * ⚠️ **Nace consolidando `SidebarResendCooldownTest`** (2026-08-23, auditoría de A8): aquel fichero
 * cubría la cuenta atrás y este añade el ESCAPE, que no lo guardaba nadie. Son la misma pantalla, así
 * que tener dos ficheros habría dejado su sujeto repartido — y el nombre es lo primero que lee quien
 * venga a tocarla dentro de seis meses.
 *
 * ⚠️ Los dos últimos casos son guardas de **CABLEADO** sobre el marcado: las zonas de la cuenta no
 * las monta `scripts/render-sidebar.mjs` —solo los once pasos del embudo—, así que el contrato de
 * árbol no las ve. Lo que estos casos impiden es que la pantalla se quede sin salida, no cómo se
 * comporta al pulsarla: eso lo cubren `stores/auth.test.js` y `account/verify.test.js`.
 */
class SidebarVerifyScreenTest extends TestCase
{
    private const MODULE = 'resources/js/sidebar/account/verify.js';

    private const SERVICE = 'app/Domain/Identity/Services/SelfSignup.php';

    private const ZONE = 'resources/js/sidebar/account/zones/RegisterZone.vue';

    // ── La cuenta atrás del reenvío ESPEJA el limitador del servidor ──────────────────────────
    //
    // ⚠️⚠️ **El problema que esto cierra existe porque el endpoint es MUDO a propósito.**
    // `POST /api/v1/auth/email/resend` responde **202 pase lo que pase** —exista la cuenta, esté ya
    // verificada o haya saltado un limitador—, que es la misma anti-enumeración del alta y del
    // enlace de recuperar (`SEC-06`). `SelfSignup::resendVerification()` devuelve `false` cuando
    // descarta el envío, pero **ese `false` no sale del servidor**.
    //
    // ▶ De ahí que la espera sea estado de PANTALLA. Y de ahí el fallo que esta guarda impide: si la
    // cuenta atrás del cajón fuera **más corta** que el limitador por IP, el botón se ofrecería
    // mientras el servidor tira el reenvío a la basura y contesta 202 igual. El cliente pulsa, lee
    // «reenviado» y **no le llega nada** — sin error, sin aviso y sin nada que mirar. Es la familia
    // de `DECISIONES #115`: una señal que mide una cosa y se lee como otra.
    //
    // ⚠️ **Se cruzan las DOS fuentes, no se copia una.** Un test que solo aseverara «30» en el módulo
    // JS se quedaría en verde el día que alguien suba el limitador del servidor a 60 — que es justo
    // cuando más falta hace el rojo. Mismo cuño que `AccountDoorWiringTest`, que cruza el mapa de
    // puertas de PHP con las zonas de `navigation.js`.

    /** La espera del cajón no puede ser MENOR que el limitador por IP del servicio. */
    public function test_the_drawer_waits_at_least_as_long_as_the_server_throttles(): void
    {
        $client = $this->secondsInModule();
        $server = $this->secondsInService();

        $this->assertGreaterThanOrEqual(
            $server, $client,
            "La cuenta atrás del cajón son {$client} s y el limitador por IP del servidor, {$server} s.\n".
            "⚠️ Con la del cajón más corta, el botón de reenviar se ofrece mientras el servidor DESCARTA\n".
            "el envío — y responde 202 igual, porque es mudo a propósito (anti-enumeración). El cliente\n".
            'pulsa, lee «reenviado» y no le llega nada: ni error, ni aviso, ni rastro.'
        );
    }

    /**
     * ⚠️ **Y tampoco puede ser mucho MAYOR**, que es el otro lado del mismo error.
     *
     * Una espera de tres minutos sobre un limitador de treinta segundos no rompe nada, pero convierte
     * la pantalla en un muro por una regla que ya no existe — y nadie lo notaría, porque «esperar de
     * más» no falla. El margen es la mitad: suficiente para absorber un cambio pequeño del servidor
     * sin que el rojo salte por nada, y estrecho para que una divergencia real se vea.
     */
    public function test_and_it_does_not_wall_the_client_far_beyond_what_the_server_asks(): void
    {
        $client = $this->secondsInModule();
        $server = $this->secondsInService();

        $this->assertLessThanOrEqual(
            (int) round($server * 1.5), $client,
            "La cuenta atrás del cajón son {$client} s para un limitador de {$server} s. Esperar de más ".
            'no falla, y por eso nadie lo nota: es una pantalla bloqueada por una regla que ya no existe.'
        );
    }

    /** Control: el servicio sigue siendo quien pone el limitador que este caso cree estar leyendo. */
    public function test_the_service_is_still_the_one_that_throttles_by_ip(): void
    {
        $this->assertTrue(
            method_exists(SelfSignup::class, 'resendVerification'),
            'el reenvío ha dejado de vivir en `SelfSignup`: este caso ya no mira lo que cree'
        );

        $this->assertStringContainsString(
            "'verify-resend:'", $this->source(self::SERVICE),
            'ha cambiado la clave del limitador por IP del reenvío; re-apunta este caso antes de fiarte'
        );
    }

    // ── La SALIDA de la pantalla ──────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **«¿Ya tienes cuenta?» tiene que estar SIEMPRE, y es anti-enumeración** (`#46`).
     *
     * La pantalla es idéntica para un alta buena y para un señuelo que actuó —el 201 del servidor no
     * los distingue—, así que un escape que apareciera solo en uno de los dos casos delataría cuál
     * fue. Y sin él, quien ya tenía cuenta se queda **sin salida**: la página `/email/verificar` exige
     * sesión, y el alta suelta no la abre.
     *
     * ▶ **Esta guarda nace de la auditoría de A8**, que midió que el escape del cajón **no lo
     * comprobaba nadie**: `SidebarMountTest` asevera que el texto VIAJA en el payload, no que la
     * pantalla lo pinte — y un texto que viaja y no se pinta es exactamente el hueco de los 20 iconos
     * vacíos (`#113`). El caso que lo cubría vivía en `DuplicateEmailEdgeCaseTest`, sobre el modal.
     */
    public function test_the_screen_always_offers_a_way_out(): void
    {
        $zone = $this->source(self::ZONE);

        $this->assertStringContainsString(
            "a('verify.already_have_account')", $zone,
            'La pantalla de «revisa tu correo» se ha quedado sin el escape «¿ya tienes cuenta?».'
        );

        $this->assertStringContainsString(
            'nav.go(ZONES.LOGIN)', $zone,
            'El escape ya no lleva a identificarse: sería un rótulo que no hace nada.'
        );

        // ⚠️ Y va FUERA del bloque que depende de los reenvíos: quien los agota sigue necesitando
        // salir. Si cayera dentro, desaparecería justo para quien más lo necesita.
        $exhausted = (int) mb_strpos($zone, "a('verify.resend_limit')");
        $escape = (int) mb_strpos($zone, "a('verify.already_have_account')");

        $this->assertGreaterThan(
            $exhausted, $escape,
            'El escape ha quedado por delante del aviso de reenvíos agotados: comprueba que no ha '.
            'caído dentro de la rama que solo se pinta mientras quedan reenvíos.'
        );
    }

    /** Y el botón de reenviar sigue cableado a su acción y gobernado por la puerta. */
    public function test_the_resend_button_is_wired_and_gated(): void
    {
        $zone = $this->source(self::ZONE);

        $this->assertStringContainsString(
            'store.resendVerification({ api })', $zone,
            'El botón de reenviar ya no llama a su acción: no falla, y no hace nada (`#117`).'
        );

        $this->assertStringContainsString(
            ':disabled="! gate.canResend"', $zone,
            'El botón ha dejado de gobernarse por la puerta: se ofrecería durante la cuenta atrás, y '.
            'el servidor descartaría el envío en silencio.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    private function secondsInModule(): int
    {
        $source = $this->source(self::MODULE);

        $this->assertMatchesRegularExpression(
            '/RESEND_COOLDOWN_SECONDS\s*=\s*(\d+)/', $source,
            'no se encuentra la cuenta atrás en el módulo del cajón'
        );

        preg_match('/RESEND_COOLDOWN_SECONDS\s*=\s*(\d+)/', $source, $matches);

        return (int) $matches[1];
    }

    /**
     * Los segundos del limitador por IP **del REENVÍO**.
     *
     * ⚠️⚠️ **Se acota al CUERPO de su método, y no es una precaución teórica: la primera versión de
     * este caso leyó el número equivocado.** `SelfSignup` tiene dos limitadores por IP con la misma
     * variable —`register()` usa 60 s y `resendVerification()`, 30—, así que un `preg_match` sobre el
     * fichero entero devuelve el del ALTA y se habría comparado la cuenta atrás del reenvío contra un
     * límite que no es el suyo. Es la trampa 1 de `CONVENCIONES §3.quater`: un nombre puede aparecer
     * dos veces en el fichero.
     *
     * ▶ Y por eso se exige además que dentro del método haya **exactamente uno**: si mañana aparece un
     * segundo, este caso volvería a elegir a ciegas.
     */
    private function secondsInService(): int
    {
        $source = $this->source(self::SERVICE);
        $start = mb_strpos($source, 'function resendVerification(');

        $this->assertNotFalse($start, 'ha cambiado la firma del reenvío: este caso ya no mira lo que cree');

        $next = mb_strpos($source, "\n    public function ", $start + 1);
        $body = mb_substr($source, $start, $next === false ? null : $next - $start);

        $found = preg_match_all('/RateLimiter::hit\(\$ipKey,\s*(\d+)\)/', $body, $matches);

        $this->assertSame(
            1, $found,
            "En el cuerpo del reenvío hay {$found} limitadores por IP, no uno. Con más de uno este caso ".
            'elegiría a ciegas cuál comparar, que es la trampa 1 de `CONVENCIONES §3.quater`.'
        );

        return (int) $matches[1][0];
    }

    private function source(string $relative): string
    {
        $path = base_path($relative);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
