@php
    use Filament\Support\Icons\Heroicon;

    /**
     * LA PUERTA NUEVA (`docs/specs/puerta-nueva.md` §4.4, la P1): la vista del mockup del zip (6) sobre las garantías de
     * siempre, que siguen en el componente (`ValidarRegistro`) y no aquí. La vista PINTA: el veredicto lo decide
     * `GateVerdict` y lo que se lee de la ficha, `FichaPuerta`.
     *
     * @var array{tone: string, text: string, sub: ?string, notice: bool, sound: ?string}|null $verdict
     * @var array<string, mixed>|null $ficha
     */
    $iconos = [
        'verde' => Heroicon::OutlinedCheckCircle,
        'ambar' => Heroicon::OutlinedExclamationTriangle,
        'rojo' => Heroicon::OutlinedXCircle,
        'gris' => Heroicon::OutlinedQuestionMarkCircle,
    ];
    $estado = $result['status'] ?? null;
@endphp

<div class="ppu" data-gate x-data="puertaPantalla" x-on:pointerdown="desbloquear()" x-on:puerta-sonido.window="sonar($event.detail)">
    <header class="ppu-cab">
        <div class="ppu-marca">
            {{-- El logotipo de la INSTALACIÓN (el hueco `client-logo.svg`, el mismo que usa la marca del panel), sin el
                 «Administración» de su barra; sin logotipo, el nombre del negocio. Solo modo claro (D3): basta el claro. --}}
            {{-- ⚠️ Bloques `@php … @endphp`, nunca `@php(...)` en línea: la regex de Blade para los bloques empieza en el
                 primer `@php` que encuentra —también uno en línea— y se traga todo hasta el siguiente `@endphp` (medido en
                 la P1b: media vista salió sin compilar). --}}
            @php
                $businessName = \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name');
                $clientLogo = @filemtime(public_path('img/client-logo.svg'));
            @endphp
            @if ($clientLogo)
                <img class="ppu-logo" src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}" alt="{{ $businessName }}" />
            @else
                <span class="ppu-logo ppu-logo--texto">{{ $businessName }}</span>
            @endif
            <span class="ppu-sep" aria-hidden="true"></span>
            <h1 class="ppu-titulo">{{ __('admin.puerta.nav_label') }}</h1>
        </div>

        {{-- El lector de QR es un «keyboard wedge»: teclea el código y un Enter en ESTE campo, así que escanear y teclear son
             el mismo formulario. ⚠️ La doble lectura (el mismo código en menos de 3 s) se corta en la fase de CAPTURA, antes
             que el `wire:submit`: ni reabre la ficha ni suena (el mockup). --}}
        <form wire:submit="search" class="ppu-busca" x-on:submit.capture="if (doble($refs.campo.value)) { $event.preventDefault(); $event.stopImmediatePropagation(); $refs.campo.value = ''; $refs.campo.dispatchEvent(new Event('input')) }">
            <label for="input" class="fi-sr-only">
                {{ $canViewProfile ? __('admin.puerta.ficha.campo') : __('admin.puerta.validar.input_placeholder') }}
            </label>
            <div class="ppu-fila">
                <span class="ppu-campo">
                    <x-filament::icon :icon="Heroicon::OutlinedQrCode" class="ppu-campo__ico" />
                    <input
                        id="input"
                        type="text"
                        x-ref="campo"
                        wire:model="input"
                        placeholder="{{ $canViewProfile ? __('admin.puerta.ficha.campo') : __('admin.puerta.validar.input_placeholder') }}"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        enterkeyhint="search"
                        autofocus
                        inputmode="text"
                        class="ppu-campo__input"
                        {{-- ▶ EL CURSOR VIVE AQUÍ. Tres momentos, y cada uno es un caso distinto (`#234`): al abrir (`x-init`), al
                             VOLVER de otra aplicación (`focus.window`/`visibilitychange`, con `preventScroll`) y tras cada
                             búsqueda válida (`gate-input-cleared`, que emite `search()`). Cada oyente es una mitad muda sin la
                             otra: `GateKioskTest` los asevera por separado. --}}
                        x-init="$el.focus()"
                        x-on:focus.window="$el.focus({ preventScroll: true })"
                        x-on:visibilitychange.document="document.hidden || $el.focus({ preventScroll: true })"
                        x-on:gate-input-cleared.window="$el.focus()"
                    />
                </span>
                <button type="submit" class="ppu-boton ppu-boton--buscar" wire:loading.attr="disabled" wire:target="search">
                    <span wire:loading.remove wire:target="search">{{ __('admin.puerta.ficha.buscar') }}</span>
                    <span wire:loading wire:target="search">{{ __('admin.puerta.ficha.buscando') }}</span>
                </button>
            </div>
            @if ($verdict !== null && ! $verdict['notice'] && ($result['query'] ?? null) !== null)
                {{-- ENMASCARADO en el servidor (`QueryMask`, D5): la cola ve la pantalla. --}}
                <p class="ppu-resultado" data-gate-query>{{ __('admin.puerta.ficha.resultado_para') }} <b>{{ $result['query'] }}</b></p>
            @endif
        </form>
    </header>

    @if ($verdict === null)
        {{-- Entre dos clientes: el campo vacío y el lector (la reseña del día llega en la P3). --}}
        <div class="ppu-vacio" data-gate-empty>
            <x-filament::icon :icon="Heroicon::OutlinedQrCode" class="ppu-vacio__ico" />
        </div>
    @elseif ($verdict['notice'])
        {{-- Los avisos de la BÚSQUEDA, en gris y sin nombre: no hablan del cliente. --}}
        <div class="ppu-cuerpo solo centro" wire:key="aviso-{{ $estado }}-{{ uniqid() }}">
            <p class="ppu-aviso" role="status" aria-live="polite" data-gate-status="{{ $estado }}"
               @if ($estado === \App\Livewire\Admin\Puerta\ValidarRegistro::STATUS_LOOKUP_LIMITED) data-gate-lookup-limited @endif>
                <x-filament::icon :icon="Heroicon::OutlinedInformationCircle" class="ppu-aviso__ico" />
                <span>{{ $verdict['text'] }}</span>
            </p>
        </div>
    @elseif ($ficha === null)
        {{-- Sin ficha (no encontrado, el QR, o un empleado sin `puerta.profile`): el veredicto grande y sin nombre. --}}
        <div class="ppu-cuerpo solo centro" wire:key="veredicto-{{ $estado }}-{{ uniqid() }}">
            <div class="ppu-ver grande t-{{ $verdict['tone'] }}" role="status" aria-live="polite" data-gate-status="{{ $estado }}"
                 @if ($estado === \App\Livewire\Admin\Puerta\ValidarRegistro::STATUS_CARD_REVOKED) data-gate-card-revoked @endif
                 @if ($estado === \App\Livewire\Admin\Puerta\ValidarRegistro::STATUS_CARD_UNKNOWN) data-gate-card-unknown @endif
                 @if ($verdict['sound'] !== null) x-init="$dispatch('puerta-sonido', '{{ $verdict['sound'] }}')" @endif>
                <div class="ppu-ver__top">
                    <span class="ppu-ver__ico"><x-filament::icon :icon="$iconos[$verdict['tone']]" /></span>
                    <span class="ppu-ver__txt">{{ $verdict['text'] }}</span>
                </div>
                @if ($verdict['sub'] !== null)
                    <p class="ppu-ver__sub" @if ($result['outdated'] ?? false) data-gate-outdated @endif>{{ $verdict['sub'] }}</p>
                @endif
            </div>
        </div>
    @else
        {{-- LA FICHA. Dos relojes en el navegador —el velo a los 60 s y el cierre al TTL— que NO son la garantía: la ficha
             caduca en el SERVIDOR (`ensureFresh()`). Cualquier toque o tecla reinicia los dos. Mientras llega otra búsqueda,
             la ficha abierta se APAGA: con cola, nunca se entrega con la ficha del anterior. --}}
        <section
            class="ppu-ficha"
            wire:key="ficha-{{ $profile['user_id'] }}-{{ $profile['expires_at'] }}"
            wire:loading.class="sale"
            wire:target="search"
            data-gate-profile
            data-gate-via="{{ $profile['via'] }}"
            x-data="{
                veiled: false, veilTimer: null, closeTimer: null,
                arm() {
                    clearTimeout(this.veilTimer); clearTimeout(this.closeTimer); this.veiled = false;
                    this.veilTimer = setTimeout(() => { this.veiled = true }, 60000);
                    this.closeTimer = setTimeout(() => { $wire.clear() }, {{ (int) $profile['ttl_minutes'] * 60000 }});
                }
            }"
            x-init="arm()"
            x-on:click.window="arm()"
            x-on:keydown.window="arm()"
        >
            {{-- La encuesta, SOLO en verde (la P1b): el servidor la sigue ofreciendo —nada se escribe hasta el primer toque—,
                 así que tras «Dar por firmado» aparece. --}}
            @php
                $conEncuesta = $survey !== null && $verdict['tone'] === \App\Livewire\Admin\Puerta\GateVerdict::VERDE;
            @endphp
            <div @class(['ppu-cuerpo', 'solo' => $ficha['hijos'] === [] && $ficha['invitados'] === [] && $ficha['fiesta'] === null && ! $conEncuesta])>
                <div class="ppu-col" data-gate-col="main">
                    {{-- 1 · El veredicto y, dentro de la misma tarjeta, el nombre: una sola cosa que mirar. --}}
                    <div class="ppu-ver t-{{ $verdict['tone'] }}" role="status" aria-live="polite" data-gate-status="{{ $estado }}" data-gate-waiver="{{ $ficha['descargo'] }}"
                         @if ($verdict['sound'] !== null) x-init="$dispatch('puerta-sonido', '{{ $verdict['sound'] }}')" @endif>
                        <div class="ppu-ver__top">
                            <span class="ppu-ver__ico"><x-filament::icon :icon="$iconos[$verdict['tone']]" /></span>
                            <span class="ppu-ver__txt">{{ $verdict['text'] }}</span>
                        </div>
                        <div class="ppu-ver__nombre">
                            <h2 data-gate-holder>{{ $profile['holder_name'] }}</h2>
                            <span>
                                <span data-gate-via-badge="{{ $profile['via'] }}">{{ __('admin.puerta.ficha.'.($profile['via'] === 'card' ? 'abierta_qr' : 'abierta_busqueda')) }}</span>@if ($profile['visit_registered_today'])<span data-gate-visit-badge> · {{ __('admin.puerta.ficha.visita') }}</span>@endif
                            </span>
                        </div>
                    </div>

                    {{-- 2 · Lo que hay que HACER: sin reserva, las pulseras por zona y hora con sus reservas debajo, el dinero
                         en su línea ámbar y firmar. Solo lo que haya. --}}
                    @if ($ficha['sin_reserva'] || $ficha['filas'] !== [] || $ficha['firmar'] !== [])
                        <div class="ppu-card ppu-tareas">
                            @if ($ficha['sin_reserva'])
                                <div class="ppu-tarea" data-gate-today-empty>
                                    <span class="ppu-cant neutro"><x-filament::icon :icon="Heroicon::OutlinedCalendarDays" /></span>
                                    <p class="ppu-tt">
                                        {{ __('admin.puerta.ficha.sin_reserva') }}
                                        @foreach ($ficha['otros_dias'] as $otro)
                                            <small data-gate-window>{{ __('admin.puerta.ficha.otro_dia', ['cuando' => $otro]) }}</small>
                                        @endforeach
                                    </p>
                                </div>
                            @endif

                            @if ($ficha['filas'] !== [])
                                <div data-gate-today>
                                    @foreach ($ficha['filas'] as $fila)
                                        <div class="ppu-ent">
                                            <span class="ppu-cant">{{ $fila['cifra'] }}</span>
                                            <div class="ppu-ent__main">
                                                <p class="ppu-ent__t"><b class="ppu-zona">{{ $fila['zona'] }}</b></p>
                                                @foreach ($fila['reservas'] as $res)
                                                    <div class="ppu-ent__r" data-gate-reservation="{{ $res['codigo'] }}">
                                                        <p class="ppu-l1">
                                                            @if ($res['fiesta'])<span class="ppu-badge ppu-badge--cumple">{{ __('admin.puerta.ficha.cumpleanos') }}</span>@endif
                                                            <span>{{ $res['linea'] }}@if ($res['quien'] !== null) · {{ $res['quien'] }}@endif</span>
                                                        </p>
                                                        <p class="ppu-ent__p">
                                                            <span>{{ $res['hora'] }}</span>
                                                            @if ($res['pagado'] !== null)<span class="ok"><x-filament::icon :icon="Heroicon::OutlinedCheckCircle" />{{ $res['pagado'] }}</span>@endif
                                                            @if ($res['complementos'] !== null)<span>{{ $res['complementos'] }}</span>@endif
                                                            <code>Nº {{ $res['codigo'] }}</code>
                                                        </p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @foreach ($fila['reservas'] as $res)
                                            @if ($res['dinero'] !== null)
                                                <div class="ppu-tarea ppu-pago" data-gate-{{ $res['dinero']['clase'] }}>
                                                    <span class="ppu-cant aviso"><x-filament::icon :icon="$res['dinero']['clase'] === 'refund' ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedBanknotes" /></span>
                                                    <p class="ppu-tt">{{ $res['dinero']['texto'] }}</p>
                                                </div>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </div>
                            @endif

                            @foreach ($ficha['firmar'] as $modo)
                                @if ($modo === \App\Livewire\Admin\Puerta\FichaPuerta::FIRMAR_PENDIENTE)
                                    {{-- `#336`: la aceptación RETENIDA se da por firmada con la persona delante. La confirmación va
                                         DENTRO de la ficha: una ventana del navegador taparía el lector (el mockup, el brief). --}}
                                    <div class="ppu-tarea ppu-firmar" data-gate-sign="pendiente" x-data="{ confirmando: false }">
                                        <span class="ppu-ico aviso"><x-filament::icon :icon="Heroicon::OutlinedPencilSquare" /></span>
                                        <div class="ppu-tt">
                                            <p>{{ __('admin.puerta.ficha.firmar_pendiente') }}</p>
                                            @if ($profile['waiver_declare_stale'] ?? false)
                                                <p class="ppu-tt__aviso" data-gate-waiver-stale>{{ __('admin.puerta.validar.profile.waiver_declare_stale') }}</p>
                                            @endif
                                        </div>
                                        <div class="ppu-acc" x-show="! confirmando">
                                            <button type="button" class="ppu-boton" x-on:click="confirmando = true" data-gate-declare-waiver-open>{{ __('admin.puerta.ficha.firmar_dar') }}</button>
                                        </div>
                                        <div class="ppu-conf" x-show="confirmando" x-cloak>
                                            <p>{{ __('admin.puerta.validar.profile.waiver_declare_confirm', ['name' => $profile['holder_name'] ?? '']) }}</p>
                                            <div class="ppu-conf__botones">
                                                <button type="button" class="ppu-boton ppu-boton--primario" wire:click="declareWaiver" data-gate-declare-waiver>{{ __('admin.puerta.ficha.firmar_si') }}</button>
                                                <button type="button" class="ppu-boton ppu-boton--quieto" x-on:click="confirmando = false">{{ __('admin.puerta.ficha.firmar_cancelar') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    {{-- «Cambiado» dice lo DECIDIDO (§4.8 de la puerta): una versión anterior deja pasar y la firma nueva se
                                         pide en su próxima compra, no en el mostrador. El mockup pide firmarla aquí: D9, a la vista del owner. --}}
                                    <div class="ppu-tarea ppu-firmar" data-gate-sign="{{ $modo }}" @if ($modo === \App\Livewire\Admin\Puerta\FichaPuerta::FIRMAR_CAMBIADO) data-gate-outdated @endif>
                                        <span class="ppu-ico aviso"><x-filament::icon :icon="Heroicon::OutlinedPencilSquare" /></span>
                                        <p class="ppu-tt">{{ $modo === \App\Livewire\Admin\Puerta\FichaPuerta::FIRMAR_CAMBIADO ? __('admin.waiver.gate_outdated') : __('admin.puerta.ficha.firmar_'.$modo) }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="ppu-col" data-gate-col="side">
                    {{-- 3 · «Sus hijos»: nombre de pila y edad (los apellidos no tienen campo en el DTO, `#236`) y SOLO la excepción
                         del descargo (`#320`, D1); quien cumple, el primero. --}}
                    @if ($ficha['hijos'] !== [])
                        <section class="ppu-card pad">
                            <p class="ppu-over ppu-over--ico"><x-filament::icon :icon="Heroicon::OutlinedUserGroup" />{{ __('admin.puerta.ficha.sus_hijos') }}</p>
                            <ul class="ppu-gente" data-gate-minors>
                                @foreach ($ficha['hijos'] as $hijo)
                                    <li class="ppu-persona" data-gate-minor data-gate-minor-name="{{ $hijo['nombre'] }}" data-gate-minor-age="{{ $hijo['anios'] }}" data-gate-minor-waiver="{{ $hijo['descargo'] }}">
                                        <b>{{ $hijo['nombre'] }}</b><span>{{ $hijo['edad'] }}</span>
                                        @if ($hijo['cumple'])<span class="ppu-badge ppu-badge--cumple-suave">{{ __('admin.puerta.ficha.su_cumple') }}</span>@endif
                                        @if ($hijo['excepcion'] !== null)<span class="ppu-badge ppu-badge--aviso">{{ $hijo['excepcion'] }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Los menores INVITADOS fuera de un cumpleaños (`#337`), con su nombre (D8). --}}
                    @if ($ficha['invitados'] !== [])
                        <section class="ppu-card pad">
                            <p class="ppu-over">{{ __('admin.puerta.ficha.invitados') }}</p>
                            <ul class="ppu-gente" data-gate-guest-minors>
                                @foreach ($ficha['invitados'] as $inv)
                                    <li class="ppu-persona" data-gate-guest-minor data-gate-guest-minor-name="{{ $inv['nombre'] }}">
                                        <b>{{ $inv['nombre'] }}</b>@if ($inv['edad'] !== null)<span>{{ $inv['edad'] }}</span>@endif
                                        @if ($inv['excepcion'] !== null)<span class="ppu-badge ppu-badge--aviso">{{ $inv['excepcion'] }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Un cumpleaños: UNA línea sin nombres (`#817`). --}}
                    @if ($ficha['fiesta'] !== null)
                        <p class="ppu-card pad ppu-fiesta" data-gate-guest-minors-count>{{ $ficha['fiesta'] }}</p>
                    @endif

                    {{-- LA ENCUESTA INTERNA, PREGUNTA A PREGUNTA y SOLO EN VERDE (`specs/puerta-nueva.md` §4.4, la P1b; `#817`·3; el
                         mockup): un toque guarda y pasa a la siguiente; «Siguiente» en las de varias y de texto; «Ahora no» deja el
                         resto. ▶ `#754`: ANÓNIMA, y su aviso (y la intro del panel) se lee antes de la PRIMERA pregunta (D7). --}}
                    @if ($conEncuesta)
                        @php
                            $paso = (int) ($survey['step'] ?? 0);
                            $q = $survey['questions'][$paso] ?? null;
                        @endphp
                        <section class="ppu-card pad ppu-enc gate-survey" data-gate-survey="{{ $survey['state'] }}" data-gate-survey-key="{{ $survey['key'] }}">
                            @if ($survey['state'] === 'asking' && $q !== null)
                                <p class="ppu-over">{{ __('admin.puerta.ficha.preguntale') }}</p>
                                @if ($paso === 0)
                                    @if (filled($survey['intro'] ?? null))
                                        <p class="ppu-enc__intro">{{ $survey['intro'] }}</p>
                                    @endif
                                    <p class="gate-hint" data-gate-survey-notice>{{ __('admin.puerta.validar.profile.survey_notice') }}</p>
                                @endif
                                <h3 class="ppu-enc__pregunta" data-gate-question="{{ $q['key'] }}" data-gate-question-type="{{ $q['type'] }}">{{ $q['label'] }}</h3>

                                @if (in_array($q['type'], ['choice', 'yesno', 'scale'], true))
                                    @php
                                        $opciones = match ($q['type']) {
                                            'yesno' => [['key' => '1', 'label' => __('admin.puerta.validar.profile.survey_yes')], ['key' => '0', 'label' => __('admin.puerta.validar.profile.survey_no')]],
                                            'scale' => array_map(static fn (int $n): array => ['key' => (string) $n, 'label' => (string) $n], range(\App\Domain\Platform\Services\Surveys\QuestionSchema::SCALE_MIN, \App\Domain\Platform\Services\Surveys\QuestionSchema::SCALE_MAX)),
                                            default => $q['options'],
                                        };
                                    @endphp
                                    <div class="ppu-ops">
                                        @foreach ($opciones as $o)
                                            <button type="button" class="ppu-op" wire:click="answerQuestion('{{ $q['key'] }}', '{{ $o['key'] }}')" data-gate-survey-option="{{ $o['key'] }}">{{ $o['label'] }}</button>
                                        @endforeach
                                    </div>
                                @elseif ($q['type'] === 'multi')
                                    <div class="gate-q__options">
                                        @foreach ($q['options'] as $o)
                                            <input type="checkbox" id="q-{{ $q['key'] }}-{{ $o['key'] }}" class="gate-q__input" wire:model="surveyAnswers.{{ $q['key'] }}" value="{{ $o['key'] }}">
                                            <label for="q-{{ $q['key'] }}-{{ $o['key'] }}" class="gate-q__btn">{{ $o['label'] }}</label>
                                        @endforeach
                                    </div>
                                    <button type="button" class="ppu-boton ppu-boton--primario" wire:click="answerQuestion('{{ $q['key'] }}')" data-gate-survey-next>{{ __('admin.puerta.ficha.siguiente') }}</button>
                                @else
                                    <input type="text" id="q-{{ $q['key'] }}" class="gate-q__text" wire:model="surveyAnswers.{{ $q['key'] }}" maxlength="{{ \App\Domain\Platform\Services\Surveys\QuestionSchema::TEXT_MAX }}" autocomplete="off" placeholder="{{ __('admin.puerta.validar.profile.survey_text_placeholder') }}" aria-describedby="q-{{ $q['key'] }}-hint">
                                    <p class="gate-hint" id="q-{{ $q['key'] }}-hint" data-gate-survey-text-hint>{{ __('admin.puerta.validar.profile.survey_text_hint') }}</p>
                                    <button type="button" class="ppu-boton ppu-boton--primario" wire:click="answerQuestion('{{ $q['key'] }}')" data-gate-survey-next>{{ __('admin.puerta.ficha.siguiente') }}</button>
                                @endif

                                @error('surveyAnswers.'.$q['key'])
                                    <p class="gate-q__error" data-gate-survey-error="{{ $q['key'] }}">{{ $message }}</p>
                                @enderror
                                <button type="button" class="ppu-ahora" wire:click="skipSurvey" data-gate-survey-skip>{{ __('admin.puerta.ficha.ahora_no') }}</button>
                            @elseif ($survey['state'] === 'answered')
                                <p class="ppu-guardado"><x-filament::icon :icon="Heroicon::OutlinedCheck" />{{ __('admin.puerta.ficha.guardado') }}</p>
                            @else
                                <p class="gate-hint">{{ __('admin.puerta.validar.profile.survey_declined') }}</p>
                            @endif
                        </section>
                    @endif
                </div>
            </div>

            {{-- El velo a los 60 s: OPACO, tapa la ficha entera; el campo de búsqueda queda libre. --}}
            <div x-show="veiled" x-cloak class="ppu-velo" data-gate-veil role="button" tabindex="0" x-on:click="arm()">
                <p class="ppu-velo__t"><x-filament::icon :icon="Heroicon::OutlinedEyeSlash" />{{ __('admin.puerta.ficha.velo') }}</p>
            </div>
        </section>
    @endif

    {{-- El pie, con una búsqueda en pantalla: «Nueva búsqueda» es un control de PRIVACIDAD —quita de la vista de la cola los
         datos del anterior— y se queda (`#817`); con ficha, el aviso del cierre. --}}
    @if ($verdict !== null)
        <footer class="ppu-pie gate-foot">
            @if ($ficha !== null)
                <p class="gate-expires">{{ __('admin.puerta.ficha.pie', ['min' => (int) $profile['ttl_minutes']]) }}</p>
            @endif
            <button type="button" wire:click="clear" class="ppu-boton gate-foot__btn">{{ __('admin.puerta.validar.new_search') }}</button>
        </footer>
    @endif
</div>
