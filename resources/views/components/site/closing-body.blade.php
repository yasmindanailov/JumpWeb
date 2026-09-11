{{-- ══ EL CUERPO DE LA TARJETA DEL CIERRE · el de la portada y el de las interiores ═══════════════
     `DECISIONES #526` · carril de diseño Fase 3 · T3a·4.

     Titular, texto y botones, en UN sitio: la portada lo pinta dentro de su tarjeta —la que crece y
     lleva el minijuego— y las interiores dentro de la suya, que no hace ninguna de las dos cosas.
     ▶ Es un componente y no una copia porque aquí vive una REGLA, no solo marcado: a dónde lleva
     «Reservar» cuando la venta online está cerrada (al teléfono si lo hay, si no a las tarifas). Con
     dos copias, un día la portada y las páginas mandarían al visitante a sitios distintos.

     ⚠️ La CAJA no está aquí a propósito: la de la portada lleva los manejadores del juego en el propio
     elemento, y `SaltaJuegoTest` los exige ahí.

     Las dos piezas opcionales son las que el owner quitó en las interiores (`[DECIDIDO owner]`,
     `#521`): el eslogan —una vez por superficie— y el segundo botón, «Llamar» —solo «Reservar»—. --}}
@props(['slogan' => false, 'call' => false])
<div class="reserve__body">
    @if ($slogan)
        {{-- ❗ **EL ESLOGAN A ROTULADOR, EN EL CIERRE** (`DECISIONES #477`, `[DECIDIDO owner]`).
             El marco aprobado del canvas lo manda al cierre **y** al menú, o sea una vez por
             SUPERFICIE y no una vez por página — y las dos superficies nunca se ven a la vez,
             porque el menú es `inset: 0` y tapa la portada entera (el mismo razonamiento que
             ya está escrito en `menu.blade.php`).
             ⚠️ Va **antes** del titular y no después: es el guiño que presenta la última
             pantalla antes de comprar, no un pie de página del bloque.
             ⚠️ Comparte la clave con el menú (`landing.hero.kicker`) a propósito: es el MISMO
             eslogan, y tenerlo en dos claves invita a que un día digan cosas distintas. --}}
        <p class="reserve__slogan">{{ __('landing.hero.kicker') }}</p>
    @endif
    <h2>
        {{ __('landing.reserve.title') }}<br />
        <span class="stroke">{{ __('landing.reserve.stroke') }}</span>
        <span class="fill">{{ __('landing.reserve.fill') }}</span>
    </h2>
    <p>{{ __('landing.reserve.copy') }}</p>
    <div class="reserve__actions">
        <a href="{{ $site['sales_online'] ? route('entradas') : ($site['has_phone'] ? 'tel:'.$site['phone_tel'] : route('precios')) }}"
           @if ($site['sales_online']) @click.prevent="$store.purchase.open()" @endif class="reserve__act">{{ __('landing.reserve.cta') }}</a>
        @if ($call && $site['has_phone'])
            <a href="tel:{{ $site['phone_tel'] }}" class="reserve__act reserve__act--alt">{{ __('landing.reserve.cta2') }} {{ $site['phone'] }}</a>
        @endif
    </div>
</div>
