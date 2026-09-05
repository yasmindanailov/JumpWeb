{{-- **El aviso de lo que el día elegido le hace a los complementos** (`#417`, `[DECIDIDO owner]`).

     Retirar una línea con devolución mientras se cambia una fecha es un efecto que el operador no
     pidió: lo ve con el importe delante y puede elegir otro día en vez de enterarse después por el
     historial. Sale del MISMO `plan()` que decide bajo el lock — si fuera un cálculo aparte, podría
     confirmar una cosa y aplicarse otra.

     ⚠️ Vive suelto y no en línea dentro del calendario **para poder aseverarlo**: el contenido de un
     modal de Filament no aparece en el HTML del componente (medido), así que un `assertSee` sobre la
     página pasa en vacío — la trampa de `#161`.

     ⚠️⚠️ **Los colores son `amber-*` de la paleta BASE de Tailwind, no los semánticos `warning-*` de
     Filament**, y es el mismo vocabulario que usa su banner hermano (`manage-item-blocked-banner`).
     No es preferencia: con `bg-warning-50` el recuadro salía **transparente** —medido en el panel
     real: `rgba(0, 0, 0, 0)`— porque esas utilidades compilan pero sus variables no están declaradas
     (el fallo de `#217`, escrito en `panel-navegacion.md` §4.5). *Un aviso que no se ve como aviso no
     avisa, y nada falla.*

     @var \App\Domain\Booking\Contracts\AddonDatePlan $plan  nunca vacío: eso lo filtra quien incluye
     @var string $currency --}}

<div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30">
    <p class="font-medium">{{ __('admin.orders.manage_item.addon_date_heading') }}</p>

    <ul class="mt-1 list-disc space-y-0.5 ps-4">
        @foreach ($plan->withdrawals() as $change)
            <li>
                {{ __('admin.orders.manage_item.addon_date_withdrawn', [
                    'name' => $change->productName,
                    'amount' => \App\Domain\Platform\Services\Money::format($change->chargedUnits() * $change->currentUnitCents, $currency),
                ]) }}
            </li>
        @endforeach

        @foreach ($plan->repricings() as $change)
            <li>
                {{ __('admin.orders.manage_item.addon_date_repriced', [
                    'name' => $change->productName,
                    'from' => \App\Domain\Platform\Services\Money::format($change->currentUnitCents, $currency),
                    'to' => \App\Domain\Platform\Services\Money::format((int) $change->newUnitCents, $currency),
                ]) }}
            </li>
        @endforeach
    </ul>

    @if ($plan->netDeltaCents() !== 0)
        <p class="mt-1.5">
            {{ __('admin.orders.manage_item.addon_date_balance', [
                'amount' => \App\Domain\Platform\Services\Money::format(abs($plan->netDeltaCents()), $currency),
            ]) }}
        </p>
    @endif
</div>
