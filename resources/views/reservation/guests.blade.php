@use('App\Domain\Booking\Models\TicketType')
@php
    // Atributos del <input> de cada columna por-niño, resueltos UNA vez. La pregunta «¿este tipo se
    // escribe con dígitos?» la contesta el DOMINIO (`TicketType::isNumericFieldType`) y no un
    // literal repetido en cada plantilla; la EDAD además lleva cota, porque de ese dato sale un
    // cobro (`docs/specs/cumple-mixto.md` §8.6).
    $guestInput = [];
    foreach ($guestFields as $f) {
        $isAge = ($f['type'] ?? null) === TicketType::FIELD_TYPE_AGE;
        $numeric = TicketType::isNumericFieldType($f['type'] ?? null);
        $guestInput[$f['key']] = [
            'type' => $numeric ? 'number' : 'text',
            'min' => $numeric ? ($isAge ? TicketType::GUEST_AGE_MIN : 0) : null,
            'max' => $isAge ? TicketType::GUEST_AGE_MAX : null,
        ];
    }

    // Claves de los campos por-niño OBLIGATORIOS → una ficha está "completa" cuando todas están
    // rellenas (espejo de TicketType::guestDataCompletedCount; vacío de requeridas ⇒ completa). El
    // primer campo se usa como "nombre" mostrado en la cabecera de la ficha.
    $requiredKeys = collect($guestFields)->where('required', true)->pluck('key')->all();
    $nameKey = $guestFields[0]['key'] ?? null;
    $emptyLabel = __('guestform.name_empty');
