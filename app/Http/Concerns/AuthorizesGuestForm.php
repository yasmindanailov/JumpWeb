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
        abort_unless($request->hasValidSignature() || $this->ownsGuestForm($request, $reservation), 403);

        abort_if($reservation?->order?->user?->isAnonymized() ?? false, 410);

        abort_unless($reservation?->acceptsGuestForm() ?? false, 404);
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
}
