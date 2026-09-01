<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Listeners\SignPendingWaiverOnVerification;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * **LA PUERTA CIERRA LA FIRMA** (`#336`, `[DECIDIDO owner, 2026-09-01]`).
 *
 * El alta no firma: guarda la aceptación EN ESPERA y la convierte en firma al verificar el correo
 * (`#179`, porque una firma sobre un buzón sin demostrar no prueba nada). El problema es operativo:
 * quien se registra y no abre el correo llega al parque sin exención, y pasarle la tablet a cada uno
 * es la cola que el owner quiere evitar («imagínate 100 personas, 5 filas»).
 *
 * Aquí hay un **tercer suceso** que convierte esa aceptación en firma, junto al enlace del correo y
 * al pago (`RedsysReturnHandler::autoVerifyBuyer()`, «un pago real demuestra que el titular es una
 * persona»): **el operador, con la persona delante.** Y eso es MÁS prueba que un enlace de correo, no
 * menos — el enlace demuestra el buzón; el operador ve a quien tiene enfrente.
 *
 * ⚠️⚠️ **LO QUE EL OPERADOR ACREDITA ES LA FIRMA, NUNCA EL CORREO.** Son dos hechos distintos y
 * confundirlos abre un agujero: marcar el buzón como verificado afirmaría sin prueba que es suyo, y
 * el correo verificado es lo que sostiene la RECUPERACIÓN DE CONTRASEÑA — quien se registrase con el
 * correo de otro y pasara por la puerta se llevaría de regalo el control de esa dirección. Aquí
 * `email_verified_at` **no se toca**: el cliente sigue viendo su aviso de verificar, que es verdad.
 *
 * ⚠️ **Solo con aceptación RETENIDA**, y esa es la condición que lo hace legítimo: el operador
 * confirma una aceptación que EXISTE —la persona leyó el texto y marcó la casilla—, no la inventa.
 * Sin aceptación previa sigue el flujo de siempre: pásale la tablet.
 *
 * ⚠️ **La firma conserva la IP y el navegador de la ACEPTACIÓN**, no los del mostrador, por lo mismo
 * que {@see SignPendingWaiverOnVerification}: ese rastro es el del
 * momento en que la persona leyó el texto. Lo que aporta el operador va en `declared_by_user_id`, que
 * queda en la fila para siempre. La prueba resultante dice las dos cosas: aceptada online el día X,
 * identidad confirmada en persona por Y el día Z.
 */
final class WaiverCounterDeclaration
{
    public function __construct(private readonly WaiverSigner $signer) {}

    /**
     * Convierte la aceptación retenida del titular en firma, declarada por el operador.
     *
     * @return WaiverSignature|null `null` si no había nada retenido que declarar — no es un error:
     *                              es el caso normal de quien ya firmó o nunca aceptó
     *
     * @throws WaiverDocumentStaleException si entre la aceptación y
     *                                      hoy se publicó un texto
     *                                      nuevo: **no se firma el
     *                                      viejo**, ahí toca la tablet
     */
    public function declare(User $holder, User $operator): ?WaiverSignature
    {
        // La pendiente se lee y se LIMPIA bajo lock, antes de firmar, por lo mismo que el listener de
        // la verificación: dos operadores pulsando a la vez —o un doble clic— no pueden firmar dos
        // veces ni dejar la aceptación colgada para siempre.
        $pending = DB::transaction(function () use ($holder): ?array {
            $locked = User::query()->whereKey($holder->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->waiver_pending_document_id === null) {
                return null;
            }

            $snapshot = [
                'document_id' => (int) $locked->waiver_pending_document_id,
                'channel' => in_array($locked->waiver_pending_channel, WaiverSignature::CHANNELS, true)
                    ? (string) $locked->waiver_pending_channel
                    : WaiverSignature::CHANNEL_WEB,
                'ip' => $locked->waiver_pending_ip,
                'user_agent' => $locked->waiver_pending_user_agent,
            ];

            $locked->forceFill([
                'waiver_pending_document_id' => null,
                'waiver_pending_channel' => null,
                'waiver_pending_ip' => null,
                'waiver_pending_user_agent' => null,
            ])->save();

            return $snapshot;
        });

        if ($pending === null) {
            return null;
        }

        // ⚠️ La vigencia se comprueba FUERA y el firmador la vuelve a comprobar bajo su propio lock
        // (S-3, `#181`): una publicación cruzada entre las dos lecturas firmaría una versión superada.
        $document = WaiverAcceptance::currentDocument($pending['document_id']);
        if ($document === null) {
            throw new WaiverDocumentStaleException;
        }

        $signature = $this->signer->sign($holder, $document, new WaiverSignatureRequest(
            channel: $pending['channel'],
            ip: $pending['ip'],
            userAgent: $pending['user_agent'],
            declaredBy: $operator,
        ));

        // El rastro va además al registro de auditoría: la firma ya guarda quién la declaró, pero el
        // parque revisa la operativa por aquí, no fila a fila de `waiver_signatures`.
        AuditLogger::log('puerta.waiver_declared', $holder, [
            'signature_id' => (int) $signature->getKey(),
            'legal_document_version_id' => (int) $document->getKey(),
            'accepted_channel' => $pending['channel'],
        ]);

        return $signature;
    }
}
