<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Services\Analytics\EmailOpens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * **El píxel de apertura de los correos** (`specs/correos-salientes.md` §4.12, `#797`, la C3): `GET /e/{send}.gif`.
 *
 * ⚠️⚠️ Responde SIEMPRE el mismo GIF transparente de 1×1, con `no-store` —exista el envío o no, cuente o no, falle o no
 * apuntarlo—: el gestor de correo no puede enterarse de nada ni pintar una imagen rota. La ruta va fuera de la sesión, de las
 * cookies y del visitante (`routes/web.php`): un píxel no deja nada en quien abre el correo.
 */
final class EmailOpenController
{
    /** GIF89a de 1×1 transparente (42 bytes, medido en la sonda de la C3). */
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __invoke(Request $request, string $send, EmailOpens $opens): Response
    {
        try {
            $opens->record($send, $request->userAgent());
        } catch (Throwable $e) {
            Log::warning('email_opens.record_failed', ['error' => $e::class]);
        }

        return response((string) base64_decode(self::GIF), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
