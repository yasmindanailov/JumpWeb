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

    // La columna de NOMBRE es la primera de tipo TEXTO, no la primera a secas (`#571`, spec §4.4): el
    // esquema no tiene un tipo «nombre» y las columnas las ordena el panel. De ella salen la cabecera de
    // la ficha y el destino del pegado de la lista.
    $nameKey = collect($guestFields)->firstWhere('type', TicketType::FIELD_TYPE_TEXT)['key'] ?? null;
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
            {{-- El RESGUARDO: lo que ya está comprado, y la ÚNICA superficie de tinta de la página
                 (`#570`). La superficie la declara el MARCADO: pintar el fondo no cambia los tokens. --}}
            <div class="gf-stub" data-surface="ink">
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
                        {{-- El cliente cambia sus invitados desde aquí (`specs/invitados-en-post-form.md`,
                             `#444`). ⚠️ Los límites y el plazo salen de `GuestCountPolicy`, la MISMA
                             fuente que revalida bajo el lock: una copia aquí enseñaría un número que el
                             servidor rechaza. Y cuando NO se puede, el control **no desaparece: se
                             deshabilita con su motivo** — un control que se esconde sin explicación es
                             cómo el hueco original estuvo meses sin que nadie lo viera. --}}
                        @if ($guestCount['editable'] && ! $readonly)
                            <label class="gf-stub__count">
                                <span class="sr-only">{{ __('guestform.count_label') }}</span>
                                {{-- ⚠️⚠️ `form="gf-form"` NO es decorativo (`#571`): este campo vive en el
                                     RESGUARDO, que queda FUERA del formulario, y sin el atributo el navegador
                                     no lo envía — cambiar el número de invitados no hacía nada en un
                                     navegador real desde `#444`. Los casos lo mandaban a mano y no podían
                                     verlo; lo vio la sonda de la T2. --}}
                                <input type="number" name="guest_count" inputmode="numeric" form="gf-form"
                                       value="{{ $reservation->quantity }}"
                                       min="{{ $guestCount['min'] }}"
                                       @if ($guestCount['max'] !== null) max="{{ $guestCount['max'] }}" @endif
                                       data-guest-count
                                       data-current="{{ $reservation->quantity }}">
                            </label>
                            <span class="gf-stub__hint">{{ $guestCount['hint'] }}</span>
                            {{-- El aviso de pérdida ya no vive en esta celda: sale al PAPEL, debajo del
                                 resguardo, con su título (`#570`). --}}
                        @else
                            <span class="v">{{ __('tickets.guests_count', ['count' => $reservation->quantity]) }}</span>
                            @if ($guestCount['locked_reason'] !== null)
                                <span class="gf-stub__hint">{{ $guestCount['hint'] }}</span>
                            @endif
                        @endif
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
                    <div class="gf-notice" role="status">
                        <p class="gf-notice__title">{{ __('guestform.mixed_title') }}</p>
                        @foreach ($ageSurcharge['lines'] as $line)
                            <p class="gf-notice__text">
                                {{ __('guestform.mixed_line_written', [
                                    'count' => $line['count'],
                                    'target' => $line['name'],
                                    'unit' => \App\Domain\Platform\Services\Money::format($line['unit']),
                                ]) }}
                            </p>
                        @endforeach
                        @if ($ageSurcharge['credit'] !== null)
                            <p class="gf-notice__text">
                                {{ $ageSurcharge['credit']['label'] }}: −{{ \App\Domain\Platform\Services\Money::format($ageSurcharge['credit']['cents']) }}
                            </p>
                        @endif
                        <p class="gf-notice__text">
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
                    <div class="gf-notice" role="status">
                        <p class="gf-notice__title">{{ __('guestform.mixed_title') }}</p>
                        @foreach ($ageMix->upgrades as $up)
                            <p class="gf-notice__text">
                                {{ __('guestform.mixed_line', [
                                    'count' => $up['count'],
                                    'target' => $up['name'],
                                    'target_price' => $up['target_price_cents'] === null ? '—' : \App\Domain\Platform\Services\Money::format($up['target_price_cents']),
                                    'booked' => $type->tr('name'),
                                    'booked_price' => $ageMix->basePriceCents === null ? '—' : \App\Domain\Platform\Services\Money::format($ageMix->basePriceCents),
                                ]) }}
                            </p>
                        @endforeach
                        <p class="gf-notice__text">
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
                    <div class="gf-notice gf-notice--attn" role="status">
                        {{-- `trans_choice`: «Faltan 1 edades» es el «1 invitadoS» de `#247`. --}}
                        <p class="gf-notice__text">{{ trans_choice('guestform.frozen_missing_ages', $frozenMissingAges, ['count' => $frozenMissingAges]) }}</p>
                    </div>
                @endif

                {{-- Una edad SIN PRODUCTO (`#284` D6, §22.5): no es un hueco de configuración, es «no
                     hay producto para esa edad en tus condiciones». Un texto por caso, el del parque
                     si lo escribió, con su teléfono. --}}
                @if ($noProductNotices !== [])
                    <div class="gf-notice gf-notice--err" role="alert">
                        <p class="gf-notice__title">{{ __('guestform.no_product_title') }}</p>
                        @foreach ($noProductNotices as $notice)
                            <p class="gf-notice__text">{{ $notice }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ───── Los avisos sobre PAPEL (`#570`): el plazo en amarillo, el rechazo en rojo ─────
                 ⚠️ Los rechazos se pintaban DESPUÉS del pie: quien guardaba y volvía arriba no veía que
                 algo no se había aplicado. Van pegados al resguardo, delante de lo que se vuelve a tocar.
                 ⚠️ Forma de BLOQUE a propósito: la de paréntesis se emparejaría con el cierre del bloque
                 de las fichas, más abajo, y dejaría media plantilla sin compilar (`#298`). --}}
            @if ($guestCount['editable'] && ! $readonly)
                {{-- El aviso de pérdida lo rellena el JS con el número REAL de fichas rellenas que se
                     perderían; sin JS no se pinta, y el servidor sigue devolviendo cuántas se perdieron. --}}
                <div class="gf-notice gf-notice--attn" id="gf-count-warn" role="alert" hidden
                     data-tpl="{{ __('guestform.count_warn_discard', ['count' => ':count', 'discarded' => ':discarded']) }}">
                    <p class="gf-notice__title">{{ __('guestform.count_warn_title') }}</p>
                    <p class="gf-notice__text" data-warn-text></p>
                </div>
            @endif

            @php
                $countStatus = is_string(session('status')) && str_starts_with(session('status'), 'guest-count-')
                    ? substr(session('status'), strlen('guest-count-'))
                    : null;
            @endphp
            {{-- El motivo viaja en el flash porque el REMEDIO de cada rechazo es distinto
                 (`specs/invitados-en-post-form.md` §4.7·1, `#444`): el título lo dice una vez y la
                 frase dice qué hacer. --}}
            @if ($countStatus !== null)
                <div class="gf-notice gf-notice--err" role="alert">
                    <p class="gf-notice__title">{{ __('guestform.count_error_title') }}</p>
                    <p class="gf-notice__text">{{ __('guestform.count_error_'.$countStatus) }}</p>
                </div>
            @endif

            {{-- Lo que de los EXTRAS no se pudo aplicar se DICE y no se calla: los datos del formulario
                 SÍ se guardaron (van por otra transacción, §4.5.3) y el aviso lo separa. --}}
            @if (in_array(session('status'), ['guest-form-extras-blocked', 'guest-form-stale'], true))
                <div class="gf-notice gf-notice--err" role="alert">
                    <p class="gf-notice__text">
                        {{ session('status') === 'guest-form-stale'
                            ? __('guestform.extras_stale')
                            : __('guestform.extras_blocked') }}
                    </p>
                </div>
            @endif

            {{-- ───── EL BLOQUE DE LA INVITACIÓN (T6·1, `specs/celebracion-e-invitacion.md` §4.7) ─────

                 Va ARRIBA y **antes** de que el anfitrión empiece a teclear —después ya no sirve de
                 nada—, con la puerta de rellenar a mano justo debajo y sin esconderla (canvas, turno
                 3a). No promete rellenarlo todo: promete repartir el trabajo.

                 ⚠️⚠️ **Es su PROPIO formulario y vive FUERA de `#gf-form`**, y no es una cuestión de
                 estilo: un `form` dentro de otro no es HTML válido —el navegador lo descarta— y
                 además personalizar escribe SOLO `party_invitations`, mientras que el testigo de los
                 extras es `order_items.updated_at`. Meterlo dentro haría que cambiar el tema dejara
                 obsoleta la página abierta.

                 ⚠️ El ENLACE se enseña escrito, siempre: sin JavaScript no hay ni Web Share ni
                 portapapeles, y un botón que no se puede pulsar dejaría al anfitrión sin forma de
                 repartir su fiesta. Los dos botones nacen `hidden` y los enciende el JS según lo que
                 el navegador tenga (mejora progresiva, como el resto de la hoja). --}}
            @if ($invitation !== null)
                @php $inv = $invitation['invitation']; @endphp

                {{-- Un texto con enlace NO se guarda, y se dice: el campo se queda como estaba y
                     callarlo dejaría al anfitrión creyendo que se publicó (§7.2·R9). --}}
                @if (session('status') === 'invitation-text-rejected')
                    <div class="gf-notice gf-notice--err" role="alert">
                        <p class="gf-notice__title">{{ __('guestform.invite.rejected_title') }}</p>
                        <p class="gf-notice__text">{{ __('guestform.invite.rejected') }}</p>
                    </div>
                @endif

                <section class="gf-invite" id="gf-invite">
                    <div class="gf-group__head">
                        <h2 class="gf-group__title">{{ __('guestform.invite.title') }}</h2>
                    </div>
                    <p class="gf-invite__lead">{{ __('guestform.invite.lead') }}</p>

                    @if ($invitation['shareable'])
                        <div class="gf-invite__share" data-invite
                             data-url="{{ $invitation['url'] }}"
                             data-text="{{ trim((string) $inv->honoree_name) !== ''
                                 ? __('guestform.invite.share_text', ['name' => $inv->honoree_name])
                                 : __('guestform.invite.share_text_generic') }}"
                             data-copied="{{ __('guestform.invite.copied') }}">
                            <p class="gf-invite__linkbox">
                                <span class="gf-invite__linklabel">{{ __('guestform.invite.link_label') }}</span>
                                {{-- ⚠️ El enlace se pinta como TEXTO y no como `<a>`: el anfitrión no tiene
                                     que abrir su propia invitación, tiene que repartirla, y un enlace que
                                     navega se pulsa sin querer en un teléfono. --}}
                                <span class="gf-invite__url">{{ $invitation['url'] }}</span>
                            </p>
                            <div class="gf-invite__actions">
                                <button type="button" class="btn btn--ink" data-invite-share hidden>{{ __('guestform.invite.share') }}</button>
                                <button type="button" class="btn btn--ghost" data-invite-copy hidden>{{ __('guestform.invite.copy') }}</button>
                            </div>
                            <p class="gf-sr-only" role="status" data-invite-said></p>
                        </div>

                        {{-- El plazo, escrito COMO FECHA (canvas, turno 3a): no un número de horas que
                             haya que sumar. Pasado, el enlace sigue abriendo (§7.2·R8). --}}
                        @if ($invitation['replies_open'])
                            @if ($invitation['deadline'] !== '')
                                <p class="gf-invite__deadline">{{ __('guestform.invite.deadline', ['when' => $invitation['deadline']]) }}</p>
                            @endif
                        @else
                            <p class="gf-invite__deadline">{{ __('guestform.invite.deadline_closed') }}</p>
                        @endif
                    @else
                        {{-- Sin nombre de quien cumple no se puede compartir (§4.5·2) **y la pantalla lo
                             dice**, con el remedio al lado: el campo está aquí mismo, abierto. --}}
                        <div class="gf-notice gf-notice--attn" role="status">
                            <p class="gf-notice__title">{{ __('guestform.invite.needs_name_title') }}</p>
                            <p class="gf-notice__text">{{ __('guestform.invite.needs_name') }}</p>
                        </div>
                    @endif

                    {{-- El resumen: «N vienen · M no pueden · K por repasar». --}}
                    <ul class="gf-invite__tally">
                        <li class="gf-invite__tallyitem">{{ trans_choice('guestform.invite.tally_yes', $invitation['summary']['yes'], ['count' => $invitation['summary']['yes']]) }}</li>
                        <li class="gf-invite__tallyitem">{{ trans_choice('guestform.invite.tally_no', $invitation['summary']['no'], ['count' => $invitation['summary']['no']]) }}</li>
                        <li class="gf-invite__tallyitem">{{ trans_choice('guestform.invite.tally_pending', $invitation['summary']['pending'], ['count' => $invitation['summary']['pending']]) }}</li>
                    </ul>

                    {{-- PERSONALIZAR: `details` NATIVO, que se abre sin una línea de JS. Nace ABIERTO
                         cuando aún no se puede compartir, porque entonces el remedio está dentro. --}}
                    <details class="gf-invite__custom" @if (! $invitation['shareable']) open @endif>
                        <summary class="gf-invite__summary">
                            <span>{{ __('guestform.invite.customize') }}</span>
                            <x-icons.chevron-down :width="16" :height="16" />
                        </summary>
                        <form method="POST" action="{{ $invitation['action'] }}" class="gf-invite__form">
                            @csrf
                            <div class="eventfields">
                                <label class="eventfields__field" for="inv-honoree">
                                    <span class="eventfields__label">{{ __('guestform.invite.honoree_name') }}</span>
                                    <input id="inv-honoree" type="text" name="honoree_name"
                                           maxlength="{{ \App\Domain\Booking\Models\PartyInvitation::HONOREE_NAME_MAX }}"
                                           value="{{ $inv->honoree_name }}">
                                </label>
                                <label class="eventfields__field" for="inv-age">
                                    <span class="eventfields__label">{{ __('guestform.invite.honoree_age') }} <span class="gf-opt">{{ __('guestform.optional') }}</span></span>
                                    <input id="inv-age" type="number" name="honoree_age" inputmode="numeric" min="0" max="255"
                                           value="{{ $inv->honoree_age }}">
                                </label>
                                <label class="eventfields__field" for="inv-host">
                                    <span class="eventfields__label">{{ __('guestform.invite.host_line') }} <span class="gf-opt">{{ __('guestform.optional') }}</span></span>
                                    <input id="inv-host" type="text" name="host_line"
                                           maxlength="{{ \App\Domain\Booking\Models\PartyInvitation::HOST_LINE_MAX }}"
                                           value="{{ $inv->host_line }}">
                                </label>
                                <label class="eventfields__field" for="inv-theme">
                                    <span class="eventfields__label">{{ __('guestform.invite.theme') }}</span>
                                    <select id="inv-theme" name="theme">
                                        @foreach ($invitation['themes'] as $theme)
                                            <option value="{{ $theme }}" @selected($inv->safeTheme() === $theme)>{{ __('guestform.invite.theme_'.$theme) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            {{-- ⚠️⚠️ El `hidden` de delante NO es decorativo: una casilla sin marcar no se
                                 envía, y para el dominio una clave ausente es «no lo toques» (es un
                                 PATCH). Sin él, desmarcar «enseñar mi teléfono» no lo apagaría nunca. --}}
                            <input type="hidden" name="show_host_phone" value="0">
                            <label class="gf-invite__check">
                                <input type="checkbox" name="show_host_phone" value="1" @checked($inv->show_host_phone)>
                                <span>{{ __('guestform.invite.show_phone') }}</span>
                            </label>

                            <button type="submit" class="btn btn--ink">{{ __('guestform.invite.save') }}</button>
                        </form>
                    </details>
                </section>
            @endif

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
                    <div class="gf-intro">
                        <p class="guestform__privacy">{{ __('guestform.privacy') }}</p>
                        {{-- La política FUERA de su frase, como control propio (F-08, `#570`). --}}
                        <a class="gf-legal" href="{{ route('legal.privacidad') }}">{{ __('guestform.privacy_link') }}</a>
                    </div>
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
                                    <span class="eventfields__label">{{ $type->eventFieldLabel($field) }}@unless ($field['required']) <span class="gf-opt">{{ __('guestform.optional') }}</span>@endunless</span>
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
                    @php
                        // T2 (`#571`): los CERRADOS al final, con su motivo. ⚠️ Los extras se leen por
                        // `product_id` (`GuestFormController::desiredQuantities`), así que moverlos no cambia
                        // lo que se compra: el índice de cada uno viaja igual en su nombre.
                        $addonRows = collect($addons)->sortBy(fn ($addon) => $addon->closed ? 1 : 0);
                        $extrasChosen = collect($addons)->filter(fn ($addon) => $addon->quantity > 0)->count();
                    @endphp
                    <section class="gf-group gf-extras" id="gf-extras">
                        <div class="gf-group__head">
                            <h2 class="gf-group__title">{{ __('guestform.extras_heading') }}</h2>
                            <span class="gf-group__opt" data-extras-chosen data-tpl="{{ __('guestform.extras_chosen') }}">{{ trans_choice('guestform.extras_chosen', $extrasChosen, ['count' => $extrasChosen]) }}</span>
                        </div>
                        <p class="gf-extras__lead">{{ __('guestform.extras_lead') }}</p>

                        {{-- Filas de UNA tarjeta y no una tarjeta por extra (2b: de 152 a 69 px cada uno), y el
                             motivo de un cerrado dentro de su fila, debajo del nombre. --}}
                        <ul class="gf-extras__list">
                            @foreach ($addonRows as $i => $addon)
                                <li class="gf-extra {{ $addon->closed ? 'is-closed' : '' }}">
                                    <div class="gf-extra__id">
                                        {{-- ▶ T2 (`#571`): la FILA hace de «Más info» (2b). El nombre y el precio son
                                             el `summary` de un `details` NATIVO, y el stepper queda FUERA: un control
                                             dentro de un `summary` no es HTML válido. Con «Más info» en su propia
                                             línea la fila medía 97 px en vez de 69 (medido en navegador). --}}
                                        @if ($addon->features !== [] || $addon->gifts !== [])
                                            <details class="gf-extra__more">
                                                <summary>
                                                    <span class="gf-extra__name">{{ $addon->productName }}<x-icons.chevron-down :width="14" :height="14" /></span>
                                                    <span class="gf-extra__price">{{ $addon->note }}</span>
                                                    {{-- Se VE el chevron; se ANUNCIA el nombre del desplegable. --}}
                                                    <span class="gf-sr-only">{{ __('tickets.addon_more_info') }}</span>
                                                </summary>
                                                @if ($addon->features !== [])
                                                    <ul>
                                                        @foreach ($addon->features as $feature)
                                                            <li>{{ $feature }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                {{-- Los regalos, DENTRO del «Más info» como en el cajón (`#589`, `[DECIDIDO owner]`). --}}
                                                <x-site.gifts :gifts="$addon->gifts" class="gf-extra__gifts" />
                                            </details>
                                        @else
                                            <span class="gf-extra__name">{{ $addon->productName }}</span>
                                            <span class="gf-extra__price">{{ $addon->note }}</span>
                                        @endif
                                        @if ($addon->closed || $readonly)
                                            <span class="gf-extra__why">
                                                {{ $addon->closedReason === \App\Domain\Booking\Contracts\PostFormAddonView::REASON_SOLD_AT_BOOKING
                                                    ? __('guestform.extras_closed_sold')
                                                    : __('guestform.extras_closed_cutoff') }}
                                            </span>
                                        @endif
                                        {{-- ⚠️ El «Más info» del catálogo (`#416`) es un `details` NATIVO y no el
                                             toggle de Alpine de la landing: esta página nace `no-js`, y quien no
                                             tenga JavaScript tiene que poder leer qué lleva lo que compra. El
                                             chevron sale del SET de iconos (`#257`). --}}
                                    </div>

                                    {{-- El id viaja SIEMPRE, también en los cerrados: si no, un envío
                                         normal llegaría sin ellos y el servidor no sabría que el
                                         cliente no los estaba tocando. --}}
                                    <input type="hidden" name="addons[{{ $i }}][product_id]" value="{{ $addon->productId }}">

                                    @if ($addon->closed || $readonly)
                                        <input type="hidden" name="addons[{{ $i }}][quantity]" value="{{ $addon->quantity }}">
                                        <span class="gf-extra__qty" aria-live="polite">{{ $addon->quantity }}</span>
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
                @php
                    // ▶ T2 (`#571`): el ESTADO de cada ficha se decide aquí, una vez, con la MISMA regla que el
                    // JS (`ficheState()` de `public/js/guest-form/logic.js`): completa si no le falta ninguna
                    // columna obligatoria —y sin edad sin producto, D6—, y «Falta …» nombra la primera
                    // obligatoria vacía de una ficha que YA tiene algún dato.
                    $ficheStates = [];
                    for ($i = 0; $i < $reservation->quantity; $i++) {
                        $row = $rows[$i] ?? [];
                        $noProduct = in_array($i, $noProductIndexes, true);
                        $firstEmpty = collect($guestFields)->first(fn ($field) => $field['required'] && ! filled($row[$field['key']] ?? null));
                        $hasData = collect($guestFields)->contains(fn ($field) => filled($row[$field['key']] ?? null));
                        $ficheStates[$i] = [
                            'no_product' => $noProduct,
                            'complete' => ! $noProduct && $firstEmpty === null,
                            'missing' => $hasData && $firstEmpty !== null ? $type->guestFieldLabel($firstEmpty) : null,
                            'name' => $nameKey ? trim((string) ($row[$nameKey] ?? '')) : '',
                            'regime' => $guestRegimes[$i] ?? null,
                        ];
                    }
                    // ▶ Pendientes ARRIBA y las listas PLEGADAS (2b), solo mientras se puede editar: en solo
                    // lectura se lee la lista en su orden.
                    // ⚠️⚠️ El orden de la PÁGINA deja de ser el de las POSICIONES, y por eso
                    // `TicketType::sanitizeGuestData()` ordena por clave al guardar: sin eso, cada guardado
                    // movería a los invitados de sitio.
                    $doneIdx = $readonly ? [] : array_keys(array_filter($ficheStates, fn ($state) => $state['complete']));
                    $pendingIdx = array_values(array_diff(array_keys($ficheStates), $doneIdx));
                    $pageOrder = array_merge($pendingIdx, $doneIdx);
                    $lastPos = count($pageOrder) - 1;
                    $pasteable = ! $readonly && $nameKey !== null;
                @endphp
                <section class="gf-group">
                    <div class="gf-group__head">
                        <h2 class="gf-group__title">{{ __('guestform.children_heading') }}</h2>
                        <span class="gf-group__opt" id="gf-group-count">{{ $progress['done'] }} / {{ (int) $reservation->quantity }}</span>
                    </div>

                    {{-- El PEGADO de la lista de nombres (2b): solo con JS —sin él no hay diálogo, y el formulario
                         sigue completo— y solo si el esquema tiene una columna de nombre donde dejarlo. --}}
                    @if ($pasteable)
                        <div class="gf-paste" data-paste>
                            <p class="gf-paste__prompt">{{ __('guestform.paste_prompt') }}</p>
                            <button type="button" class="btn btn--ghost" data-paste-open aria-haspopup="dialog">{{ __('guestform.paste_open') }}</button>
                            <div class="gf-notice" id="gf-paste-done" role="status" hidden>
                                <p class="gf-notice__text"></p>
                            </div>
                        </div>
                    @endif

                    <div class="gf-fiches" id="gf-fiches">
                        @if ($pendingIdx !== [] && $doneIdx !== [])
                            <p class="gf-fiches__label">{{ __('guestform.group_pending', ['count' => count($pendingIdx)]) }}</p>
                        @endif
                        @foreach ($pageOrder as $pos => $i)
                            @if ($doneIdx !== [] && $i === $doneIdx[0])
                                {{-- Las fichas LISTAS, plegadas en un `details` NATIVO: se abre sin JS, nada queda
                                     inalcanzable y sus campos viajan en el POST aunque esté cerrado. --}}
                                <details class="gf-done" id="gf-done">
                                    <summary class="gf-done__summary">
                                        <span>{{ trans_choice('guestform.group_done', count($doneIdx), ['count' => count($doneIdx)]) }}</span>
                                        <span class="gf-fiche__chev" aria-hidden="true"><x-icons.chevron-down :width="16" :height="16" /></span>
                                    </summary>
                                    <div class="gf-done__list">
                            @endif
                            @php $state = $ficheStates[$i]; @endphp
                            <div class="gf-fiche {{ $state['complete'] ? 'is-complete' : '' }}" data-i="{{ $i }}" @if ($state['no_product']) data-no-product="1" @endif>
                                <button type="button" class="gf-fiche__head" data-act="toggle" aria-expanded="false" aria-controls="gf-body-{{ $i }}">
                                    <span class="gf-fiche__cube">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="gf-fiche__who">
                                        {{-- El estado con PALABRA dentro del rótulo (`#570`): «Invitado/a 01 · Lista»,
                                             «Falta edad» en una ficha a medias (2b) o solo el número en una en
                                             blanco. El JS lo recalcula al teclear con las dos plantillas. --}}
                                        <span @class(['gf-fiche__role', 'is-missing' => $state['missing'] !== null]) data-role
                                              data-role-default="{{ __('guestform.child', ['n' => $i + 1]) }}"
                                              data-missing-tpl="{{ __('guestform.status_missing', ['field' => ':field']) }}"><span data-role-text>{{ $state['missing'] !== null ? __('guestform.status_missing', ['field' => $state['missing']]) : __('guestform.child', ['n' => $i + 1]) }}</span><span class="gf-fiche__done"> · {{ __('guestform.status_done') }}</span></span>
                                        <span class="gf-fiche__name {{ $state['name'] === '' ? 'is-empty' : '' }}" data-name data-empty="{{ $emptyLabel }}">{{ $state['name'] !== '' ? $state['name'] : $emptyLabel }}</span>
                                        {{-- El régimen que le toca a ESTE niño por su edad
                                             (`docs/specs/cumple-mixto.md` §15), y **solo cuando difiere** del
                                             pack reservado o no hay producto para esa edad (`#570`): repetir el
                                             pack que ya dice el resguardo no informaba y se comía el nombre.
                                             ⚠️ Refleja lo GUARDADO: se actualiza al guardar, no al
                                             teclear — el veredicto es del servidor (`CE-4`). --}}
                                        @if ($state['regime'] !== null && $state['regime']['state'] === \App\Domain\Booking\Services\GuestAgeMixReader::ROW_OK && ! $state['regime']['own'])
                                            <span class="gf-fiche__regime is-other">{{ $state['regime']['name'] }}</span>
                                        @elseif ($state['regime'] !== null && $state['regime']['state'] === \App\Domain\Booking\Services\GuestAgeMixReader::ROW_OUT_OF_RANGE)
                                            <span class="gf-fiche__regime is-unknown">{{ __('guestform.regime_no_product') }}</span>
                                        @endif
                                    </span>
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
                                                        <span class="eventfields__label">{{ $type->guestFieldLabel($field) }}@unless ($field['required']) <span class="gf-opt">{{ __('guestform.optional') }}</span>@endunless</span>
                                                        @if ($field['type'] === 'textarea')
                                                            <textarea id="c-{{ $i }}-{{ $field['key'] }}" name="guests[{{ $i }}][{{ $field['key'] }}]" rows="2" data-label="{{ $type->guestFieldLabel($field) }}" @if ($field['required']) data-required @endif @if ($field['key'] === $nameKey) data-name-input @endif @disabled($readonly)>{{ $rows[$i][$field['key']] ?? '' }}</textarea>
                                                        @else
                                                            <input id="c-{{ $i }}-{{ $field['key'] }}" type="{{ $guestInput[$field['key']]['type'] }}" @if ($guestInput[$field['key']]['min'] !== null) min="{{ $guestInput[$field['key']]['min'] }}" inputmode="numeric" @endif @if ($guestInput[$field['key']]['max'] !== null) max="{{ $guestInput[$field['key']]['max'] }}" @endif name="guests[{{ $i }}][{{ $field['key'] }}]" value="{{ $rows[$i][$field['key']] ?? '' }}" data-label="{{ $type->guestFieldLabel($field) }}" @if ($field['required']) data-required @endif @if ($field['key'] === $nameKey) data-name-input @endif @disabled($readonly)>
                                                        @endif
                                                    </label>
                                                @endforeach
                                            </div>

                                            {{-- «Anterior» y «Siguiente» siguen el orden de la PÁGINA, no el de las
                                                 posiciones (T2): con las pendientes arriba, «la siguiente» es la
                                                 que se ve debajo. --}}
                                            @unless ($readonly)
                                                <div class="gf-fiche__foot">
                                                    <button type="button" class="btn btn--ghost gf-nav-prev" data-act="prev" @if ($pos === 0) disabled @endif>
                                                        <x-icons.arrow-left :width="13" :height="13" />{{ __('guestform.nav_prev') }}
                                                    </button>
                                                    <button type="button" class="btn btn--ink" data-act="next" @if ($pos === $lastPos) disabled @endif>
                                                        {{ $pos === $lastPos ? __('guestform.nav_last') : __('guestform.nav_next') }}<x-icons.arrow-right :width="13" :height="13" />
                                                    </button>
                                                </div>
                                            @endunless
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if ($doneIdx !== [])
                                    </div>
                                </details>
                        @endif
                    </div>
                </section>

                {{-- ───── GUARDAR, PEGADO abajo con la cuenta (2b, `#571`) ─────
                     ⚠️ Con veinte fichas el único botón de la página quedaba al final de toda la página. La
                     barra es `sticky` y no `fixed`: viaja mientras se rellena y al final se queda en su sitio.
                     La cuenta va oculta para lectores de pantalla: la dice ya el estado del resguardo. --}}
                @unless ($readonly)
                    <p class="gf-savebar__help">{{ __('guestform.hint') }}</p>
                    <div class="gf-savebar">
                        <span class="gf-savebar__count" aria-hidden="true">
                            <span class="gf-savebar__label">{{ __('guestform.meter_label') }}</span>
                            <span class="gf-savebar__num"><em id="gf-savebar-done">{{ $progress['done'] }}</em> / {{ $progress['total'] }}</span>
                        </span>
                        {{-- ⚠️⚠️ **El DISQUETE se retira y no se sustituye** (`#258`). Tres motivos, y
                             el tercero decide: el set del artboard no dibuja «guardar» y no había
                             equivalente; un disquete es el único anacronismo que quedaba en toda la
                             web; y §06 del artboard dice «máximo un icono por fila de texto — si
                             hacen falta tres, lo que falta es una lista». Este botón ya dice
                             «Guardar» con todas sus letras, así que el icono no informaba: decoraba.
                             ▶ Si el owner lo quiere de vuelta, es una línea y un glifo del
                             diseñador. --}}
                        {{-- `#570` · el botón que GUARDA no es el color de comprar: aquí no se compra nada
                             (los extras y el suplemento se pagan en el parque). Va en el secundario del
                             sistema, que `#539` pasó de tinta a Azul Muro. --}}
                        <button type="submit" class="btn btn--lg btn--ink">
                            {{ __('guestform.submit') }}
                        </button>
                    </div>
                @endunless
            </form>
        </main>

        {{-- Footer mínimo, DATA-DRIVEN (mismas fuentes que el footer del sitio): nombre del negocio +
             texto de derechos editable (con fallback i18n) + teléfono; etiqueta «Privacidad» de
             `landing.footer.legal` (la misma que usa el footer principal). --}}
        @php $gfLegal = (array) __('landing.footer.legal'); @endphp
        <footer class="gf-foot">
            <span class="gf-foot__copy">© {{ date('Y') }} {{ \Illuminate\Support\Str::upper($site['name'] ?? config('app.name')) }} — {{ $site['footer_rights'] ?? __('landing.footer.rights') }}</span>
            <span class="gf-foot__links">
                <a href="{{ route('legal.privacidad') }}">{{ $gfLegal[1] ?? __('guestform.footer_privacy') }}</a>
                @if (! empty($site['phone']))
                    <a class="gf-foot__tel" href="tel:{{ preg_replace('/\s+/', '', $site['phone']) }}">{{ $site['phone'] }}</a>
                @endif
            </span>
        </footer>
    </div>

    {{-- Toast de guardado (flash del servidor; relevante al recargar por enlace firmado).
         ⚠️ También tras personalizar la invitación (T6·1): es otro POST y otra redirección, pero para
         quien pulsa es el mismo gesto de guardar, y sin acuse parecería que no pasó nada. --}}
    <div class="gf-toast {{ in_array(session('status'), ['guest-form-saved', 'invitation-saved'], true) ? 'is-on' : '' }}" id="gf-toast" role="status">
        {{-- ⚠️ CUADRADO: el check pasó a la rejilla 24 del set y 12×10 ya no encoge, DEFORMA. --}}
        <span class="tcheck"><x-icons.check :width="12" :height="12" /></span>
        <span>{{ __('guestform.toast_saved') }}</span>
    </div>

    {{-- ───── El diálogo del PEGADO de la lista de nombres (2b, `#571`) ─────
         `dialog` NATIVO con `showModal()`: atrapa el foco, se cierra con Esc y devuelve el foco al botón que
         lo abrió sin una línea propia. Va FUERA del formulario de las fichas: su texto no se envía nunca.
         Las frases con plural las resuelve `choice()` en el navegador, porque `trans_choice` no existe allí. --}}
    @if ($pasteable)
        <dialog class="gf-dialog" id="gf-paste" aria-labelledby="gf-paste-title" aria-describedby="gf-paste-help"
                data-count-tpl="{{ __('guestform.paste_count') }}"
                data-scope-tpl="{{ __('guestform.paste_scope') }}"
                data-kept-tpl="{{ __('guestform.paste_kept') }}"
                data-overflow-tpl="{{ __('guestform.paste_overflow') }}"
                data-apply-tpl="{{ __('guestform.paste_apply') }}"
                data-done-tpl="{{ __('guestform.paste_done') }}">
            <form method="dialog" class="gf-dialog__body">
                <h2 class="gf-dialog__title" id="gf-paste-title">{{ __('guestform.paste_title') }}</h2>
                <p class="gf-dialog__help" id="gf-paste-help">{{ __('guestform.paste_help') }}</p>
                <label class="gf-sr-only" for="gf-paste-text">{{ __('guestform.paste_label') }}</label>
                <textarea class="gf-dialog__text" id="gf-paste-text" rows="6" autocomplete="off" spellcheck="false"></textarea>
                <div class="gf-notice" data-paste-summary aria-live="polite" hidden>
                    <p class="gf-notice__title" data-paste-count></p>
                    <p class="gf-notice__text" data-paste-scope></p>
                </div>
                <div class="gf-dialog__actions">
                    <button type="submit" class="btn btn--ghost" value="cancel">{{ __('guestform.paste_cancel') }}</button>
                    <button type="button" class="btn btn--ink" data-paste-apply disabled>{{ trans_choice('guestform.paste_apply', 0, ['count' => 0]) }}</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- El JS de la página. Mejora progresiva: sin JS las fichas salen abiertas y el formulario funciona.
         ⚠️ Es un MÓDULO desde la T2 (`#571`): importa la lógica pura —el pegado y el estado de una ficha—, que
         así se prueba con `node --test`. Si el módulo no llega, la página se queda en `no-js`, que es un
         formulario completo. La URL lleva la fecha del fichero, como las hojas de estilo. --}}
    <script type="module">
        import { choice, ficheState, parseNames, planPaste } from '{{ asset('js/guest-form/logic.js') }}?v={{ @filemtime(public_path('js/guest-form/logic.js')) }}';

        const de = document.documentElement;
        const form = document.getElementById('gf-form');
        const fichesEl = document.getElementById('gf-fiches');

        if (form && fichesEl) {
            try {
                // ⚠️⚠️ Desde la T2 el ORDEN DE LA PÁGINA no es el de las POSICIONES: pendientes arriba y las
                // listas plegadas. La navegación sigue a la página (`fiches`); lo que depende de la POSICIÓN
                // —qué fichas se pierden al bajar invitados, dónde cae cada nombre pegado— se lee por
                // `data-i` (`byIndex`).
                const fiches = [...fichesEl.querySelectorAll('.gf-fiche')];
                const byIndex = [...fiches].sort((a, b) => Number(a.dataset.i) - Number(b.dataset.i));
                const total = fiches.length;
                const doneGroup = document.getElementById('gf-done');
                const meterDone = document.getElementById('gf-meter-done');
                const meterBar = document.getElementById('gf-meter-bar');
                const groupCount = document.getElementById('gf-group-count');
                const barDone = document.getElementById('gf-savebar-done');

                const fieldsOf = (fiche) => [...fiche.querySelectorAll('input[name^="guests["], textarea[name^="guests["]')];
                const filled = (el) => (el.value || '').trim() !== '';
                const isEmpty = (fiche) => !fieldsOf(fiche).some(filled);
                // La MISMA regla que pinta el servidor al cargar. Una edad sin producto la decide él (D6).
                const stateOf = (fiche) => ficheState(
                    fieldsOf(fiche).map((el) => ({ required: el.hasAttribute('data-required'), filled: filled(el), label: el.dataset.label || '' })),
                    fiche.dataset.noProduct === '1',
                );

                function setOpen(target) {
                    fiches.forEach((fiche) => {
                        const on = fiche === target;
                        fiche.classList.toggle('is-open', on);
                        const head = fiche.querySelector('.gf-fiche__head');
                        if (head) head.setAttribute('aria-expanded', on ? 'true' : 'false');
                        // a11y: la ficha colapsada sale del orden de foco (#264-audit). `inert` NO desactiva el
                        // envío del formulario (los valores siguen yendo en el POST).
                        const body = fiche.querySelector('.gf-fiche__body');
                        if (body) body.inert = !on;
                    });
                    // Una ficha lista vive dentro del grupo plegado: abrirla abre el grupo.
                    if (target && doneGroup && doneGroup.contains(target)) doneGroup.open = true;
                }

                function refresh(fiche) {
                    const state = stateOf(fiche);
                    fiche.classList.toggle('is-complete', state.complete);
                    const role = fiche.querySelector('[data-role]');
                    const roleText = fiche.querySelector('[data-role-text]');
                    if (role && roleText) {
                        role.classList.toggle('is-missing', state.missing !== null);
                        roleText.textContent = state.missing !== null
                            ? (role.dataset.missingTpl || '').replace(':field', state.missing)
                            : (role.dataset.roleDefault || '');
                    }
                    const nameEl = fiche.querySelector('[data-name]');
                    const nameInput = fiche.querySelector('[data-name-input]');
                    if (nameEl && nameInput) {
                        const value = nameInput.value.trim();
                        nameEl.textContent = value !== '' ? value : (nameEl.dataset.empty || '');
                        nameEl.classList.toggle('is-empty', value === '');
                    }
                }

                function updateProgress() {
                    const done = fiches.filter((fiche) => stateOf(fiche).complete).length;
                    if (meterDone) meterDone.textContent = done;
                    if (meterBar) meterBar.style.width = (total ? (done / total) * 100 : 0) + '%';
                    if (groupCount) groupCount.textContent = done + ' / ' + total;
                    if (barDone) barDone.textContent = done;
                }

                fichesEl.addEventListener('click', (event) => {
                    const act = event.target.closest('[data-act]');
                    const fiche = event.target.closest('.gf-fiche');
                    if (!fiche || !act) return;
                    if (act.dataset.act === 'toggle') {
                        setOpen(fiche.classList.contains('is-open') ? null : fiche);
                        return;
                    }
                    const pos = fiches.indexOf(fiche);
                    const target = act.dataset.act === 'prev' ? fiches[pos - 1] : fiches[pos + 1];
                    if (!target) return;
                    setOpen(target);
                    // Con la barra pegada abajo, la ficha de al lado puede quedar debajo: se trae a la vista.
                    target.querySelector('.gf-fiche__head')?.scrollIntoView({ block: 'nearest' });
                });

                fichesEl.addEventListener('input', (event) => {
                    const fiche = event.target.closest('.gf-fiche');
                    if (!fiche) return;
                    refresh(fiche);
                    updateProgress();
                });

                const toast = document.getElementById('gf-toast');
                if (toast && toast.classList.contains('is-on')) {
                    setTimeout(() => toast.classList.remove('is-on'), 2600);
                }

                // ── El PEGADO de la lista de nombres (2b, `#571`) ──────────────────────────────────────
                // ⚠️ Pegar NO guarda: escribe en los campos, y se dice. Y NUNCA pisa una ficha con datos, que
                // es lo que el diálogo promete antes de aplicar (`planPaste`).
                const paste = document.querySelector('[data-paste]');
                const dialog = document.getElementById('gf-paste');
                if (paste && dialog && typeof dialog.showModal === 'function') {
                    const opener = paste.querySelector('[data-paste-open]');
                    const doneNotice = document.getElementById('gf-paste-done');
                    const text = dialog.querySelector('textarea');
                    const summary = dialog.querySelector('[data-paste-summary]');
                    const countEl = dialog.querySelector('[data-paste-count]');
                    const scopeEl = dialog.querySelector('[data-paste-scope]');
                    const apply = dialog.querySelector('[data-paste-apply]');
                    const slots = () => byIndex.map((fiche) => ({ index: Number(fiche.dataset.i), empty: isEmpty(fiche) }));
                    let plan = planPaste([], slots());

                    const preview = () => {
                        const names = parseNames(text.value);
                        plan = planPaste(names, slots());
                        summary.hidden = names.length === 0;
                        countEl.textContent = choice(dialog.dataset.countTpl, names.length);
                        scopeEl.textContent = [
                            plan.placed > 0 ? choice(dialog.dataset.scopeTpl, plan.placed) : '',
                            plan.placed > 0 && plan.kept > 0 ? choice(dialog.dataset.keptTpl, plan.kept) : '',
                            plan.overflow > 0 ? choice(dialog.dataset.overflowTpl, plan.overflow) : '',
                        ].filter(Boolean).join(' ');
                        apply.disabled = plan.placed === 0;
                        apply.textContent = choice(dialog.dataset.applyTpl, plan.placed);
                    };

                    opener.addEventListener('click', () => {
                        text.value = '';
                        preview();
                        dialog.showModal();
                        text.focus();
                    });
                    text.addEventListener('input', preview);

                    apply.addEventListener('click', () => {
                        preview();
                        if (plan.placed === 0) return;
                        const touched = plan.assignments.map(({ index, name }) => {
                            const fiche = byIndex.find((candidate) => Number(candidate.dataset.i) === index);
                            fiche.querySelector('[data-name-input]').value = name;
                            refresh(fiche);
                            return fiche;
                        });
                        updateProgress();
                        dialog.close();
                        doneNotice.querySelector('.gf-notice__text').textContent = choice(dialog.dataset.doneTpl, plan.placed);
                        doneNotice.hidden = false;
                        // Lo que el pegado no puede poner es la EDAD: se abre la primera ficha y el foco va al
                        // primer campo obligatorio que siga vacío.
                        setOpen(touched[0]);
                        const next = [...touched[0].querySelectorAll('[data-required]')].find((el) => !filled(el));
                        (next || opener).focus();
                    });
                }

                // ── EXTRAS y NÚMERO DE INVITADOS: el stepper (`#413` T3, `#444`) ─────────────────────────
                // ⚠️⚠️ Esto es DECORADO, no el suelo. El control real es el `input[type=number]` que ya está en
                // el HTML: sin JavaScript se teclea la cantidad y se guarda igual. Y el importe que vale es el
                // que recalcula el SERVIDOR (`PAY-12`); esto solo lo anticipa.
                const extrasTotal = document.querySelector('[data-extras-total]');
                const extrasChosen = document.querySelector('[data-extras-chosen]');
                const extraInputs = [...document.querySelectorAll('[data-extra-price]')];

                // El formato lo pinta el servidor; aquí solo se sustituye el número.
                const moneyFromPage = (cents) => (cents / 100).toFixed(2).replace('.', ',') + ' €';

                function refreshExtras() {
                    if (extrasTotal) {
                        const cents = extraInputs.reduce((sum, input) => {
                            const qty = parseInt(input.value, 10);
                            return !isNaN(qty) && qty > 0 ? sum + qty * parseInt(input.dataset.extraPrice, 10) : sum;
                        }, 0);
                        extrasTotal.textContent = moneyFromPage(cents);
                    }
                    if (extrasChosen) {
                        // Cuenta TODOS los extras con cantidad, también los cerrados, igual que el servidor.
                        const chosen = [...form.querySelectorAll('input[name^="addons["][name$="[quantity]"]')]
                            .filter((input) => (parseInt(input.value, 10) || 0) > 0).length;
                        extrasChosen.textContent = choice(extrasChosen.dataset.tpl, chosen);
                    }
                }

                function stepper(input, onChange) {
                    const min = parseInt(input.getAttribute('min'), 10) || 0;
                    const max = parseInt(input.getAttribute('max'), 10);
                    const wrap = document.createElement('span');
                    wrap.className = 'gf-extra__step';
                    let sync = () => {};

                    const button = (label, delta) => {
                        const b = document.createElement('button');
                        b.type = 'button';           // dentro de un <form>, un <button> sin type ENVÍA
                        b.textContent = label;
                        b.setAttribute('aria-hidden', 'true');   // el control accesible es el input
                        b.tabIndex = -1;
                        b.addEventListener('click', () => {
                            let next = (parseInt(input.value, 10) || 0) + delta;
                            if (next < min) next = min;
                            if (!isNaN(max) && next > max) next = max;
                            input.value = String(next);
                            onChange();
                            sync();
                        });
                        return b;
                    };
                    const minus = button('−', -1);
                    const plus = button('+', 1);
                    sync = () => {
                        const qty = parseInt(input.value, 10) || 0;
                        minus.disabled = qty <= min;
                        plus.disabled = !isNaN(max) && qty >= max;
                    };

                    input.addEventListener('input', () => { onChange(); sync(); });
                    input.parentNode.insertBefore(wrap, input);
                    wrap.append(minus, input, plus);
                    sync();
                }

                extraInputs.forEach((input) => stepper(input, refreshExtras));
                refreshExtras();

                // ⚠️⚠️ BAJAR INVITADOS DESTRUYE FICHAS, y se dice ANTES de guardar (`#444`). Se cuentan las
                // fichas RELLENAS y no las filas, y POR POSICIÓN (`byIndex`): las que se pierden son las del
                // final de la lista, y la página ya no las enseña en ese orden.
                // ⚠️ En el DOCUMENTO y no dentro del formulario: el campo vive en el resguardo (ver su `form=`).
                const countInput = document.querySelector('[data-guest-count]');
                const countWarn = document.getElementById('gf-count-warn');
                if (countInput && countWarn) {
                    const countWarnTpl = countWarn.dataset.tpl || '';
                    // El título del aviso es fijo (`#570`): lo que se reescribe es SU FRASE.
                    const countWarnText = countWarn.querySelector('[data-warn-text]') || countWarn;
                    stepper(countInput, () => {
                        const wanted = parseInt(countInput.value, 10);
                        const current = parseInt(countInput.dataset.current, 10) || 0;
                        if (!wanted || wanted >= current) {
                            countWarn.hidden = true;
                            return;
                        }
                        const lost = byIndex.slice(wanted).filter((fiche) => !isEmpty(fiche)).length;
                        countWarn.hidden = lost === 0;
                        // Singular y plural por las fichas que se pierden («de 1 fichas» era la forma única de antes).
                        countWarnText.textContent = choice(countWarnTpl, lost, { count: wanted, discarded: lost });
                    });
                }

                // Acordeón ya cableado → modo JS. La clase `js` se marca AL FINAL: si algo de arriba falla, el
                // documento se queda en `no-js` (todo abierto y usable). `gf-initing` suprime la animación del
                // colapso inicial. Se abre la primera PENDIENTE; si están todas listas, ninguna.
                de.classList.add('gf-initing');
                de.classList.remove('no-js');
                de.classList.add('js');
                setOpen(fiches.find((fiche) => !stateOf(fiche).complete) || null);
                updateProgress();
                requestAnimationFrame(() => de.classList.remove('gf-initing'));
            } catch (error) {
                // Fallo del acordeón → reponer no-js: fichas abiertas y formulario usable (nunca atascado).
                de.classList.remove('js', 'gf-initing');
                de.classList.add('no-js');
            }
        }

        // ───── COMPARTIR LA INVITACIÓN (T6·1, §4.7) ─────
        //
        // ⚠️⚠️ **En su propio `try` y FUERA del `if` del acordeón**: son dos mejoras independientes, y
        // una reserva sin fichas —o un fallo del acordeón— no puede dejar al anfitrión sin repartir su
        // fiesta. El enlace ya está escrito en la página; esto solo añade los dos atajos.
        //
        // ⚠️ Los botones nacen `hidden` y se encienden **por lo que el navegador tiene**: `share` no
        // existe en casi ningún escritorio y el portapapeles necesita contexto seguro. Pintar un botón
        // que no hace nada es peor que no pintarlo.
        //
        // ⚠️ `navigator.share` se invoca DENTRO del gesto, sin `await` previo: Safari exige activación
        // del usuario y una promesa intermedia la pierde — el mismo motivo por el que la invitación
        // nace en el GET y no en un `fetch` (§4.5·1).
        try {
            const invite = document.querySelector('[data-invite]');
            if (invite) {
                const url = invite.dataset.url || '';
                const text = invite.dataset.text || '';
                const shareBtn = invite.querySelector('[data-invite-share]');
                const copyBtn = invite.querySelector('[data-invite-copy]');
                const said = invite.querySelector('[data-invite-said]');

                if (shareBtn && url && navigator.share) {
                    shareBtn.hidden = false;
                    shareBtn.addEventListener('click', () => {
                        // Sin `catch` no: cancelar el diálogo del sistema RECHAZA la promesa, y un
                        // rechazo sin capturar es un error en la consola por cerrar un menú.
                        navigator.share({ text, url }).catch(() => {});
                    });
                }

                if (copyBtn && url && navigator.clipboard) {
                    copyBtn.hidden = false;
                    const label = copyBtn.textContent;
                    const copied = invite.dataset.copied || label;
                    copyBtn.addEventListener('click', () => {
                        navigator.clipboard.writeText(url).then(() => {
                            copyBtn.textContent = copied;
                            // Se ANUNCIA aparte: el cambio de rótulo de un botón que acaba de pulsarse
                            // no lo lee un lector de pantalla.
                            if (said) said.textContent = copied;
                            setTimeout(() => { copyBtn.textContent = label; }, 2500);
                        }).catch(() => {});
                    });
                }
            }
        } catch (error) {
            // El enlace escrito sigue ahí: sin los atajos, la página no pierde ninguna capacidad.
        }
    </script>
</x-focused-layout>
