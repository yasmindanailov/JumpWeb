{{-- ══ LOS CANALES DE CONTACTO · una tarjeta por cada uno que la instalación tenga ═══════════════
     `DECISIONES #535` · carril de diseño Fase 3 · T3b. Artboard `Contacto PJP` 1a/1b.

     El artboard dibuja TRES tarjetas —fijo, móvil con WhatsApp y correo— porque su dueño tiene esos
     tres datos. Aquí se pintan los que el panel tenga, y solo ésos: un canal sin dato no se anuncia.
     Sin ninguno, el bloque entero desaparece (vacío es una respuesta, no un hueco).

     ❗❗ **Y EL DATO CONTESTA SOLO LA PREGUNTA QUE EL CANVAS LE HACÍA AL OWNER** (*«¿el WhatsApp es
     el mismo número que el móvil, y quién lo lee?»*): cuando el teléfono y el WhatsApp son el mismo
     número sale **UNA** tarjeta que dice las dos cosas. Con dos, la página repetiría el mismo número
     bajo dos rótulos distintos — que es exactamente lo que hace creer que son dos números.

     ⚠️ **La comparación es por DÍGITOS.** `contact.phone` se escribe «+34 641 99 57 14» y el payload
     normaliza `contact.whatsapp` a «34641995714»: comparados en crudo no coincidirían nunca, y la
     regla quedaría escrita y muerta.

     ⚠️ **Va aquí y no en el controlador** porque `$site` lo reparte un `View::composer('*')`, así que
     fuera de una vista no existe. Componerlo antes obligaría a releer `settings` y a repetir la
     normalización del teléfono — dos derivaciones del mismo dato, que es como divergen. --}}
@php
    $phone = (string) ($site['phone'] ?? '');
    $phoneTel = (string) ($site['phone_tel'] ?? '');
    $whatsapp = (string) ($site['whatsapp'] ?? '');   // ya normalizado a dígitos en el payload
    $email = (string) ($site['email'] ?? '');

    $mismoNumero = $whatsapp !== '' && $phoneTel !== ''
        && preg_replace('/\D/', '', $phoneTel) === $whatsapp;

    $canales = [];

    if ($phoneTel !== '') {
        $canales[] = [
            'k' => $mismoNumero ? 'phone_whatsapp' : 'phone',
            'v' => $phone !== '' ? $phone : $phoneTel,
            // Con un solo número manda el canal por el que hoy escribe más gente, que es además el
            // que el artboard pone en esa tarjeta.
            'href' => $mismoNumero ? 'https://wa.me/'.$whatsapp : 'tel:'.$phoneTel,
        ];
    }

    if ($whatsapp !== '' && ! $mismoNumero) {
        $canales[] = ['k' => 'whatsapp', 'v' => '+'.$whatsapp, 'href' => 'https://wa.me/'.$whatsapp];
    }

    if ($email !== '') {
        $canales[] = ['k' => 'email', 'v' => $email, 'href' => 'mailto:'.$email];
    }
@endphp

{{-- ⚠️ **`$attributes` se propaga, y no es cortesía**: quien lo usa le pasa el `grid-area` de la
     página (`.contact-layout__channels`). Sin esto la clase se pierde en silencio y el bloque se
     auto-coloca en la celda que queda libre — funcionaba por casualidad, y la casualidad se acaba en
     cuanto la rejilla gane una fila. Lo vio la sonda, que no encontró el selector. --}}
@if ($canales)
    <section {{ $attributes->class('channels') }} aria-labelledby="channels-title">
        <h2 class="channels__title" id="channels-title">{{ __('site.contact_channels_title') }}</h2>
        <ul class="channels__list" role="list">
            @foreach ($canales as $c)
                <li>
                    {{-- ⚠️ Los canales externos abren fuera; `tel:` y `mailto:` NO llevan `target`:
                         abrir una pestaña en blanco para entregar el enlace al sistema operativo la
                         deja abierta y vacía. --}}
                    <a class="channel" href="{{ $c['href'] }}"
                       @if (str_starts_with($c['href'], 'https://')) target="_blank" rel="noopener" @endif>
                        <span class="channel__body">
                            <span class="channel__label">{{ __('site.contact_channel.'.$c['k'].'.t') }}</span>
                            <span class="channel__value">{{ $c['v'] }}</span>
                            <span class="channel__hint">{{ __('site.contact_channel.'.$c['k'].'.d') }}</span>
                        </span>
                        <span class="channel__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
