@props([
    'schedule',          // App\Domain\Content\Services\ScheduleDisplay (lo pasa el HomeController)
])

{{--
    **07 · VISÍTANOS** — carril de diseño Fase 2 · T2g (`DECISIONES #487`). Artboards
    `Visitanos PJP` **7b** (móvil, 🔒 aprobada y con la sección cerrada) + `Escritorio PJP` **3b**.

    ▶ **Dos mitades: cuándo y dónde.** En móvil se apilan; en escritorio van a dos columnas iguales
    (544 + 544), que es lo único que cambia — el contenido es el mismo.

    ❗❗❗ **CUATRO ESTADOS, NO DOS.** Es regla dura del sistema: *«un dato con hora tiene cuatro
    estados: "hoy no abre" y "hoy ya ha cerrado" son hechos distintos, y decirlos igual deja el
    titular contradiciendo a la tabla, que sigue enseñando las horas de hoy»*. Los compone
    {@see \App\Domain\Content\Services\HeroStatus}, en campos NUEVOS que no tocan el chip del hero.

    ❗❗❗ **EL DÍA SE RESALTA SOLO MIENTRAS SU HORARIO ESTÁ VIGENTE**, y son DOS condiciones que se
    suman: `is_today` (que ya se apaga cuando manda una temporada o una fecha especial, `#307`) **y**
    que el estado sea `open` o `later`. Con el parque ya cerrado, la fila de hoy vuelve a ser una
    fila de calendario — si siguiera resaltada, el resaltado diría «esto es lo que rige ahora» sobre
    unas horas que ya pasaron.

    ⚠️⚠️ **TRES COSAS DEL ARTBOARD NO SE ESCRIBEN, y las tres son `[DECIDIDO owner, 2026-09-10]`:**
      · **el aparcamiento** — no entra en esta tanda: el dato no está en el panel y hay dos
        versiones en conflicto (`#297` ya lo retiró del código por lo mismo);
      · **«Los festivos, como el finde»** — es una promesa que el producto no puede saber, así que
        la sección publica las fechas especiales que haya y nada más;
      · **el teléfono y «Cómo llegar»** — el canvas cierra la sección con *«cero enlaces y cero
        botones»* con el mapa cargado. El teléfono sigue en el menú y en el pie (medido en `#307`),
        así que no se pierde; lo que sí se pierde es la puerta a Google Maps **cuando el mapa carga**
        —queda el propio mapa, que es interactivo—, y con el mapa bloqueado vuelve.

    ▶ Esto **revierte parte de `#307`**, que puso aquí tres tarjetas con el teléfono. Aquella tanda
    rompía el molde editorial heredado; ésta adopta el artboard, que es la decisión de `#469`.
--}}

@php
    $rows = $schedule->weeklyRows();
    $specials = $schedule->upcomingSpecialDates(40);
    $hasAddress = filled($site['address1'] ?? null) || filled($site['address2'] ?? null);

    // ⚠️ **El aviso va FUERA del pliegue y la lista DENTRO**, y el artboard escribe por qué: «lo que
    // urge no se esconde detrás de un clic». «Cerca» son tres semanas — un calendario entero está
    // muerto 360 días al año.
    $proxima = collect($specials)->first(fn (array $s): bool => $s['days_away'] >= 0 && $s['days_away'] <= 21);

    // ⚠️⚠️ **El estado y la tabla NO se pintan si el parque no tiene horario.** El artboard lo dice
    // con sus palabras: «si el horario no está puesto en el panel, el producto no dice ni "abierto"
    // ni la tabla: no se inventa nada». La sección se queda con la mitad de dónde.
    $hayHorario = $rows !== [] && ! empty($heroStatus);
    $vigente = $hayHorario && in_array($heroStatus['face'] ?? '', ['open', 'later'], true);
@endphp

