@props([
    // Los productos cuyos complementos se anuncian. Se deduplican POR ID entre todos ellos.
    'products',
    // Rótulo y frase del bloque: los escribe cada sección, porque dicen cosas distintas
    // («Antes de saltar» en tarifas · «Tu fiesta, tu manera» en cumpleaños).
    'title',
    'lede',
    // Nivel del encabezado. La sección lo decide: dentro de un `<h2>` de sección va un `h3`.
    'level' => 3,
    // ¿Fuera los grupos de elección excluyente (el menú)? `/cumpleanos` los enseña en su propio
    // bloque —«Qué comen»— y el artboard pide que no se repitan aquí (`DECISIONES #528`).
    'withoutChoices' => false,
    // ¿Fuera los complementos que son TIEMPO (la hora extra)? `/precios` los lleva a una fila de su
    // tabla, porque su precio depende del día y la columna lo dice sola (`DECISIONES #531`).
    'withoutTimeExtras' => false,
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
     es adorno de accesibilidad: sin esto una ficha que no cabe en pantalla **no se alcanza sin
     ratón**. Lo dice el propio artboard.
     ⚠️⚠️ **CORRECCIÓN (`#549`): este aviso decía «dentro no hay ningún control, son fichas y no
     botones», y desde hoy es FALSO** — cada ficha con texto lleva su «Más info». El `tabindex` NO se
     retira por eso: los complementos **sin** texto siguen sin control dentro, así que quitarlo
     dejaría justo a esas fichas fuera del recorrido. *Una premisa que deja de ser cierta no tumba la
     conclusión: hay que volver a justificarla.* --}}
