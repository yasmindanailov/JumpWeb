<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
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
     * personales del usuario (derecho de portabilidad, art. 20). Incluye:
     *  - perfil, consents, roles
     *  - PEDIDOS (auditoría 2026-05-26, hallazgo I): código, estado, total y líneas (entrada/pack
     *    con su franja + datos de evento, más sus complementos anidados). Los precios van en
     *    céntimos para precisión y `currency` separado, igual que en BD.
     */
    public function export(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing([
            'consents', 'roles',
            'orders.items.ticketType', 'orders.items.slot', 'orders.items.children.ticketType',
            'orders.tickets',
        ]);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'pending_email' => $user->pending_email,
                'pending_email_sent_at' => $user->pending_email_sent_at?->toIso8601String(),
                'phone' => $user->phone,
                'locale' => $user->locale,
                'marketing_opt_in' => (bool) $user->marketing_opt_in,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'consents' => $user->consents->map(fn ($consent) => [
                'type' => $consent->type,
                'accepted_at' => $consent->accepted_at?->toIso8601String(),
                'ip' => $consent->ip,
                'version' => $consent->version,
            ])->all(),
            'roles' => $user->roles->pluck('name')->all(),
            'orders' => $user->orders->map(fn ($order) => [
                'code' => $order->code,
                'status' => $order->status,
                'subtotal_cents' => (int) $order->subtotal,
                'tax_cents' => (int) $order->tax,
                'total_cents' => (int) $order->total,
                'currency' => $order->currency,
                'created_at' => $order->created_at?->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'expires_at' => $order->expires_at?->toIso8601String(),
                'items' => $order->items->whereNull('parent_item_id')->values()->map(fn ($item) => [
                    'product' => $item->ticketType?->tr('name'),
                    'date' => $item->slot?->date?->toDateString(),
                    'time' => $item->slot?->start_time,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => (int) $item->unit_price,
                    'seats' => $item->seats,
                    'event_data' => $item->event_data ?: null,
                    'addons' => $item->children->map(fn ($addon) => [
                        'product' => $addon->ticketType?->tr('name'),
                        'quantity' => $addon->quantity,
                        'unit_price_cents' => (int) $addon->unit_price,
                    ])->values()->all(),
                ])->all(),
                'tickets' => $order->tickets->map(fn ($ticket) => [
                    'code' => $ticket->code ?? null,
                ])->all(),
            ])->all(),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $filename = 'mis-datos-'.now()->format('Y-m-d').'.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
