{{-- EL DESENLACE del pedido manual (`DECISIONES #466`, T4 de `specs/asistente-crear-pedido.md`).

     `[owner]`: «después de crear el pedido, una pantalla nueva: pedido creado correctamente…». Antes
     había un *toast* y una redirección a la ficha: el operador aterrizaba en una pantalla de
     administración de 2.347 px con el cliente delante y sin que nada le dijera qué hacer ahora.

     ❗❗❗ **La regla de esta pantalla es no prometer nada que no haya pasado.** Lo que dice el bloque
     del correo sale del MISMO predicado que usa `ManualOrderFulfiller` (`filled($user->email)`), y
     los enlaces, de las MISMAS autoridades que él consulta. Con un cliente sin correo —el alta de
     mostrador solo pide teléfono (`#263`)— **no se envía nada**, y decirlo es el punto entero.

     ⚠️ **El dinero lo pinta `reservation-financials`**, el pintor ÚNICO del libro (`#311`): esta
     pantalla no compone ni un importe. Y los enlaces usan el mismo partial de copiar que la ficha
     del pedido, con su respaldo `execCommand` para cuando el portapapeles no está disponible. --}}
@php
    use App\Domain\Booking\Services\ManualOrderFulfiller;

    $tieneCorreo = filled($resumen['email']);
    $metodo = $resumen['method'] === ManualOrderFulfiller::METHOD_DATAFONO
        ? __('admin.orders.create_manual.method_datafono')
        : __('admin.orders.create_manual.method_cash');
@endphp

<div class="cmo-done">
    <x-filament::section>
        <div class="cmo-done__head">
            <span class="cmo-done__tick" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8" />
            </span>
            <div>
                <h2 class="cmo-done__title">{{ __('admin.orders.create_manual.done_title') }}</h2>
                <p class="cmo-done__code" data-done-code>{{ $resumen['code'] }}</p>
                @if ($resumen['customer'])
                    <p class="cmo-done__who">{{ $resumen['customer'] }}</p>
                @endif
            </div>
        </div>

        {{-- QUÉ SE HA RESERVADO: lo que el operador lee en voz alta antes de despedir al cliente. --}}
        <ul class="cmo-done__lines">
            @foreach ($resumen['reservations'] as $linea)
                <li class="cmo-done__line">
                    <span>{{ $linea['qty'] }}× {{ $linea['label'] }}</span>
                    @if ($linea['when'])
                        <span class="cmo-done__when">{{ $linea['when'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        {{-- EL DINERO, por el pintor único del libro: Total · Pagado · saldo, con su desglose
             plegado. Encima, el método con el que se acaba de cobrar, que el libro no dice. --}}
        <div class="cmo-done__money">
            <p class="cmo-done__method">{{ __('admin.orders.create_manual.done_charged', ['method' => $metodo]) }}</p>
            @include('filament.orders.partials.reservation-financials', ['book' => $resumen['book']])
        </div>
    </x-filament::section>

    {{-- EL CORREO: lo que se le ha enviado al cliente… o el aviso de que NO se le ha enviado nada. --}}
    <x-filament::section
        :icon="$tieneCorreo ? 'heroicon-o-envelope' : 'heroicon-o-exclamation-triangle'"
        :icon-color="$tieneCorreo ? 'gray' : 'warning'"
    >
        <x-slot name="heading">
            {{ $tieneCorreo
                ? __('admin.orders.create_manual.done_mail_title')
                : __('admin.orders.create_manual.done_no_mail_title') }}
        </x-slot>

        @if ($tieneCorreo)
            <ul class="cmo-done__sent" data-done-sent>
                <li>{{ __('admin.orders.create_manual.done_mail_confirmation', ['email' => $resumen['email']]) }}</li>
                @if ($resumen['sent']['guest_form'] > 0)
                    <li>{{ trans_choice('admin.orders.create_manual.done_mail_guest_form', $resumen['sent']['guest_form'], ['count' => $resumen['sent']['guest_form']]) }}</li>
                @endif
                @if ($resumen['sent']['guardian'] > 0)
                    <li>{{ trans_choice('admin.orders.create_manual.done_mail_guardian', $resumen['sent']['guardian'], ['count' => $resumen['sent']['guardian']]) }}</li>
                @endif
            </ul>
        @else
            <p class="cmo-done__warn" data-done-no-mail>{{ __('admin.orders.create_manual.done_no_mail_body') }}</p>
        @endif
    </x-filament::section>

    {{-- LOS ENLACES que el operador puede entregar a mano. Existen tenga correo o no: el cliente que
         dice «no me ha llegado» está delante, y aquí ya está lo que necesita. --}}
    @if ($resumen['links'] !== [])
        <x-filament::section icon="heroicon-o-link">
            <x-slot name="heading">{{ __('admin.orders.create_manual.done_links_title') }}</x-slot>

            <div class="space-y-4" data-done-links>
                @foreach ($resumen['links'] as $enlace)
                    <div>
                        <p class="cmo-done__link-label">
                            {{ $enlace['kind'] === 'guardian'
                                ? __('admin.orders.create_manual.done_link_guardian', ['product' => $enlace['label']])
                                : __('admin.orders.create_manual.done_link_guest_form', ['product' => $enlace['label']]) }}
                        </p>
                        @include('filament.orders.partials.guest-form-link', ['url' => $enlace['url']])
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Los menores que la asignación no pudo colocar: era un *toast*, y los toasts desaparecen. --}}
    @if ($resumen['dependents_skipped'] > 0)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">{{ __('admin.orders.dependents.manual_assign_failed', ['count' => $resumen['dependents_skipped']]) }}</x-slot>
            <p class="cmo-done__warn">{{ __('admin.orders.create_manual.done_dependents_hint') }}</p>
        </x-filament::section>
    @endif

    {{-- Las DOS salidas. «Otro pedido» es la primera porque en un mostrador hay cola: es lo que se
         hace después de cobrar, y la ficha del pedido está a un toque de distancia igualmente. --}}
    <div class="cmo-done__cta">
        <x-filament::button
            size="lg"
            icon="heroicon-o-plus-circle"
            wire:click="startAnotherOrder"
        >
            {{ __('admin.orders.create_manual.done_another') }}
        </x-filament::button>

        <x-filament::button
            size="lg"
            color="gray"
            tag="a"
            :href="$resumen['url']"
            icon="heroicon-o-arrow-top-right-on-square"
        >
            {{ __('admin.orders.create_manual.done_view_order') }}
        </x-filament::button>
    </div>
</div>
