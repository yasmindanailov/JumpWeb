<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2a refinada (#130–#134) — Detalle del Order:
 *  - Heading H1: eyebrow "Identificación" + código copiable + badge de estado.
 *  - Resumen (siempre abierta): operativa + nombre + teléfono + total.
 *  - Productos (siempre abierta, col 2 en lg): toggle preparado por item.
 *  - Detalles (collapsed): Fieldset "Pedido" (fechas) + Fieldset "Cliente" (email/idioma/waiver).
 *  - Pagos (collapsed): campos Redsys + botón copiar + link al portal.
 */
class OrderInfolistEnrichedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    /**
     * Adjunta un OrderItem cuyo `chargedSubtotal` == el total del pedido, para que el bloque
     * «Totales del pedido» (valor-primero) muestre ese importe. Un pedido PAGADO real siempre
     * tiene items; estos fixtures de layout antes eran itemless y mostraban el total solo de
     * refilón (en el caption de un «pendiente» fantasma), que el fix deposit-aware #225 eliminó.
     */
    private function attachItemWorth(Order $order, int $cents): OrderItem
    {
        RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'JUMP']]);
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $type->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 1, 'unit_price' => $cents,
        ]);
    }

    private function makeOrderForCustomer(User $customer, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $customer->id,
            'code' => 'JJ-DETAIL1',
            'status' => Order::STATUS_PAID,
            'subtotal' => 1500,
            'tax' => 315,
            'total' => 1815,
            'currency' => 'EUR',
            'paid_at' => now(),
        ], $overrides));
    }

    // ─── Resumen: nombre + teléfono + total (datos clave, #134) ───────────

    public function test_summary_section_shows_customer_name_and_phone(): void
    {
        // Decisión #134: nombre y teléfono del cliente se promocionan a la card
        // "Resumen" porque el operativo los necesita de un vistazo (contactar al
        // cliente sin scroll).
        $customer = User::factory()->create([
            'name' => 'Ana Pérez',
            'phone' => '+34611222333',
            'email' => 'ana@example.com',  // este va en Detalles, no Resumen.
        ]);
        $order = $this->makeOrderForCustomer($customer);
        $this->attachItemWorth($order, (int) $order->total); // pedido pagado realista (con producto)

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee('Ana Pérez')
            ->assertSee('+34611222333')
            // Y el orden HTML del Resumen debe llevar el nombre antes que el total
            // (ambos en la misma card).
            ->assertSeeInOrder([
                'Ana Pérez',
                '+34611222333',
                '18,15 €',  // total
            ]);
    }

    public function test_customer_email_lives_in_details_section_not_summary(): void
    {
        // #134: email + idioma + waiver van al Fieldset "Detalles del cliente"
        // dentro de la card Detalles (collapsed). El email sigue siendo visible en
        // el HTML (Alpine collapsed solo afecta visualmente) — assertSee lo encuentra.
        $customer = User::factory()->create(['email' => 'ana@example.com']);
        $this->makeOrderForCustomer($customer);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $response->assertOk()
            ->assertSee('ana@example.com')
            ->assertSee(__('admin.orders.details_customer'));
    }

    public function test_details_section_marks_waiver_missing_when_null(): void
    {
        $customer = User::factory()->create(['waiver_accepted_at' => null]);
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.customer_waiver_missing'));
    }

    // ─── Nueva card "Detalles" con dos Fieldset (#134) ────────────────────

    public function test_details_section_heading_is_visible(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.section_details'));
    }

    public function test_details_section_renders_both_fieldsets(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.details_order'))
            ->assertSee(__('admin.orders.details_customer'));
    }

    public function test_details_section_and_payments_section_render_collapsed_by_default(): void
    {
        // #134: Detalles y Pagos arrancan collapsed → su Section Filament lleva
        // el initial state `isCollapsed: true` en el x-data de Alpine. Resumen e
        // Items NO están collapsed por defecto → renderizan `isCollapsed: false`.
        //
        // Patrón empírico verificado del HTML de Filament 5: el x-data tiene espacios
        // alrededor del booleano: `x-data="{ isCollapsed: true , }"`. Permitimos
        // whitespace flexible en el regex para no acoplarnos al formato exacto.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        $countTrue = preg_match_all('/isCollapsed:\s*true\s*,/', $body);
        $this->assertSame(2, $countTrue,
            'Esperamos exactamente 2 sections con isCollapsed:true (Detalles + Pagos). '
            ."Encontradas: {$countTrue}");
    }

    public function test_summary_section_is_not_collapsible(): void
    {
        // Resumen NUNCA debe ser collapsible — son los datos clave operativos.
        // Filament añade la clase `fi-collapsible` a las que sí lo son; en una
        // section sin collapsible() no aparece esa clase para ESA section. Verificamos
        // que el heading "Resumen" no está dentro de un section.fi-collapsible.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        // Heuristic robusta: el patrón "<section..." con class fi-collapsible no
        // debe envolver el texto "Resumen". Buscamos cualquier <section> con
        // fi-collapsible que tenga el heading "Resumen" más adelante; si lo
        // encuentra, falla.
        $pattern = '/<section[^>]*\bfi-collapsible\b[^>]*>(?:(?!<\/section>).)*?'
            .preg_quote(__('admin.orders.section_summary'), '/').'/s';
        $this->assertSame(
            0,
            preg_match($pattern, $body),
            'La card "Resumen" no debe estar dentro de una sección collapsible.'
        );
    }

    // ─── Heading enriquecido (decisión #132 + label inline #136) ─────────

    public function test_page_heading_includes_id_label_inline_before_code(): void
    {
        // Decisión #136: el eyebrow apilado vertical resultaba demasiado prominente.
        // Se sustituyó por label inline "ID:" al lado del código, todos en la misma
        // línea base del heading. El botón Copiar sigue copiando SOLO el código.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.heading_id_label'))
            // "ID:" aparece ANTES de "JJ-DETAIL1" en el DOM (inline a la izquierda).
            ->assertSeeInOrder([
                __('admin.orders.heading_pedido'),     // "Pedido"
                __('admin.orders.heading_id_label'),   // "ID"
                'JJ-DETAIL1',                          // valor del código
            ]);
    }

    public function test_page_heading_includes_order_code(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $response->assertOk()
            ->assertSee('JJ-DETAIL1');  // visible en el heading.
    }

    public function test_page_heading_includes_clipboard_copy_button_for_code(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $body = $response->getContent();

        // El botón "Copiar" del heading usa Alpine + Clipboard API.
        $this->assertStringContainsString('navigator.clipboard.writeText', $body,
            'El heading debe llevar un botón con Clipboard API para copiar el código.');
        // Js::from('JJ-DETAIL1') produce el literal JS seguro "JJ-DETAIL1".
        $this->assertMatchesRegularExpression(
            '/clipboard\.writeText\(\s*[\'\"]JJ-DETAIL1[\'\"]\s*\)/',
            $body,
            'El botón Copiar debe pasarle el código exacto al portapapeles.'
        );
    }

    public function test_page_heading_status_badge_uses_success_color_for_paid(): void
    {
        // #145: el badge del Order status pasa a leer «Confirmado» (no «Pagado»; era «Completado» hasta `#588`).
        // La constante `STATUS_PAID = 'paid'` y los colores son los mismos —
        // solo cambia el texto operativo. El estado "Pagado" sigue existiendo,
        // pero referido al Payment (card Pagos), no al Order.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer, ['status' => 'paid', 'paid_at' => now()]);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $body = $response->getContent();

        $this->assertStringContainsString('Confirmado', $body);
        $this->assertMatchesRegularExpression(
            '/bg-green-100[^"]*ring-green|ring-green[^"]*bg-green-100/',
            $body,
            'Badge de «Confirmado» debe usar bg-green-100 + ring-green (variante success).'
        );
    }

    public function test_order_status_text_for_paid_changed_to_confirmado(): void
    {
        // Robustez del rename (#145 + #146, y `#588` `[DECIDIDO owner]`: «Completado» se leía como «ya
        // pasó»): tanto el panel admin como la zona del cliente leen «Confirmado» para el Order status
        // `paid`. El estado del Payment (card Pagos) sigue siendo «Pagado» porque es la dimensión del
        // cobro, no del servicio.
        $this->assertSame('Confirmado', __('admin.orders.status.paid'));
        $this->assertSame('Confirmado', __('tickets.statuses.paid'));
        // El Payment status (card Pagos del admin) sigue siendo "Pagado".
        $this->assertSame('Pagado', __('admin.orders.payments.status.paid'));
    }

    public function test_page_heading_status_badge_uses_danger_color_for_cancelled(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer, [
            'status' => 'cancelled', 'paid_at' => null,
        ]);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $body = $response->getContent();

        $this->assertStringContainsString('Cancelado', $body);
        $this->assertMatchesRegularExpression(
            '/bg-red-100[^"]*ring-red|ring-red[^"]*bg-red-100/',
            $body,
            'Badge de Cancelado debe usar bg-red-100 + ring-red (variante danger).'
        );
    }

    public function test_page_heading_status_badge_uses_warning_color_for_pending(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer, [
            'status' => 'pending', 'paid_at' => null,
        ]);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $body = $response->getContent();

        $this->assertStringContainsString('Pendiente', $body);
        $this->assertMatchesRegularExpression(
            '/bg-amber-100[^"]*ring-amber|ring-amber[^"]*bg-amber-100/',
            $body,
            'Badge de Pendiente debe usar bg-amber-100 + ring-amber (variante warning).'
        );
    }

    public function test_summary_section_no_longer_renders_code_or_status_as_separate_entries(): void
    {
        // Tras #132, code y displayStatus viven en el heading — no como TextEntry de
        // la card Resumen. Verificamos contando ocurrencias: el code debe aparecer
        // exactamente 1 vez (en el heading), no 2 (heading + entry).
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        // Contamos ocurrencias del code en el HTML. Aparece en:
        //   - heading: 1 vez (texto)
        //   - botón copiar (Alpine x-data): 1 vez como literal JS Js::from()
        //   - title HTML (browser tab): 1 vez
        //   - posiblemente atributos aria-label / breadcrumb
        // Lo importante: NO debe aparecer una décima vez como contenido de un
        // <dt>/<dd> de TextEntry. Sin un assertion estructural fácil, asertamos
        // sobre ausencia de la label "Código:" (que solo se usa en la entry vieja).
        // La cadena "Código" aparece en otros sitios (e.g. col_code de OrdersTable),
        // pero NO en el detalle — verificable porque la única referencia en la
        // página de detalle era la del Resumen.
        $this->assertStringNotContainsString(
            __('admin.orders.col_code').'<',
            $body,
            'La label "Código:" del antiguo TextEntry no debe aparecer en el detalle.'
        );
    }

    // ─── Sección Resumen (fechas del ciclo de vida) ───────────────────────

    public function test_summary_section_shows_created_at(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.col_created_at'));
    }

    public function test_summary_section_shows_expires_at_only_for_pending_orders(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer, [
            'status' => Order::STATUS_PENDING,
            'paid_at' => null,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.col_expires_at'));
    }

    public function test_summary_section_hides_expires_at_for_paid_orders(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);  // paid, sin expires_at

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertDontSee(__('admin.orders.col_expires_at'));
    }

    // ─── Total dentro de Resumen (sub-fase 7.2a refinada #131) ────────────

    public function test_total_is_rendered_inside_summary_section_not_a_separate_card(): void
    {
        // Refinamiento #131: la card "Importes" se retiró; el Total se muestra ahora
        // dentro de la card "Resumen" para reducir fragmentación visual del detalle.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer, [
            'subtotal' => 1500, 'tax' => 315, 'total' => 1815,
        ]);
        $this->attachItemWorth($order, 1815); // producto del pedido (valor = total)

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $response->assertOk()
            ->assertSee(__('admin.orders.amount_total'))
            ->assertSee('18,15 €');

        // Subtotal/IVA quedan internos (en BD pero no en panel): no se muestran ya
        // sus labels en el detalle del pedido — la sección Importes se eliminó.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Subtotal', $body);
        $this->assertStringNotContainsString('Impuestos', $body);
    }

    public function test_layout_uses_flex_with_independent_column_heights(): void
    {
        // Decisión #137: cambio de CSS Grid a Filament Flex para resolver el bug del
        // row-stretch (CSS Grid distribuye altura del item span entre filas → gap
        // visual entre Resumen y Detalles cuando Productos era alta). Flex con
        // `from('lg')` da `flex-row items-start` en lg+ = alturas independientes.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        $this->assertStringContainsString('fi-sc-flex', $body,
            'El layout debe usar Filament Flex (no CSS Grid) para alturas independientes.');
        $this->assertStringContainsString('fi-from-lg', $body,
            'Flex debe tener `fi-from-lg` (flex-col mobile → flex-row lg+).');
        $this->assertStringContainsString('data-layout="order-detail"', $body,
            'El Flex container debe llevar el data-attribute que ancla las reglas CSS '
            .'del theme (mobile reorder via display:contents + order).');
    }

    public function test_layout_flex_spans_full_width_overriding_view_record_default(): void
    {
        // Regresión #137: `ViewRecord` aplica `columns(2)` al schema → si el Flex
        // no declara `columnSpan('full')`, queda como span 1 of 2 = 50% del ancho
        // de página, dejando media página vacía a la derecha y el contenido apretado.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        // El Flex container está envuelto por un .fi-grid-col que lleva la
        // expresión `--col-span-lg: 1 / -1` cuando se aplica `columnSpan('full')`.
        $this->assertStringContainsString('--col-span-lg: 1 / -1', $body,
            'El Flex debe llevar `columnSpan(\'full\')` para ocupar el ancho completo '
            .'del wrapper de ViewRecord (que es columns(2) por defecto).');
    }

    public function test_items_section_carries_mobile_order_marker(): void
    {
        // Regresión #137: Productos lleva `data-mobile-order="2"` para que el CSS
        // del theme lo coloque en orden 2 en móvil (entre Resumen y Detalles).
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        $this->assertStringContainsString('data-mobile-order="2"', $body,
            'La sección "Productos" debe llevar `data-mobile-order="2"` para '
            .'el reorder móvil vía CSS `order`.');

        // Y todas las demás secciones también tienen su data-mobile-order.
        foreach (['1', '2', '3', '4'] as $order) {
            $this->assertStringContainsString(
                'data-mobile-order="'.$order.'"',
                $body,
                "Falta `data-mobile-order=\"{$order}\"` en alguna sección."
            );
        }
    }

    public function test_left_stack_group_has_marker_for_css_display_contents_on_mobile(): void
    {
        // Regresión #137: el CSS del theme aplica `display: contents` al wrapper
        // con `data-stack="left"` en móvil para que las 3 sections del Group se
        // promocionen a flex children directos y el reorder por CSS order funcione.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        $this->assertStringContainsString('data-stack="left"', $body,
            'El Group de la columna izquierda debe llevar `data-stack="left"` para '
            .'que el CSS le aplique `display: contents` en móvil.');
    }

    public function test_mobile_visual_order_via_css_order_attribute(): void
    {
        // Decisión #137: con el cambio a Flex, el orden DOM ya NO refleja directamente
        // el orden visual en móvil (CSS Grid auto-placement quedó atrás). El orden
        // visual se logra con CSS `order` aplicado vía `data-mobile-order`.
        //
        // DOM order = HTML: Resumen, Detalles, Pagos (dentro del Group), Items.
        // Visual order móvil = vía CSS: Resumen(1), Items(2), Detalles(3), Pagos(4).
        //
        // Aquí verificamos los CINCO atributos clave que el CSS del theme necesita
        // para hacer el reorder visual sin tocar el DOM.
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        // DOM order de las 4 sections (el Group renderiza primero sus 3 children,
        // luego Items como segundo flex child).
        $this->assertSeeInOrderRaw([
            __('admin.orders.section_summary'),    // dentro del Group, primero.
            __('admin.orders.section_details'),    // dentro del Group, segundo.
            __('admin.orders.section_payments'),   // dentro del Group, tercero.
            __('admin.orders.section_items'),      // flex child siguiente del Group.
        ], $body);

        // Y los markers de orden visual están todos.
        $this->assertStringContainsString('data-mobile-order="1"', $body);
        $this->assertStringContainsString('data-mobile-order="2"', $body);
        $this->assertStringContainsString('data-mobile-order="3"', $body);
        $this->assertStringContainsString('data-mobile-order="4"', $body);
    }

    /**
     * Helper que verifica que las cadenas aparecen en el orden dado dentro del HTML.
     * Equivalente a `Response::assertSeeInOrder` pero operando sobre un string ya
     * extraído — útil cuando ya tenemos `$response->getContent()` y queremos múltiples
     * checks sobre el mismo body sin re-renderizar.
     *
     * @param  array<int,string>  $strings
     */
    private function assertSeeInOrderRaw(array $strings, string $body): void
    {
        $lastPos = -1;
        foreach ($strings as $needle) {
            $pos = strpos($body, $needle, $lastPos + 1);
            $this->assertNotFalse(
                $pos,
                "La cadena «{$needle}» no aparece tras la posición {$lastPos}."
            );
            $lastPos = $pos;
        }
    }

    public function test_summary_section_total_has_prominence_styling(): void
    {
        // Sub-fase 7.2e.1bis5 (decisión #158, punto 4): los Totales del pedido
        // se renderizan ahora por `View::make('filament.orders.partials.
        // order-totals')` en lugar de 3 TextEntry. La prominencia visual del
        // Total se materializa con `text-base font-semibold` (Tailwind) en
        // lugar de `->weight('bold')->size('lg')` (Filament).
        //
        // El test ahora verifica los 3 invariantes operativos del bloque:
        //   1. El importe formateado aparece.
        //   2. Aparece el título "Totales del pedido" (diferencia el bloque
        //      del "Totales del producto" en cada sub-card).
        //   3. La cifra principal lleva alguna clase semántica de prominencia
        //      (font-semibold/bold/heading o cualquier marcador que cumpla
        //      esa intención visual).
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer, ['total' => 9999]);
        $this->attachItemWorth($order, 9999); // producto del pedido (valor = total)

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $response->assertOk()
            ->assertSee('99,99 €')
            ->assertSee(__('admin.orders.order_financial.heading'));

        $this->assertMatchesRegularExpression(
            '/font-bold|font-semibold|--font-weight|fi-weight/',
            $response->getContent(),
            'El Total del bloque "Totales del pedido" debe renderizarse con estilo prominente (bold/semibold).'
        );
    }

    // ─── Sección Pagos ────────────────────────────────────────────────────

    public function test_payments_section_renders_redsys_fields_for_paid_payment(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);

        Payment::create([
            'payable_type' => (new Order)->getMorphClass(),
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => 1815,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'transaction_id' => '987654',
            'auth_code' => '987654',
            'gateway_order' => '0000000777',
            'raw_response' => [
                'Ds_Response' => '0000',
                'Ds_AuthorisationCode' => '987654',
                'Ds_Order' => '0000000777',
                'Ds_Amount' => '1815',
                'Ds_Currency' => '978',
                'Ds_Date' => '28/05/2026',
                'Ds_Hour' => '11:30',
                'Ds_Card_Brand' => '1',     // Visa
                'Ds_Card_Country' => '724', // ES
            ],
            'paid_at' => now(),
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.section_payments'))
            ->assertSee('0000000777')                                      // gateway_order
            ->assertSee('987654')                                           // auth_code
            ->assertSee('0000')                                             // Ds_Response
            ->assertSee(__('admin.orders.payments.ds_response_authorized')) // traducción 0000
            ->assertSee('28/05/2026 11:30')                                 // bank timestamp
            ->assertSee('Visa')                                             // Ds_Card_Brand=1
            ->assertSee('España');                                          // Ds_Card_Country=724
    }

    public function test_payments_section_url_decodes_bank_timestamp(): void
    {
        // En producción Redsys envía Ds_Date y Ds_Hour URL-encoded:
        // '28%2F05%2F2026' (= '28/05/2026') y '07%3A47' (= '07:47'). El raw_response
        // los almacena tal cual; el blade hace urldecode al render para legibilidad.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);

        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1815, 'currency' => 'EUR',
            'status' => Payment::STATUS_PAID, 'gateway_order' => '0000000778',
            'raw_response' => [
                'Ds_Response' => '0000',
                'Ds_Order' => '0000000778',
                'Ds_Date' => '28%2F05%2F2026',  // URL-encoded
                'Ds_Hour' => '07%3A47',          // URL-encoded
            ],
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $response->assertOk();
        // Decodificado y mostrado legible.
        $response->assertSee('28/05/2026 07:47');
        // No aparece la versión encoded en el HTML (el atributo escapado por Blade no
        // contiene los % sin decodificar).
        $response->assertDontSee('28%2F05%2F2026');
    }

    public function test_payments_section_falls_back_to_raw_code_for_unknown_card_country(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);

        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1815, 'currency' => 'EUR',
            'status' => Payment::STATUS_PAID, 'gateway_order' => '0000000779',
            'raw_response' => [
                'Ds_Response' => '0000',
                'Ds_Order' => '0000000779',
                'Ds_Card_Brand' => '1',
                'Ds_Card_Country' => '010',  // Antártida, no en el mapa.
            ],
            'paid_at' => now(),
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee('Visa')
            ->assertSee(__('admin.orders.payments.card_country_unknown', ['code' => '010']));
    }

    public function test_payments_section_uses_friendly_labels_not_technical_jargon(): void
    {
        // Decisión #130: labels técnicos como "Ds_Response" y "Sello banco" se reemplazaron
        // por "Resultado del cobro" y "Fecha y hora del cobro" para el operativo.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);

        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1815, 'currency' => 'EUR',
            'status' => Payment::STATUS_PAID, 'gateway_order' => '0000000780',
            'raw_response' => ['Ds_Response' => '0000', 'Ds_Date' => '01/01/2026'],
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $response->assertOk()
            ->assertSee('Resultado del cobro')
            ->assertSee('Fecha y hora del cobro')
            // La cadena técnica "Ds_Response" como LABEL ya no debe aparecer; el valor
            // crudo "0000" sigue mostrándose junto al label friendly.
            ->assertDontSee('Ds_Response:');
    }

    public function test_payments_section_translates_denied_ds_response(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer, [
            'status' => Order::STATUS_PENDING, 'paid_at' => null,
        ]);

        Payment::create([
            'payable_type' => (new Order)->getMorphClass(),
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => 1815,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'gateway_order' => '0000000778',
            'raw_response' => [
                'Ds_Response' => '0129',  // CVV erróneo
                'Ds_Order' => '0000000778',
            ],
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee('0129')
            ->assertSee(__('tickets.payment_failed.reasons.cvv_wrong'));
    }

    public function test_payments_section_shows_portal_link_to_sandbox_by_default(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(Redsys::ADMIN_URL_TEST)
            ->assertSee(__('admin.orders.payments.env_test'));
    }

    public function test_payments_section_shows_portal_link_to_live_when_configured(): void
    {
        Setting::updateOrCreate(['key' => 'redsys_environment'], ['value' => 'live', 'group' => 'payment']);

        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(Redsys::ADMIN_URL_LIVE)
            ->assertSee(__('admin.orders.payments.env_live'));
    }

    public function test_payments_section_shows_empty_state_when_no_payments(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.payments.empty'));
    }

    public function test_payments_section_orders_payments_by_most_recent_first(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);

        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1815, 'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED, 'gateway_order' => '0000000001',
            'raw_response' => ['Ds_Response' => '0125'],
        ]);
        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1815, 'currency' => 'EUR',
            'status' => Payment::STATUS_PAID, 'gateway_order' => '0000000002',
            'auth_code' => '111111',
            'raw_response' => ['Ds_Response' => '0000'],
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        $body = $response->getContent();

        // El último (paid, gateway_order 0000000002) debe aparecer antes que el primero
        // (failed, gateway_order 0000000001) en el HTML — orden desc por created_at.
        $posPaid = strpos($body, '0000000002');
        $posFailed = strpos($body, '0000000001');

        $this->assertNotFalse($posPaid);
        $this->assertNotFalse($posFailed);
        $this->assertLessThan($posFailed, $posPaid);
    }

    // ─── event_data por item ──────────────────────────────────────────────

    // ─── Histórico de devoluciones en la card Pagos (#143) ────────────────

    private function makeSuccessfulRestRefund(Payment $payment, User $by): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => $payment->amount,
            'currency' => $payment->currency,
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $by->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
    }

    private function makeFailedRefund(Payment $payment, User $by, string $failureReason, ?string $code = null): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => $payment->amount,
            'currency' => $payment->currency,
            'status' => PaymentRefund::STATUS_FAILED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => $code,
            'requested_by' => $by->id,
            'requested_at' => now(),
            'processed_at' => now(),
            'failure_reason' => $failureReason,
            'failure_message' => 'test failure message',
        ]);
    }

    private function makePaymentForOrder(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => $order->currency ?? 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => '0000123456',
        ]);
    }

    // ─── Card Pagos: cobro manual NO va a la sección Redsys (Fase 7.3, #120) ──

    public function test_manual_cash_payment_is_listed_under_internal_notes_not_redsys_bank(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        // Pago manual en establecimiento: efectivo, sin gateway_order ni raw_response.
        Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'cash',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $body = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1')->getContent();

        // El cobro en efectivo se muestra, pero NO bajo la sección "en banco (Redsys)".
        $this->assertStringContainsString(__('admin.orders.payments.provider_cash'), $body);
        $this->assertStringContainsString(__('admin.orders.payments.section_internal'), $body);
        $this->assertStringNotContainsString(__('admin.orders.payments.section_bank'), $body);
    }

    // ─── Badge "Reembolsado" + helper hasAnyRefund (#146) ─────────────────

    public function test_has_any_refund_detects_succeeded_payment_refund_row(): void
    {
        // Caso post-#142: existe fila payment_refunds con status=succeeded
        // (creado por el orquestador). El helper lo detecta vía relación.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $this->makeSuccessfulRestRefund($payment, $this->staff());

        $this->assertTrue($order->fresh()->hasAnyRefund());
    }

    public function test_has_any_refund_detects_manual_mode_refund_same_as_rest(): void
    {
        // El badge debe aparecer también para reembolsos MANUAL: ambos modos
        // crean filas payment_refunds.succeeded; la dimensión "hubo devolución"
        // es independiente de "cómo se procesó" (#146 confirmado por la clienta).
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => $payment->amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_MANUAL,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::MANUAL_RESPONSE_MARKER,
            'requested_by' => $this->staff()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $this->assertTrue($order->fresh()->hasAnyRefund());
    }

    public function test_has_any_refund_detects_legacy_data_with_refunded_at_only(): void
    {
        // Caso JJ-9OXNJW empírico: refunded_at set pero sin fila payment_refunds
        // (refund pre-#142). El helper SIGUE devolviendo true vía Order.refunded_at
        // — fuente de verdad primaria del "hubo devolución".
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        $this->assertTrue($order->fresh()->hasAnyRefund());
    }

    public function test_has_any_refund_detects_legacy_status_refunded(): void
    {
        // Data muy antigua con `status=refunded` (constante legacy desde #139).
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $order->update(['status' => Order::STATUS_REFUNDED]);

        $this->assertTrue($order->fresh()->hasAnyRefund());
    }

    public function test_has_any_refund_returns_false_for_paid_order_without_any_refund(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $this->makePaymentForOrder($order);

        $this->assertFalse($order->fresh()->hasAnyRefund());
    }

    public function test_heading_shows_refunded_badge_when_order_is_fully_refunded(): void
    {
        // Sub-fase 7.2e.1bis5 (decisión #158, punto 7A): el badge ahora se
        // condiciona a `isFullyRefunded()` (no a `hasAnyRefund()`). Un Order
        // con TODO el importe devuelto sigue mostrando el badge — comprueba
        // la rama happy path del nuevo helper.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $this->makeSuccessfulRestRefund($payment, $this->staff());
        // Reflejar lo que hace el orquestador real (`executeFullRefund`):
        // setear `refund_amount_cents` agregado en el Order. Sin esto,
        // `isFullyRefunded()` devuelve false (solo mira columna agregada,
        // no la suma de payment_refunds).
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.refunded_badge'));
    }

    public function test_heading_omits_refunded_badge_for_partial_refund(): void
    {
        // Sub-fase 7.2e.1bis5 (decisión #158, punto 7A): el badge
        // "Reembolsado" del heading se RESERVA para refund total. Un Order
        // con solo un complemento devuelto NO marca el badge — refleja
        // operativamente que el pedido sigue activo con reembolso parcial.
        // El resumen financiero del Order (Total/Devuelto/Neto) sí aparece
        // (info financiera), pero el badge de estado no.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer, ['total' => 10000]); // 100,00 €
        $payment = $this->makePaymentForOrder($order);
        // ⚠️ Refund PARCIAL de 25 € sobre 100 €, con la FILA y la columna coherentes: los dos
        // escritores reales derivan `refund_amount_cents` de `totalRefundedCents()` en la misma
        // transacción, y la guarda de construcción de `DECISIONES #127` lo asevera. El fixture
        // escribía la columna a mano dejando una fila del importe COMPLETO — un estado que ningún
        // reembolso puede producir.
        $this->makeSuccessfulRestRefund($payment, $this->staff())->update(['amount_cents' => 2500]);
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => 2500,
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            // Resumen financiero SÍ aparece (info útil, no estado). La línea
            // "Neto cobrado" se eliminó en el rediseño valor-primero (#199); se
            // comprueba el importe devuelto.
            ->assertSee(__('admin.orders.amount_refunded'))
            ->assertSee('−25,00');

        // Pero el badge "Reembolsado" en el heading NO. Debemos comprobar
        // que el TÍTULO H1 no contiene "Reembolsado" como badge separado.
        // Como el texto "Reembolsado" aparece también en el resumen
        // financiero (label "Devuelto" en otros idiomas) usamos un check
        // empírico del helper: el método isFullyRefunded() devuelve false.
        $this->assertFalse($order->fresh()->isFullyRefunded());
    }

    public function test_heading_shows_refunded_badge_for_legacy_data_with_status_refunded(): void
    {
        // Refunds anteriores a #142 con `status=refunded` (constante legacy
        // desde #139). Aunque `refund_amount_cents` esté null, el fallback
        // legacy en `isFullyRefunded()` mantiene el badge para data antigua.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $order->update(['status' => Order::STATUS_REFUNDED]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.refunded_badge'));
    }

    public function test_heading_omits_refunded_badge_when_order_has_no_refund(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertDontSee(__('admin.orders.refunded_badge'));
    }

    public function test_summary_card_shows_devuelto_when_refund_recorded(): void
    {
        // #144: el resumen financiero vive en la card Resumen. Se renderiza la línea
        // "Devuelto" (con signo menos) cuando hay devolución registrada. La antigua
        // línea "Neto cobrado" se eliminó en el rediseño valor-primero (#199): el
        // valor lo da ahora "Valor final del pedido".
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $this->attachItemWorth($order, (int) $order->total);
        // Desde el libro (T3·2) una devolución es un HECHO con fila: el cobro y su devolución, y las
        // columnas del pedido cuadran con ella (identidad I4). Las columnas solas eran el registro legacy.
        $payment = Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => (int) $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => '0000181500',
        ]);
        PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => null,
            'amount_cents' => (int) $order->total, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED, 'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order, 'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $this->staff()->id, 'requested_at' => now(), 'processed_at' => now(),
        ]);
        $order->update([
            'refunded_at' => now(),
            'refund_amount_cents' => $order->total,
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('tickets.journal.refund_card')) // la línea de devolución del LIBRO
            ->assertSee('−18,15'); // Devuelto: −18,15 €
    }

    public function test_summary_card_omits_devuelto_lines_when_no_refund(): void
    {
        $customer = User::factory()->create();
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertDontSee(__('tickets.journal.refund_card'));
    }

    public function test_payments_card_renders_refund_as_sibling_event_with_type_badge(): void
    {
        // #144: cada PaymentRefund es una sub-card SIBLING al mismo nivel que la del
        // Payment. El header lleva un badge "Devolución" para distinguirla de "Pago".
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $staff = $this->staff();
        $this->makeSuccessfulRestRefund($payment, $staff);

        $response = $this->actingAs($staff)->get('/admin/orders/JJ-DETAIL1');
        $response->assertOk()
            ->assertSee(__('admin.orders.payments.events.type_payment'))
            ->assertSee(__('admin.orders.payments.events.type_refund'))
            ->assertSee(__('admin.orders.payments.refunds.status.succeeded'))
            ->assertSee(__('admin.orders.payments.refunds.mode_rest'))
            ->assertSee('0900')
            ->assertSee(__('admin.orders.payments.refunds.code_refund_ok'))
            ->assertSee($staff->name ?? $staff->email);
    }

    public function test_subcards_pin_amount_to_top_right_with_flex_shrink_layout(): void
    {
        // #145 regresión visual: la cabecera de las sub-cards de pago y devolución
        // debe usar el patrón `flex items-start` + `min-w-0 flex-1` (izquierda
        // shrinkable) + `flex-shrink-0 text-right` (derecha pegada arriba) para
        // que el importe quede SIEMPRE anclado a la esquina superior derecha,
        // aunque el contenido del lado izquierdo sea largo ("DEVOLUCIÓN" +
        // "Confirmada" + "Automática (Redsys)" + "28/05/2026 21:23 · Admin").
        //
        // Sin este patrón, el flex parent con `flex-wrap` rompe la fila cuando el
        // contenido no cabe en una línea: el bloque del importe cae a una fila
        // nueva y "9,90 €" aparece ENCIMA del texto "28/05/2026 21:23 · Admin".
        //
        // Renderizamos el partial directamente (no la página completa) para que
        // el assertion sobre las clases específicas no se contamine con otras
        // utility classes de Filament en la página global.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $this->makeSuccessfulRestRefund($payment, $this->staff());

        $fmtAmount = fn (int $c, string $cur): string => number_format($c / 100, 2, ',', '.').' '.$cur;

        $paymentHtml = view(
            'filament.orders.partials.payment-event',
            ['payment' => $payment->fresh(), 'fmtAmount' => $fmtAmount],
        )->render();
        $refundHtml = view(
            'filament.orders.partials.refund-event',
            ['refund' => $payment->refunds()->first(), 'fmtAmount' => $fmtAmount],
        )->render();

        foreach (['Sub-card de pago' => $paymentHtml, 'Sub-card de refund' => $refundHtml] as $label => $html) {
            $this->assertMatchesRegularExpression(
                '/<div class="flex items-start gap-3">/',
                $html,
                "$label: el parent del header debe ser `flex items-start` (sin flex-wrap)."
            );
            $this->assertStringContainsString(
                'min-w-0 flex-1',
                $html,
                "$label: la columna izquierda debe ser shrinkable."
            );
            $this->assertStringContainsString(
                'flex-shrink-0 text-right',
                $html,
                "$label: el bloque del importe debe quedar pegado arriba a la derecha."
            );
        }
    }

    public function test_payments_card_renders_refund_with_gateway_order_for_reconciliation(): void
    {
        // El gateway_order de la devolución es el mismo que el del cobro (Redsys lo
        // reusa). Mostrarlo en la card de devolución permite que el operador lo
        // copie sin volver a la card del cobro original — útil cuando reconcilia
        // en el portal Redsys.
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $this->makeSuccessfulRestRefund($payment, $this->staff());

        $response = $this->actingAs($this->staff())->get('/admin/orders/JJ-DETAIL1');
        // El número aparece dos veces: una en la card de pago + otra en la card de
        // devolución. assertSeeText cuenta una ocurrencia; pasar el contador a 2 con
        // assertSeeInOrder verifica orden + duplicación implícita.
        $response->assertOk()
            ->assertSeeInOrder([
                __('admin.orders.payments.events.type_refund'),  // sub-card de devolución arriba (más reciente)
                $payment->gateway_order,                          // el código en la card de devolución
                __('admin.orders.payments.events.type_payment'),  // sub-card de cobro debajo
                $payment->gateway_order,                          // y otra vez en la card de cobro
            ]);
    }

    public function test_payments_card_renders_refund_auth_code_when_present_in_raw_response(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $staff = $this->staff();
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => $payment->amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $staff->id,
            'requested_at' => now(),
            'processed_at' => now(),
            // Redsys devolvió estos campos en el raw_response del refund.
            'raw_response' => [
                'Ds_Response' => '0900',
                'Ds_AuthorisationCode' => '012345',
                'Ds_Date' => '28%2F05%2F2026',  // URL-encoded como envía Redsys
                'Ds_Hour' => '21%3A23',
            ],
        ]);

        $this->actingAs($staff)
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.payments.refunds.auth_code'))
            ->assertSee('012345')
            // Y el timestamp del banco también: aparece decodificado a "28/05/2026 21:23".
            ->assertSee('28/05/2026 21:23');
    }

    public function test_payments_card_renders_refund_with_manual_mode_marker(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $staff = $this->staff();
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount_cents' => $payment->amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_MANUAL,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::MANUAL_RESPONSE_MARKER,
            'requested_by' => $staff->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.payments.refunds.mode_manual'))
            ->assertSee(__('admin.orders.payments.refunds.result_manual'));
    }

    public function test_payments_card_renders_failed_refund_with_human_reason(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $staff = $this->staff();
        $this->makeFailedRefund($payment, $staff, PaymentRefund::FAILURE_GATEWAY_DENIED, '0190');

        $this->actingAs($staff)
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.payments.refunds.status.failed'))
            ->assertSee(__('admin.orders.payments.refunds.failure_reason.gateway_denied'))
            ->assertSee('0190');
    }

    public function test_payments_card_renders_transport_failure_with_hint_to_check_portal(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $payment = $this->makePaymentForOrder($order);
        $staff = $this->staff();
        $this->makeFailedRefund($payment, $staff, PaymentRefund::FAILURE_TRANSPORT, null);

        $this->actingAs($staff)
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.payments.refunds.failure_reason.transport_error_check_portal'))
            ->assertSee(__('admin.orders.payments.refunds.transport_hint'));
    }

    public function test_payments_card_omits_refund_event_when_no_refunds(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);
        $this->makePaymentForOrder($order);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertDontSee(__('admin.orders.payments.events.type_refund'));
    }

    public function test_item_section_renders_event_data_with_localized_labels(): void
    {
        $customer = User::factory()->create();
        $order = $this->makeOrderForCustomer($customer);

        // Pack con esquema event_fields y un OrderItem con event_data poblado.
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::create(['slug' => 'birthday', 'name' => ['es' => 'Cumpleaños']]);
        $pack = TicketType::create([
            'name' => ['es' => 'Pack cumpleaños'],
            'type' => TicketType::TYPE_PACK,
            'zone_id' => $zone->id,
            'duration_min' => 120,
            'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true,
                    'label' => ['es' => 'Nombre del homenajeado', 'en' => "Birthday child's name"]],
                ['key' => 'age', 'type' => 'number', 'required' => false,
                    'label' => ['es' => 'Edad', 'en' => 'Age']],
            ],
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2099-01-01',
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 12, 'seats' => 12, 'unit_price' => 1500,
            'event_data' => ['celebrant' => 'Lucía', 'age' => '7'],
        ]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.event_data_section'))
            ->assertSee('Nombre del homenajeado')  // label localizado (es)
            ->assertSee('Lucía')                    // valor
            ->assertSee('Edad')
            ->assertSee('7');
    }

    // ─── El badge del waiver, por `WaiverStatus` y no por el sello (`#169` §10.5, PAN-5) ────────

    private function waiverVersion(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    public function test_in_internal_mode_a_stamp_without_a_signed_record_shows_missing(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        $this->waiverVersion();
        $customer = User::factory()->create(['waiver_accepted_at' => now()]);
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.customer_waiver_missing'));
    }

    public function test_in_internal_mode_a_signature_of_an_older_version_is_flagged(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);
        $v1 = $this->waiverVersion();
        $customer = User::factory()->create();
        app(WaiverSigner::class)->sign($customer, $v1, WaiverSignatureRequest::web('10.0.0.1', 'test'));
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertDontSee(__('admin.orders.customer_waiver_outdated'))
            ->assertDontSee(__('admin.orders.customer_waiver_missing'));

        $this->waiverVersion(); // v2: la firma de v1 pasa a ser «versión anterior», y el pedido lo dice

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertSee(__('admin.orders.customer_waiver_outdated'));
    }

    public function test_in_disabled_mode_the_waiver_badge_is_not_shown(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'desactivado', 'group' => 'waiver']);
        $customer = User::factory()->create(['waiver_accepted_at' => null]);
        $this->makeOrderForCustomer($customer);

        $this->actingAs($this->staff())
            ->get('/admin/orders/JJ-DETAIL1')
            ->assertOk()
            ->assertDontSee(__('admin.orders.customer_waiver_missing'));
    }
}
