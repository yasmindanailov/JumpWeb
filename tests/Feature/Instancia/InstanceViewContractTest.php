<?php

namespace Tests\Feature\Instancia;

use App\Http\Instancia\InstanceViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL CONTRATO DE VISTA** — qué le promete el producto a cada vista que una instancia puede vestir
 * (`specs/paquete-de-instancia.md` §4.6, `DECISIONES #649`).
 *
 * ❗❗❗ **El modo de fallo que cierra esta guarda es silencioso y AFECTA A TODOS LOS CLIENTES A LA VEZ**:
 * alguien renombra `answers` en `ContactController`, la suite del producto sigue verde —ninguna prueba
 * suya mira esa variable— y la landing de cada instancia se rompe en su propio servidor, cada una por
 * su cuenta y sin que nadie relacione una cosa con la otra.
 *
 * ⚠️⚠️ **Afirma sobre los DATOS de la vista, nunca sobre el HTML.** Es la línea entera de `#649`: el
 * contrato son las variables que la vista recibe, no el marcado que produce. Si esta guarda mirara el
 * marcado volvería a atar el producto a la landing de un cliente, que es justo el defecto que la
 * decisión arregla.
 *
 * ⚠️ Y compara el conjunto EXACTO, no «al menos éstas»: quitar una variable rompe a las instancias, y
 * añadir una también cuenta —es contrato nuevo que hay que declarar, y declararlo es lo que hace que
 * el siguiente que escriba una landing sepa con qué puede contar—.
 */
class InstanceViewContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_dressable_view_receives_exactly_what_the_contract_promises(): void
    {
        foreach (InstanceViews::CONTRATO_DE_VISTAS as $vista => $contrato) {
            $respuesta = $this->get(route($contrato['ruta']))->assertOk();

            // ⚠️ `original` es la VISTA, no el HTML: `getData()` da lo que el controlador le pasó, que
            // es exactamente el sujeto de esta guarda. `viewData($clave)` solo saca una y `getContent()`
            // daría el marcado, que aquí no se mira a propósito.
            $recibidas = array_keys($respuesta->original->getData());
            sort($recibidas);
            $prometidas = $contrato['datos'];
            sort($prometidas);

            $this->assertSame(
                $prometidas,
                $recibidas,
                "La vista «{$vista}» ya no recibe lo que el contrato promete.\n".
                '▶ Si es a propósito, actualiza `InstanceViews::CONTRATO_DE_VISTAS` Y avisa a las '.
                'instancias: lo que aquí cambia les rompe la landing en su servidor, no aquí.'
            );
        }
    }
}
