@php
    /**
     * **La INVITACIÓN DIGITAL de una fiesta** (`docs/specs/celebracion-e-invitacion.md` §4.6, T5·1;
     * `DECISIONES #521`).
     *
     * ❗❗ **HOJA EN BLANCO, y aquí es donde más importa.** Esta pantalla NO pinta ni una respuesta, ni
     * cuántos han contestado, **ni si un nombre concreto ya lo hizo**. Ése es el motivo por el que su
     * enlace puede repartirse a un grupo de clase entero por un chat de padres. Si algún día alguien
     * añade aquí «los que ya vienen», rompe la razón por la que esta página existe.
     *
     * ⚠️ **La regla de los bloques es «sin dato, sin bloque»** (§4.6): nada de celdas con un guion. Una
     * invitación que dice «Dónde: —» es peor que una que no dice dónde, porque parece rota.
     *
     * ▶ **T5·1 usa el molde ya aprobado** (`focused-layout` + `.gf-*`, el mismo de sus dos hermanas):
     * el vestido del canvas —banda de tema, confeti y chapa de edad— llega con **los tres temas**, que
     * el owner elige viéndolos renderizados (§3.4). Inventarlos aquí sería decidir por él.
     *
     * ⚠️ **Sin JS y sin formulario**: la barra de contestar es la T5·2, y llega con el aviso de
     * privacidad que `§7.2·R7` exige para recoger datos de un menor. Por eso esta unidad puede existir
     * sola: **no recoge nada**.
     */
