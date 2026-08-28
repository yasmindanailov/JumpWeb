{{-- «Abrir calendario»: la puerta al calendario amplio (`DECISIONES #241`).

     `[OWNER, 2026-08-28]`: «lo de "otra fecha" mejor un CTA "abrir calendario" y así puedes elegir
     otra fecha del calendario más amplio». Antes era un campo más apilado en la columna; ahora es una
     ACCIÓN que se busca cuando hace falta — que es como lo usa el mostrador: la tira resuelve la
     reserva de los próximos días y el calendario está para el cumpleaños de dentro de tres meses. --}}
<button
    type="button"
    @class(['cmo-calcta', 'is-open' => $open])
    aria-expanded="{{ $open ? 'true' : 'false' }}"
    wire:click="toggleCalendar"
>
    <span class="cmo-calcta__ico" aria-hidden="true"></span>
    <span>{{ $open ? __('admin.orders.create_manual.calendar_hide') : __('admin.orders.create_manual.calendar_show') }}</span>
</button>
