{{-- ══ EL ANFITRIÓN MÍNIMO de /contacto · lo que el producto sirve SIN paquete de instancia ═════
     F5 · T2b (`specs/paquete-de-instancia.md` §4.1 y §4.4, `DECISIONES #654`). La landing de PlayJump
     se fue a su instancia (`web/contacto.blade.php` de su paquete); esto es lo que queda en el
     producto: el armazón, el formulario ENTERO y los datos —canales, atajos, dirección—, sin arte de
     ningún cliente. Es marcado del PRODUCTO, así que sus guardas SÍ pueden mirar el HTML
     (`AnfitrionContactoTest`); las de la landing de un cliente, no (`#649`).

     ⚠️ Una instalación recién montada sirve esto y FUNCIONA: se puede escribir al parque desde el
     primer día. No es una página de error ni un «próximamente».
     ⚠️ Usa solo lo que el producto publica —`<x-layout>`, `<x-site.nav>`, `<x-site.page-head>`, los
     canales, el honeypot, Turnstile, las bandas y el pie—: lo mismo que una landing de instancia puede
     usar por la vía B. Lo que aquí no hay (la fachada, las superficies de tinta, la salida al mapa de
     la portada) es diseño de la instancia, no del producto.
     ⚠️ La suite corre SIN paquete (`phpunit.xml`), así que `/contacto` es ESTA página en cualquier
     máquina: el gate no puede salir verde donde hay paquete y rojo donde no (§4.5). --}}
<x-layout :title="__('site.contact_title')" :description="__('site.contact_intro_plain')">
<div x-data="landing">
    <x-site.nav />
    <main id="main" class="page page--contact wrap">
        {{-- La entradilla promete el plazo SOLO con horario publicado: `$heroStatus` es `null` sin
             ninguna apertura configurada, y prometer «te contestamos en nuestro horario» sin horario
             sería una promesa que el producto no puede sostener. --}}
        <x-site.page-head
            :title="__('site.contact_title')"
            :lede="$heroStatus ? __('site.contact_intro') : __('site.contact_intro_plain')" />

        <div class="contact-layout">
            <x-site.contact-channels class="contact-layout__channels" />

            <div class="contact-layout__form">
                @if (session('contact_sent'))
                    <div class="form-success">{{ __('site.contact_success') }}</div>
                @else
                    <form method="POST" action="{{ route('contacto.store') }}" class="form contact-form">
                        @csrf

                        <h2 class="contact-form__title">{{ __('site.contact_form_title') }}</h2>

                        <x-site.honeypot />

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
                            {{-- ⚠️ Sin opción preseleccionada (`#535`): quien no toca el desplegable no manda
                                 un tema que no ha elegido. Las claves son las del controlador (`$topics`). --}}
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

                        {{-- El aviso de privacidad SIN casilla (`#350`): aviso, no aceptación; y el enlace
                             fuera de la frase, como control propio. Es criterio del producto, no de una
                             landing: las dos altas lo siguen igual. --}}
                        <div class="contact-form__privacy">
                            <p>{{ __('site.contact_privacy_notice') }}</p>
                            <a href="{{ route('legal.privacidad') }}" target="_blank" rel="noopener" data-tap>
                                <span>{{ __('site.contact_privacy_link') }}</span>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>

                        <x-site.turnstile />

                        {{-- Tinta, no el relleno de acción (`#551`): en toda la web el relleno de acción
                             significa COMPRAR, y enviar un mensaje no es comprar. --}}
                        <div class="contact-form__send">
                            <button type="submit" class="btn btn--ink btn--lg">{{ __('site.contact_send') }}</button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="contact-layout__side">
                {{-- Los atajos salen del INVENTARIO (`answers`, que pasa el controlador): una página en
                     mantenimiento no se ofrece y el ancla de Dudas solo sale con dudas en el panel. --}}
                @if ($answers)
                    <aside class="answers" aria-labelledby="answers-title">
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

                {{-- La dirección, ESCRITA por el producto (`VenueAddress`, `#650`). Sin salida al mapa: el
                     ancla de la portada es de la landing de cada instancia, no de este respaldo. --}}
                @php($direccion = \App\Domain\Platform\Services\VenueAddress::written($site['address1'] ?? null, $site['address2'] ?? null))
                @if ($direccion)
                    <section class="where" aria-labelledby="where-title">
                        <p class="where__label" id="where-title">{{ __('site.contact_where_title') }}</p>
                        <p class="where__addr">{{ $direccion }}</p>
                    </section>
                @endif
            </div>
        </div>
        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