@endphp
<x-focused-layout :title="__('guestform.title')">
    <div class="gf-page">
        {{-- Marca pequeña (no es un nav).

             ⚠️ **El logotipo entra por el MISMO hueco que el del armazón** (el componente
             `site.brand`,
             `DECISIONES #143`): el fichero es del cliente, no se versiona, y si no está **el suelo
             sigue siendo el nombre en la fuente de rótulo**. Un logotipo es marca, y la marca no
             vive en este repo.

             ⚠️ Aquí va como `<img>` y NO en línea, al revés que el armazón: aquello se inlina para
             poder ANIMAR una pieza concreta del dibujo (`#254`), y esta página no tiene coreografía
             — pagar ~64 KB de marcado por una imagen quieta sería el coste sin la razón.

             ⚠️⚠️ **Ni el nombre de un componente ni una directiva se escriben LITERALES en un
             comentario de Blade**: se compilan igual. Un `x-site.brand` entre ángulos aquí abre un
             componente que nadie cierra y la plantilla muere con un error que señala a otra línea —
             es la trampa de `DECISIONES #307`, en la que ya cayó el comentario escrito para
             advertirla.

             ⚠️⚠️ **Y la forma de BLOQUE no es estilo: es lo único que funciona AQUÍ.** Este fichero
             tiene un `@php…@endphp` más abajo, y la forma con paréntesis se empareja con ESE cierre
             —**200 líneas sin compilar**, con el error señalando a otra línea (`#298`)—. La de
             paréntesis solo es segura después del último bloque, que es donde ya se usa.

             ⚠️ `filemtime` con arroba resuelve existencia y cache-busting en una sola llamada. --}}
        @php $clientLogo = @filemtime(public_path('img/client-logo.svg')); @endphp
        <div class="gf-mark">
            @if ($clientLogo)
                <img class="gf-mark__logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
                     alt="{{ $site['name'] ?? config('app.name') }}" />
            @else
                <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            @endif
            <span class="gf-mark__sub">{{ __('guestform.eyebrow') }}</span>
        </div>

        <main class="gf-sheet">
            {{-- ───── Contexto de la reserva (stub tipo ticket) ───── --}}
            <div class="gf-stub">
                <div class="gf-stub__top">
                    {{-- Badge = nombre del producto (data-driven), recto. --}}
                    <span class="gf-stub__badge">{{ $type->tr('name') }}@if ($type->duration_min) · {{ $type->duration_min }} min @endif</span>
                </div>

                <h1 class="gf-stub__title">{{ __('guestform.heading') }}</h1>
                <p class="gf-stub__lede">{{ __('guestform.subtitle') }}</p>

                <div class="gf-stub__meta">
                    @if ($reservation->slot)
                        <div class="gf-stub__cell">
                            <span class="k">{{ __('guestform.fact_when') }}</span>
                            <span class="v">{{ \App\Domain\Platform\Services\DisplayTime::dayLabel($reservation->slot->date) }} · {{ $reservation->displayTimeWindow() }}</span>
                        </div>
                    @endif
                    <div class="gf-stub__cell">
                        <span class="k">{{ __('guestform.fact_guests') }}</span>
                        <span class="v">{{ __('tickets.guests_count', ['count' => $reservation->quantity]) }}</span>
                    </div>
                    <div class="gf-stub__cell">
                        <span class="k">{{ __('guestform.fact_ref') }}</span>
                        <span class="v mono">{{ $order->code }}</span>
                    </div>
                </div>

                {{-- Progreso. El texto i18n completo va en un status accesible (fuente de verdad del
                     servidor); el medidor visual lo refleja. --}}
                @if ($progress['total'] > 0)
                    <p class="gf-sr-only" role="status">
                        {{ $progress['done'] >= $progress['total']
                            ? __('guestform.progress_complete', ['total' => $progress['total']])
                            : __('guestform.progress', ['done' => $progress['done'], 'total' => $progress['total']]) }}
                    </p>
                    <div class="gf-meter">
                        <div class="gf-meter__head">
                            <span class="gf-meter__label">{{ __('guestform.meter_label') }}</span>
                            <span class="gf-meter__count"><em id="gf-meter-done">{{ $progress['done'] }}</em> / <span>{{ $progress['total'] }}</span></span>
                        </div>
                        <div class="gf-meter__track">
                            <div class="gf-meter__bar" id="gf-meter-bar" style="width: {{ $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Fiesta MIXTA (`docs/specs/cumple-mixto.md` §14): se le dice al cliente EN EL
                     SITIO donde declara las edades, que es lo que el owner pidió como
                     «transparente y fácil de entender».
                     ⚠️ Aparece SIEMPRE que la fiesta sea mixta, tenga o no cargo. Antes solo salía
                     con dinero de por medio, y eso dejaba al cliente viendo una etiqueta «MIXTA» en
                     su pedido sin una línea que la explicara — medido sobre un pedido real.
                     ⚠️ El IMPORTE sale de lo ESCRITO en su pedido; la EXPLICACIÓN, del veredicto. --}}
                @if ($ageSurcharge['charge_cents'] > 0 || $ageSurcharge['credit_cents'] > 0)
                    {{-- ⚠️⚠️ **Con dinero escrito, TODO sale de lo escrito**, también la explicación.
                         Componerla con los precios de hoy la hacía contradecir al importe en cuanto
                         el parque retocaba una tarifa —«Jump a 30,00 € en vez de Kids a 18,00 €»
                         encima de un suplemento de 7,00 €—, y el cliente que hiciera la resta
                         tendría razón. Se pinta aunque el veredicto ya no se pueda derivar (alguien
                         retiró la familia): mientras lo deba, tiene derecho a leer por qué.
                         ▶ T4 (§24.5): el DESCUENTO es una línea más de lo escrito, con su frase
                         compuesta por el dominio, y el total es el NETO — que puede ser a favor. --}}
                    <div class="gf-mix" role="status">
                        <p class="gf-mix__title">{{ __('guestform.mixed_title') }}</p>
                        @foreach ($ageSurcharge['lines'] as $line)
                            <p class="gf-mix__text">
                                {{ __('guestform.mixed_line_written', [
                                    'count' => $line['count'],
                                    'target' => $line['name'],
                                    'unit' => \App\Domain\Platform\Services\Money::format($line['unit']),
                                ]) }}
                            </p>
                        @endforeach
                        @if ($ageSurcharge['credit'] !== null)
                            <p class="gf-mix__text">
                                {{ $ageSurcharge['credit']['label'] }}: −{{ \App\Domain\Platform\Services\Money::format($ageSurcharge['credit']['cents']) }}
                            </p>
                        @endif
                        <p class="gf-mix__text">
                            @if ($ageSurcharge['cents'] > 0)
                                {{ __('guestform.mixed_surcharge', ['amount' => \App\Domain\Platform\Services\Money::format($ageSurcharge['cents'])]) }}
                            @elseif ($ageSurcharge['cents'] < 0)
                                {{ __('guestform.mixed_discount_total', ['amount' => \App\Domain\Platform\Services\Money::format(-$ageSurcharge['cents'])]) }}
                            @else
                                {{ __('guestform.mixed_net_zero') }}
                            @endif
                        </p>
                    </div>
                @elseif ($ageMix->mixed)
                    {{-- Sin dinero escrito manda el veredicto de hoy: es información, no una deuda. --}}
                    <div class="gf-mix" role="status">
                        <p class="gf-mix__title">{{ __('guestform.mixed_title') }}</p>
                        @foreach ($ageMix->upgrades as $up)
                            <p class="gf-mix__text">
                                {{ __('guestform.mixed_line', [
                                    'count' => $up['count'],
                                    'target' => $up['name'],
                                    'target_price' => $up['target_price_cents'] === null ? '—' : \App\Domain\Platform\Services\Money::format($up['target_price_cents']),
                                    'booked' => $type->tr('name'),
                                    'booked_price' => $ageMix->basePriceCents === null ? '—' : \App\Domain\Platform\Services\Money::format($ageMix->basePriceCents),
                                ]) }}
                            </p>
                        @endforeach
                        <p class="gf-mix__text">
                            @if ($ageMix->hasSavings())
                                {{-- El veredicto aún no gobierna (faltan edades por declarar): se
                                     anuncia que el descuento llegará al completar, no que «no se
                                     descuenta solo» — eso CADUCÓ con la T4 (`[DECIDIDO owner]` D5). --}}
                                {{ __('guestform.mixed_savings_pending', ['amount' => \App\Domain\Platform\Services\Money::format($ageMix->savingsCents)]) }}
                            @else
                                {{ __('guestform.mixed_no_difference') }}
                            @endif
                        </p>
                    </div>
                @endif

                {{-- El dinero solo se mueve al guardar con TODAS las edades (`#285` §20.6): con cargo
                     escrito y edades en blanco, el cliente tiene que saber que está congelado — antes
                     el congelado era silencioso (lo cazó el T0). --}}
                @if ($frozenMissingAges > 0)
                    <div class="gf-mix" role="status">
                        {{-- `trans_choice`: «Faltan 1 edades» es el «1 invitadoS» de `#247`. --}}
                        <p class="gf-mix__text">{{ trans_choice('guestform.frozen_missing_ages', $frozenMissingAges, ['count' => $frozenMissingAges]) }}</p>
                    </div>
                @endif

                {{-- Una edad SIN PRODUCTO (`#284` D6, §22.5): no es un hueco de configuración, es «no
                     hay producto para esa edad en tus condiciones». Un texto por caso, el del parque
                     si lo escribió, con su teléfono. --}}
                @if ($noProductNotices !== [])
                    <div class="gf-mix" role="alert">
                        <p class="gf-mix__title">{{ __('guestform.no_product_title') }}</p>
                        @foreach ($noProductNotices as $notice)
                            <p class="gf-mix__text">{{ $notice }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="gf-perf"></div>

            <form method="POST" action="{{ $formAction }}" class="gf-form" id="gf-form">
                @csrf
                {{-- El TESTIGO de la reserva: si el parque la movió mientras el cliente tenía la
                     pantalla abierta, el servidor rechaza el envío entero en vez de dejar que pise
                     su trabajo (§4.8·5). --}}
                <input type="hidden" name="expected_version" value="{{ $version }}">

                {{-- Aviso: privacidad (editable) o solo-lectura (evento ya celebrado). Componentes existentes. --}}
                @if ($readonly)
                    <div class="guestform__readonly" role="status">{{ __('guestform.readonly_notice') }}</div>
                @else
                    <p class="guestform__privacy">{{ __('guestform.privacy') }}</p>
                @endif

                {{-- ───── 00 · Datos generales (event_fields fase postform, data-driven) ───── --}}
                @if (count($generalFields))
                    <section class="gf-group">
                        <div class="gf-group__head">
                            <h2 class="gf-group__title">{{ __('guestform.general_heading') }}</h2>
                        </div>
                        <div class="eventfields">
                            @foreach ($generalFields as $field)
                                <label class="eventfields__field" for="g-{{ $field['key'] }}">
                                    <span class="eventfields__label">{{ $type->eventFieldLabel($field) }}@if ($field['required']) <span class="eventfields__req" aria-hidden="true">*</span>@endif</span>
                                    @if ($field['type'] === 'textarea')
                                        <textarea id="g-{{ $field['key'] }}" name="general[{{ $field['key'] }}]" rows="2" @disabled($readonly)>{{ $reservation->event_data[$field['key']] ?? '' }}</textarea>
                                    @else
                                        <input id="g-{{ $field['key'] }}" type="{{ TicketType::isNumericFieldType($field['type']) ? 'number' : 'text' }}" @if (TicketType::isNumericFieldType($field['type'])) min="0" inputmode="numeric" @endif name="general[{{ $field['key'] }}]" value="{{ $reservation->event_data[$field['key']] ?? '' }}" @disabled($readonly)>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ───── 00.bis · EXTRAS de venta posterior (`specs/complementos-post-reserva.md`) ─────

                     La tercera zona del formulario, y la única que mueve DINERO: lo que se añade aquí
                     sube el total de la reserva y **se paga en el parque** (`DECISIONES #244`).

                     ⚠️⚠️ **El suelo es un `<input type="number">`, no un stepper.** Esta página es de
                     mejora progresiva declarada —nace `no-js` y el JS la enciende al final—, así que
                     un +/− hecho a botones dejaría a quien no tiene JavaScript **sin poder comprar**.
                     El JS lo decora; el importe lo recalcula siempre el servidor (`PAY-12`).

                     ⚠️ Se pintan también los CERRADOS, con su motivo: quien pidió tapas hace dos
                     semanas tiene que seguir viéndolas, o creería que se han perdido. --}}
                @if (count($addons))
                    <section class="gf-group gf-extras" id="gf-extras">
                        <div class="gf-group__head">
                            <h2 class="gf-group__title">{{ __('guestform.extras_heading') }}</h2>
                        </div>
                        <p class="gf-extras__lead">{{ __('guestform.extras_lead') }}</p>

                        <ul class="gf-extras__list">
                            @foreach ($addons as $i => $addon)
                                <li class="gf-extra {{ $addon->closed ? 'is-closed' : '' }}">
                                    <div class="gf-extra__id">
                                        <span class="gf-extra__name">{{ $addon->productName }}</span>
                                        <span class="gf-extra__price">{{ $addon->note }}</span>

                                        {{-- El «Más info» del catálogo (`#416`). ⚠️ Es un `<details>`
                                             NATIVO, no el toggle de Alpine que usa la landing
                                             (`addon-chip.blade.php`): esta página nace `no-js` por
                                             decisión declarada, y con Alpine quien no tenga
                                             JavaScript se quedaría **sin poder leer qué lleva lo que
                                             está comprando**. Aquí no es adorno — estos extras se
                                             eligen en esta pantalla y en ninguna otra. --}}
                                        @if ($addon->features !== [])
                                            <details class="gf-extra__more">
                                                {{-- El chevron sale del SET de iconos (`#257`), no de
                                                     bordes rotados a mano: un dibujo propio aquí iría
                                                     en otra rejilla y con otro trazo que el resto de
                                                     la página, y eso no falla — solo se nota mirando
                                                     la pantalla entera. Esta misma vista ya lo usa
                                                     para plegar la ficha de cada invitado. --}}
                                                <summary>{{ __('tickets.addon_more_info') }}<x-icons.chevron-down :width="14" :height="14" /></summary>
                                                <ul>
                                                    @foreach ($addon->features as $feature)
                                                        <li>{{ $feature }}</li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </div>

                                    {{-- El id viaja SIEMPRE, también en los cerrados: si no, un envío
                                         normal llegaría sin ellos y el servidor no sabría que el
                                         cliente no los estaba tocando. --}}
                                    <input type="hidden" name="addons[{{ $i }}][product_id]" value="{{ $addon->productId }}">

                                    @if ($addon->closed || $readonly)
                                        <input type="hidden" name="addons[{{ $i }}][quantity]" value="{{ $addon->quantity }}">
                                        <span class="gf-extra__qty" aria-live="polite">{{ $addon->quantity }}</span>
                                        <span class="gf-extra__why">
                                            {{ $addon->closedReason === \App\Domain\Booking\Contracts\PostFormAddonView::REASON_SOLD_AT_BOOKING
                                                ? __('guestform.extras_closed_sold')
                                                : __('guestform.extras_closed_cutoff') }}
                                        </span>
                                    @else
                                        <label class="gf-extra__field" for="x-{{ $addon->productId }}">
                                            <span class="sr-only">{{ __('guestform.extras_qty_label', ['name' => $addon->productName]) }}</span>
                                            <input
                                                id="x-{{ $addon->productId }}"
                                                type="number"
                                                name="addons[{{ $i }}][quantity]"
                                                value="{{ $addon->quantity }}"
                                                min="0"
                                                max="{{ $addon->maxQuantity }}"
                                                step="1"
                                                inputmode="numeric"
                                                data-extra-price="{{ $addon->unitPriceCents }}"
                                            >
                                        </label>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <p class="gf-extras__total">
                            <span>{{ __('guestform.extras_total') }}</span>
                            <strong id="gf-extras-total" data-extras-total>{{ $extrasTotal }}</strong>
                        </p>
                        <p class="gf-extras__where">{{ __('guestform.extras_where') }}</p>
                    </section>
                @endif

                {{-- ───── 01 · Ficha de cada invitado (acordeón; una ficha por niño, data-driven) ───── --}}
                <section class="gf-group">
                    <div class="gf-group__head">
                        <h2 class="gf-group__title">{{ __('guestform.children_heading') }}</h2>
                        <span class="gf-group__opt" id="gf-group-count">{{ $progress['done'] }} / {{ (int) $reservation->quantity }}</span>
                    </div>

                    @unless ($readonly)
                        <div class="gf-bulk">
                            <span>{{ __('guestform.bulk_prompt') }}</span>
                            <button type="button" id="gf-open-pending" class="btn btn--ghost btn--sm">{{ __('guestform.bulk_action') }}</button>
                        </div>
                    @endunless

                    <div class="gf-fiches" id="gf-fiches">
                        @for ($i = 0; $i < $reservation->quantity; $i++)
                            @php
                                // Una ficha con edad SIN PRODUCTO no está completa aunque tenga todas
                                // sus columnas (D6): el servidor manda, y el JS lo respeta por `data-no-product`.
                                $noProduct = in_array($i, $noProductIndexes, true);
                                $complete = ! $noProduct && collect($requiredKeys)->every(fn ($k) => filled($rows[$i][$k] ?? null));
                                $headName = $nameKey ? trim((string) ($rows[$i][$nameKey] ?? '')) : '';
                            @endphp
                            <div class="gf-fiche {{ $complete ? 'is-complete' : '' }}" data-i="{{ $i }}" @if ($noProduct) data-no-product="1" @endif>
                                <button type="button" class="gf-fiche__head" data-act="toggle" aria-expanded="false" aria-controls="gf-body-{{ $i }}">
                                    <span class="gf-fiche__cube">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="gf-fiche__who">
                                        <span class="gf-fiche__role">{{ __('guestform.child', ['n' => $i + 1]) }}</span>
                                        <span class="gf-fiche__name {{ $headName === '' ? 'is-empty' : '' }}" data-name data-empty="{{ $emptyLabel }}">{{ $headName !== '' ? $headName : $emptyLabel }}</span>
                                    </span>
                                    {{-- El régimen que le toca a ESTE niño por su edad
                                         (`docs/specs/cumple-mixto.md` §15). Informativo: dice a qué
                                         pack de la familia pertenece, y se marca cuando NO es el
                                         reservado, que es el que mueve el precio de la fiesta.
                                         ⚠️ Refleja lo GUARDADO: se actualiza al guardar, no al
                                         teclear — el veredicto es del servidor (`CE-4`). --}}
                                    @php($regime = $guestRegimes[$i] ?? null)
                                    @if ($regime !== null && $regime['state'] === \App\Domain\Booking\Services\GuestAgeMixReader::ROW_OK)
                                        <span @class(['gf-fiche__regime', 'is-other' => ! $regime['own']])>{{ $regime['name'] }}</span>
                                    @elseif ($regime !== null && $regime['state'] === \App\Domain\Booking\Services\GuestAgeMixReader::ROW_OUT_OF_RANGE)
                                        <span class="gf-fiche__regime is-unknown">{{ __('guestform.regime_no_product') }}</span>
                                    @endif
                                    <span class="gf-fiche__status gf-fiche__status--pending">{{ __('guestform.status_pending') }}</span>
                                    <span class="gf-fiche__status gf-fiche__status--done">{{ __('guestform.status_done') }}</span>
                                    <span class="gf-fiche__chev" aria-hidden="true">
                                        <x-icons.chevron-down :width="16" :height="16" />
                                    </span>
                                </button>

                                <div class="gf-fiche__body" id="gf-body-{{ $i }}">
                                    <div>
                                        <div class="gf-fiche__pad">
                                            <div class="eventfields">
                                                @foreach ($guestFields as $idx => $field)
                                                    <label class="eventfields__field" for="c-{{ $i }}-{{ $field['key'] }}">
                                                        <span class="eventfields__label">{{ $type->guestFieldLabel($field) }}@if ($field['required']) <span class="eventfields__req" aria-hidden="true">*</span>@endif</span>
                                                        @if ($field['type'] === 'textarea')
                                                            <textarea id="c-{{ $i }}-{{ $field['key'] }}" name="guests[{{ $i }}][{{ $field['key'] }}]" rows="2" @if ($field['required']) data-required @endif @if ($idx === 0) data-name-input @endif @disabled($readonly)>{{ $rows[$i][$field['key']] ?? '' }}</textarea>
                                                        @else
                                                            <input id="c-{{ $i }}-{{ $field['key'] }}" type="{{ $guestInput[$field['key']]['type'] }}" @if ($guestInput[$field['key']]['min'] !== null) min="{{ $guestInput[$field['key']]['min'] }}" inputmode="numeric" @endif @if ($guestInput[$field['key']]['max'] !== null) max="{{ $guestInput[$field['key']]['max'] }}" @endif name="guests[{{ $i }}][{{ $field['key'] }}]" value="{{ $rows[$i][$field['key']] ?? '' }}" @if ($field['required']) data-required @endif @if ($idx === 0) data-name-input @endif @disabled($readonly)>
                                                        @endif
                                                    </label>
                                                @endforeach
                                            </div>

                                            @unless ($readonly)
                                                <div class="gf-fiche__foot">
                                                    <button type="button" class="btn btn--ghost btn--sm gf-nav-prev" data-act="prev" @if ($i === 0) disabled @endif>
                                                        <x-icons.arrow-left :width="13" :height="13" />{{ __('guestform.nav_prev') }}
                                                    </button>
                                                    <button type="button" class="btn btn--sm" data-act="next" @if ($i === $reservation->quantity - 1) disabled @endif>
                                                        {{ $i === $reservation->quantity - 1 ? __('guestform.nav_last') : __('guestform.nav_next') }}<x-icons.arrow-right :width="13" :height="13" />
                                                    </button>
                                                </div>
                                            @endunless
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </section>

                {{-- ───── Barra de guardar (botón = componente .btn del sitio) ───── --}}
                @unless ($readonly)
                    <div class="gf-savebar">
                        {{-- ⚠️⚠️ **El DISQUETE se retira y no se sustituye** (`#258`). Tres motivos, y
                             el tercero decide: el set del artboard no dibuja «guardar» y no había
                             equivalente; un disquete es el único anacronismo que quedaba en toda la
                             web; y §06 del artboard dice «máximo un icono por fila de texto — si
                             hacen falta tres, lo que falta es una lista». Este botón ya dice
                             «Guardar» con todas sus letras, así que el icono no informaba: decoraba.
                             ▶ Si el owner lo quiere de vuelta, es una línea y un glifo del
                             diseñador. --}}
                        <button type="submit" class="btn btn--lg">
                            {{ __('guestform.submit') }}
                        </button>
                        <p class="gf-savebar__help">{{ __('guestform.hint') }}</p>
                    </div>
                @endunless
            </form>
        </main>

        {{-- Footer mínimo, DATA-DRIVEN (mismas fuentes que el footer del sitio): nombre del negocio +
             texto de derechos editable (con fallback i18n) + teléfono; etiqueta «Privacidad» de
             `landing.footer.legal` (la misma que usa el footer principal). --}}
        @php($gfLegal = (array) __('landing.footer.legal'))
        <footer class="gf-foot">
            <span>© {{ date('Y') }} {{ \Illuminate\Support\Str::upper($site['name'] ?? config('app.name')) }} — {{ $site['footer_rights'] ?? __('landing.footer.rights') }}</span>
            <span class="gf-foot__links">
                <a href="{{ route('legal.privacidad') }}">{{ $gfLegal[1] ?? __('guestform.footer_privacy') }}</a>
                @if (! empty($site['phone']))
                    <a href="tel:{{ preg_replace('/\s+/', '', $site['phone']) }}">{{ $site['phone'] }}</a>
                @endif
            </span>
        </footer>
    </div>

    {{-- Toast de guardado (flash del servidor; relevante al recargar por enlace firmado). --}}
    <div class="gf-toast {{ session('status') === 'guest-form-saved' ? 'is-on' : '' }}" id="gf-toast" role="status">
        {{-- ⚠️ CUADRADO: el check pasó a la rejilla 24 del set y 12×10 ya no encoge, DEFORMA. --}}
        <span class="tcheck"><x-icons.check :width="12" :height="12" /></span>
        <span>{{ __('guestform.toast_saved') }}</span>
    </div>

    {{-- ⚠️⚠️ Cuando algo de los EXTRAS no se pudo aplicar, **se dice y no se calla**: los datos del
         formulario SÍ se guardaron (van por otra transacción, §4.5.3) y el aviso lo separa. Callarlo
         sería que quien creyó pedir tapas se entere en la puerta del parque. Va como aviso PERSISTENTE
         y no como toast: es una noticia, no una confirmación. --}}
    @if (in_array(session('status'), ['guest-form-extras-blocked', 'guest-form-stale'], true))
        <div class="guestform__readonly gf-extras__warn" role="alert">
            {{ session('status') === 'guest-form-stale'
                ? __('guestform.extras_stale')
                : __('guestform.extras_blocked') }}
        </div>
    @endif

    {{-- Acordeón (JS plano; mejora progresiva: sin JS las fichas salen abiertas y el form funciona). --}}
    <script>
        (function () {
            'use strict';
            var de = document.documentElement;
            var form = document.getElementById('gf-form');
            var fichesEl = document.getElementById('gf-fiches');
            if (!form || !fichesEl) return; // sin form/fichas → se queda en no-js (todo abierto y usable)

            try {
            var fiches = Array.prototype.slice.call(fichesEl.querySelectorAll('.gf-fiche'));
            var total = fiches.length;
            var meterDone = document.getElementById('gf-meter-done');
            var meterBar = document.getElementById('gf-meter-bar');
            var groupCount = document.getElementById('gf-group-count');

            function isComplete(fiche) {
                // Una edad SIN PRODUCTO la decide el SERVIDOR (D6): mientras esté, la ficha no se da
                // por completa aunque tenga todas sus columnas. Se recalcula al guardar.
                if (fiche.getAttribute('data-no-product') === '1') return false;
                var req = fiche.querySelectorAll('[data-required]');
                for (var i = 0; i < req.length; i++) {
                    if (req[i].value.trim() === '') return false;
                }
                return true; // vacuosamente completa si no hay campos obligatorios
            }

            function setOpen(idx) {
                fiches.forEach(function (f) {
                    var on = (+f.getAttribute('data-i') === idx);
                    f.classList.toggle('is-open', on);
                    var head = f.querySelector('.gf-fiche__head');
                    if (head) head.setAttribute('aria-expanded', on ? 'true' : 'false');
                    // a11y: la ficha colapsada sale del orden de foco / árbol de accesibilidad (#264-audit).
                    // `inert` NO desactiva el envío del formulario (los valores siguen yendo en el POST).
                    var body = f.querySelector('.gf-fiche__body');
                    if (body) body.inert = !on;
                });
            }

            function refresh(fiche) {
                fiche.classList.toggle('is-complete', isComplete(fiche));
                var nameEl = fiche.querySelector('[data-name]');
                var nameInput = fiche.querySelector('[data-name-input]');
                if (nameEl && nameInput) {
                    var val = nameInput.value.trim();
                    nameEl.textContent = val !== '' ? val : (nameEl.getAttribute('data-empty') || '');
                    nameEl.classList.toggle('is-empty', val === '');
                }
            }

            function updateProgress() {
                var done = 0;
                fiches.forEach(function (f) { if (isComplete(f)) done++; });
                if (meterDone) meterDone.textContent = done;
                if (meterBar) meterBar.style.width = (total ? (done / total * 100) : 0) + '%';
                if (groupCount) groupCount.textContent = done + ' / ' + total;
            }

            fichesEl.addEventListener('click', function (e) {
                var act = e.target.closest('[data-act]');
                var fiche = e.target.closest('.gf-fiche');
                if (!fiche || !act) return;
                var i = +fiche.getAttribute('data-i');
                var a = act.getAttribute('data-act');
                if (a === 'toggle') {
                    setOpen(fiche.classList.contains('is-open') ? -1 : i);
                } else if (a === 'prev' && i > 0) {
                    setOpen(i - 1);
                } else if (a === 'next' && i < total - 1) {
                    setOpen(i + 1);
                }
            });

            fichesEl.addEventListener('input', function (e) {
                var fiche = e.target.closest('.gf-fiche');
                if (!fiche) return;
                refresh(fiche);
                updateProgress();
            });

            var openPending = document.getElementById('gf-open-pending');
            if (openPending) {
                openPending.addEventListener('click', function () {
                    var target = fiches.find(function (f) { return !isComplete(f); }) || fiches[0];
                    if (!target) return;
                    setOpen(+target.getAttribute('data-i'));
                    var input = target.querySelector('input, textarea');
                    if (input) setTimeout(function () { try { input.focus({ preventScroll: false }); } catch (e) { input.focus(); } }, 320);
                });
            }

            var toast = document.getElementById('gf-toast');
            if (toast && toast.classList.contains('is-on')) {
                setTimeout(function () { toast.classList.remove('is-on'); }, 2600);
            }

            // ── EXTRAS: el stepper y el total en vivo (`#413` T3) ──────────────────────────────
            // ⚠️⚠️ Esto es DECORADO, no el suelo. El control real es el `input[type=number]` que ya
            // está en el HTML: sin JavaScript se teclea la cantidad y se guarda igual. Y el importe
            // que vale es el que recalcula el SERVIDOR (`PAY-12`); esto solo lo anticipa.
            var extrasTotal = document.querySelector('[data-extras-total]');
            var extraInputs = [].slice.call(document.querySelectorAll('[data-extra-price]'));

            function moneyFromPage(cents) {
                // El formato lo pinta el servidor en el HTML; aquí solo se sustituye el número, así
                // que se toma la forma del propio texto en vez de inventar una convención nueva.
                return (cents / 100).toFixed(2).replace('.', ',') + ' €';
            }

            function refreshExtrasTotal() {
                if (!extrasTotal) { return; }
                var cents = 0;
                extraInputs.forEach(function (input) {
                    var qty = parseInt(input.value, 10);
                    if (!isNaN(qty) && qty > 0) { cents += qty * parseInt(input.getAttribute('data-extra-price'), 10); }
                });
                extrasTotal.textContent = moneyFromPage(cents);
            }

            extraInputs.forEach(function (input) {
                var min = parseInt(input.getAttribute('min'), 10) || 0;
                var max = parseInt(input.getAttribute('max'), 10);
                var step = document.createElement('span');
                step.className = 'gf-extra__step';

                function button(label, delta) {
                    var b = document.createElement('button');
                    b.type = 'button';           // dentro de un <form>, un <button> sin type ENVÍA
                    b.textContent = label;
                    b.setAttribute('aria-hidden', 'true');   // el control accesible es el input
                    b.tabIndex = -1;
                    b.addEventListener('click', function () {
                        var next = (parseInt(input.value, 10) || 0) + delta;
                        if (next < min) { next = min; }
                        if (!isNaN(max) && next > max) { next = max; }
                        input.value = String(next);
                        refreshExtrasTotal();
                        sync();
                    });
                    return b;
                }

                var minus = button('−', -1);
                var plus = button('+', 1);

                function sync() {
                    var qty = parseInt(input.value, 10) || 0;
                    minus.disabled = qty <= min;
                    plus.disabled = !isNaN(max) && qty >= max;
                }

                input.addEventListener('input', function () { refreshExtrasTotal(); sync(); });
                input.parentNode.insertBefore(step, input);
                step.appendChild(minus);
                step.appendChild(input);
                step.appendChild(plus);
                sync();
            });
            refreshExtrasTotal();

            // Acordeón ya cableado → activar modo JS (las fichas no-abiertas se colapsan). La clase `js`
            // se marca AQUÍ, AL FINAL: si algo de arriba hubiera fallado, el documento se queda en
            // `no-js` (todo abierto y usable) en vez de colapsado-inaccesible. `gf-initing` suprime la
            // animación del colapso inicial (se quita en el siguiente frame).
            de.classList.add('gf-initing');
            de.classList.remove('no-js');
            de.classList.add('js');
            var firstPending = fiches.find(function (f) { return !isComplete(f); });
            setOpen(firstPending ? +firstPending.getAttribute('data-i') : (fiches[0] ? 0 : -1));
            updateProgress();
            requestAnimationFrame(function () { de.classList.remove('gf-initing'); });
            } catch (e) {
                // Fallo del acordeón → reponer no-js: fichas abiertas y formulario usable (nunca stuck).
                de.classList.remove('js');
                de.classList.remove('gf-initing');
                de.classList.add('no-js');
            }
        })();
    </script>
</x-focused-layout>
