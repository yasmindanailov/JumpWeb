{{-- ══ TURNSTILE · el widget anti-bot, SOLO si la instalación tiene claves ══════════════════════
     Mecanismo del PRODUCTO (F5 · T2b, `DECISIONES #654`). Vivía en línea en `pages/contact.blade.php`.
     Sin claves (`security.turnstile_*`) no se pinta nada y el envío funciona igual; con claves, el
     servidor DESCARTA todo envío sin token —en silencio, como el honeypot (`ContactController`)—.

     ⚠️⚠️ Y por eso es un componente y no tres líneas que copiar: una landing de instancia que las
     olvidara, en una instalación con claves, tendría un formulario que NO ENTREGA NADA sin que nada
     falle ni nadie avise. La instancia pone `<x-site.turnstile />` dentro de su `<form>` y ya.
     El CSP ya permite `challenges.cloudflare.com`. --}}
@if (\App\Domain\Platform\Services\Turnstile::enabled())
    <div class="cf-turnstile" data-sitekey="{{ \App\Domain\Platform\Services\Turnstile::siteKey() }}"></div>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
