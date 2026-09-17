@php
    /**
     * El JUSTIFICANTE de un menor INVITADO a una reserva — «waiver offshore»
     * (`docs/specs/waiver-por-reserva.md` §4.6 y §12.4/§12.5, tandas T2 y T8).
     *
     * ⚠️ **Es una HOJA EN BLANCO y eso es una propiedad, no una carencia**: esta pantalla NO lista ni
     * un dato de los justificantes ya firmados. Por eso repartir su enlace a veinte padres no es una
     * fuga —como sí lo sería repartir el del post-form, que enseña los datos de todos los invitados—.
     * Si algún día alguien añade aquí «los que ya han firmado», rompe el motivo por el que existe.
     *
     * ⚠️ Sin JS: el formulario es un POST normal y funciona ENTERO. Lo único que el JS añade es el
     * selector de menores a cargo, que es una comodidad —rellena campos que se pueden teclear— y por
     * eso vive detrás de `html.js`.
     *
     * ▶ **T8 · la cabecera.** Antes esta pantalla resolvía la reserva con UN PÁRRAFO mientras su
     * hermana —el post-form— tenía un resguardo con badge, titular y tres celdas. Ahora hablan el
     * mismo idioma, y la cabecera dice **las dos cosas que un padre necesita saber antes de firmar**:
     * a qué visita va su hijo y CON QUIÉN.
     */
    $status = session('guardian_status');
    $minorName = session('guardian_minor');
    $errors ??= new \Illuminate\Support\ViewErrorBag;

    // ⚠️⚠️ **UNA visita, no las del pedido** (`#401`). Antes esto pintaba `visitDates` —las fechas de
    // TODAS las líneas del pedido— y con una excursión el lunes y una entrada el miércoles la hoja
    // decía «Días de la visita: 03/09/2026 · 07/09/2026» sin decir a cuál iba el niño. Lo cazó el
    // owner con un pedido real delante.
    $dayLabel = $context->date === null
        ? __('guardian.booking.no_date')
        : \App\Domain\Platform\Services\DisplayTime::dayLabel(\Illuminate\Support\Carbon::parse($context->date));

    // ▶ **T3 · la piel** (`docs/specs/celebracion-e-invitacion.md` §4.3, grietas J-01…J-08 del canvas).
    // ⚠️⚠️ La hora llega COMPUESTA del dominio (`timeWindow`): esta vista la montaba con el fin de la
    // FRANJA y una fiesta de dos horas decía «17:00 – 18:00» (la trampa de `#426`).
    //
    // Los cinco desenlaces en cuatro tonos (J-02). El rechazo del dominio comparte tono y título con el
    // anti-robot porque dicen lo mismo: no se ha registrado nada.
    $refused = in_array($status, ['not_paid', 'closed', 'full'], true);
    $outcome = match (true) {
        $status === 'signed' => ['tone' => ' gf-notice--ok', 'role' => 'status', 'title' => __('guardian.done.signed_title'),
            'text' => $minorName ? __('guardian.done.signed', ['name' => $minorName]) : __('guardian.done.signed_generic')],
        $status === 'already' => ['tone' => '', 'role' => 'status', 'title' => __('guardian.done.already_title'),
            'text' => $minorName ? __('guardian.done.already', ['name' => $minorName]) : __('guardian.done.already_generic')],
        $status === 'stale' => ['tone' => ' gf-notice--attn', 'role' => 'alert', 'title' => __('guardian.done.stale_title'), 'text' => __('guardian.done.stale')],
        // Turnstile falla también a personas: se le DICE, no se le miente.
        $status === 'antibot' => ['tone' => ' gf-notice--err', 'role' => 'alert', 'title' => __('guardian.done.refused_title'), 'text' => __('guardian.done.antibot')],
        // El dominio rechazó bajo el lock lo que la pantalla creía posible: entre pintar y enviar cambió
        // el mundo. Se dice con la misma frase que el estado bloqueado.
        $refused => ['tone' => ' gf-notice--err', 'role' => 'alert', 'title' => __('guardian.done.refused_title'), 'text' => __('guardian.blocked.'.$status)],
        default => null,
    };

    // A QUIÉN se autoriza, en la barra de firmar. Sin JS solo se conoce al volver con errores.
    $whoName = trim(old('minor_name', '').' '.old('minor_surname', ''));
