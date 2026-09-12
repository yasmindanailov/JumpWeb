<x-layout :title="__('site.contact_title')" :description="__('site.contact_intro_plain')">
<div x-data="landing">
    <x-site.nav />

    {{-- ══ /contacto · PREGUNTAR, Y NADA DE LO QUE YA HACE «VISÍTANOS» ═══════════════════════════
         Carril de diseño Fase 3 · T3b (`DECISIONES #535`). Artboard `Contacto PJP` **1a** (móvil) +
         **1b** (escritorio).

         ❗❗❗ **LO QUE ESTA PÁGINA NO HACE, Y ES SU DISEÑO**: aquí **no hay mapa**, ni tabla de
         horario, ni aparcamiento. Eso lo contesta la sección «Visítanos» de la portada con su
         horario en vivo, y duplicarlo aquí convertiría esta URL en «un trozo de la portada con otro
         título» —el riesgo que el propio canvas le pone a esta página—. La dirección va **escrita**,
         que es la regla que cerró aquella sección (el dato crítico siempre fuera del iframe), con un
         enlace a donde el mapa ya está.

         ⚠️⚠️ **Y el argumento que la salva CAMBIÓ dentro de esta tanda.** El artboard dice que lo
         que la distingue es que «el horario de atención no es el de apertura»; `[DECIDIDO owner,
         2026-09-12]`: **son el mismo**. Así que lo que la separa de «Visítanos» no es *cuándo*, es
         *qué se hace aquí*: allí se consulta, aquí **se escribe**. ▶ Consecuencia práctica: **no hay
         campo nuevo de horario de atención** —el plazo se deriva del horario que ya existe— y a nadie
         se le ocurra añadirlo luego «para completar el artboard».

         ⚠️ El plazo se reparte en dos sitios a propósito: la ENTRADILLA dice **cuándo** y la línea
         de al lado del botón dice **por dónde**. El artboard pide que el plazo se repita junto al
         botón «que es donde se decide enviar»; repetir la misma frase habría sido ruido. --}}
    <main id="main" class="page page--contact wrap">
        {{-- La entradilla promete el plazo SOLO si hay horario publicado: `$heroStatus` es `null`
             cuando no hay ninguna apertura configurada (`HeroStatus::current()`), y prometer «te
             contestamos en nuestro horario» sin horario sería una promesa que el producto no puede
             sostener. Cero consultas nuevas: el payload ya lo trae resuelto. --}}
        <x-site.page-head
            :title="__('site.contact_title')"
            :lede="$heroStatus ? __('site.contact_intro') : __('site.contact_intro_plain')" />

        <div class="contact-layout">
            {{-- ⚠️ **Los canales van los PRIMEROS en el DOM**, como en el artboard de móvil: quien
                 entra tiene que ver que puede llamar antes de ponerse a escribir. En escritorio el
                 grid los coloca en la columna derecha, a la altura del inicio del formulario, así
                 que el orden de lectura y el de tabulación siguen siendo el mismo en los dos. --}}
            <x-site.contact-channels class="contact-layout__channels" />

            <div class="contact-layout__form">
                @if (session('contact_sent'))
                    <div class="form-success">{{ __('site.contact_success') }}</div>
                @else
                    <form method="POST" action="{{ route('contacto.store') }}" class="form contact-form">
                        @csrf

                        <h2 class="contact-form__title">{{ __('site.contact_form_title') }}</h2>
                        <p class="contact-form__lede">{{ __('site.contact_form_lede') }}</p>

                        {{-- honeypot anti-spam: display:none para que el autocompletar no lo rellene.
                             ⚠️ No se llama `website` por capricho de nombre: ese sí lo rellenan los
                             gestores de contraseñas (`waiver-por-reserva.md` §8.2) — aquí se conserva
                             el nombre histórico porque cambiarlo invalidaría el filtro del servidor,
                             que es lo único que lo lee. --}}
                        <div style="display:none" aria-hidden="true">
                            <label>{{ __('site.contact_hp') }}<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>

                        {{-- Escritorio: los campos se emparejan de dos en dos —nombre con correo,
                             teléfono con tema— y el mensaje va entero. El artboard lo razona así: «un
                             campo de nombre a 672 px de ancho es un campo mal hecho». --}}
                        <div class="form__row">
                            <label class="form__field">
                                <span>{{ __('site.contact_name') }}</span>
                                <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>
                                @error('name')<em class="form__error">{{ $message }}</em>@enderror
                            </label>
                            <label class="form__field">
                                <span>{{ __('site.contact_email') }}</span>
                                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                                @error('email')<em class="form__error">{{ $message }}</em>@enderror
                            </label>
                        </div>

                        <div class="form__row">
                            <label class="form__field">
                                <span>{{ __('site.contact_phone') }} <i class="form__opt">({{ __('site.contact_phone_hint') }})</i></span>
                                <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                                @error('phone')<em class="form__error">{{ $message }}</em>@enderror
                            </label>
                            {{-- ⚠️ **Sin opción preseleccionada.** El artboard deja «Un cumpleaños»
                                 arriba; así, quien no toca el desplegable manda un tema que no ha
                                 elegido, y el correo llegaría mal clasificado sin que nadie lo sepa.
                                 La primera opción no tiene valor: sin elegir, no se afirma nada. --}}
                            <label class="form__field">
                                <span>{{ __('site.contact_topic') }}</span>
                                <select name="topic">
                                    <option value="">{{ __('site.contact_topic_none') }}</option>
                                    @foreach ($topics as $topic)
                                        <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ __('site.contact_topics.'.$topic) }}</option>
                                    @endforeach
                                </select>
                                @error('topic')<em class="form__error">{{ $message }}</em>@enderror
                            </label>
                        </div>

                        <label class="form__field">
                            <span>{{ __('site.contact_message') }}</span>
                            <textarea name="message" rows="5" required>{{ old('message') }}</textarea>
                            @error('message')<em class="form__error">{{ $message }}</em>@enderror
                        </label>

                        {{-- ❗❗ **EL AVISO DE PRIVACIDAD, SIN CASILLA** (`[DECIDIDO owner, 2026-09-12]`).
                             El artboard pide una casilla obligatoria; este repo ya decidió lo
                             contrario para las dos altas en `#350` —«la privacidad pierde la casilla
                             pero NO el rastro»—, y tener dos criterios en la misma web sería peor que
                             cualquiera de los dos. Lo que sí cierra un hueco real: hasta hoy esta
                             página **no decía nada** de qué se hace con lo que escribes.
                             ⚠️ **El enlace va FUERA de la frase y se distingue del párrafo**: en `#350`
                             el mismo enlace era invisible —idéntico color, sin subrayado— y eso lo
                             convertía en carga en lugar de en información. --}}
                        {{-- ⚠️ **El enlace va FUERA del párrafo y es un control propio**, como pide
                             el artboard: dentro de la frase mide 18 px de alto contra el suelo de 48
                             (medido). Ahí sería una excepción táctil nueva —hoy solo hay UNA en toda
                             la web, el enlace en línea del texto de cookies (`#264`)—, y ésta es
                             evitable: la frase no lo necesita pegado. --}}
                        <div class="contact-form__privacy">
                            <p>{{ __('site.contact_privacy_notice') }}</p>
                            <a href="{{ route('legal.privacidad') }}" target="_blank" rel="noopener" data-tap>
                                <span>{{ __('site.contact_privacy_link') }}</span>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>

                        {{-- Anti-bot Turnstile (auditoría Fase 1, A4): solo se renderiza si hay claves
                             configuradas (security.turnstile_*). Sin claves no aparece nada y el envío
                             funciona igual. El CSP ya permite challenges.cloudflare.com. --}}
                        @if (\App\Domain\Platform\Services\Turnstile::enabled())
                            <div class="cf-turnstile" data-sitekey="{{ \App\Domain\Platform\Services\Turnstile::siteKey() }}"></div>
                            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                        @endif

                        {{-- ⚠️ **El botón es TINTA, no naranja** — la variante secundaria que la hoja
                             de componentes declara (`.btn--ink`, `#551`). El relleno de acción
                             significa COMPRAR en toda la web, y enviar un mensaje no es comprar. --}}
                        <div class="contact-form__send">
                            <button type="submit" class="btn btn--ink btn--lg">{{ __('site.contact_send') }}</button>
                            <span class="contact-form__note">{{ __('site.contact_reply_note') }}</span>
                        </div>
                    </form>
                @endif
            </div>

            <div class="contact-layout__side">
                {{-- ══ QUIZÁ YA ESTÁ CONTESTADO ══════════════════════════════════════════════════
                     Los destinos salen del INVENTARIO (`SiteDestinations::answersItself()`), así que
                     una página en mantenimiento deja de ofrecerse aquí también y el ancla de Dudas
                     solo sale si la portada la pinta. Sin ninguno, la chapa no se dibuja.
                     ⚠️ `data-surface="ink"` va en la TARJETA, nunca en su contenedor (`#484`): ahí
                     además PINTA, y en un contenedor sin radio dejaría un rectángulo detrás. --}}
                @if ($answers)
                    <aside class="answers" data-surface="ink" aria-labelledby="answers-title">
                        <h2 class="answers__title" id="answers-title">{{ __('site.contact_answers_title') }}</h2>
                        <ul class="answers__list" role="list">
                            @foreach ($answers as $a)
                                <li>
                                    <a class="answers__link" href="{{ $a['url'] }}" data-tap>
                                        <span>{{ $a['t'] }}</span>
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </aside>
                @endif

                {{-- ══ DÓNDE ESTAMOS · escrita, y el mapa donde vive ═════════════════════════════
                     ⚠️ El enlace lleva a `/#info`, que es el ancla que la sección «Visítanos» pinta
                     de verdad — verificado en `home.blade.php`. El precedente roto está fichado:
                     `#478` enlazó a `/precios#zona-<slug>` y esa página emite CERO anclas así. --}}
                @if (filled($site['address1'] ?? null) || filled($site['address2'] ?? null))
                    {{-- ⚠️ Las dos líneas del panel se unen con COMA, no con un espacio: «Ctra. de
                         Granada 30813 Lorca» se lee como un número de portal. Y se juntan filtrando
                         las vacías, o una instalación que solo rellene la primera se llevaría una
                         coma colgando al final — el mismo defecto que el colofón del pie ya evita. --}}
                    <section class="where" aria-labelledby="where-title">
                        <p class="where__label" id="where-title">{{ __('site.contact_where_title') }}</p>
                        <p class="where__addr">{{ collect([$site['address1'] ?? null, $site['address2'] ?? null])->filter(fn ($l) => filled($l))->implode(', ') }}</p>
                        <a class="where__cta" href="{{ url('/#info') }}" data-tap>
                            <span>{{ __('site.contact_where_cta') }}</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </section>
                @endif
            </div>
        </div>
    </main>

    <x-site.footer />
</div>
</x-layout>
