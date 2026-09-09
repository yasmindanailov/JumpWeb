@props([
    // La salida de `RateCards::compose()`: una entrada por zona con sus tarjetas.
    'zones',
    // El rótulo de la tarifa especial: si no hay, no se escribe la nota de días.
    'specialLabel' => null,
])

{{-- ══ SECCIÓN 02 · «CUÁNTO» · EL CARRIL DE TARIFAS ═══════════════════════════════════════════
     `docs/specs/rediseno-desde-canvas.md` §5.4 · T2c · `DECISIONES #479`.
     Artboards: **`Precios PJP` 10a** (móvil, ✅ aprobada y aplicada) y `Escritorio PJP` 2a.

     ❗❗❗ **EL FOCO ES LA PIEZA, no el carril.** En móvil lo pone el ARRASTRE y en escritorio el
     CURSOR o el Tab, pero en las dos superficies significa lo mismo: **una tarjeta viva y las
     demás en Nube al 94 %**, con el relleno de acción viajando con ella. Es lo que hace que la
     sección se lea igual en las dos y lo que sostiene el mapa del naranja del sistema —un solo
     relleno de acción por pantalla—.

     ⚠️⚠️ **El velo es NUBE, nunca opacidad**, y está medido en el propio sistema: `opacity` mueve
     el fondo, la tinta y el gris a la vez, y una cifra a media opacidad se queda en 2,4 de
     contraste. Con Nube al 100 % el precio velado da 4,28 —pasa por ser cifra grande, no por ser
     texto—, y eso es una decisión declarada, no un descuido.

     ⚠️ **En escritorio NO hay carril**: la lista cabe en la columna, así que las piezas pasan a
     pistas de rejilla. Sin scroll no hay asoma, ni ajuste, ni foco por arrastre — pero **sí hay
     foco**, que es lo que `Escritorio PJP` 2b intentó quitar y quedó descartado. --}}
