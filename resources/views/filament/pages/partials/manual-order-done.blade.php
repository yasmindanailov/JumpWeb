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

    {{-- ❗❗❗ **LO QUE EL CLIENTE TIENE QUE RECIBIR**, en UNA lista (`#467`, `[owner]`: «la parte de que
         al cliente le ha llegado un formulario o lo que sea, más profesional»).

         Antes eran DOS bloques —«se le ha enviado» arriba, «enlaces para entregar a mano» abajo— y el
         operador tenía que emparejar de cabeza cada correo con su enlace. Ahora cada entregable es una
         FILA: qué es · de qué reserva · **si ha salido o no** · y su enlace copiable al lado.

         ⚠️ El estado va en una PASTILLA y no en prosa: es lo que el operador escanea con el cliente
         delante. Verde = ha salido; ámbar = **no**, y hay que entregarlo a mano.

         ⚠️ El enlace se ofrece aunque el correo haya salido: el cliente que dice «no me ha llegado»
         está delante, y así el operador no tiene que ir a la ficha a buscarlo. --}}
    <x-filament::section icon="heroicon-o-paper-airplane">
        <x-slot name="heading">{{ __('admin.orders.create_manual.done_delivery_title') }}</x-slot>

        @unless ($tieneCorreo)
            {{-- El caso que motiva la tanda: sin correo NO SE ENVÍA NADA, y el operador tiene que
                 enterarse antes de despedir al cliente. Va arriba y con peso, no como una nota. --}}
            <div class="cmo-done__alert" data-done-no-mail>
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                <p>
                    <strong>{{ __('admin.orders.create_manual.done_no_mail_title') }}</strong>
                    {{ __('admin.orders.create_manual.done_no_mail_body') }}
                </p>
            </div>
        @endunless

        <ul class="cmo-done__deliveries" data-done-deliveries>
            @foreach ($resumen['deliverables'] as $entrega)
                <li class="cmo-done__delivery" data-done-delivery="{{ $entrega['kind'] }}">
                    <div class="cmo-done__delivery-head">
                        <div class="cmo-done__delivery-what">
                            <p class="cmo-done__delivery-title">
                                {{ __('admin.orders.create_manual.done_item_'.$entrega['kind']) }}
                            </p>
                            @if ($entrega['subject'])
                                <p class="cmo-done__delivery-sub">
                                    {{ $entrega['subject'] }}@if ($entrega['when']) · {{ $entrega['when'] }} @endif
                                </p>
                            @endif
                        </div>

                        {{-- ⚠️ **Tres estados y no dos.** «Entrégalo tú» solo vale si hay ALGO que
                             entregar: la confirmación del pedido no tiene enlace, así que sin correo
                             simplemente **no se envía** — pedirle al operador que la entregue sería
                             mandarle a hacer algo que no existe. Lo vio el ojo, no la sonda. --}}
                        <x-filament::badge :color="$entrega['sent'] ? 'success' : 'warning'" class="shrink-0">
                            @if ($entrega['sent'])
                                {{ __('admin.orders.create_manual.done_state_sent', ['email' => $resumen['email']]) }}
                            @elseif ($entrega['url'])
                                {{ __('admin.orders.create_manual.done_state_by_hand') }}
                            @else
                                {{ __('admin.orders.create_manual.done_state_not_sent') }}
                            @endif
                        </x-filament::badge>
                    </div>

                    @if ($entrega['url'])
                        @include('filament.orders.partials.guest-form-link', ['url' => $entrega['url'], 'hint' => false])
                    @endif
                </li>
            @endforeach
        </ul>
    </x-filament::section>

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
