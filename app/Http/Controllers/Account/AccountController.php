<?php

namespace App\Http\Controllers\Account;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 4.5 — Mi cuenta. Zona privada (middleware `auth` + `verified`).
 * La página aloja los componentes Livewire de perfil, contraseña, sesiones (4.5a)
 * y privacidad/RGPD (4.5b). El borrado de cuenta vive en su componente Livewire.
 */
class AccountController extends Controller
{
    /** Página "Mi cuenta". */
    public function index(): View
    {
        return view('account.index');
    }

    /** Fase 5 — "Mis pedidos": reservas del usuario con su detalle (código, estado, líneas, total). */
    public function orders(Request $request): View
    {
        // #146: eager-load `payments.refunds` para que `Order::hasAnyRefund()` no
        // dispare N+1 al pintar el badge "Reembolsado" en la lista.
        // #179: paginado (3/pág) — pocas reservas por página, el resto en otras
        // páginas (decisión clienta).
        $orders = $request->user()->orders()
            // `adjustments` para `itemCollectedCents()` (oculta complementos cancelados nunca
            // cobrados) sin N+1 al pintar las líneas de cada pedido.
            ->with(['items.ticketType', 'items.slot', 'items.children.ticketType', 'payments.refunds', 'adjustments'])
            ->latest()
            ->paginate(3)
            ->withQueryString();

        return view('account.orders', ['orders' => $orders]);
    }

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
