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

    $dates = collect($context->visitDates)
        ->map(fn (string $d): string => \App\Domain\Platform\Services\DisplayTime::format(\Illuminate\Support\Carbon::parse($d), 'd/m/Y'));
@endphp
<x-focused-layout :title="__('guardian.title')">
    <div class="gf-page">
        <div class="gf-mark">
            <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            <span class="gf-mark__sub">{{ __('guardian.title') }}</span>
        </div>

        <main class="gf-sheet">
            {{-- ───── El RESGUARDO: qué visita y quién responde ─────
                 Reutiliza `.gf-stub` del post-form entero: mismos tokens, mismo molde. Son la misma
                 clase de pantalla —enfocada, pública, sin nav— y darle a ésta una cabecera propia
                 sería inventar un segundo lenguaje para el mismo problema. --}}
            <div class="gf-stub">
                <div class="gf-stub__top">
                    <span class="gf-stub__badge">{{ __('guardian.stub.badge') }}</span>
                </div>

                <h1 class="gf-stub__title">{{ __('guardian.stub.heading') }}</h1>
                <p class="gf-stub__lede">{{ __('guardian.stub.lede') }}</p>

                <div class="gf-stub__meta">
                    <div class="gf-stub__cell">
                        <span class="k">{{ __('guardian.booking.reference') }}</span>
                        <span class="v mono">{{ $context->code }}</span>
                    </div>
                    <div class="gf-stub__cell">
                        <span class="k">{{ $dates->count() > 1 ? __('guardian.booking.dates') : __('guardian.booking.date') }}</span>
                        <span class="v">{{ $dates->isEmpty() ? __('guardian.booking.no_date') : $dates->implode(' · ') }}</span>
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

            <div class="gf-perf"></div>

            <div class="gf-form">

                {{-- ───── Desenlace del envío anterior ───── --}}
                @if ($status === 'signed')
                    <p class="guardian__notice guardian__notice--ok" role="status">
                        {{ $minorName ? __('guardian.done.signed', ['name' => $minorName]) : __('guardian.done.signed_generic') }}
                    </p>
                @elseif ($status === 'already')
                    <p class="guardian__notice" role="status">
                        {{ $minorName ? __('guardian.done.already', ['name' => $minorName]) : __('guardian.done.already_generic') }}
                    </p>
                @elseif ($status === 'stale')
                    <p class="guardian__notice guardian__notice--bad" role="alert">{{ __('guardian.done.stale') }}</p>
                @elseif ($status === 'antibot')
                    {{-- Turnstile falla también a personas: se le DICE, no se le miente. --}}
                    <p class="guardian__notice guardian__notice--bad" role="alert">{{ __('guardian.done.antibot') }}</p>
                @elseif (in_array($status, ['not_paid', 'closed', 'full'], true))
                    {{-- El dominio rechazó bajo el lock lo que la pantalla creía posible: entre pintar
                         y enviar cambió el mundo. Se dice con la misma frase que el estado bloqueado. --}}
                    <p class="guardian__notice guardian__notice--bad" role="alert">{{ __('guardian.blocked.'.$status) }}</p>
                @endif

                @if ($blocked !== null)
                    {{-- No se puede firmar: se dice POR QUÉ y no se pinta el formulario. La puerta que
                         manda sigue estando en el dominio; esto solo evita un envío inútil. --}}
                    <section class="gf-group">
                        <div class="gf-group__head">
                            <h2 class="gf-group__title">{{ __('guardian.blocked.heading') }}</h2>
                        </div>
                        <p class="guestform__privacy">{{ __('guardian.blocked.'.$blocked) }}</p>
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

                    <form method="POST" action="{{ $formAction }}" novalidate>
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

                            <div class="eventfields">
                                <label class="eventfields__field @error('minor_name') is-invalid @enderror" for="minor_name">
                                    <span class="eventfields__label">{{ __('guardian.minor.name') }} <span class="eventfields__req" aria-hidden="true">*</span></span>
                                    <input id="minor_name" name="minor_name" type="text" required autocomplete="off"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::NAME_MAX }}"
                                           value="{{ old('minor_name') }}">
                                    @error('minor_name')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('minor_surname') is-invalid @enderror" for="minor_surname">
                                    <span class="eventfields__label">{{ __('guardian.minor.surname') }} <span class="eventfields__req" aria-hidden="true">*</span></span>
                                    <input id="minor_surname" name="minor_surname" type="text" required autocomplete="off"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::SURNAME_MAX }}"
                                           value="{{ old('minor_surname') }}">
                                    @error('minor_surname')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('minor_born_on') is-invalid @enderror" for="minor_born_on">
                                    <span class="eventfields__label">{{ __('guardian.minor.born_on') }} <span class="eventfields__req" aria-hidden="true">*</span></span>
                                    <input id="minor_born_on" name="minor_born_on" type="date" required value="{{ old('minor_born_on') }}">
                                    <span class="eventfields__help">{{ __('guardian.minor.born_on_help') }}</span>
                                    @error('minor_born_on')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>
                            </div>
                        </section>

                        {{-- ───── 2 · Quien firma ───── --}}
                        <section class="gf-group">
                            <div class="gf-group__head">
                                <h2 class="gf-group__title"><span class="gf-group__num">2</span> {{ __('guardian.guardian.heading') }}</h2>
                            </div>
                            <p class="guestform__privacy">{{ __('guardian.guardian.help') }}</p>
                            <div class="eventfields">
                                <label class="eventfields__field @error('guardian_name') is-invalid @enderror" for="guardian_name">
                                    <span class="eventfields__label">{{ __('guardian.guardian.name') }} <span class="eventfields__req" aria-hidden="true">*</span></span>
                                    <input id="guardian_name" name="guardian_name" type="text" required autocomplete="given-name"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::NAME_MAX }}"
                                           value="{{ old('guardian_name', $prefill['guardian_name']) }}">
                                    @error('guardian_name')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('guardian_surname') is-invalid @enderror" for="guardian_surname">
                                    <span class="eventfields__label">{{ __('guardian.guardian.surname') }} <span class="eventfields__req" aria-hidden="true">*</span></span>
                                    <input id="guardian_surname" name="guardian_surname" type="text" required autocomplete="family-name"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::SURNAME_MAX }}"
                                           value="{{ old('guardian_surname') }}">
                                    @error('guardian_surname')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('guardian_relationship') is-invalid @enderror" for="guardian_relationship">
                                    <span class="eventfields__label">{{ __('guardian.guardian.relationship') }} <span class="eventfields__req" aria-hidden="true">*</span></span>
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
                                    <span class="eventfields__label">{{ __('guardian.guardian.email') }}</span>
                                    <input id="guardian_email" name="guardian_email" type="email" autocomplete="email"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::EMAIL_MAX }}"
                                           value="{{ old('guardian_email', $prefill['guardian_email']) }}">
                                    <span class="eventfields__help">{{ __('guardian.guardian.email_help') }}</span>
                                    @error('guardian_email')<span class="eventfields__error">{{ $message }}</span>@enderror
                                </label>

                                <label class="eventfields__field @error('guardian_phone') is-invalid @enderror" for="guardian_phone">
                                    <span class="eventfields__label">{{ __('guardian.guardian.phone') }}</span>
                                    <input id="guardian_phone" name="guardian_phone" type="tel" autocomplete="tel"
                                           maxlength="{{ \App\Domain\Identity\Models\GuardianAuthorization::PHONE_MAX }}"
                                           value="{{ old('guardian_phone', $prefill['guardian_phone']) }}">
                                    <span class="eventfields__help">{{ __('guardian.guardian.phone_help') }}</span>
                                    @error('guardian_phone')<span class="eventfields__error">{{ $message }}</span>@enderror
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
                                <span class="gf-group__opt">{{ __('guardian.waiver.version', [
                                    'version' => $document->version,
                                    'date' => \App\Domain\Platform\Services\DisplayTime::format($document->published_at, 'd/m/Y'),
                                ]) }}</span>
                            </div>

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

                            {{-- Casilla SEPARADA y DESMARCADA por defecto (§4.4). --}}
                            <label class="guardian__accept @error('accept_waiver') is-invalid @enderror" for="accept_waiver">
                                <input type="checkbox" id="accept_waiver" name="accept_waiver" value="1" required>
                                <span>{{ __('guardian.waiver.accept') }}</span>
                            </label>
                            @error('accept_waiver')<span class="eventfields__error">{{ $message }}</span>@enderror
                        </section>

                        <div class="gf-savebar">
                            <p class="guestform__privacy">{{ __('guardian.notice') }}</p>
                            {{-- Deber de información (art. 13): quien rellena esto es un tercero que
                                 no ha aceptado nada antes y está entregando datos de un MENOR. La
                                 política se enlaza, no se resume. --}}
                            <p class="guestform__privacy">
                                {!! __('guardian.privacy', [
                                    'link' => '<a href="'.e(route('legal.privacidad')).'">'.e(__('guardian.privacy_link')).'</a>',
                                ]) !!}
                            </p>

                            @if (\App\Domain\Platform\Services\Turnstile::enabled())
                                <div class="cf-turnstile" data-sitekey="{{ \App\Domain\Platform\Services\Turnstile::siteKey() }}"></div>
                                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                            @endif

                            <button type="submit" class="btn btn--lg">{{ __('guardian.submit') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </main>
    </div>

    @if ($blocked === null && $dependents !== [])
        {{-- El selector de menores a cargo, EN LÍNEA y sin dependencias.
             ⚠️ Va aquí y no en un módulo del cajón porque esta pantalla **no carga el cajón**: es una
             hoja enfocada y pública, y traerse 265 KiB de motor SPA para rellenar cuatro campos sería
             pagar el presupuesto entero de la compra por una comodidad.
             ⚠️ **Rellena y NO envía**: quien firma revisa lo que ha quedado puesto. Y el `change` que
             vuelve a «a mano» **vacía**, para que no queden datos de otro niño en el formulario. --}}
        <script>
            (function () {
                var pick = document.querySelector('[data-guardian-pick-select]');
                if (! pick) return;

                var set = function (id, value) {
                    var el = document.getElementById(id);
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
                });
            })();
        </script>
    @endif
</x-focused-layout>