@php($extras = \App\Domain\Content\Services\LandingAddonPresenter::unique($products, $withoutChoices, $withoutTimeExtras))

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
                {{-- ¿Este complemento tiene algo que contar? Son los DOS campos del catálogo, porque
                     ninguno de los dos lo llevan todos (el censo está en `LandingAddonPresenter`).
                     ⚠️ **El botón nace solo si hay texto**: un «Más info» que abre el vacío es la
                     misma pieza sin sujeto que `#487` retiró del pliegue de fechas especiales. --}}
                @php($cuenta = $extra['description'] !== null || ! empty($extra['features']))
                <div class="addon-card"@if ($cuenta) x-data="{ info: false }"@endif>
                    {{-- El MARCADOR del complemento, el mismo campo que ya elige el panel
                         (`ticket_types.icon`). ⚠️ `aria-hidden`: su nombre va al lado y un icono que
                         se anunciara lo diría dos veces. --}}
                    <span class="addon-card__ico" aria-hidden="true">
                        <x-dynamic-component :component="'icons.'.$extra['icon']" :width="24" :height="24" />
                    </span>
                    {{-- ⚠️⚠️ **EL CUERPO ES UNA COLUMNA DESDE `#549`** (`[owner]`: «el "más info" lo
                         ponemos en la segunda fila del texto»). La PRIMERA fila es la de siempre
                         —nombre y precio juntos, que es la decisión de `#480` y no se toca—; la
                         segunda es el control, y el texto se despliega debajo.
                         ⚠️ El icono se queda FUERA de la columna y alineado arriba: si entrara
                         dentro, al abrir el texto se iría al centro vertical de la ficha. --}}
                    <div class="addon-card__body">
                    <p class="addon-card__row">
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
                    </p>

                    @if ($cuenta)
                        {{-- ❗❗ **EL «MÁS INFO» ES EL MISMO DEL PRODUCTO, NO UNO NUEVO**: comparte la
                             receta de `.addons__moreinfo`, que ya visten el cajón de compra y los
                             chips de `/servicios`. La clase propia solo ajusta su sitio en la ficha.
                             Inventar aquí un tercer enlace de «más info» es como murió el sistema de
                             sombras de `#196`.
                             ⚠️ `data-tap` porque el control mide ~20 px de alto y el suelo del
                             sistema es 48: el pseudo centrado de `#264` amplía el área **sin mover el
                             dibujo**, que es lo que deja la ficha en dos renglones.
                             ⚠️⚠️ **Y su guarda NO puede vivir en el censo de `TouchTargetTest`**: ese
                             caso exige que la clase se pinte en una ruta real, y el botón depende de
                             un texto que el seeder **no siembra** —ni debe, que es contenido de una
                             instalación (`#490`)—, así que ahí vigilaría el vacío. Lo vigila
                             `LandingAddonsTest`, que se trae su propio sujeto.
                             ⚠️⚠️ **`aria-expanded` SÍ y `aria-controls` NO, y es medido, no pereza**:
                             este bloque se pinta **una vez por zona** (`rate-rail` lo llama dentro de
                             su `@foreach`), así que un `id="addon-info-{id}"` se DUPLICA en cuanto las
                             dos zonas comparten un complemento —los calcetines, hoy— y un `id`
                             repetido rompe la referencia en vez de darla. El texto va justo detrás del
                             botón, que es el patrón de «disclosure» de la guía ARIA, donde
                             `aria-controls` es opcional y está mal soportado. --}}
                        <button type="button" class="addons__moreinfo addon-card__more" data-tap
                                @click="info = ! info" :aria-expanded="info">{{ __('tickets.addon_more_info') }}</button>
                        {{-- ⚠️ `x-cloak` para que el texto no asome en el primer pintado, antes de que
                             Alpine lo pliegue. Sin JavaScript **queda abierto**, que es el suelo
                             correcto: el dato se lee igual y la ficha solo sale más alta. --}}
                        <div class="addon-card__info" x-show="info" x-cloak>
                            @if ($extra['description'])
                                <p class="addon-card__desc">{{ $extra['description'] }}</p>
                            @endif
                            @if (! empty($extra['features']))
                                {{-- La lista de ventajas es la del producto (`.addons__features`),
                                     con su ✓: el mismo molde que el cajón y los chips. --}}
                                <ul class="addons__features">
                                    @foreach ($extra['features'] as $feature)
                                        <li>{{ $feature }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ══ LAS FLECHAS ═══════════════════════════════════════════════════════════════════════
             `[owner]`: *«le ponemos unas flechas para que el usuario sepa que hay que hacer slide, y
             si no puede hacer slide con móvil entonces por accesibilidad necesitamos unas flechas»*.

             ❗❗ **No se pintan solas: las enciende el MISMO hecho que las velas.** `ui/rail-sails.js`
             marca el envoltorio con `data-rail-scroll` cuando su carril no cabe, y de ahí cuelgan las
             dos cosas — la vela que dice «hay más» y las flechas que lo recorren. Un carril que cabe
             entero (escritorio con dos complementos, medido en `#498`) **no enseña flechas**: serían
             96 px de control para no llevar a ninguna parte, la regla de `#487`.
             ▶ Y eso da el suelo sin JavaScript **gratis y del lado seguro**: sin JS no hay atributo,
             así que no hay flechas — nunca dos botones muertos.

             ❗❗ **VAN EN LOS LATERALES Y DENTRO DEL ENVOLTORIO** (`[owner]`: «ponlo en los
             laterales»), que es la única forma de que floten sobre los cantos del carril: el
             envoltorio es la caja que mide exactamente lo que mide el carril —y la que ya está
             `position: relative` para sus velas—, mientras `.addons-rail` incluye rótulo y entradilla,
             así que centrarse en ÉL dejaría las flechas a media altura del bloque entero.
             ⚠️ Van DESPUÉS del carril en el marcado aunque se pinten encima: un control se anuncia tras
             el contenido que recorre.

             ⚠️ **Sin `aria-controls`, por el mismo motivo que el «Más info»**: el bloque se repite por
             zona y un `id` fijo se duplicaría. El rótulo de cada flecha ya dice qué recorre. --}}
        <div class="addons-rail__nav" data-rail-nav>
            <button type="button" class="rail-arrow" data-rail-prev
                    aria-label="{{ __('landing.rates.addon_prev') }}">
                <span aria-hidden="true">&larr;</span>
            </button>
            <button type="button" class="rail-arrow" data-rail-next
                    aria-label="{{ __('landing.rates.addon_next') }}">
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
        </div>{{-- /.addons-rail__wrap --}}
    </div>
@endif
