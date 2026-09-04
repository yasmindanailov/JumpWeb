{{-- LAS TARJETAS DE PRODUCTO del asistente (`#462`, T2).

     ⚠️⚠️ **Esto sustituye a un `Select` plano de 18 opciones, y es el crítico C1 de la auditoría.**
     Su rótulo era `«{zona} · {nombre}»` y **nada decía si aquello era una entrada o un pack** —ni el
     prefijo de zona servía—. Con él, una admin vendió un cumpleaños como diez entradas sueltas:
     119,00 € en vez de 180,00 €, la sala sin reservar, 60 min de ocupación en vez de 120 y el
     formulario de invitados que nunca se pidió.

     ▶ **Lo que cierra el agujero es el AGRUPADO**, no la tarjeta: el operador ya no elige de una
     lista donde las dos cosas se parecen, elige dentro de «Entradas» o dentro de «Packs». Y el rango
     de invitados, que solo pintan los packs, es la marca inconfundible de un producto de grupo.

     ⚠️ **Los datos los compone el SERVIDOR** (`productCards()`): aquí no se decide qué producto se
     ofrece, ni se calcula un precio, ni se elige un icono. Y el clic va a `pickProduct()`, que
     **vuelve a comprobar** que el producto siga siendo vendible: el navegador propone, el servidor
     decide (`AFORO-02`).

     ⚠️ **El icono es el MARCADOR del producto** (`ticket_types.icon`), el mismo que el panel ya deja
     elegir en el catálogo y que la web pinta en su tarjeta de precio. Va `aria-hidden`: el nombre
     está justo al lado y un icono que se anunciara lo duplicaría en el nombre accesible del botón.

     ⚠️ **«Más info» NO es un `<button>` dentro del botón de la tarjeta** —anidar controles es HTML
     inválido y el clic se vuelve impredecible—: la tarjeta es un `<button>` y el «Más info» es su
     hermano, dentro del mismo marco. --}}
@php($grupos = $grupos ?? [])

<div class="cmo-products">
    @forelse ($grupos as $grupo)
        <section class="cmo-products__group" aria-labelledby="cmo-pg-{{ $grupo['type'] }}">
            <h3 id="cmo-pg-{{ $grupo['type'] }}" class="cmo-products__group-title">{{ $grupo['label'] }}</h3>

            <ul class="cmo-products__grid">
                @foreach ($grupo['items'] as $p)
                    <li @class(['cmo-card', 'is-selected' => $p['selected']])>
                        <button
                            type="button"
                            class="cmo-card__pick"
                            wire:click="pickProduct({{ $p['id'] }})"
                            wire:loading.attr="disabled"
                            @if ($p['selected']) aria-current="true" @endif
                        >
                            <span class="cmo-card__ico" aria-hidden="true">
                                <x-dynamic-component :component="'icons.'.$p['icon']" :width="30" :height="30" />
                            </span>

                            <span class="cmo-card__body">
                                <span class="cmo-card__name">{{ $p['name'] }}</span>

                                <span class="cmo-card__meta">
                                    @if ($p['zone'])<span>{{ $p['zone'] }}</span>@endif
                                    @if ($p['duration'])<span>{{ __('admin.orders.create_manual.product_minutes', ['n' => $p['duration']]) }}</span>@endif
                                    {{-- Solo los packs: es lo que hace imposible confundirlos con una entrada. --}}
                                    @if ($p['is_pack'])
                                        <span class="cmo-card__guests">{{ __('admin.orders.create_manual.product_guest_range', ['min' => $p['min'], 'max' => $p['max'] ?? '∞']) }}</span>
                                    @endif
                                </span>
                            </span>

                            <span class="cmo-card__price">
                                @if ($p['price_varies'])<span class="cmo-card__from">{{ __('admin.orders.create_manual.product_from') }}</span>@endif
                                {{ number_format($p['price'] / 100, 2, ',', '.') }} €
                            </span>
                        </button>

                        @if ($p['has_info'])
                            <button
                                type="button"
                                class="cmo-card__info"
                                wire:click="mountAction('productInfo', { product: {{ $p['id'] }} })"
                            >
                                {{ __('admin.orders.create_manual.product_more_info') }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        {{-- Sin productos vendibles no hay pedido que crear, y decirlo aquí evita que el operador
             crea que la pantalla no ha cargado. --}}
        <p class="cmo-products__empty">{{ __('admin.orders.create_manual.product_none') }}</p>
    @endforelse
</div>
