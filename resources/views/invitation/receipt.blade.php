@php
    /**
     * **El RECIBO de una respuesta** (`docs/specs/celebracion-e-invitacion.md` §4.5·6, T5·3;
     * `DECISIONES #703`): lo que se le ofrece a quien acaba de decir que su hijo viene.
     *
     * ❗❗ **Todo aquí es OPCIONAL, y eso es el diseño.** El padre ya ha hecho lo que se le pedía —decir
     * que viene—; esto son dos ofertas, no un segundo formulario obligatorio. Quien cierre la pestaña
     * sin tocar nada ha terminado bien.
     *
     * ⚠️⚠️ **Dura DOS HORAS** (D9). La firma de la URL caduca sola y Laravel responde 403 antes de
     * llegar aquí: no es un enlace de edición. Un enlace permanente convertiría cada respuesta en una
     * credencial viva sobre los datos de un menor, repartida por un chat de padres.
     *
     * ⚠️ **Sigue siendo una hoja en blanco**: no dice cuántos han contestado ni quiénes son. Lo único
     * que enseña es lo que escribió quien la abre.
     */
    $saved = session('receipt_status');
@endphp
<x-focused-layout :title="__('invitation.receipt.title')">
    <div class="gf-page">
        @php $clientLogo = @filemtime(public_path('img/client-logo.svg')); @endphp
        <div class="gf-mark">
            @if ($clientLogo)
                <img class="gf-mark__logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
                     alt="{{ $site['name'] ?? config('app.name') }}">
            @else
                <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            @endif
            <span class="gf-mark__sub">{{ __('invitation.receipt.title') }}</span>
        </div>

        <main class="gf-sheet">
            <div class="gf-stub invitation-card" data-surface="ink" data-theme="{{ $reply->invitation?->safeTheme() }}">
                <span class="grain" aria-hidden="true"></span>
                <div class="gf-stub__top">
                    <span class="gf-stub__badge">{{ __('invitation.receipt.badge') }}</span>
                </div>
                <h1 class="gf-stub__title">{{ __('invitation.receipt.heading', ['name' => $childName]) }}</h1>
                <p class="gf-stub__lede">{{ __('invitation.receipt.lede') }}</p>
            </div>

            @if ($saved === 'saved')
                <div class="gf-notice gf-notice--ok" role="status" data-receipt-saved>
                    <p class="gf-notice__title">{{ __('invitation.receipt.saved_title') }}</p>
                    <p class="gf-notice__text">{{ __('invitation.receipt.saved') }}</p>
                </div>
            @elseif ($saved === 'closed')
                <div class="gf-notice gf-notice--err" role="alert" data-receipt-closed>
                    <p class="gf-notice__title">{{ __('invitation.done.refused_title') }}</p>
                    <p class="gf-notice__text">{{ __('invitation.receipt.closed') }}</p>
                </div>
            @endif

            @if ($open)
                <form class="gf-form" method="POST" action="{{ request()->fullUrl() }}" data-receipt-form>
                    @csrf

                    {{-- ───── G2 · Quién viene ─────
                         Las columnas del pack, **en su orden y todas opcionales**. Sin la del nombre,
                         que ya se contestó: volver a pedirlo abriría la puerta a que no coincidan.
                         ⚠️ Aquí puede haber ALERGIAS, que son dato de salud (art. 9): por eso esta
                         pantalla lleva su aviso propio y no solo el del pie. --}}
                    @if ($fields !== [])
                        <div class="gf-group" data-receipt-fields>
                            <div class="gf-group__head">
                                <p class="gf-group__title">{{ __('invitation.receipt.g2_title') }}</p>
                            </div>
                            <p class="invitation__note">{{ __('invitation.receipt.g2_lede') }}</p>

                            <div class="eventfields">
                                @foreach ($fields as $field)
                                    @php $key = (string) ($field['key'] ?? ''); @endphp
                                    <label class="eventfields__field">
                                        <span class="eventfields__label">
                                            {{ $reply->reservation?->ticketType?->guestFieldLabel($field) ?? $key }}
                                        </span>
                                        <input type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}"
                                               name="guest_data[{{ $key }}]" maxlength="2000"
                                               value="{{ old('guest_data.'.$key, $data[$key] ?? '') }}">
                                    </label>
                                @endforeach
                            </div>

                            {{-- El aviso de G2, donde de verdad hace falta: aquí se recogen datos de
                                 salud de un menor que va a leer un tercero (§7.2·R7). --}}
                            <p class="invitation__privacy">{{ __('invitation.receipt.g2_privacy') }}</p>
                        </div>
                    @endif

                    {{-- ───── G3 · ¿Vas tú con él? ─────
                         ⚠️⚠️ **BORRADOR DE TEXTO PENDIENTE DEL OWNER** (§8, D4). Las tres opciones y su
                         consecuencia están decididas; las palabras las corrige él.
                         ⚠️ «Voy con él» **NO pide firma** (D4): el adulto se queda en el parque y se
                         identifica en la puerta. Decirle que firme sería pedirle un papel que su
                         presencia hace innecesario. --}}
                    <div class="gf-group" data-receipt-companion>
                        <div class="gf-group__head">
                            <p class="gf-group__title">{{ __('invitation.receipt.g3_title') }}</p>
                        </div>

                        <div class="guardian__pick">
                            @foreach (['with_adult', 'alone', 'unknown'] as $option)
                                <label class="guardian__accept">
                                    <input type="radio" name="companion" value="{{ $option }}"
                                           @checked(old('companion', $companion) === $option)>
                                    <span>
                                        <strong>{{ __('invitation.receipt.g3.'.$option.'_title') }}</strong>
                                        {{ __('invitation.receipt.g3.'.$option) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        {{-- El salto al justificante, con la respuesta ATADA: esa firma no descuenta
                             plaza porque la plaza ya tiene dueño (§4.5·7, `#576`). --}}
                        <a class="btn btn--ghost invitation__go" href="{{ $waiverUrl }}">
                            {{ __('invitation.receipt.g3_sign') }}
                        </a>
                    </div>

                    <div class="invitation__answers">
                        <button type="submit" class="btn btn--lg btn--ink">{{ __('invitation.receipt.save') }}</button>
                    </div>
                </form>
            @endif

            <p class="invitation__privacy" data-invitation-privacy>
                {{ __('invitation.privacy.text') }}
                <a class="gf-legal" href="{{ route('legal.privacidad') }}">{{ __('invitation.privacy.link') }}</a>
            </p>
        </main>
    </div>
</x-focused-layout>
