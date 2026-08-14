<x-layout :title="__('account.orders.title')" :auth-modal="null">
<div x-data="landing">
    <x-site.nav />

    <main class="page wrap account">
        <div class="page__head">
            <div class="eyebrow">{{ __('account.orders.eyebrow') }}</div>
            <h1 class="page__title">{{ __('account.orders.title') }}</h1>
            <p class="account__intro">{{ __('account.orders.subtitle') }}</p>
        </div>

        {{-- Status flash (`order-retry-unavailable` / `order-retry-failed`) los pinta
             automáticamente el layout, leyendo `account.status.*` (audit edge cases 2026-05-28).
             No duplicar aquí. --}}

        {{-- #179: el estado "vacío" se decide por el TOTAL de pedidos, no por el
             slice de la página actual (`isEmpty()` en un paginador mira solo la
             página) — así una página fuera de rango no muestra el mensaje vacío. --}}
        @if ($orders->total() === 0)
            <p class="account__muted">{{ __('account.orders.empty') }}</p>
        @else
            <ul class="orders">
                @foreach ($orders as $order)
                    <li class="orders__item">
                        <div class="orders__head">
                            <span class="orders__code">{{ __('tickets.order_code') }}: <strong>{{ $order->code }}</strong></span>
                            {{-- displayStatus() (#116): si la Order está pending pero `expires_at`
                                 ya cruzó, mostramos 'expired' (estado efectivo) en vez del crudo
                                 de BD, que aún no ha sido actualizado por `orders:expire`. --}}
                            {{-- #172: badges del pedido agrupados y alineados a la DERECHA. --}}
                            <span class="orders__head-badges">
                                <span class="orders__status orders__status--{{ $order->displayStatus() }}">{{ __('tickets.statuses.'.$order->displayStatus()) }}</span>
                                {{-- Badge secundario "Reembolsado" (#146 + 7.2e.1bis5/#158):
                                     solo si el pedido está fully-refunded (todo devuelto).
                                     Para reembolsos parciales, el resumen financiero de abajo
                                     ("Reembolsado el DD/MM/YYYY: −X €") + los badges por línea
                                     (#172) dan la info sin marcar el pedido entero. --}}
                                @if ($order->isFullyRefunded())
                                    <span class="orders__status orders__status--refunded-badge">{{ __('tickets.refunded_badge') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="orders__meta">{{ \App\Domain\Platform\Services\DisplayTime::format($order->created_at) }}</div>

                        <div class="orders__products">
                            {{-- #F11: ocultamos los PRINCIPALES "fantasma" net-cero (cancelados,
                                 nunca cobrados online ni reembolsados — p. ej. una entrada
                                 gratuita cancelada): saldrían como "0,00 € · Cancelado" y solo
                                 confunden. Mismo criterio que los complementos (autoridad única:
                                 Order::isVoidedLeftoverItem). El cancelado con cargo real SÍ se
                                 sigue mostrando (tachado). --}}
                            @foreach ($order->items->whereNull('parent_item_id')->reject(fn ($item) => $order->isVoidedLeftoverItem($item)) as $item)
                                {{-- #172: un producto/complemento CANCELADO se refleja (tachado + badge
                                     "Cancelado") en lugar de aparecer como activo. El reembolso asociado
                                     ya se ve en el resumen financiero de abajo. --}}
                                @php($itemCancelled = $item->isCancelled())
                                @php($itemRefunded = $order->itemRefundedCents($item) > 0)
                                @php($itemFinished = ! $itemCancelled && $item->displayStatusForCustomer() === \App\Domain\Booking\Models\OrderItem::STATUS_FINISHED)
                                {{-- Subcard sutil POR PRODUCTO (#217 UX): separa visualmente cada reserva de un
                                     mismo pedido y agrupa producto + complementos + su propio formulario. --}}
                                <div class="orders__product">
                                <div @class(['orders__line', 'orders__line--cancelled' => $itemCancelled, 'orders__line--finished' => $itemFinished])>
                                    <span class="orders__line-name"><x-icons.product :is-pack="$item->ticketType?->isPack() ?? false" /><span class="orders__line-text">@if ($item->ticketType?->isPack()){{ __('tickets.guests_count', ['count' => $item->quantity]) }} · {{ $item->ticketType?->tr('name') }}@else{{ $item->quantity }}&times; {{ $item->ticketType?->tr('name') }}@endif</span></span>
                                    @if ($item->slot)
                                        <span class="orders__line-when">{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::parse($item->slot->date)->locale(app()->getLocale())->isoFormat('ddd D MMM')) }} · {{ $item->displayTimeWindow() }}</span>
                                    @endif
                                    {{-- #172: badges de la línea agrupados a la DERECHA. Cancelado tiene
                                         prioridad sobre Finalizado; "Reembolsado" se añade si el item tiene
                                         reembolso registrado (puede coexistir con Cancelado). --}}
                                    @if ($itemCancelled || $itemFinished || $itemRefunded)
                                        <span class="orders__line-badges">
                                            @if ($itemCancelled)
                                                <span class="orders__line-badge orders__line-badge--cancelled">{{ __('account.orders.item_cancelled') }}</span>
                                            @elseif ($itemFinished)
                                                <span class="orders__line-badge orders__line-badge--finished">{{ __('account.orders.item_finished') }}</span>
                                            @endif
                                            @if ($itemRefunded)
                                                <span class="orders__line-badge orders__line-badge--refunded">{{ __('tickets.refunded_badge') }}</span>
                                            @endif
                                        </span>
                                    @endif
                                    <span class="orders__line-price">{{ number_format($item->chargedSubtotalCents() / 100, 2, ',', '.') }} €</span>
                                    @if ($item->ticketType?->isPack() && ! empty($item->event_data))
                                        <ul class="orders__event">
                                            @foreach ($item->ticketType->eventFields() as $field)
                                                @if (! empty($item->event_data[$field['key']]))
                                                    <li><span class="orders__event-label">{{ $item->ticketType->eventFieldLabel($field) }}:</span> {{ $item->event_data[$field['key']] }}</li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                {{-- Complementos agrupados bajo su producto (#87). Heredan estado del parent (#127).
                                     #172: un complemento CANCELADO se tacha + badge "Cancelado" (antes salía como activo).
                                     #192-fix: ocultamos los cancelados que nunca se cobraron ni se reembolsaron
                                     (p. ej. un menú sustituido en un cambio de menú): son net-cero y solo confunden. --}}
                                @foreach ($item->children->reject(fn ($c) => $order->isVoidedLeftoverItem($c)) as $child)
                                    @php($childCancelled = $child->isCancelled())
                                    @php($childRefunded = $order->itemRefundedCents($child) > 0)
                                    @php($childBadge = $child->addonBadgeKey())
                                    @php($childNote = $child->partialFreeNote())
                                    <div @class(['orders__line', 'orders__line--addon', 'orders__line--cancelled' => $childCancelled, 'orders__line--finished' => ! $childCancelled && $itemFinished])>
                                        <span class="orders__line-name">+ {{ $child->ticketType?->tr('name') }}@if ($childBadge) <span class="orders__line-included">{{ __('tickets.addon_badge_'.$childBadge) }}</span>@endif <span class="orders__line-unit">· {{ $child->quantity }}&times; {{ number_format($child->unit_price / 100, 2, ',', '.') }} €@if ($childNote) ({{ $childNote }})@endif</span></span>
                                        @if ($childCancelled || $childRefunded)
                                            <span class="orders__line-badges">
                                                @if ($childCancelled)
                                                    <span class="orders__line-badge orders__line-badge--cancelled">{{ __('account.orders.item_cancelled') }}</span>
                                                @endif
                                                @if ($childRefunded)
                                                    <span class="orders__line-badge orders__line-badge--refunded">{{ __('tickets.refunded_badge') }}</span>
                                                @endif
                                            </span>
                                        @endif
                                        <span class="orders__line-price">{{ number_format($child->chargedSubtotalCents() / 100, 2, ',', '.') }} €</span>
                                    </div>
                                @endforeach
                                {{-- Post-form de datos por invitado (#217), INDIVIDUALIZADO POR RESERVA: el botón
                                     va JUSTO DEBAJO de SU producto. Resaltado (btn--zone) si está pendiente; ghost
                                     si ya está completo (editable). Si la reserva YA SE CELEBRÓ → enlace de SOLO
                                     LECTURA «Ver» (no editable; coherente con el aviso del sidecart). --}}
                                {{-- Una reserva que ya NO admite el formulario conserva el botón pero DESACTIVADO (no
                                     clicable, decisión clienta 2026-06-15). Ocurre en DOS casos: (a) la RESERVA está
                                     cancelada (`cancelled_at` del item), o (b) el PEDIDO entero está cancelado/reembolsado
                                     (el item del pack puede no estar marcado uno a uno). El post-form ya no aplica —al
                                     pulsarlo daría 404: `Order::guestFormItems()` excluye cancelados y `GuestFormController`
                                     exige pedido pagado— pero el botón permanece visible para no «desaparecer» el contexto
                                     de la reserva. El `<button disabled>` hereda el estilo apagado de `.btn` (landing.css). --}}
                                @php($orderDisplayStatus = $order->displayStatus())
                                @php($orderTerminated = in_array($orderDisplayStatus, [\App\Domain\Booking\Models\Order::STATUS_CANCELLED, \App\Domain\Booking\Models\Order::STATUS_REFUNDED], true))
                                @if ($item->guestFormStatus() !== null && ($orderDisplayStatus === \App\Domain\Booking\Models\Order::STATUS_PAID || $orderTerminated))
                                    <div class="orders__product-form">
                                        @if ($itemCancelled || $orderTerminated)
                                            <button type="button" class="btn btn--ghost orders__guestform-btn" disabled>{{ __('account.orders.guest_form_cancelled', ['product' => $item->ticketType?->tr('name')]) }}</button>
                                        @elseif ($item->isFinishedInPractice())
                                            <a href="{{ route('reservation.guests', ['reservation' => $item]) }}" class="orders__product-form-link">{{ __('account.orders.guest_form_past', ['product' => $item->ticketType?->tr('name')]) }}</a>
                                        @else
                                            <a href="{{ route('reservation.guests', ['reservation' => $item]) }}"
                                               class="btn {{ $item->needsGuestForm() ? 'btn--zone' : 'btn--ghost' }} orders__guestform-btn">
                                                {{ $item->needsGuestForm()
                                                    ? __('account.orders.guest_form_pending', ['product' => $item->ticketType?->tr('name')])
                                                    : __('account.orders.guest_form_done', ['product' => $item->ticketType?->tr('name')]) }}
                                            </a>
                                        @endif
                                    </div>
                                @endif
                                {{-- #225 F3 → REUBICADA (decisión clienta 2026-06-15): la señal POR PRODUCTO
                                     («Señal X · Y en el parque») baja al PIE de la card, separada de los datos del
                                     evento (homenajeado, etc.), como pie financiero de la reserva — la señal real
                                     (no el agregado online mixto del pedido). Un item CANCELADO no la muestra
                                     (`ReservationFinancials` excluye cancelados → `aCobrarPuerta` 0). --}}
                                @php($rfLine = \App\Domain\Booking\Services\ReservationFinancials::make($order, $item))
                                @if (($item->ticketType?->hasDeposit() ?? false) && $rfLine->aCobrarPuerta > 0)
                                    <span class="orders__product-deposit">{{ __('tickets.deposit_card_note', ['deposit' => number_format($rfLine->pagadoOnline / 100, 2, ',', '.').' €', 'rest' => number_format($rfLine->aCobrarPuerta / 100, 2, ',', '.').' €']) }}</span>
                                @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Robustez del desglose (#196/#197/#198): desglose detallado en
                             "Mis pedidos" (espejo del panel; decisión de mostrarlo al cliente,
                             revisa #139a). Ledger: "Subtotal" → cambios → "Total" (en vez de
                             "Neto", #198.3). Estilo inline `@php(...)` (coherencia del fichero). --}}
                        @php($s = $order->financialSummary())
                        @php($eur = fn (int $c) => \App\Domain\Platform\Services\Money::format($c))
                        @php($refundColCents = (int) ($order->refund_amount_cents ?? 0))
                        @php($hasRefundCol = $order->refunded_at !== null && $refundColCents > 0)
                        @php($hasBreakdown = $s->hasPendingAtGate() || $hasRefundCol || $s->hasPendienteDevolucion())
                        {{-- Total final = VALOR de los productos que quedan (= totalFinalNeto, mismo
                             número que el headline «Valor final» del panel). Deposit-aware (#225): el
                             antiguo `totalWithChanges − pendiente` trataba Order.total como cobrado
                             online y daba cifras infladas en pedidos con señal. La devolución (devuelto
                             / pendiente) se muestra como eje aparte debajo. --}}
                        @php($finalTotal = $s->totalFinalNeto())
                        {{-- Señal/depósito (#225): el predicado canónico es que EXISTAN ajustes
                             `deposit_remainder` vivos (`$s->depositRemainder`), NO `online < total`
                             —que se encendería falsamente por una línea cancelada en un pedido sin
                             señal (onlineDueCents excluye cancelados; total es inmutable). --}}
                        @php($senalOnline = $order->onlineDueCents())
                        @php($tieneSenal = $s->depositRemainder > 0)

                        {{-- Primera línea: "Subtotal" si hay desglose de cambios; si no, "Total". --}}
                        <div class="orders__total">
                            <span>{{ $hasBreakdown ? __('tickets.subtotal') : __('tickets.total') }}</span>
                            <strong>{{ $eur((int) $order->total) }}</strong>
                        </div>

                        {{-- Señal pagada online (#225): informativo, ya cobrado (color neutro). --}}
                        @if ($tieneSenal)
                            <div class="orders__gate">
                                <span class="orders__gate-label">{{ __('tickets.deposit_paid_online') }}</span>
                                <strong>{{ $eur($senalOnline) }}</strong>
                            </div>
                        @endif

                        {{-- A cobrar en el parque: resto de la señal y/o cambios que subieron el importe.
                             #225 F3: el desglose ↳ queda OCULTO por defecto tras un toggle «Ver desglose»
                             (Alpine). El agregado y el caption SIEMPRE visibles. Como hasPendingAtGate ⟺
                             Σ(↳) > 0, el toggle siempre tiene contenido que mostrar. --}}
                        @if ($s->hasPendingAtGate())
                            @php($gateLines = $order->pendingAtGateLines())
                            <div x-data="{ open: false }">
                                <div class="orders__gate">
                                    <span class="orders__gate-label">{{ __('tickets.at_gate') }}</span>
                                    <strong class="orders__gate-amount">+{{ $eur($s->pendingAtGate()) }}</strong>
                                </div>
                                <button type="button" class="orders__gate-toggle" x-on:click="open = ! open" :aria-expanded="open ? 'true' : 'false'">
                                    <span x-show="! open">{{ __('tickets.show_breakdown') }}</span>
                                    <span x-show="open" x-cloak>{{ __('tickets.hide_breakdown') }}</span>
                                </button>
                                <div x-show="open" x-cloak>
                                    @foreach ($gateLines as $gateLine)
                                        <div class="orders__gate-line"><span>↳ {{ $gateLine['label'] }}</span><strong>+{{ $eur($gateLine['amount']) }}</strong></div>
                                    @endforeach
                                    {{-- #225 (feedback clienta): «Resto de la señal» DESGLOSADO POR PRODUCTO («de X»);
                                         dos productos con señal → dos líneas. --}}
                                    @foreach ($order->depositRemainderPendingByProduct() as $dr)
                                        <div class="orders__gate-line"><span>↳ {{ __('tickets.deposit_remainder_line') }} {{ __('tickets.deposit_for_product', ['product' => $dr['name']]) }}</span><strong>+{{ $eur($dr['amount']) }}</strong></div>
                                    @endforeach
                                </div>
                                <p class="orders__gate-caption">{{ __($tieneSenal ? 'tickets.at_gate_caption_deposit' : 'tickets.at_gate_caption') }}</p>
                            </div>
                        @endif

                        {{-- Reembolso con éxito registrado (#146). `refund_amount_cents` legacy-safe. --}}
                        @if ($hasRefundCol)
                            <div class="orders__refund">
                                <span class="orders__refund-label">
                                    {{ __('tickets.refunded_on', ['date' => \App\Domain\Platform\Services\DisplayTime::format($order->refunded_at, 'd/m/Y')]) }}
                                </span>
                                <strong class="orders__refund-amount">−{{ $eur($refundColCents) }}</strong>
                            </div>
                        @endif

                        {{-- Devolución debida aún no procesada (una reducción/cancelación cuyo
                             reembolso no se ha completado) + el PORQUÉ (#198.1). --}}
                        @if ($s->hasPendienteDevolucion())
                            <div class="orders__refund">
                                <span class="orders__refund-label">{{ __('tickets.pendiente_devolucion') }}</span>
                                <strong class="orders__refund-amount">−{{ $eur($s->pendienteDevolucion()) }}</strong>
                            </div>
                            <p class="orders__gate-caption">{{ __('tickets.pendiente_devolucion_caption') }}</p>
                        @endif

                        {{-- Total final (reemplaza "Neto", #198.3): lo que el cliente acaba pagando. --}}
                        @if ($hasBreakdown)
                            <div class="orders__final">
                                <span>{{ __('tickets.total') }}</span>
                                <strong>{{ $eur($finalTotal) }}</strong>
                            </div>
                        @endif

                        {{-- Reintentar el pago (audit edge cases 2026-05-28): solo si la Order
                             es pending + no expirada + tiene Payment intentado. El controller
                             revalida estas condiciones (defensa contra carrera con orders:expire). --}}
                        @if ($order->canBeRetried())
                            <form method="POST" action="{{ route('account.orders.retry', ['code' => $order->code]) }}" class="orders__retry">
                                @csrf
                                <button type="submit" class="btn btn--zone orders__retry-btn">
                                    {{ __('account.orders.retry_payment') }}
                                </button>
                                <p class="orders__retry-hint">{{ __('account.orders.retry_hint') }}</p>
                            </form>
                        @endif

                        {{-- "Gestionar" para Orders pagadas (#117): cualquier cambio (fecha,
                             cancelación, devolución, datos) se canaliza por la página de
                             contacto para que la operativa valore el caso. El modal explica
                             el porqué y destaca el código de pedido para que el cliente lo
                             pueda copiar fácilmente y proporcionarlo al contactar. Reusa
                             las clases `.modal*` ya validadas por el modal de auth.
                             Estado Alpine LOCAL por Order: cada modal es independiente. --}}
                        @if ($order->displayStatus() === \App\Domain\Booking\Models\Order::STATUS_PAID)
                            {{-- ⚠️ La llave del bloqueo lleva el ID del PEDIDO, y eso no es cosmético: esta
                                 página pinta un modal por pedido, así que con una llave compartida abrir
                                 A, abrir B y cerrar A soltaría el scroll con B todavía delante. El dueño
                                 único vive en `resources/js/ui/scroll-lock.js` (`sidebar-spa.md` §6). --}}
                            <div class="orders__manage" x-data="{
                                open: false,
                                show() {
                                    this.open = true;
                                    $store.scrollLock.lock('order-manage:{{ $order->id }}');
                                    this.$nextTick(() => this.$refs.panel?.querySelector('a,button')?.focus());
                                },
                                hide() {
                                    this.open = false;
                                    $store.scrollLock.unlock('order-manage:{{ $order->id }}');
                                }
                            }">
                                <button type="button" class="btn btn--ghost orders__manage-btn" @click="show()">
                                    {{ __('account.orders.manage') }}
                                </button>

                                <div x-cloak x-show="open" class="modal"
                                     @keydown.escape.window="hide()">
                                    <div class="modal__backdrop" @click="hide()"></div>
                                    <div class="modal__panel modal__panel--narrow" role="dialog" aria-modal="true"
                                         aria-labelledby="manage-title-{{ $order->id }}" x-ref="panel">
                                        <button type="button" class="modal__close" @click="hide()"
                                                aria-label="{{ __('account.close') }}">&times;</button>

                                        <div class="manage">
                                            <span class="eyebrow">{{ __('account.orders.manage_eyebrow') }}</span>
                                            <h2 id="manage-title-{{ $order->id }}" class="manage__title">
                                                {{ __('account.orders.manage_title') }}
                                            </h2>
                                            <p class="manage__intro">{{ __('account.orders.manage_intro') }}</p>

                                            {{-- Código del pedido destacado para que el cliente
                                                 lo copie de un vistazo al contactar (#117). --}}
                                            <div class="manage__code" role="group" aria-label="{{ __('tickets.order_code') }}">
                                                <span class="manage__code-label">{{ __('tickets.order_code') }}</span>
                                                <strong class="manage__code-value">{{ $order->code }}</strong>
                                            </div>

                                            <p class="manage__note">{{ __('account.orders.manage_note') }}</p>

                                            <div class="manage__actions">
                                                <a href="{{ route('contacto') }}" class="btn btn--zone btn--lg manage__cta">
                                                    {{ __('account.orders.manage_cta') }}
                                                </a>
                                                <button type="button" class="btn btn--ghost manage__cancel" @click="hide()">
                                                    {{ __('account.close') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            <x-site.pagination :paginator="$orders" />
        @endif

        <a href="{{ route('account') }}" class="page__back">{{ __('account.orders.back') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
