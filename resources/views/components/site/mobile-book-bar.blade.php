{{-- Barra flotante de reserva (MÓVIL) — jerarquía de CTAs, mockup `design_mockup/jerarquia-ctas.html` §03.
     En móvil el CTA primario SALE del header (ver `.nav-cta-med { display:none }` en site.css, Capa D) y
     reaparece como botón FLOTANTE inferior que entra deslizando desde abajo:
       • Landing (única con hero): aparece cuando el CTA «prime» del hero abandona el viewport (mismo
         sentinel `.hero__stage-bottom` que usa el reveal del header en desktop) → hero y barra NUNCA
         co-visibles.
       • Resto de páginas (sin hero): aparece al hacer scroll, antes que en la landing (~1/3 de pantalla).
       • En ambos casos se oculta al llegar al pie real (`.foot`), con el sidecart abierto o con el
         banner de cookies.
     El botón toma del CTA «prime» del hero (`home.blade.php`) la estructura, icono, textos (`landing.hero.*`),
     tipografía y ALTURA, pero en su variante NEGRA (`.cta-prime` base, no `--onvideo`) y FULL-WIDTH (ancho del
     viewport con el padding del contenedor), con hover y clic en color de marca. Solo cambia el contenedor
     (float sticky inferior). El `<a>` a `/entradas` da fallback sin JS; `@click.prevent` reutiliza el store de
     compra. La aparición vive en `Alpine.data('mobileBookBar')` (app.js); el deslizamiento, en CSS (`.book-bar`).
     En desktop no se muestra (CSS `display:none` por encima de 720px). --}}
<div class="book-bar" x-data="mobileBookBar"
     :class="visible && 'book-bar--on'"
     x-effect="document.body.classList.toggle('book-bar-visible', visible)">
    <a href="{{ route('entradas') }}" @click.prevent="$store.purchase.open()"
       class="cta-prime book-bar__cta">
        <span class="cta-prime__ico"><x-icons.ic-e2 :width="54" :height="35" /></span>
        <span class="cta-prime__body">
            <span class="cta-prime__t">{{ __('landing.hero.cta_buy') }}</span>
            <span class="cta-prime__s">
                @if (! empty($ctaMinPriceLabel))
                    {{ __('landing.hero.cta_buy_from', ['amount' => $ctaMinPriceLabel]) }}
                @else
                    {{ __('landing.hero.cta_buy_no_price') }}
                @endif
            </span>
        </span>
        <span class="cta-prime__arrow" aria-hidden="true">→</span>
    </a>
</div>
