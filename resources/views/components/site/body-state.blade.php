{{-- EL ESTADO DEL `<body>` QUE LEEN EL CONSENTIMIENTO, LA ANALÍTICA Y LOS PÍXELES (T4b·4 de `isla-y-landing-nueva.md`
     §4.12): son ATRIBUTOS, y se incluyen DENTRO de la etiqueta con `<body … @include('components.site.body-state')>`,
     en los dos layouts de las páginas públicas (`components/layout.blade.php` y `components/pagina.blade.php`).
     Sacado TAL CUAL del layout de siempre, sin cambiar un atributo: las guardas de la T3 de la analítica
     (`CookieGateBlockingTest`, `PixelsTest`…) lo leen en el HTML y siguen igual. ⚠️ Las páginas de la FIESTA no lo
     llevan (`#739`: el invitado no es un visitante; sin banner, driver ni píxeles). --}}
{{-- Estado inicial del consentimiento de cookies (#219), calculado por el servidor → lo lee el
     store `cookies` de Alpine (`ui/cookie-consent.js`), igual que `purchase`/`auth`. Una clave
     `data-cookie-<categoría>` por cada una de `CookieConsent::OPTIONAL` (T3a: cuatro, ya no dos
     escritas aquí) y la lista en `data-consent-categories`, que es de donde el almacén las lee.
     ⚠️ El tracker (`cajon/track.js`) manda al libro TODAS las `data-cookie-*` como foto del
     consentimiento; `data-consent-categories` no empieza por `cookie` a propósito. --}}
      data-cookie-enabled="{{ ($cookieBannerEnabled ?? false) ? '1' : '' }}"
      data-cookie-decided="{{ ($cookieConsent['decided'] ?? false) ? '1' : '' }}"
      @foreach (\App\Domain\Identity\Services\CookieConsent::OPTIONAL as $consentCategory)
      data-cookie-{{ $consentCategory }}="{{ ($cookieConsent[$consentCategory] ?? false) ? '1' : '' }}"
      @endforeach
      data-consent-categories="{{ implode(',', \App\Domain\Identity\Services\CookieConsent::OPTIONAL) }}"
      data-cookie-endpoint="{{ route('cookies.consent') }}"
      {{-- La herramienta de análisis (`specs/analitica.md` §4.3, T3a·2): solo con driver activo y completo
           viajan sus datos, y el cargador (`cajon/driver.js`) solo la trae con `data-cookie-analytics="1"`.
           `data-analytics-person` —el id OPACO de la cuenta, un HMAC— va únicamente con sesión Y con la
           categoría consentida: es lo que permite el `identify` del régimen identificado. --}}
      @php($analyticsBody = \App\Domain\Platform\Services\Analytics\Drivers::forBody(auth()->id() === null ? null : (int) auth()->id(), (bool) ($cookieConsent['analytics'] ?? false) && ! (bool) (auth()->user()?->analytics_opt_out ?? false)))
      @if ($analyticsBody !== null)
      data-analytics-driver="{{ $analyticsBody['driver'] }}"
      data-analytics-key="{{ $analyticsBody['key'] }}"
      data-analytics-host="{{ $analyticsBody['host'] }}"
      @if ($analyticsBody['person'] !== null) data-analytics-person="{{ $analyticsBody['person'] }}" @endif
      @endif
      {{-- Los píxeles de anuncios (`specs/analitica.md` §4.3, T3b·1): un atributo por píxel CONFIGURADO, y el
           cargador (`cajon/pixels.js`) solo los trae con `data-cookie-marketing="1"`. Sin píxeles, nada. --}}
      @foreach (\App\Domain\Platform\Services\Analytics\Pixels::forBody() as $pixelAttribute => $pixelId)
      {{ $pixelAttribute }}="{{ $pixelId }}"
      @endforeach