<div class="visit">
    {{-- ── CUÁNDO ─────────────────────────────────────────────────────────────────────────────
         La tarjeta blanca es la ÚNICA de la sección, y eso es lo que distingue al dato: el estado
         comparte nivel tipográfico con el titular de la sección (en móvil no hay ningún nivel legal
         por encima de 34), así que lo que lo separa son **el color y la tarjeta**. --}}
    @if ($hayHorario)
        <div class="visit__when">
            <p class="visit__today">
                <span class="visit__dot visit__dot--{{ $heroStatus['face'] }}" aria-hidden="true"></span>
                <span>{{ __('landing.info.today_is', ['day' => mb_strtolower($heroStatus['day'])]) }}</span>
            </p>

            <p class="visit__state visit__state--{{ $heroStatus['face'] }}">{{ $heroStatus['title'] }}</p>
            @if (! empty($heroStatus['line']))
                <p class="visit__line">{{ $heroStatus['line'] }}</p>
            @endif

            <dl class="visit__rows">
                @foreach ($rows as $row)
                    {{-- ⚠️ El resaltado es SUPERFICIE (Nube), nunca color: el color de esta sección
                         está reservado al estado, que es el único verde que hay aquí. --}}
                    <div @class(['is-now' => $vigente && $row['is_today']])>
                        <dt>{{ $row['label'] }}</dt>
                        <dd>{{ $row['time'] }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($proxima)
                {{-- El aviso de la excepción que viene. Una regla no avisa de su excepción: «los
                     festivos como el finde» es cierta hasta el 25 de diciembre. --}}
                <p class="visit__exception">
                    <span class="visit__dot visit__dot--warn" aria-hidden="true"></span>
                    <span>{{ __('landing.info.special_soon', ['date' => $proxima['date'], 'detail' => $proxima['detail']]) }}</span>
                </p>
            @endif

            {{-- ⚠️⚠️ **El pliegue solo existe si hay MÁS de una fecha que enseñar**, y no es celo: un
                 control que abre una lista de un elemento —el que ya está anunciado encima— cuesta
                 48 px para no decir nada. Con cero fechas no hay ni aviso ni pliegue, que es la
                 regla del sistema: una sección cuyo contenido pone el panel desaparece con cero
                 filas, sin caja vacía. --}}
            @if (count($specials) > 1)
                <div class="visit__fold" x-data="{ abierto: false }">
                    {{-- ⚠️ **El signo es + y −, no una flecha**: una flecha dice «vas a otro sitio» y
                         aquí no te mueves. Es la misma distinción que la FAQ.
                         ⚠️ Y el control es un botón SIN BORDE: dentro de una tarjeta que ya tiene el
                         suyo, un segundo borde a 12 px del primero se lee como caja dentro de caja. --}}
                    <button type="button" class="visit__fold-btn"
                            x-on:click="abierto = ! abierto"
                            x-bind:aria-expanded="abierto ? 'true' : 'false'"
                            aria-controls="visit-specials">
                        <span x-text="abierto ? @js(__('landing.info.specials_close')) : @js(__('landing.info.specials_open'))">{{ __('landing.info.specials_open') }}</span>
                        <span class="visit__fold-sign" aria-hidden="true" x-text="abierto ? '–' : '+'">+</span>
                    </button>

                    <dl class="visit__rows visit__rows--specials" id="visit-specials" x-cloak x-show="abierto">
                        @foreach ($specials as $sd)
                            <div><dt>{{ $sd['date'] }}</dt><dd>{{ $sd['detail'] }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    @endif

    {{-- ── DÓNDE ──────────────────────────────────────────────────────────────────────────────
         ⚠️⚠️ **La dirección va SIEMPRE fuera del iframe**, con mapa y sin mapa, y es la regla que
         cerró la sección en el canvas: un bloqueador tumba el widget **con las cookies aceptadas** y
         entonces no salta ningún aviso — sale un hueco. Si la dirección viviera dentro del
         condicional del consentimiento, ese visitante se quedaría sin saber dónde es. --}}
    <div class="visit__where">
        <x-site.consent-frame category="maps" :src="$site['maps_embed']"
            :title="__('landing.info.address_title')"
            wrapper-class="visit__frame"
            frame-class="visit__iframe"
            referrerpolicy="no-referrer-when-downgrade" allowfullscreen>
            {{-- ⚠️ **La puerta a Google Maps SOLO existe con el mapa bloqueado**, que es la mitad
                 de la regla de cierre del canvas: con el mapa cargado la sección se queda en «cero
                 enlaces y cero botones» porque el propio mapa ya es interactivo. Sin él, quien
                 viene conduciendo se quedaría sin ninguna forma de abrirlo. --}}
            <x-slot:fallback>
                @if (($site['maps'] ?? '#') !== '#')
                    <a class="consent-frame__ph-link visit__maps" href="{{ $site['maps'] }}"
                       target="_blank" rel="noopener">
                        <span>{{ __('landing.info.open_in_maps') }}</span>
                        <x-icons.arrow-right class="arrow" :width="18" :height="18" />
                    </a>
                @endif
            </x-slot:fallback>

            <span class="map-pin"></span>
        </x-site.consent-frame>

        @if ($hasAddress)
            {{-- ⚠️ **`visit__place` y NO `visit__addr`**: aquélla es de `/contacto` y reusarla habría
                 hecho dos cosas malas a la vez — vestir esto con los valores de otra página y dejar
                 muertas las reglas de ésta. Lo cazó la guarda, no una relectura. --}}
            <p class="visit__place">
                <span class="visit__addr-1">{{ $site['address1'] }}</span>
                <span class="visit__addr-2">{{ $site['address2'] }}</span>
            </p>
        @endif
        <p class="visit__credit">{{ __('landing.info.map_credit') }}</p>
    </div>
</div>
