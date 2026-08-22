<?php

namespace Tests\Feature\Account;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pulido #179 — la lista «Mis pedidos» del cliente se pagina (8/pág) para que no
 * crezca sin límite.
 */
class AccountOrdersPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function makeOrders(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Order::create([
                'user_id' => $user->id, 'code' => 'JJ-PG'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
                'currency' => 'EUR', 'paid_at' => now(),
            ]);
        }
    }

    public function test_orders_list_is_paginated(): void
    {
        $user = $this->verifiedUser();
        $this->makeOrders($user, 10); // 3/pág → 4 páginas

        // Página 1: control de paginación + "Siguientes".
        $this->actingAs($user)
            ->get('/mi-cuenta/pedidos')
            ->assertOk()
            ->assertSee(__('account.orders.pagination.page', ['current' => 1, 'last' => 4]))
            // ⚠️ `assertSeeText`/`assertDontSeeText` (`TESTING.md` §2.ter): desde el 2026-08-22 el
            // `data-boot` del cajón lleva estos textos **para el cliente con sesión** —los pinta el
            // área de cliente—, y esta página se sirve siempre con sesión. Un `assertSee` pasaría
            // aquí pintara la página lo que pintara.
            ->assertSeeText(__('account.orders.pagination.next'));

        // Página 2: "Anteriores" + "Siguientes".
        $this->actingAs($user)
            ->get('/mi-cuenta/pedidos?page=2')
            ->assertOk()
            ->assertSee(__('account.orders.pagination.page', ['current' => 2, 'last' => 4]))
            ->assertSeeText(__('account.orders.pagination.prev'));
    }

    public function test_no_pagination_control_with_a_single_page(): void
    {
        $user = $this->verifiedUser();
        $this->makeOrders($user, 3); // cabe en una página

        $this->actingAs($user)
            ->get('/mi-cuenta/pedidos')
            ->assertOk()
            ->assertDontSeeText(__('account.orders.pagination.next'));
    }

    public function test_empty_message_when_user_has_no_orders(): void
    {
        $this->actingAs($this->verifiedUser())
            ->get('/mi-cuenta/pedidos')
            ->assertOk()
            // ⚠️ `assertSeeText` (`TESTING.md` §2.ter): `account.orders.empty` viaja desde el
            // 2026-08-22 en el `data-boot` del cajón, que va en TODAS las páginas.
            ->assertSeeText(__('account.orders.empty'));
    }

    public function test_out_of_range_page_does_not_show_empty_message(): void
    {
        $user = $this->verifiedUser();
        $this->makeOrders($user, 10); // 2 páginas; el usuario SÍ tiene pedidos

        // Página fuera de rango: el slice está vacío, pero NO debe salir el
        // mensaje "no tienes pedidos" (el estado vacío mira el total, no la página).
        $this->actingAs($user)
            ->get('/mi-cuenta/pedidos?page=99')
            ->assertOk()
            // ⚠️ Y su simétrico: un `assertDontSee` fallaría SIEMPRE por la misma razón.
            ->assertDontSeeText(__('account.orders.empty'));
    }
}
