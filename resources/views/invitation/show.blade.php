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
     * ⚠️ **Sin una línea de JS**: el formulario es un POST normal y funciona entero. Lo único que se
     * carga de fuera es el anti-robot, y solo si la instalación lo tiene configurado.
     */
    $status = session('invitation_status');
    $child = session('invitation_child');
    $receipt = session('invitation_receipt');

    // Los desenlaces, en cuatro tonos. El rechazo del dominio comparte tono y título con el anti-robot
    // porque dicen lo mismo: no se ha guardado nada.
    //
    // ⚠️⚠️ **`full` NO está, y su ausencia es la propiedad** (`#700`): un «sí» ya no se rechaza por
    // lista completa, porque distinguirlo del aceptado decía si ese niño estaba invitado.
    $outcome = match (true) {
        $status === 'yes' => ['tone' => ' gf-notice--ok', 'role' => 'status',
            'title' => __('invitation.done.yes_title'),
            'text' => $child ? __('invitation.done.yes', ['name' => $child]) : __('invitation.done.yes_generic')],
        $status === 'no' => ['tone' => '', 'role' => 'status',
            'title' => __('invitation.done.no_title'), 'text' => __('invitation.done.no')],
        // Turnstile falla también a personas: se le DICE, no se le miente con un «hecho».
        $status === 'antibot' => ['tone' => ' gf-notice--err', 'role' => 'alert',
            'title' => __('invitation.done.refused_title'), 'text' => __('invitation.done.antibot')],
        // Entre que abrió la página y pulsó cambió el mundo: la fiesta, el plazo o el tope.
        in_array($status, ['closed', 'cutoff', 'no_name', 'too_many'], true) => [
            'tone' => ' gf-notice--err', 'role' => 'alert',
            'title' => __('invitation.done.refused_title'), 'text' => __('invitation.refused.'.$status)],
        default => null,
    };
