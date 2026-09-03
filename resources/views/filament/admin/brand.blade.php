{{-- Marca del panel: el logotipo de la instalación (o el wordmark del negocio si no lo hay) con
     «Administración» debajo. Se renderiza en el topbar vía `brandLogo`.

     ▶ `#215` puso el subtítulo · `#325` trajo el logotipo de la instalación · **`#461` le da la
     variante para FONDO OSCURO y cambia el subtítulo a «Administración»**.

     ⚠️⚠️ **NO HAY FICHERO NUEVO: se reutiliza `client-logo-ink.svg`, el hueco que ya existe desde
     `#216`.** La web lo usa en `components/site/brand.blade.php` para el menú a pantalla completa,
     que cae sobre tinta; el modo oscuro del panel es el MISMO problema —un fondo oscuro— y por
     tanto el mismo rol. Inventar un `client-logo-dark.svg` habría sido pedirle al cliente dos
     ficheros para una sola cosa, y dejar que se desincronizaran.

     ⚠️ **Por qué hace falta**: el panel tiene modo oscuro de verdad (tres modos, por defecto
     siguiendo al sistema) y en oscuro el topbar es casi negro —medido: `oklch(0.21 …)`—. El
     logotipo va dentro de un `<img>`, y **una imagen no se adapta a un tema**: no ve la clase
     `dark` del documento. Las salidas descartadas: `filter: invert()` destroza los colores de
     marca, y servirlo en línea con `currentColor` solo vale para un logotipo de UN color (el de
     este cliente es multicolor, `#211`).

     ⚠️⚠️ **Con DOS imágenes el nombre accesible se DUPLICA o se PIERDE** —`display: none` saca el
     `alt` del árbol de accesibilidad, así que la que se oculte en cada tema se queda muda—: cuando
     están las dos, **ninguna lleva `alt` con texto** (van `aria-hidden`) y el nombre lo pone un
     `sr-only` que está SIEMPRE presente. Con una sola se conserva el `alt` de siempre. Es
     literalmente el patrón que la web ya resolvió en `#216`; aquí se copia, no se reinventa.

     ⚠️ **Falla hacia VISIBLE, nunca hacia un hueco**: sin la variante de tinta se sigue enseñando
     el logotipo claro en los dos temas (la conducta de antes de `#461`), y sin logotipo se cae al
     wordmark de texto, que se adapta solo por CSS. Lo vigila `PanelShellTest`.

     ⚠️ `@filemtime` hace las DOS cosas en una llamada a disco —existencia y cache-busting—: devuelve
     `false` si el fichero no está, así que no hace falta un `file_exists` aparte. --}}
@php($businessName = \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name'))
@php($clientLogo = @filemtime(public_path('img/client-logo.svg')))
@php($clientLogoInk = @filemtime(public_path('img/client-logo-ink.svg')))

<span class="fi-jj-brand flex h-full flex-col justify-center items-start text-left leading-tight">
    @if ($clientLogo && $clientLogoInk)
        <img class="fi-jj-brand-logo h-9 w-auto dark:hidden"
             src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
             alt="" aria-hidden="true" />
        <img class="fi-jj-brand-logo hidden h-9 w-auto dark:block"
             src="{{ asset('img/client-logo-ink.svg') }}?v={{ $clientLogoInk }}"
             alt="" aria-hidden="true" />
        <span class="sr-only">{{ $businessName }}</span>
    @elseif ($clientLogo)
        <img class="fi-jj-brand-logo h-9 w-auto"
             src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
             alt="{{ $businessName }}" />
    @else
        <span class="fi-jj-brand-wordmark text-lg font-bold tracking-tight text-gray-950 dark:text-white">
            {{ $businessName }}<span class="text-primary-500">.</span>
        </span>
    @endif

    <span class="fi-jj-brand-subtitle text-xs font-medium text-gray-500 dark:text-gray-400">
        {{ __('admin.panel_subtitle') }}
    </span>
</span>
