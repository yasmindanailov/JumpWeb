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
    //
    // ⚠️⚠️ **Y esta guarda estaba MIRANDO EL LIMITADOR EQUIVOCADO** (corregido el 2026-08-23,
    // `DECISIONES #125`). El reenvío tiene DOS cooldowns —por IP y por CORREO destinatario— y este
    // caso leía solo el de IP, el más CORTO de los dos. Lo mismo hacía `verify.js`, que descartaba
    // por escrito el de correo como «protege al buzón de una víctima, no a este cliente»: en el alta
    // SUELTA el cliente **es** el destinatario, así que ese limitador le ata igual.
    // ▶ **Medido en navegador, dos veces** (`V17·2`, `docs/VERIFICACION-E2E-CAJON.md` §5.quinquies):
    // con 30 s en el cajón, cuatro reenvíos produjeron **DOS correos**. Los pulsados a los 32 s y
    // 93 s salieron; los de 63 s y 123 s los tiró el limitador por correo. HTTP **202** en los
    // cuatro, y la pantalla descontando «te quedan N» en los cuatro.
    // ▶ La guarda estaba bien construida y bien razonada: **eligió mal cuál de los dos números
    // manda**. Ahora lee el que ATA, que es el mayor, y hay un caso que vigila cuál es.

    /** La espera del cajón no puede ser MENOR que el cooldown que ATA en el servidor. */
    public function test_the_drawer_waits_at_least_as_long_as_the_server_throttles(): void
    {
        $client = $this->secondsInModule();
        $server = $this->secondsInService();

        $this->assertGreaterThanOrEqual(
            $server, $client,
            "La cuenta atrás del cajón son {$client} s y el cooldown que ATA en el servidor, {$server} s.\n".
            "⚠️ Con la del cajón más corta, el botón de reenviar se ofrece mientras el servidor DESCARTA\n".
            "el envío — y responde 202 igual, porque es mudo a propósito (anti-enumeración). El cliente\n".
            "pulsa, lee «reenviado», GASTA uno de sus cuatro reenvíos y no le llega nada: ni error, ni\n".
            'aviso, ni rastro. Medido así el 2026-08-23: 4 reenvíos, 2 correos (`DECISIONES #125`).'
        );
    }

    /**
     * ⚠️⚠️ **Y cuál de los dos cooldowns ata no puede cambiar sin que nadie se entere.**
     *
     * El de CORREO es el que protege el buzón de alguien que no ha pedido nada: sin él, con IPs
     * rotativas se bombardea una dirección ajena a verificaciones. Si algún día quedara por debajo
     * del de IP, el caso de arriba seguiría verde —sigue leyendo el máximo— y la defensa habría
     * desaparecido en silencio. Esto es lo único que lo dice.
     */
    public function test_the_per_email_cooldown_is_the_one_that_binds(): void
    {
        $this->assertGreaterThanOrEqual(
            SelfSignup::RESEND_IP_COOLDOWN_SECONDS,
            SelfSignup::RESEND_EMAIL_COOLDOWN_SECONDS,
            'El cooldown por CORREO ha quedado por debajo del de IP. No es un ajuste de cadencia: es '.
            'la única defensa contra bombardear el buzón de un tercero con IPs rotativas.'
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
     * Los segundos del cooldown que **ATA** en el reenvío: el mayor de los dos que aplica el
     * servicio (por IP y por CORREO destinatario).
     *
     * ⚠️⚠️ **Se leen las CONSTANTES, no el código fuente, y ese cambio es la mitad del arreglo de
     * `#125`.** La versión anterior hacía `preg_match` de `RateLimiter::hit($ipKey, 30)` sobre el
     * cuerpo del método, con toda una defensa escrita para no confundirse de `$ipKey` — y aun así
     * **no vio el segundo limitador**, el de correo, que estaba tres líneas más abajo con otra
     * variable. Un regex acotado a un nombre solo encuentra lo que ya sabías que buscabas.
     * ▶ Con los dos cooldowns promovidos a constantes con nombre, la ambigüedad desaparece **por
     * construcción**: aquí se toma el máximo y no hay nada que elegir a ciegas.
     *
     * ⚠️ Lo que sí sigue haciendo falta es comprobar que las constantes son las que el método USA:
     * una constante que nadie aplica es una foto, no una regla.
     */
    private function secondsInService(): int
    {
        $body = $this->resendBody();

        foreach (['RESEND_IP_COOLDOWN_SECONDS', 'RESEND_EMAIL_COOLDOWN_SECONDS'] as $constant) {
            $this->assertStringContainsString(
                'self::'.$constant, $body,
                "El reenvío ya no aplica `{$constant}`: la constante existe pero no gobierna nada, ".
                'así que comparar contra ella sería comparar contra una foto.'
            );
        }

        // ⚠️ Y que no haya aparecido un TERCER cooldown con su número suelto: sería justo el que este
        // caso no miraría, que es exactamente cómo se coló el de correo hasta el 2026-08-23.
        $literals = preg_match_all('/RateLimiter::hit\([^,]+,\s*\d+\s*\)/', $body);

        $this->assertSame(
            0, $literals,
            "En el cuerpo del reenvío hay {$literals} limitador(es) con el número escrito a mano. ".
            'Promuévelo a constante en `SelfSignup` o este caso volverá a mirar solo una parte.'
        );

        return max(
            SelfSignup::RESEND_IP_COOLDOWN_SECONDS,
            SelfSignup::RESEND_EMAIL_COOLDOWN_SECONDS,
        );
    }

    /** El cuerpo de `resendVerification()`, acotado a su método. */
    private function resendBody(): string
    {
        $source = $this->source(self::SERVICE);
        $start = mb_strpos($source, 'function resendVerification(');

        $this->assertNotFalse($start, 'ha cambiado la firma del reenvío: este caso ya no mira lo que cree');

        $next = mb_strpos($source, "\n    public function ", $start + 1);

        return mb_substr($source, $start, $next === false ? null : $next - $start);
    }

    private function source(string $relative): string
    {
        $path = base_path($relative);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