@endphp
<x-focused-layout :title="__('invitation.title')">
    {{-- ───── La VISTA PREVIA al pegar el enlace en un chat (§4.6, T5·4) ─────
         ⚠️⚠️ **Solo nombre, edad, día, hora y negocio**, y lo compone el controlador. Esta tarjeta la
         pinta el chat de la clase entera y a veces un tercero que nadie controla: ni la dirección, ni
         el menú, ni una sola respuesta salen de aquí. Y **sin `og:url`**: la página ya está en el
         enlace que se pega, así que repetir el token en una meta no añade nada y lo deja en un sitio
         más del que copiarlo. --}}
    <x-slot:head>
        <meta property="og:site_name" content="{{ $site['name'] ?? config('app.name') }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $preview['title'] }}">
        <meta property="og:description" content="{{ $preview['description'] }}">
        @if ($preview['image'])
            <meta property="og:image" content="{{ $preview['image'] }}">
            {{-- Declaradas solo cuando el fichero es NUESTRO y se ha podido medir: un chat que recibe
                 medidas falsas reserva un hueco que luego no encaja. --}}
            @if ($preview['width'] && $preview['height'])
                <meta property="og:image:width" content="{{ $preview['width'] }}">
                <meta property="og:image:height" content="{{ $preview['height'] }}">
            @endif
            <meta name="twitter:card" content="summary_large_image">
        @endif
    </x-slot:head>

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
                {{-- ───── Añadir al calendario ─────
                     El orden de §4.6: después de quién invita y ANTES de dónde. Es un enlace a un
                     fichero que sirve el servidor, **sin una línea de JS** —la condición de esta
                     página—, y con `download` para que el móvil lo abra con su calendario en vez de
                     enseñarlo como texto.
                     ⚠️ El bloque no existe si falta la hora o la duración: un `.ics` sin cuándo no es
                     un recordatorio, es un fichero roto en la carpeta de descargas de un padre. --}}
                @if ($calendarUrl !== null)
                    <div class="gf-group" data-invitation-calendar>
                        <div class="gf-group__head">
                            <p class="gf-group__title">{{ __('invitation.calendar.title') }}</p>
                        </div>
                        <div class="invitation__panel">
                            <a class="btn btn--ghost invitation__go" href="{{ $calendarUrl }}" download>
                                {{ __('invitation.calendar.add') }}
                            </a>
                        </div>
                    </div>
                @endif

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

                {{-- ───── Desenlace de lo que se acaba de contestar ─────
                     Un tono por desenlace y el título delante, igual que la hoja hermana: antes tres
                     de cinco salían en el mismo gris y «hecho» se leía igual que «no hemos guardado
                     nada» (la grieta J-02 del justificante).
                     ⚠️⚠️ **No hay desenlace de «lista completa»** (`#700`): dejó de existir porque
                     distinguirlo del «sí» aceptado decía si ese niño estaba en la lista. --}}
                @if ($outcome !== null)
                    <div class="gf-notice{{ $outcome['tone'] }}" role="{{ $outcome['role'] }}"
                         data-invitation-outcome="{{ $status }}">
                        <p class="gf-notice__title">{{ $outcome['title'] }}</p>
                        <p class="gf-notice__text">{{ $outcome['text'] }}</p>

                        {{-- Las dos ofertas de G1, tras un «sí» (§4.5·6). El enlace es una credencial
                             de DOS HORAS atada a esa respuesta: viaja por flash y **nunca se pinta en
                             la página**, que la ve cualquiera con el enlace de la fiesta. --}}
                        @if ($receipt !== null)
                            <a class="btn btn--ink invitation__receipt" href="{{ $receipt }}"
                               data-invitation-receipt>{{ __('invitation.receipt.cta') }}</a>
                            <p class="gf-notice__text">{{ __('invitation.receipt.hint') }}</p>
                        @endif
                    </div>
                @endif

                {{-- ───── Pasado el plazo ─────
                     La información de la fiesta SE SIGUE VIENDO (§7.2·R8): hace falta justo el día de
                     la fiesta. Lo único que se cierra son los botones, y se dice por qué. --}}
                @unless ($repliesOpen)
                    <div class="gf-notice" role="status" data-invitation-closed>
                        <p class="gf-notice__title">{{ __('invitation.closed.title') }}</p>
                        <p class="gf-notice__text">{{ __('invitation.closed.text') }}</p>
                    </div>
                @endunless
            </div>

            {{-- ───── CONTESTAR ─────
                 ⚠️⚠️ **Barra propia y NO `.gf-savebar`**, aunque hablen el mismo idioma: esa clase es de
                 TRES páginas y aquí hace falta otra cosa —un campo y DOS botones, no un «guardar»—.
                 Tocarla para que cupieran movería el post-form y el justificante, que es exactamente
                 como la T2 rompió la barra de firmar (401 px de 844, §10.3).

                 ⚠️ «Sí, viene» y «No podemos» van **con el mismo peso** (§4.6): no hay respuesta
                 correcta, y jerarquizar una empujaría a decir que sí a quien no puede ir. --}}
            @if ($repliesOpen)
                <form class="invitation__bar" method="POST"
                      action="{{ route('invitation.reply', ['token' => $invitation->token]) }}"
                      data-invitation-form>
                    @csrf
                    <label class="invitation__field">
                        <span class="invitation__label">{{ __('invitation.field') }}</span>
                        <input type="text" name="child_name" required maxlength="120"
                               autocomplete="off" value="{{ old('child_name') }}"
                               placeholder="{{ __('invitation.field_hint') }}">
                    </label>
                    @error('child_name')
                        <p class="invitation__error" role="alert">{{ $message }}</p>
                    @enderror

                    {{-- El anti-robot es la caja de un TERCERO: no se recolorea ni se redibuja, y se
                         DICE qué es. Le falla también a personas, y aquí un fallo es un niño que se
                         queda sin confirmar. --}}
                    @if (\App\Domain\Platform\Services\Turnstile::enabled())
                        <div class="guardian__third">
                            <span class="guardian__third-label">{{ __('invitation.antibot_label') }}</span>
                            <div class="cf-turnstile" data-sitekey="{{ \App\Domain\Platform\Services\Turnstile::siteKey() }}"></div>
                        </div>
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    @endif

                    <div class="invitation__answers">
                        <button type="submit" name="attending" value="1" class="btn btn--lg btn--ink">
                            {{ __('invitation.yes') }}
                        </button>
                        <button type="submit" name="attending" value="0" class="btn btn--lg btn--ghost">
                            {{ __('invitation.no') }}
                        </button>
                    </div>
                </form>
            @endif

            {{-- ───── El AVISO DE PRIVACIDAD (§7.2·R7) ─────
                 ❗❗ **Sin casilla** (el criterio de `#350`) y al pie de la página. Dice las tres cosas
                 que hay que decir: **para qué** son los datos, **quién los va a ver** —y aquí lo lee un
                 TERCERO, el anfitrión, que no es el parque— y **cuándo se borran**. La política queda
                 como control de 48 para quien quiera leerla entera.
                 ⚠️ Va aquí aunque esta pantalla solo pida un nombre: el nombre de un niño YA es un dato
                 personal de un menor, y quien lo escribe no tiene cuenta ni ha aceptado nada. --}}
            <p class="invitation__privacy" data-invitation-privacy>
                {{ __('invitation.privacy.text') }}
                {{-- La misma ruta y la misma clase de enlace legal que sus dos hermanas. --}}
                <a class="gf-legal" href="{{ route('legal.privacidad') }}">{{ __('invitation.privacy.link') }}</a>
            </p>
        </main>
    </div>
</x-focused-layout>
