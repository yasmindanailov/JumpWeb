@props([
    // Los productos cuyos complementos se anuncian. Se deduplican POR ID entre todos ellos.
    'products',
    // Rótulo y frase del bloque: los escribe cada sección, porque dicen cosas distintas
    // («Antes de saltar» en tarifas · «Tu fiesta, tu manera» en cumpleaños).
    'title',
    'lede',
    // Nivel del encabezado. La sección lo decide: dentro de un `<h2>` de sección va un `h3`.
    'level' => 3,
])

{{-- ══ EL BLOQUE DE COMPLEMENTOS · UN SOLO MOLDE PARA LAS DOS SECCIONES ══════════════════════════
     `Precios PJP` 13 · `Cumpleanos Pagina PJP` 5a · `[DECIDIDO owner, 2026-09-09]` y confirmado en
     `#483`: *«es el mismo formato y diseño que los complementos de las entradas; simplemente
     mostramos los complementos disponibles para los cumpleaños sin repetirse»*.

     ▶ **Nace extrayéndolo de `<x-site.rate-rail>`, donde `#480` lo escribió.** No es refactor por
     gusto: la sección 04 pide EXACTAMENTE la misma pieza con otros productos dentro, y con dos
     copias el día que alguien cambie el signo, la unidad o el «desde» de una, la portada publicará
     dos moldes distintos a media pantalla de distancia **sin que nada falle**.

     ❗❗❗ **Salen de la tarjeta porque LOS COMPARTEN CASI TODAS**, y hay una regla del sistema que lo
     pide: *«una comparativa solo compara lo que difiere; lo común va a su propio bloque»*.

     ⚠️⚠️ **Son los de los productos QUE SE VEN**, y por eso el llamante decide el alcance: en
     tarifas el bloque vive DENTRO del panel de cada zona —«Hora extra · KIDS» y «Hora extra · JUMP»
     son dos productos con dos precios, y un bloque único tendría que enseñar los dos o elegir uno—;
     en cumpleaños los dos packs se ven a la vez, así que el bloque es uno y lleva los de ambos.
     ⚠️ **Sin repetir, y la deduplicación es por ID y nunca por nombre**: en otra instalación dos
     complementos distintos pueden llamarse igual, y fundirlos por rótulo publicaría el precio de uno
     bajo el nombre del otro.

     ❗❗ **EL CARRIL LLEVA EL FOCO DEL TECLADO** (`tabindex="0"`, `role="group"`, `aria-label`), y no
     es adorno de accesibilidad: **dentro no hay ningún control** —son fichas, no botones—, así que
     sin esto la segunda ficha **no se alcanza sin ratón**. Lo dice el propio artboard. --}}
@php($extras = \App\Domain\Content\Services\LandingAddonPresenter::unique($products))

@if (! empty($extras))
    <div class="addons-rail">
        <h{{ $level }} class="addons-rail__title">{{ $title }}</h{{ $level }}>
        <p class="addons-rail__lede">{{ $lede }}</p>

        {{-- ❗❗ **EL ENVOLTORIO EXISTE PARA LAS VELAS, y no puede ser el propio carril**: un
             pseudo-elemento dentro de un contenedor con scroll **viaja con el contenido** y se iría
             hacia la izquierda en cuanto alguien deslizara. Es la misma razón por la que la vela del
             pie vive en `.foot__links-wrap` y no en la fila (`#252`). --}}
        <div class="addons-rail__wrap">
        <div class="addons-rail__track" tabindex="0" role="group" aria-label="{{ $title }}">
            @foreach ($extras as $extra)
                <div class="addon-card">
                    {{-- El MARCADOR del complemento, el mismo campo que ya elige el panel
                         (`ticket_types.icon`). ⚠️ `aria-hidden`: su nombre va al lado y un icono que
                         se anunciara lo diría dos veces. --}}
                    <span class="addon-card__ico" aria-hidden="true">
                        <x-dynamic-component :component="'icons.'.$extra['icon']" :width="24" :height="24" />
                    </span>
                    {{-- ⚠️ **Nombre y precio en la MISMA fila** (`[owner]`): la ficha era de dos y
                         quedaba alta para lo que dice. El nombre se estira y el precio se queda
                         pegado al canto, sin partirse. --}}
                    <span class="addon-card__name">{{ $extra['name'] }}</span>
                    {{-- ❗❗ **El «+» y la UNIDAD, para que el precio no se confunda con el del
                         producto** (`[owner]`). El signo es honesto aquí y no lo era en la tarifa
                         especial: un complemento **se suma** a lo que compras, mientras que la
                         especial es un precio ALTERNATIVO — por eso aquélla va entera y éste con
                         signo.
                         ⚠️⚠️ **La unidad sale del PIVOTE, no se escribe**: `per_guest` dice «por
                         invitado» y `fixed` dice «cada uno». Poner «por persona» en un complemento
                         `fixed` sería FALSO — de ésos se elige cantidad, no se cobra uno por
                         cabeza. --}}
                    <span class="addon-card__price">
                        @if ($extra['price'])
                            {{-- ⚠️ «desde» cuando el precio cambia según el día: sin él, el bloque
                                 anunciaría el más barato como si fuera el único, y el checkout
                                 cobraría otro. --}}
                            @if ($extra['varies']){{ __('landing.rates.from') }} @endif+{{ $extra['price'] }}&nbsp;€<span class="addon-card__unit">{{ $extra['perGuest'] ? __('landing.rates.addon_per_guest') : __('landing.rates.addon_each') }}</span>
                        @elseif ($extra['badge'])
                            {{ __('tickets.addon_badge_'.$extra['badge']) }}
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
        </div>
    </div>
@endif
