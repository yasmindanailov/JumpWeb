<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\WaiverProof;
use App\Domain\Platform\Services\AuditLogger;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 6 · waiver — el PDF del REGISTRO probatorio de una firma
 * (`docs/specs/waiver-probatorio.md` §4.5, §4.6).
 *
 * GET /admin/usuarios/{user}/waiver/{signature}/pdf
 *
 * Defensa en profundidad (patrón de `ReservationSlipController`):
 *  1. Middleware `web+auth+staff_or_admin` (acceso al panel) + `throttle` + `no-store` (`RGPD-04`:
 *     el documento lleva nombre, email, ip y user-agent del firmante).
 *  2. Permiso PROPIO `waiver.view` (§4.6): el registro está fuera de toda superficie normal.
 *  3. IDOR: la firma DEBE pertenecer al usuario de la URL.
 *  4. Cada consulta se AUDITA (`waiver.proof_downloaded`), sin PII en el payload.
 *
 * Se compone del SNAPSHOT y se sirve en el IDIOMA en que se firmó: el documento es lo que la
 * persona vio, no la plantilla del CMS ni el idioma del panel. Funciona igual sobre una cuenta ya
 * anonimizada — es exactamente para eso para lo que la prueba se conserva.
 */
class WaiverProofController extends Controller
{
    public function __invoke(Request $request, User $user, WaiverSignature $signature): Response
    {
        abort_unless($request->user()->hasPermission('waiver.view'), 403);

        // IDOR: la firma debe ser de la persona de la URL.
        abort_unless((int) $signature->user_id === (int) $user->getKey(), 404);

        $proof = WaiverProof::make($signature);

        // El documento va en el idioma del texto firmado, sea cual sea el del panel.
        App::setLocale($proof->locale());

        AuditLogger::log('waiver.proof_downloaded', $signature, [
            'signature_id' => $signature->getKey(),
            'user_id' => $user->getKey(),
            'version' => $proof->version->version,
            'locale' => $proof->locale(),
            'integrity_ok' => $proof->integrityOk(),
        ]);

        $pdf = Pdf::loadView('pdf.waiver-proof', ['proof' => $proof])->setPaper('a4');

        return $pdf->stream("waiver-{$signature->getKey()}.pdf");
    }
}
