<?php

namespace App\Http\Concerns;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use Illuminate\Http\Request;

/**
 * Control de acceso al JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §4.6).
 *
 * **Lo que se copia de {@see AuthorizesGuestForm} no son tres líneas: es el ORDEN**, que aquí es una
 * propiedad de seguridad. Se autoriza ANTES de comprobar elegibilidad, de modo que un desconocido no
 * pueda deducir por el código de estado si un pedido existe, si está pagado o si su titular ejerció
 * el derecho de supresión.
 *
 * ⚠️ **La primera redacción de la spec decía «410 antes de comprobar cualquier otra cosa» e invertía
 * justo la propiedad que citaba dos líneas después.** Con el orden al revés, un 410 y un 404 le
 * cuentan a cualquiera cosas distintas sobre pedidos ajenos.
 *
 * La escalada, y por qué cada peldaño:
 *  1. **403** — ni firma válida ni titular autenticado. El acceso sin sesión **es el caso normal**
 *     aquí, no la excepción: quien firma es un adulto sin cuenta y la firma HMAC es lo único que
 *     prueba que el enlace se lo dio quien podía dárselo.
 *  2. **410** — el titular se anonimizó (RGPD art. 17). La supresión CIERRA el canal: el enlace no
 *     puede seguir aceptando nombres y fechas de nacimiento de menores para un pedido borrado.
 *     `Gone` y no `Forbidden` porque el recurso existió y dejó de existir a propósito.
 *  3. **404** — la instalación no gestiona el waiver aquí (modo ≠ `interno`): no hay texto que
 *     firmar, así que esta pantalla no existe. Solo lo ve quien ya demostró acceso.
 *
 * ⚠️⚠️ **Lo que NO se comprueba aquí**: que el pedido esté pagado, que la visita no haya pasado y que
 * quede cupo. Esas tres son del DOMINIO y se re-comprueban **bajo el lock** al escribir
 * (`GuardianAuthorizationSigner`), porque entre pintar el formulario y enviarlo pueden cambiar — y un
 * `POST` forjado desde una pestaña vieja es el caso real (`SEC-04`). Aquí solo se usan para decidir
 * qué se PINTA.
 */
trait AuthorizesGuardianAuthorization
{
    /**
     * @param  OrderItem|null  $reservation  `null` cuando el identificador no resuelve a ninguna
     *                                       reserva; se trata como el peldaño 3 y NO como un 404
     *                                       temprano, para no responder distinto a un desconocido
     *                                       según exista o no el id.
     */
    protected function authorizeGuardianAccess(Request $request, ?OrderItem $reservation): void
    {
        // ⚠️ Las claves de atribución no cuentan para la firma (`EmailUtm`, analítica §4.1): el correo las pega
        // DESPUÉS de firmar. Con `hasValidSignature()` a secas, el enlace del correo daría 403 al padre.
        abort_unless($request->hasValidSignatureWhileIgnoring(EmailUtm::IGNORED_QUERY) || $this->ownsOrder($request, $reservation), 403);

        abort_if($reservation?->order?->user?->isAnonymized() ?? false, 410);

        abort_unless($reservation !== null && WaiverSettings::isInternal(), 404);
    }

    /** ¿Quien pregunta es el titular del pedido? (el RESPONSABLE, que también puede abrir el enlace) */
    protected function ownsOrder(Request $request, ?OrderItem $reservation): bool
    {
        $user = $request->user();

        return $user !== null
            && (int) ($reservation?->order?->user_id ?? 0) === (int) $user->getAuthIdentifier();
    }

    /**
     * Por dónde entró quien firma, para el rastro de auditoría. No es PII (`RGPD-02`): dice el CANAL,
     * no quién.
     */
    protected function guardianVia(Request $request): string
    {
        return $request->hasValidSignatureWhileIgnoring(EmailUtm::IGNORED_QUERY) ? 'signed_link' : 'account';
    }
}
