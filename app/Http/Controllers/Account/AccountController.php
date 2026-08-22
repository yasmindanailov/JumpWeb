<?php

namespace App\Http\Controllers\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **Lo único que queda de «Mi cuenta» en la web: la DESCARGA de los datos** (RGPD art. 20).
 *
 * ⚠️ Las páginas `/mi-cuenta` y `/mi-cuenta/pedidos` se retiraron en la tanda 3 del área de cliente
 * (`DECISIONES #120(u)`): sus **rutas** sobreviven como puerta que abre el cajón en su zona
 * (`Http\Sidebar\AccountDoor`), y sus componentes Livewire —perfil, contraseña, sesiones y
 * borrado— se fueron con ellas, porque su lógica vive desde la tanda 2 en `Identity\Services\*` y
 * la consume el cajón por `/api/v1`.
 *
 * ⚠️ **Esto NO es una vista, es una descarga**, y por eso sobrevive: un fichero adjunto es una
 * decisión de navegador, y el cajón usa `GET /api/v1/me/export`, que sirve el MISMO documento
 * —`MePrivacyTest` lo compara campo a campo—.
 */
class AccountController extends Controller
{
    /**
     * RGPD (4.5b) — Descargar mis datos: copia legible por máquina (JSON) de los datos
     * personales del usuario (derecho de portabilidad, art. 20).
     *
     * ⚠️ **La composición del documento ya no vive aquí**: bajó a
     * `Identity\Services\AccountPrivacy` en la tanda 2 · paso 8, para que `GET /api/v1/me/export` no
     * la reescribiera. Lo que queda es lo de ESTA superficie: servirlo como **descarga**, que es una
     * decisión de navegador y no del documento (la API devuelve el cuerpo y su cliente decide).
     *
     * ⚠️ El `no-store` lo pone el alias de la ruta (`RGPD-04`): es un controlador plano, así que no
     * recibe el que Livewire estampa en sus componentes.
     */
    public function export(Request $request, AccountPrivacy $privacy): Response
    {
        /** @var User $user */
        $user = $request->user();

        $json = json_encode(
            $privacy->exportFor($user),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $filename = 'mis-datos-'.now()->format('Y-m-d').'.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
