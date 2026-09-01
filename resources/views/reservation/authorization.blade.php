@php
    /**
     * El JUSTIFICANTE de un menor INVITADO a una reserva — «waiver offshore»
     * (`docs/specs/waiver-por-reserva.md` §4.6, tanda T2).
     *
     * ⚠️ **Es una HOJA EN BLANCO y eso es una propiedad, no una carencia**: esta pantalla NO lista ni
     * un dato de los justificantes ya firmados. Por eso repartir su enlace a veinte padres no es una
     * fuga —como sí lo sería repartir el del post-form, que enseña los datos de todos los invitados—.
     * Si algún día alguien añade aquí «los que ya han firmado», rompe el motivo por el que existe.
     *
     * ⚠️ Sin JS: el formulario es un POST normal y funciona entero. El único JS de la página es el
     * widget de Turnstile, que se carga solo si la instalación tiene claves.
     */
    $status = session('guardian_status');
    $minorName = session('guardian_minor');
    $errors ??= new \Illuminate\Support\ViewErrorBag;
@endphp
<x-focused-layout :title="__('guardian.title')">
    <div class="gf-page">
        <div class="gf-mark">
            <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            <span class="gf-mark__sub">{{ __('guardian.title') }}</span>
        </div>

        <main class="gf-sheet">
            <div class="gf-form">

                {{-- ───── Desenlace del envío anterior ───── --}}
                @if ($status === 'signed')
                    <p class="guardian__notice" role="status">
                        {{ $minorName ? __('guardian.done.signed', ['name' => $minorName]) : __('guardian.done.signed_generic') }}
                    </p>
                @elseif ($status === 'already')
                    <p class="guardian__notice" role="status">
                        {{ $minorName ? __('guardian.done.already', ['name' => $minorName]) : __('guardian.done.already_generic') }}
                    </p>
                @elseif ($status === 'stale')
                    <p class="guardian__notice" role="alert">{{ __('guardian.done.stale') }}</p>
                @elseif (in_array($status, ['not_paid', 'closed', 'full'], true))
                    {{-- El dominio rechazó bajo el lock lo que la pantalla creía posible: entre pintar
                         y enviar cambió el mundo. Se dice con la misma frase que el estado bloqueado. --}}
                    <p class="guardian__notice" role="alert">{{ __('guardian.blocked.'.$status) }}</p>
                @endif

                {{-- ───── La reserva que se autoriza ───── --}}
                <section class="gf-group">
                    <div class="gf-group__head">
                        <h1 class="gf-group__title">{{ __('guardian.booking.heading') }}</h1>
                    </div>
                    <p class="guestform__privacy">
                        <strong>{{ __('guardian.booking.reference') }}:</strong> {{ $context->code }}
                        @if (count($context->visitDates) === 1)
                            · <strong>{{ __('guardian.booking.date') }}:</strong> {{ \App\Domain\Platform\Services\DisplayTime::format(\Illuminate\Support\Carbon::parse($context->visitDates[0]), 'd/m/Y') }}
                        @elseif (count($context->visitDates) > 1)
                            · <strong>{{ __('guardian.booking.dates') }}:</strong>
                            {{ collect($context->visitDates)->map(fn (string $d): string => \App\Domain\Platform\Services\DisplayTime::format(\Illuminate\Support\Carbon::parse($d), 'd/m/Y'))->implode(' · ') }}
                        @else
                            · {{ __('guardian.booking.no_date') }}
                        @endif
                    </p>
                </section>

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
                    <p class="guestform__privacy">{{ __('guardian.intro') }}</p>

                    <form method="POST" action="{{ $formAction }}" novalidate>
                        @csrf
                        <input type="hidden" name="document_id" value="{{ $document->getKey() }}">

                        {{-- Honeypot: un campo que ninguna persona ve y que un bot rellena. Mismo
                             patrón que `/contacto` y el alta. --}}
                        <div class="gf-sr-only" aria-hidden="true">
                            <label for="website">Web</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        {{-- ───── El menor ───── --}}
                        <section class="gf-group">
                            <div class="gf-group__head">
                                <h2 class="gf-group__title">{{ __('guardian.minor.heading') }}</h2>
                            </div>
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

                        {{-- ───── Quien firma ───── --}}
                        <section class="gf-group">
                            <div class="gf-group__head">
                                <h2 class="gf-group__title">{{ __('guardian.guardian.heading') }}</h2>
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

                        {{-- ───── El texto que se firma ─────
                             Se presenta EN EL PROPIO FLUJO (`waiver-probatorio.md` §4.4), no detrás de
                             un modal: obligar a abrirlo se RETIRÓ del diseño porque un booleano que
                             envía el navegador no prueba nada y rompe el flujo de teclado. Lo que se
                             prueba es que se le PRESENTÓ y que lo aceptó explícitamente. --}}
                        <section class="gf-group">
                            <div class="gf-group__head">
                                <h2 class="gf-group__title">{{ __('guardian.waiver.heading') }}</h2>
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
</x-focused-layout>
