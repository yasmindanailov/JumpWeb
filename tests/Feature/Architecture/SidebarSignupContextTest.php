<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **El alta del EMBUDO declara su contexto, y nadie más puede decidirlo por ella** (2026-08-23,
 * `docs/specs/auth-en-cajon.md` §4.3).
 *
 * `POST /api/v1/auth/register` tiene dos políticas y las separa un campo:
 *  · `context: purchase` → **pay-first**: el servidor NO manda correo de verificación y **abre
 *    sesión**, porque el pago sustituye a la verificación —un bot no paga— (`DECISIONES #31`);
 *  · `context: standalone` → manda el correo y no abre sesión.
 *
 * ⚠️⚠️ **Hasta hoy `register.js` mandaba `purchase` QUEMADO**, porque su único cliente era el embudo.
 * Al traer el alta también al área de cliente, dejarlo allí habría convertido el alta SUELTA en
 * pay-first: sin correo de verificación y con sesión abierta. Un cambio de política que **nadie
 * decidió y que ninguna pantalla delata**.
 *
 * ▶ El arreglo mueve la decisión a quien llama, y eso abre el hueco que esta guarda cierra: el módulo
 * tiene su caso del default y el store tiene el suyo del paso a través, pero **nada comprobaba el
 * eslabón de arriba** —que el embudo lo declare—. Es literalmente la familia de `DECISIONES #117` y
 * `#118`: los dos extremos probados y el medio sin cablear, con la diferencia de que aquí el fallo
 * no es silencioso sino caro y visible —el cliente que está pagando se queda en «revisa tu correo» en
 * mitad de la compra—, que es justo por lo que el default se eligió conservador.
 *
 * ⚠️ Guarda de CABLEADO, no de conducta: comprueba que el eslabón existe, no cómo se comporta. Lo que
 * hace cada contexto lo prueban `Api\V1\AuthRegistrationTest` (servidor) y `register.test.js`
 * (cliente); que el store no se lo coma por el camino, `stores/auth.test.js`.
 */
class SidebarSignupContextTest extends TestCase
{
    private const FUNNEL = 'resources/js/sidebar/sections/PurchaseSection.vue';

    private const MODULE = 'resources/js/sidebar/register.js';

    /**
     * El embudo pide el alta **una sola vez** y esa llamada lleva el contexto de compra.
     *
     * ⚠️ El recuento del ancla va en el mismo caso y no es adorno (`CONVENCIONES §3.quater`, trampa 3):
     * si un día hubiera dos llamadas, un `assertStringContainsString` seguiría en verde con la segunda
     * sin contexto, y el caso estaría midiendo la que no falla.
     */
    public function test_the_funnel_declares_the_pay_first_context_when_it_signs_up(): void
    {
        $funnel = $this->source(self::FUNNEL);

        $this->assertSame(
            1, substr_count($funnel, 'authStore.register('),
            'El embudo ha dejado de pedir el alta una sola vez. Con dos llamadas, la comprobación de '.
            'abajo puede quedarse mirando la que sí lleva contexto mientras la otra no lo lleva.'
        );

        $this->assertMatchesRegularExpression(
            '/authStore\.register\(\{[^}]*context:\s*CONTEXT_PURCHASE/',
            $funnel,
            "El alta del EMBUDO ya no declara `context: CONTEXT_PURCHASE`.\n".
            '⚠️ Sin él, el servidor aplica el contexto suelto: manda correo de verificación y NO abre '.
            'sesión, así que el cliente que está comprando se queda en «revisa tu correo» a mitad del '.
            'embudo y no puede pagar.'
        );
    }

    /**
     * **Y el módulo no puede volver a decidirlo por su cuenta.**
     *
     * ⚠️ Se comprueba sobre el CUERPO de la petición, no sobre el fichero entero: la constante tiene
     * que seguir declarada y exportada ahí —es de donde la importan sus dos clientes—, así que buscar
     * su nombre a secas daría un rojo permanente y sin sentido. Lo que no puede volver es que el
     * cuerpo la escriba en vez de reenviar el parámetro.
     */
    public function test_the_module_no_longer_decides_the_context_on_its_own(): void
    {
        $module = $this->source(self::MODULE);
        $start = mb_strpos($module, "api.post('/auth/register'");

        $this->assertNotFalse($start, 'ha cambiado la llamada del alta: este caso ya no mira lo que cree');

        $body = mb_substr($module, $start, (int) mb_strpos($module, '});', $start) - $start);

        $this->assertStringContainsString('context,', $body, 'el cuerpo ha dejado de reenviar el contexto de quien llama');

        $this->assertStringNotContainsString(
            'context: CONTEXT_PURCHASE', $body,
            "El módulo ha vuelto a quemar el contexto de COMPRA en el cuerpo de la petición.\n".
            '⚠️ Con eso, el alta del ÁREA DE CLIENTE pasa a ser pay-first sin que nadie lo decida: '.
            'nadie recibe el correo de verificación y todas las altas abren sesión.'
        );
    }

    private function source(string $relative): string
    {
        $path = base_path($relative);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