@endphp
<x-focused-layout :title="__('guardian.title')">
    <div class="gf-page">
        {{-- El logotipo entra como FICHERO por el mismo hueco que en la hoja hermana (J-05): es lo primero
             que ve alguien que no conoce esta web. Sin él, el nombre en la fuente de rótulo. --}}
        @php $clientLogo = @filemtime(public_path('img/client-logo.svg')); @endphp
        <div class="gf-mark">
            @if ($clientLogo)
                <img class="gf-mark__logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
                     alt="{{ $site['name'] ?? config('app.name') }}">
            @else
                <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            @endif
            <span class="gf-mark__sub">{{ __('guardian.title') }}</span>
        </div>

        <main class="gf-sheet">
            {{-- ───── El RESGUARDO: qué visita y quién responde ─────
                 Reutiliza `.gf-stub` del post-form entero: mismos tokens, mismo molde. Son la misma
                 clase de pantalla —enfocada, pública, sin nav— y darle a ésta una cabecera propia
                 sería inventar un segundo lenguaje para el mismo problema. --}}
            <div class="gf-stub" data-surface="ink">
                <div class="gf-stub__top">
                    <span class="gf-stub__badge">{{ __('guardian.stub.badge') }}</span>
                </div>

                <h1 class="gf-stub__title">{{ __('guardian.stub.heading') }}</h1>
                <p class="gf-stub__lede">{{ __('guardian.stub.lede') }}</p>

                {{-- ⚠️ **A QUÉ va el menor**, que es lo que faltaba y lo primero que un padre mira.
                     La hoja decía la referencia del pedido y las fechas de todas sus líneas; ahora
                     dice el producto, el día y la hora de ESTA visita. --}}
                {{-- ⚠️ Solo el PRODUCTO: el día y la hora viven en su celda de abajo, y la captura
                     enseñó que decirlos aquí también los repetía dos veces en cuatro centímetros. --}}
                <p class="guardian__what" data-guardian-what>
                    <strong>{{ $context->productName }}</strong>
                </p>

                <div class="gf-stub__meta">
                    <div class="gf-stub__cell">
                        <span class="k">{{ __('guardian.booking.reference') }}</span>
                        <span class="v mono">{{ $context->orderCode }}</span>
                    </div>
                    <div class="gf-stub__cell">
                        <span class="k">{{ __('guardian.booking.date') }}</span>
                        <span class="v">{{ $dayLabel }}</span>
                        @if ($context->timeWindow)
                            <span class="v mono">{{ $context->timeWindow }}</span>
                        @endif
                    </div>
                    {{-- §12.4, `[DECIDIDO owner]`: **quién responde del menor durante la visita**. Un
                         padre está confiando a su hijo a un adulto que no es él, y hasta la T8 esta
                         pantalla no decía ni quién era.
                         ⚠️⚠️ Nombre y TELÉFONO, **nunca el correo**: este enlace lo reparte el propio
                         responsable a gente que no conocemos.
                         ⚠️ Sin apellidos, y no es un olvido: `users` tiene UNA columna `name`. --}}
                    <div class="gf-stub__cell">
                        <span class="k">{{ __('guardian.booking.responsible') }}</span>
                        <span class="v">{{ $responsible['name'] !== '' ? $responsible['name'] : '—' }}</span>
                        @if ($responsible['phone'] !== '')
                            <span class="v mono">{{ $responsible['phone'] }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="gf-form">

                {{-- ───── Desenlace del envío anterior ─────
                     El aviso sobre papel de la hoja (`.gf-notice`, `#570`): un tono por desenlace y el
                     título delante. Antes tres de los cinco salían con el mismo gris, y «ha quedado
                     registrada» se leía igual que «NO hemos registrado nada» (J-02). --}}
                @if ($outcome !== null)
                    <div class="gf-notice{{ $outcome['tone'] }}" role="{{ $outcome['role'] }}" data-guardian-outcome="{{ $status }}">
                        <p class="gf-notice__title">{{ $outcome['title'] }}</p>
                        <p class="gf-notice__text">{{ $outcome['text'] }}</p>
                    </div>
                @endif

                @if ($blocked !== null)
                    {{-- No se puede firmar: se dice POR QUÉ y no se pinta el formulario. La puerta que
                         manda sigue estando en el dominio; esto solo evita un envío inútil. --}}
                    <section class="gf-group">
                        <div class="gf-group__head">
                            <h2 class="gf-group__title">{{ __('guardian.blocked.heading') }}</h2>
                        </div>
                        <p class="guardian__text">{{ __('guardian.blocked.'.$blocked) }}</p>
                        {{-- Sin formulario la política sigue siendo el único control legal de la página. --}}
                        <a class="gf-legal" href="{{ route('legal.privacidad') }}">{{ __('guardian.privacy_link') }}</a>
                    </section>
                @else
                    {{-- ⚠️ **El `guardian.intro` de la T2 se RETIRA aquí, y lo vio la captura.** Decía
                         «rellena este formulario para autorizar la entrada de un menor **a tu cargo**»
                         justo debajo del `lede` del resguardo, que ya dice lo mismo y mejor: dos
                         párrafos seguidos explicando la misma cosa. Y además chocaba con el
                         vocabulario del producto — «menor a cargo» es en este sistema el hijo que uno
                         tiene DECLARADO en su cuenta, que es exactamente lo que este menor NO es.
                         ▶ **La clave se retira de `lang/` con la línea**: se comprobó que no tenía otro
                         consumidor antes de tocarla, y una clave sin pantalla es peso muerto que el
                         siguiente agente tiene que descartar. --}}

                    {{-- ⚠️ El `<form>` lleva también `.gf-form`: el hueco entre grupos es del contenedor, y sin
                         la clase los tres pasos y el cierre iban PEGADOS (la ayuda de la fecha tocaba el
                         ordinal del paso 2). Lo enseñó la captura de la T3; ningún test mira el aire. --}}
                    <form class="gf-form" method="POST" action="{{ $formAction }}" novalidate>
                        @csrf
                        <input type="hidden" name="document_id" value="{{ $document->getKey() }}">

                        {{-- Honeypot: un campo que ninguna persona ve y que un bot rellena.
                             ⚠️ NO se llama `website` (como en `/contacto`): ese nombre mapea al tipo de
                             autocompletado `url` y un gestor de contraseñas puede rellenárselo a una
                             persona real — aquí eso costaría una prueba legal que su firmante cree
                             tener. --}}
                        <div class="gf-sr-only" aria-hidden="true">
                            <label for="contact_ref">Ref</label>
                            <input type="text" id="contact_ref" name="contact_ref" tabindex="-1" autocomplete="off">
                        </div>

                        {{-- ───── 1 · El menor ───── --}}
                        <section class="gf-group">
                            <div class="gf-group__head">
                                <h2 class="gf-group__title"><span class="gf-group__num">1</span> {{ __('guardian.minor.heading') }}</h2>
                            </div>

                            {{-- §12.5 — **con sesión, el menor se ELIGE.** `Dependent` tiene exactamente
                                 los cuatro campos que este bloque pide, así que un padre registrado lo
                                 rellena de un clic (relación incluida).
                                 ⚠️ **Es una COMODIDAD y va detrás de `html.js`**: sin JS los campos se
                                 teclean y el formulario funciona igual. Un selector que fuera la única
                                 vía dejaría fuera a quien tenga el JS caído — que es justo el caso que
                                 destapó el defecto del anti-bot en la T2.
                                 ⚠️ **No enlaza la cuenta con la firma** (§4.3): copia el dato y ya. --}}
                            @if ($dependents !== [])
                                <label class="eventfields__field guardian__pick" for="dependent_pick" data-guardian-pick>
                                    <span class="eventfields__label">{{ __('guardian.minor.pick') }}</span>
                                    <select id="dependent_pick" data-guardian-pick-select>
                                        <option value="">{{ __('guardian.minor.pick_manual') }}</option>
                                        @foreach ($dependents as $dependent)
                                            <option value="{{ $dependent['id'] }}"
                                                    data-name="{{ $dependent['name'] }}"
                                                    data-surname="{{ $dependent['surname'] }}"
                                                    data-born-on="{{ $dependent['born_on'] }}"
                                                    data-relationship="{{ $dependent['relationship'] }}">{{ $dependent['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <span class="eventfields__help">{{ __('guardian.minor.pick_help') }}</span>
                                </label>
                            @endif

                            {{-- ⚠️ Sin asterisco (T3): de ocho campos dos son opcionales, así que se marca LO
                                 OPCIONAL, como en la hoja hermana; el asterisco iba sin leyenda y en el color
                                 de la zona. Y el ERROR va delante de la ayuda: es lo que hay que leer. --}}
                            <div class="eventfields">
                                <label class="eventfields__field @error('minor_name') is-invalid @enderror" for="minor_name">
                                    <span class="eventfields__label">{{ __('guardian.minor.name') }}</span>
                                    <input id="minor_name" name="minor_name" type="text" required autocomplete="off"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::NAME_MAX }}"
                                           value="{{ old('minor_name') }}">
                                    @error('minor_name')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('minor_surname') is-invalid @enderror" for="minor_surname">
                                    <span class="eventfields__label">{{ __('guardian.minor.surname') }}</span>
                                    <input id="minor_surname" name="minor_surname" type="text" required autocomplete="off"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::SURNAME_MAX }}"
                                           value="{{ old('minor_surname') }}">
                                    @error('minor_surname')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('minor_born_on') is-invalid @enderror" for="minor_born_on">
                                    <span class="eventfields__label">{{ __('guardian.minor.born_on') }}</span>
                                    <input id="minor_born_on" name="minor_born_on" type="date" required value="{{ old('minor_born_on') }}">
                                    @error('minor_born_on')<span class="eventfields__error">{{ $message }}</span>@enderror
                                    <span class="eventfields__help">{{ __('guardian.minor.born_on_help') }}</span>
                                </label>
                            </div>
                        </section>

                        {{-- ───── 2 · Quien firma ───── --}}
                        <section class="gf-group">
                            <div class="gf-group__head">
                                <h2 class="gf-group__title"><span class="gf-group__num">2</span> {{ __('guardian.guardian.heading') }}</h2>
                            </div>
                            <p class="guardian__text">{{ __('guardian.guardian.help') }}</p>
                            <div class="eventfields">
                                <label class="eventfields__field @error('guardian_name') is-invalid @enderror" for="guardian_name">
                                    <span class="eventfields__label">{{ __('guardian.guardian.name') }}</span>
                                    <input id="guardian_name" name="guardian_name" type="text" required autocomplete="given-name"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::NAME_MAX }}"
                                           value="{{ old('guardian_name', $prefill['guardian_name']) }}">
                                    @error('guardian_name')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('guardian_surname') is-invalid @enderror" for="guardian_surname">
                                    <span class="eventfields__label">{{ __('guardian.guardian.surname') }}</span>
                                    <input id="guardian_surname" name="guardian_surname" type="text" required autocomplete="family-name"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::SURNAME_MAX }}"
                                           value="{{ old('guardian_surname') }}">
                                    @error('guardian_surname')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('guardian_relationship') is-invalid @enderror" for="guardian_relationship">
                                    <span class="eventfields__label">{{ __('guardian.guardian.relationship') }}</span>
                                    <select id="guardian_relationship" name="guardian_relationship" required>
                                        <option value="">{{ __('guardian.guardian.relationship_placeholder') }}</option>
                                        @foreach ($relationships as $relationship)
                                            <option value="{{ $relationship }}" @selected(old('guardian_relationship') === $relationship)>
                                                {{ __('guardian.relationships.'.$relationship) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('guardian_relationship')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('guardian_email') is-invalid @enderror" for="guardian_email">
                                    <span class="eventfields__label">{{ __('guardian.guardian.email') }} <span class="gf-opt">{{ __('guestform.optional') }}</span></span>
                                    <input id="guardian_email" name="guardian_email" type="email" autocomplete="email"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::EMAIL_MAX }}"
                                           value="{{ old('guardian_email', $prefill['guardian_email']) }}">
                                    @error('guardian_email')<span class="eventfields__error">{{ $message }}</span>@enderror
                                    <span class="eventfields__help">{{ __('guardian.guardian.email_help') }}</span>
                                </label>

                                <label class="eventfields__field @error('guardian_phone') is-invalid @enderror" for="guardian_phone">
                                    <span class="eventfields__label">{{ __('guardian.guardian.phone') }} <span class="gf-opt">{{ __('guestform.optional') }}</span></span>
                                    <input id="guardian_phone" name="guardian_phone" type="tel" autocomplete="tel"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::PHONE_MAX }}"
                                           value="{{ old('guardian_phone', $prefill['guardian_phone']) }}">
                                    @error('guardian_phone')<span class="eventfields__error">{{ $message }}</span>@enderror
                                    <span class="eventfields__help">{{ __('guardian.guardian.phone_help') }}</span>
                                </label>
                            </div>
                        </section>

                        {{-- ───── 3 · El texto que se firma ─────
                             Se presenta EN EL PROPIO FLUJO (`waiver-probatorio.md` §4.4), no detrás de
                             un modal: obligar a abrirlo se RETIRÓ del diseño porque un booleano que
                             envía el navegador no prueba nada y rompe el flujo de teclado. Lo que se
                             prueba es que se le PRESENTÓ y que lo aceptó explícitamente. --}}
                        <section class="gf-group">
                            <div class="gf-group__head">
                                <h2 class="gf-group__title"><span class="gf-group__num">3</span> {{ __('guardian.waiver.heading') }}</h2>
                            </div>
                            {{-- La versión en SU línea, debajo del titular: a su derecha lo estrangulaba
                                 en un teléfono, y al partir quedaba sola y alineada al otro lado. --}}
                            <span class="guardian__version">{{ __('guardian.waiver.version', [
                                'version' => $document->version,
                                'date' => \App\Domain\Platform\Services\DisplayTime::format($document->published_at, 'd/m/Y'),
                            ]) }}</span>

                            <div class="guardian__waiver">
                                <h3 class="guardian__waiver-title">{{ $document->title }}</h3>
                                @foreach ($document->body as $section)
                                    @if (! empty($section['h']))
                                        <h4 class="guardian__waiver-h">{{ $section['h'] }}</h4>
                                    @endif
                                    @if (! empty($section['p']))
                                        <p class="guardian__waiver-p">{{ $section['p'] }}</p>
                                    @endif
                                @endforeach
                            </div>

                            {{-- Casilla SEPARADA y DESMARCADA por defecto (§4.4).
                                 ▶ Es la casilla del SISTEMA (J-03): 24, y marcada, relleno de tinta con el ✓
                                 en papel. Era un `input` de navegador de 13 px, y es el gesto legal de toda
                                 la pantalla. Sigue siendo el `input` —con `appearance: none`—, así que el
                                 teclado, el `required` y el lector de pantalla no se enteran del cambio. --}}
                            <label class="guardian__accept @error('accept_waiver') is-invalid @enderror" for="accept_waiver">
                                <input type="checkbox" class="guardian__check" id="accept_waiver" name="accept_waiver" value="1" required>
                                <span>{{ __('guardian.waiver.accept') }}</span>
                            </label>
                            @error('accept_waiver')<span class="eventfields__error">{{ $message }}</span>@enderror
                        </section>

                        {{-- ───── El cierre: lo legal, EN EL FLUJO y no en la barra ─────
                             ⚠️⚠️ Estos dos párrafos y el anti-robot vivían DENTRO de `.gf-savebar`, y la T2
                             (`#571`) hizo esa barra pegada y en fila para la hoja hermana: medido a
                             390 × 844, aquí ocupaba 401 px pegada abajo y el botón se salía 65 px de la
                             pantalla, sin que fallara ningún test. `GuardianSkinTest` mira qué lleva la barra. --}}
                        <div class="guardian__close">
                            <p class="guestform__privacy">{{ __('guardian.notice') }}</p>
                            {{-- Deber de información (art. 13): quien rellena esto es un tercero que
                                 no ha aceptado nada antes y está entregando datos de un MENOR. La
                                 política se enlaza, no se resume — y FUERA de su frase, como control
                                 propio de 48 (J-07). --}}
                            <div class="gf-intro">
                                <p class="guestform__privacy">{{ __('guardian.privacy') }}</p>
                                <a class="gf-legal" href="{{ route('legal.privacidad') }}">{{ __('guardian.privacy_link') }}</a>
                            </div>

                            {{-- El anti-robot es la caja de un TERCERO: no se recolorea ni se redibuja, y se
                                 DICE qué es (J-08). Segunda excepción declarada del sistema, tras el botón
                                 de Google. Le falla a personas, y aquí un fallo es un niño que no entra. --}}
                            @if (\App\Domain\Platform\Services\Turnstile::enabled())
                                <div class="guardian__third">
                                    <span class="guardian__third-label">{{ __('guardian.antibot_label') }}</span>
                                    <div class="cf-turnstile" data-sitekey="{{ \App\Domain\Platform\Services\Turnstile::siteKey() }}"></div>
                                </div>
                                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                            @endif
                        </div>

                        {{-- ───── La BARRA de firmar, pegada abajo (J-01) ─────
                             Es la que permite leer el descargo ENTERO: el botón ya no puede quedarse fuera
                             de la pantalla. A la izquierda, A QUIÉN se autoriza —lo único que hay que releer
                             antes de firmar—; con JS sigue a lo que se teclea, y sin él aparece al volver
                             con errores. ⚠️ Solo eso y el botón: un párrafo aquí dentro es media pantalla.
                             ▶ «Firmar» y no el rótulo largo, que no cabe junto a un nombre a 390; el largo
                             se queda de nombre accesible (contiene al visible). En el SECUNDARIO del sistema
                             como «Guardar» en la hoja hermana (`#539`): aquí tampoco se compra nada. --}}
                        <div class="gf-savebar">
                            <span class="gf-savebar__count guardian__who" data-guardian-who @if ($whoName === '') hidden @endif>
                                <span class="gf-savebar__label">{{ __('guardian.bar.minor') }}</span>
                                <span class="guardian__who-name" data-guardian-who-name>{{ $whoName }}</span>
                            </span>
                            <button type="submit" class="btn btn--lg btn--ink" aria-label="{{ __('guardian.submit') }}">{{ __('guardian.submit_short') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </main>
    </div>

    @if ($blocked === null)
        {{-- Dos comodidades EN LÍNEA y sin dependencias: el nombre del menor en la barra de firmar, y el
             selector de menores a cargo (solo con sesión).
             ⚠️ Va aquí y no en un módulo del cajón porque esta pantalla **no carga el cajón**: es una
             hoja enfocada y pública, y traerse 265 KiB de motor SPA para rellenar cuatro campos sería
             pagar el presupuesto entero de la compra por una comodidad.
             ⚠️ El selector **rellena y NO envía**: quien firma revisa lo que ha quedado puesto. Y el
             `change` que vuelve a «a mano» **vacía**, para que no queden datos de otro niño. --}}
        <script>
            (function () {
                var who = document.querySelector('[data-guardian-who]');
                var whoName = document.querySelector('[data-guardian-who-name]');
                var field = function (id) { return document.getElementById(id); };

                // A QUIÉN se autoriza: nombre y apellidos tal como están escritos ahora mismo.
                var sync = function () {
                    if (! who || ! whoName) return;
                    var parts = [field('minor_name'), field('minor_surname')]
                        .map(function (el) { return el ? el.value.trim() : ''; })
                        .filter(function (v) { return v !== ''; });
                    whoName.textContent = parts.join(' ');
                    who.hidden = parts.length === 0;
                };
                ['minor_name', 'minor_surname'].forEach(function (id) {
                    var el = field(id);
                    if (el) el.addEventListener('input', sync);
                });
                sync();
                @if ($dependents !== [])

                // ⚠️ Este tramo solo se EMITE con menores a cargo: sin sesión la página no nombra el
                // selector ni en su script (lo asevera `GuardianAuthorizationScreenTest`).
                var pick = document.querySelector('[data-guardian-pick-select]');
                if (! pick) return;

                var set = function (id, value) {
                    var el = field(id);
                    if (el) el.value = value || '';
                };

                pick.addEventListener('change', function () {
                    var opt = pick.options[pick.selectedIndex];
                    var chosen = opt && opt.value !== '';

                    set('minor_name', chosen ? opt.dataset.name : '');
                    set('minor_surname', chosen ? opt.dataset.surname : '');
                    set('minor_born_on', chosen ? opt.dataset.bornOn : '');
                    // La relación es del ADULTO con el menor, así que vive en el bloque 2 — pero es un
                    // dato que la ficha del menor a cargo ya tiene declarado, y volver a preguntarlo
                    // sería pedir dos veces lo mismo. Solo se pone si la ficha lo trae.
                    if (chosen && opt.dataset.relationship) set('guardian_relationship', opt.dataset.relationship);
                    // Rellenar por código no dispara `input`: la barra se pone al día a mano.
                    sync();
                });
                @endif
            })();
        </script>
    @endif
</x-focused-layout>