<div class="rates"
     x-data="rateRail(@js(collect($zones)->pluck('slug')->first()), @js(collect($zones)->mapWithKeys(fn ($z) => [$z['slug'] => $z['featured'] ?? 0])))">

    {{-- ⚠️⚠️ **LA PESTAÑA DEL SISTEMA, y no `.zone-tab`.** Aquella clase la comparten `/servicios`
         y **el cajón en Vue** (`IdentifyStep`, `AuthTabs`), que es Fase 4: retocarla aquí habría
         cambiado el embudo de compra desde una tanda de la portada. `.tabset` es el componente del
         canvas —pista en Nube con 4 de hueco y 4 de relleno, radio 16 fuera y 10 dentro, activa en
         blanco— y las demás superficies lo adoptan al rehacerse.
         ⚠️ El ORDEN lo manda `zones.position`, o sea el panel. El artboard ordena Kids · Jump y
         esta instalación tiene Jump primero: es DATO, y va en los pasos de despliegue. --}}
    <div class="tabset" role="tablist" aria-label="{{ __('landing.rates.pick_zone') }}">
        @foreach ($zones as $z)
            <button type="button" class="tabset__tab" role="tab"
                    id="rate-tab-{{ $z['slug'] }}"
                    aria-controls="rate-panel-{{ $z['slug'] }}"
                    :class="zone === @js($z['slug']) && 'is-active'"
                    :aria-selected="zone === @js($z['slug']) ? 'true' : 'false'"
                    @click="pick(@js($z['slug']))">{{ $z['name'] }}</button>
        @endforeach
    </div>

    @foreach ($zones as $z)
        <div class="rates__panel" role="tabpanel"
             id="rate-panel-{{ $z['slug'] }}"
             aria-labelledby="rate-tab-{{ $z['slug'] }}"
             x-show="zone === @js($z['slug'])" @if (! $loop->first) x-cloak @endif>

            {{-- ⚠️ **`<ul>` y no `<div>`**: son las tarifas de esa zona, o sea una lista, y el
                 lector de pantalla anuncia cuántas hay antes de recorrerlas. En un carril donde
                 solo se ve una y media, saber que son tres es justo lo que falta. --}}
            <ul class="rates__rail" role="list"
                x-ref="rail-{{ $z['slug'] }}"
                @scroll.passive="mira(@js($z['slug']))">

                @foreach ($z['cards'] as $i => $card)
                    <li class="rate-card"
                        :class="{ 'is-focus': foco[@js($z['slug'])] === {{ $i }} }"
                        @if ($z['featured'] === $i) data-featured @endif
                        @mouseenter="enfoca(@js($z['slug']), {{ $i }})"
                        @focusin="enfoca(@js($z['slug']), {{ $i }})">

                        {{-- ❗ **EL NOMBRE MANDA** (10a): va en rótulo y es lo primero que se lee.
                             El matiz solo aparece cuando AÑADE un dato —«sin límite» sí, «120 min»
                             no, porque es «2 horas» dicho otra vez—, y esa regla vive en el
                             dominio (`RateCards::nameAndNuance()`), no aquí. --}}
                        <p class="rate-card__head">
                            <span class="rate-card__name">{{ $card['name'] }}</span>
                            @if ($card['nuance'])
                                <span class="rate-card__nuance">{{ $card['nuance'] }}</span>
                            @endif
                            {{-- El chip sale de `ticket_types.badge`, que el panel ya rellena.
                                 «Lo que no hay, no se pinta». --}}
                            @if ($card['badge'])
                                <span class="rate-card__badge">{{ $card['badge'] }}</span>
                            @endif
                        </p>

                        {{-- ⚠️ La cifra y el «€» van en dos elementos porque van a dos tamaños (38 y
                             20 en el artboard). La UNIDAD es hermana, nunca hija del número: dentro
                             heredaría su interlineado y se partiría en dos — el defecto que `#309`
                             arregló en la tarjeta vieja. --}}
                        <p class="rate-card__price">
                            <span class="rate-card__num">{{ $card['price'] }}</span>
                            <span class="rate-card__cur">€</span>
                            @if ($card['unit'])<span class="rate-card__unit">{{ $card['unit'] }}</span>@endif
                        </p>

                        @if ($card['days'])
                            <p class="rate-card__days">{{ $card['days'] }}</p>
                        @endif

                        {{-- La tarifa especial, con su precio ENTERO. Los días NO se repiten aquí:
                             van una vez al pie de la sección (regla dura del canvas). --}}
                        @if ($card['special'])
                            <p class="rate-card__special">
                                <b>{{ $card['special'] }}</b>
                                <span>{{ __('landing.rates.special_suffix') }}</span>
                            </p>
                        @endif

                        {{-- ══ LOS COMPLEMENTOS, EN PÍLDORAS ═══════════════════════════════════
                             ❗❗ **La forma es la del artboard 10a y NO la lista de la tarjeta
                             antigua**: cápsula con borde de 1,5, «+ nombre · precio» y la unidad al
                             lado. La lista vieja gastaba un rótulo («COMPLEMENTOS DISPONIBLES»), un
                             enlace «Más info» y una fila por complemento — tres piezas de interfaz
                             para decir que puedes añadir unos calcetines.
                             ⚠️ **El dato es el MISMO** (`LandingAddonPresenter`): aquí no hay un
                             segundo camino para saber qué admite una entrada, solo otra forma.
                             ⚠️⚠️ **Los EXCLUYENTES se agrupan y se dicen**: un `choice_group` son
                             opciones entre las que se elige UNA, y pintarlas sueltas diría que se
                             pueden sumar. Hoy ninguna entrada los usa —son de los packs—, pero la
                             píldora no puede mentir el día que alguien los configure. --}}
                        @php
                            $extras = \App\Domain\Content\Services\LandingAddonPresenter::rows($card['ticket'], false);
                            $sueltos = array_values(array_filter($extras, fn ($r) => $r['group'] === null));
                            $grupos = [];
                            foreach ($extras as $r) {
                                if ($r['group'] !== null) { $grupos[$r['group']][] = $r; }
                            }
                        @endphp
                        @if (! empty($extras))
                            <div class="rate-card__addons">
                                @foreach ($sueltos as $row)
                                    <x-site.addon-pill :row="$row" :unit="$card['unit']" />
                                @endforeach

                                @foreach ($grupos as $miembros)
                                    <span class="rate-card__addon-group">
                                        <span class="rate-card__addon-choose">{{ __('tickets.addon_choose_one') }}</span>
                                        @foreach ($miembros as $row)
                                            <x-site.addon-pill :row="$row" :unit="$card['unit']" />
                                        @endforeach
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- ⚠️⚠️ **El CTA conserva las tres ramas del producto y no se simplifica.**
                             Con la compra online cerrada (`sales.online_enabled=0`, que es como está
                             producción) todas las entradas caen a «Llamar», y una entrada no
                             vendible sin teléfono configurado **no pinta botón**. Reescribirlo aquí
                             para que siempre hubiera un «Reservar» habría ofrecido comprar lo que el
                             flujo no puede vender.
                             ❗ **La ZONA va dentro del rótulo de comprar** (9a): se dice una vez por
                             tarjeta y en el único sitio donde equivocarse cuesta dinero. «Llamar» no
                             la lleva: ese botón no compra nada. --}}
                        <p class="rate-card__cta">
                            @if ($card['sellable'] && $site['sales_online'])
                                <button type="button" class="btn rate-card__btn" @click="$store.purchase.open()">{{ $card['cta'] }}</button>
                            @elseif ($site['has_phone'])
                                <a href="tel:{{ $site['phone_tel'] }}" class="btn rate-card__btn">{{ __('landing.pricing.call') }}</a>
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>

            {{-- **Los días de la tarifa especial, UNA vez por sección.** Regla dura del canvas: un
                 término que se usa en un sitio y se esquiva en otro no se aprende nunca.
                 ⚠️ Sale del rótulo de la tarifa, o sea del panel: aquí no se escribe ningún día. --}}
            @if ($specialLabel && collect($z['cards'])->contains(fn ($c) => $c['special'] !== null))
                <x-site.special-rate-note />
            @endif
        </div>
    @endforeach
</div>
