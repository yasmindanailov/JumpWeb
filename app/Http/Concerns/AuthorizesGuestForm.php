<?php

namespace App\Http\Concerns;

use App\Domain\Booking\Models\OrderItem;
use Illuminate\Http\Request;

/**
 * Control de acceso al post-form de una reserva, compartido por la página web y por la API
 * (Fase 3 · paso 5).
 *
 * **Lo que se comparte no es el ahorro de tres líneas: es el ORDEN**, que aquí es una propiedad de
 * seguridad y no un detalle. Se autoriza ANTES de comprobar elegibilidad, de modo que un tercero no
 * pueda deducir por el código de estado si una reserva existe, si está pagada o si su titular
 * ejerció el derecho de supresión. Con las comprobaciones al revés, un 410 y un 404 le contarían a
 * cualquiera cosas distintas sobre pedidos ajenos.
 *
 * La escalada, y por qué cada peldaño:
 *  1. **403** — ni firma válida ni titular autenticado. El acceso sin sesión existe porque el enlace
 *     viaja por correo semanas antes del evento: la firma HMAC ES la prueba de titularidad.
 *  2. **410** — el titular se anonimizó (RGPD art. 17). La supresión CIERRA el canal: el enlace no
 *     puede seguir abriendo —ni re-escribiendo— nombres y alergias de menores de una reserva
 *     borrada. `Gone` y no `Forbidden` porque el recurso existió y dejó de existir a propósito.
 *  3. **404** — no es una reserva con post-form de un pedido pagado. Solo lo ve quien ya demostró
 *     acceso, así que no filtra nada.
 */
trait AuthorizesGuestForm
{
    /**
     * @param  OrderItem|null  $reservation  `null` cuando el identificador no resuelve a ninguna
     *                                       reserva; se trata como el peldaño 3 y NO como un 404
     *                                       temprano, para no responder distinto a un desconocido
     *                                       según exista o no el id.
     */
    protected function authorizeGuestFormAccess(Request $request, ?OrderItem $reservation): void
    {
        abort_unless(
            $this->hasLiveGuestFormSignature($request, $reservation)
                || $this->ownsGuestForm($request, $reservation),
            403
        );

        abort_if($reservation?->order?->user?->isAnonymized() ?? false, 410);

        abort_unless($reservation?->acceptsGuestForm() ?? false, 404);
    }

    /**
     * ¿La firma es válida **y sigue viva**? (`specs/complementos-post-reserva.md` §4.6.bis, `#413` D14.)
     *
     * La firma prueba que el enlace lo emitimos nosotros; **no prueba que siga valiendo**. La versión
     * viaja dentro de la URL firmada y se compara con la de la reserva, así que rotarla invalida en
     * el acto todos los enlaces anteriores — la palanca que a esta credencial le faltaba, porque es
     * HMAC y `RGPD-06` no tiene fila que revocar.
     *
     * ⚠️ **Va en el peldaño del 403 a propósito**: un enlace rotado es una credencial retirada, no un
     * recurso que dejó de existir. Responder 404 aquí le contaría a un desconocido que la reserva
     * existía, que es justo lo que la escalada evita.
     *
     * ⚠️ **Un enlace SIN `v` se lee como versión 0**, que es el default de la columna: los enlaces
     * emitidos antes de que esto existiera siguen abriendo. La versión solo separa cuando alguien rota.
     */
    protected function hasLiveGuestFormSignature(Request $request, ?OrderItem $reservation): bool
    {
        if (! $request->hasValidSignature()) {
            return false;
        }

        return (int) $request->query('v', 0) === ($reservation?->guestFormLinkVersion() ?? -1);
    }

    /** ¿Quien pregunta es el titular del pedido de esta reserva? */
    protected function ownsGuestForm(Request $request, ?OrderItem $reservation): bool
    {
        $user = $request->user();

        return $user !== null
            && (int) ($reservation?->order?->user_id ?? 0) === (int) $user->getAuthIdentifier();
    }

    /**
     * Por dónde entró quien guarda, para el rastro de auditoría. No es PII (`RGPD-02`): dice el
     * CANAL, no quién.
     */
    protected function guestFormVia(Request $request): string
    {
        return $request->hasValidSignature() ? 'signed_link' : 'account';
    }

    /**
     * Lo que el cliente ENVÍA de verdad para una clave del post-form (`guests` / `general`), con la
     * única semántica que este formulario admite: **ausente = «no lo toques» (`null`)** y **presente
     * = «esto es el estado completo»**.
     *
     * ⚠️⚠️ **Esto es un arreglo de PÉRDIDA DE DATOS, no una comodidad** (T0 de
     * `specs/complementos-post-reserva.md`, `DECISIONES #413`). Las dos superficies de cliente
     * convertían la ausencia en `[]` (`$validated['guests'] ?? []` en la API, `input('guests', [])`
     * en la web), y `[]` significa **vaciar**: medido sobre una reserva real de 8 invitados, un `PUT`
     * que solo mandaba `general` **borró los nombres y las edades de los ocho niños** y respondió
     * 200. El contrato incluso documentaba esa forma como una virtud —«un formulario de invitados se
     * guarda a trozos»—, y guardar a trozos borraba el otro trozo.
     *
     * ⚠️ **Un valor que no es una lista se trata como AUSENTE, no como vacío**: ante un cuerpo
     * malformado, conservar es la única respuesta segura — destruir datos de menores por un tipo
     * equivocado no puede ser la conducta por defecto.
     *
     * @param  array<string, mixed>|null  $validated  el `validate()` de la API (solo trae las claves
     *                                                presentes); `null` en la web, donde se mira la
     *                                                petición directamente
     * @return array<mixed>|null
     */
    protected function submittedGuestFormArray(Request $request, string $key, ?array $validated = null): ?array
    {
        $source = $validated ?? $request->all();

        if (! array_key_exists($key, $source)) {
            return null;
        }

        $value = $source[$key];

        return is_array($value) ? $value : null;
    }
}