@endphp
<x-focused-layout :title="__('invitation.title')">
    <div class="gf-page">
        {{-- Lo primero que ve alguien que no conoce esta web. Mismo hueco que las hojas hermanas. --}}
        @php $clientLogo = @filemtime(public_path('img/client-logo.svg')); @endphp
        <div class="gf-mark">
            @if ($clientLogo)
                <img class="gf-mark__logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
                     alt="{{ $site['name'] ?? config('app.name') }}">
            @else
                <span class="gf-mark__brand">{{ $site['name'] ?? config('app.name') }}</span>
            @endif
            <span class="gf-mark__sub">{{ __('invitation.title') }}</span>
        </div>

        <main class="gf-sheet">
            {{-- ───── La TARJETA: quién cumple y cuántos hace ─────
                 En tinta, como el resguardo de sus hermanas: es la pieza que se mira primero y la que
                 se reenvía en una captura. --}}
            {{-- El TEMA viaja en el marcado y lo pinta el CSS con formas del sistema: banda arriba,
                 confeti de fondo y el color de la chapa de edad (§3.4). Sale por `safeTheme()`, así
                 que un tema retirado de la lista cerrada cae al de por defecto en vez de dejar la
                 tarjeta sin vestir. --}}
            <div class="gf-stub invitation-card" data-surface="ink"
                 data-theme="{{ $invitation->safeTheme() }}" data-invitation-card>
                {{-- El CONFETI es `.grain`, la pieza del sistema: «la única textura que el sistema
                     admite sobre tinta». Decorativa, así que fuera del árbol de accesibilidad. --}}
                <span class="grain" aria-hidden="true"></span>

                <div class="gf-stub__top">
                    <span class="gf-stub__badge">{{ __('invitation.badge') }}</span>
                </div>

                <h1 class="gf-stub__title">{{ __('invitation.heading') }}</h1>

                {{-- El nombre y la CHAPA de la edad, en la misma fila: son los dos datos que se miran
                     primero, y en el canvas la edad es una pieza, no una frase. --}}
                <div class="invitation__who">
                    <p class="invitation__honoree" data-invitation-honoree>{{ $honoreeName }}</p>
                    @if ($honoreeAge !== null)
                        {{-- `aria-label` con la frase entera: la chapa dice «8 años» en dos renglones
                             sueltos, y un lector de pantalla leería «8» y «años» como dos cosas. --}}
                        <span class="invitation__age" data-invitation-age
                              aria-label="{{ trans_choice('invitation.age', $honoreeAge, ['count' => $honoreeAge]) }}">
                            <span aria-hidden="true">{{ $honoreeAge }}</span>
                        </span>
                    @endif
                </div>

                <div class="gf-stub__meta">
                    @if ($dayLabel !== null)
                        <div class="gf-stub__cell">
                            <span class="k">{{ __('invitation.when') }}</span>
                            <span class="v">{{ $dayLabel }}</span>
                            @if ($timeWindow)
                                {{-- La ventana llega COMPUESTA del dominio (base + hora extra): montarla
                                     con el fin de la franja diría una hora de menos en una fiesta de
                                     dos horas (la trampa de `#426`). --}}
                                <span class="v mono">{{ $timeWindow }}</span>
                            @endif
                        </div>
                    @endif

                    @if (trim($hostLine) !== '')
                        <div class="gf-stub__cell">
                            <span class="k">{{ __('invitation.host') }}</span>
                            <span class="v">{{ $hostLine }}</span>
                            {{-- El teléfono sale de la CUENTA y solo si el anfitrión lo marcó: en esta
                                 página no hay ningún campo donde teclear uno. --}}
                            @if ($hostPhone !== null)
                                <a class="v mono invitation__call" href="tel:{{ preg_replace('/\s+/', '', $hostPhone) }}"
                                   data-invitation-call>{{ $hostPhone }}</a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="gf-form">
                {{-- ───── Dónde ─────
                     ⚠️⚠️ **Enlace externo, NUNCA un mapa embebido** (§4.6). Un iframe de Google en una
                     página pública es un tercero cargando dentro de la fiesta de un niño, con su
                     consentimiento de cookies detrás. Y el `Referrer-Policy: no-referrer` que pone el
                     controlador es lo que impide que pulsarlo le mande **el token** a Google. --}}
                @if (($site['address1'] ?? '') !== '' || ($site['name'] ?? '') !== '')
                    <div class="gf-group" data-invitation-where>
                        <div class="gf-group__head">
                            <p class="gf-group__title">{{ __('invitation.where') }}</p>
                        </div>
                        {{-- El MISMO molde de bloque con superficie que usa el justificante para
                             enmarcar el anti-robot: borde, radio y papel suave del sistema. Antes
                             esto eran dos párrafos sueltos sobre el fondo, sin caja. --}}
                        <div class="invitation__panel">
                            <p class="invitation__place">{{ $site['name'] ?? config('app.name') }}</p>
                            @if (($site['address1'] ?? '') !== '' || ($site['address2'] ?? '') !== '')
                                <p class="invitation__note">
                                    {{ $site['address1'] }}@if (($site['address2'] ?? '') !== '')<br>{{ $site['address2'] }}@endif
                                </p>
                            @endif
                            @if (($site['maps'] ?? '#') !== '#')
                                {{-- Botón del sistema en su variante discreta: es una acción, y como
                                     párrafo con un enlace subrayado no lo parecía. `--action-brand`
                                     NO entra aquí (§4.6, cero naranja): esta página no vende nada. --}}
                                <a class="btn btn--ghost invitation__go" href="{{ $site['maps'] }}"
                                   target="_blank" rel="noopener noreferrer">{{ __('invitation.directions') }}</a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ───── El menú ─────
                     Los complementos COMPRADOS de esta reserva que el catálogo marcó (D12). Los
                     ofrecidos no: pondrían en la invitación cosas que nadie ha pagado. --}}
                @if ($menu !== [])
                    <div class="gf-group" data-invitation-menu>
                        <div class="gf-group__head">
                            <p class="gf-group__title">{{ __('invitation.menu') }}</p>
                        </div>
                        {{-- ⚠️ La MISMA pieza con la que el post-form lista los complementos
                             (`.gf-extras__list` + `.gf-extra`), porque el menú **es** una lista de
                             complementos: tarjeta con borde, filas separadas y el nombre en semibold.
                             Era un `<ul>` con viñetas del navegador. --}}
                        <ul class="gf-extras__list">
                            @foreach ($menu as $dish)
                                <li class="gf-extra">
                                    <span class="gf-extra__id">
                                        @if ($dish['features'] !== [])
                                            {{-- El «Más info» de cada plato: la MISMA pieza que el
                                                 post-form (`#416`), `<details>` nativo — funciona
                                                 **sin una línea de JS**, que es la condición de esta
                                                 página. --}}
                                            <details class="gf-extra__more">
                                                <summary>
                                                    <span class="gf-extra__name">
                                                        {{ $dish['name'] }}<x-icons.chevron-down :width="14" :height="14" />
                                                    </span>
                                                    {{-- Se VE el chevron; se ANUNCIA qué despliega. --}}
                                                    <span class="gf-sr-only">{{ __('invitation.menu_more') }}</span>
                                                </summary>
                                                <ul>
                                                    @foreach ($dish['features'] as $feature)
                                                        <li>{{ $feature }}</li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @else
                                            {{-- Sin detalles, una fila y ya: un desplegable vacío es
                                                 peor que ninguno. --}}
                                            <span class="gf-extra__name">{{ $dish['name'] }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- ───── Lo que todavía no está ─────
                     ⚠️ Se DICE en vez de callarse. Un padre que abre la invitación y no encuentra dónde
                     contestar pensaría que la página está rota; esto le dice que lo hará aquí mismo.
                     La barra de contestar es la T5·2, con su aviso de privacidad. --}}
                <div class="gf-notice" role="status" data-invitation-soon>
                    <p class="gf-notice__title">{{ __('invitation.soon.title') }}</p>
                    <p class="gf-notice__text">
                        {{ $repliesOpen ? __('invitation.soon.open') : __('invitation.soon.closed') }}
                    </p>
                </div>
            </div>
        </main>
    </div>
</x-focused-layout>
