<?php

namespace App\Http\Middleware;

use App\Domain\Content\Services\SocialEmbed;
use App\Domain\Platform\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 4.5.0 — Cabeceras de seguridad (estándar "Reforzado", regla 9 de docs/SEGURIDAD.md, #54).
 *
 * Añade defensas de navegador a todas las respuestas web: evitar adivinar tipos
 * (nosniff), anti-clickjacking, política de "referer", Permissions-Policy y una CSP
 * "progresiva" compatible con la pila real del sitio (Alpine + Livewire, Bunny Fonts y
 * Turnstile). La CSP estricta (sin 'unsafe-*') y HSTS se endurecen en la Fase 9
 * (ver docs/SEGURIDAD.md §10 y la decisión #54 en docs/DECISIONES.md).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');

        // No pisar una CSP específica que una respuesta concreta haya fijado.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    /**
     * CSP "progresiva": bloquea framing/base/forms y orígenes externos no usados, pero
     * permite 'unsafe-inline'/'unsafe-eval', que hoy necesitan Alpine, Livewire y los
     * estilos inline del mockup. Orígenes externos reales: Bunny Fonts (tipografías) y
     * Cloudflare Turnstile (anti-bot por clave). Se endurecerá en la Fase 9 (#54).
     */
    protected function contentSecurityPolicy(): string
    {
        $script = ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://challenges.cloudflare.com'];
        $style = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];
        $connect = ["'self'", 'https://challenges.cloudflare.com'];

        // `form-action`: el cliente envía el formulario auto-POST al TPV de Redsys (5.5b, #104).
        // Limitamos al origen Redsys del **entorno configurado** — defensa en profundidad
        // adicional (#113, B2): en producción el navegador NO admite envíos a la URL de
        // sandbox (y viceversa). Hardening puro: si por error alguien dejara el setting
        // `redsys_environment=test` en producción, la pasarela aún funcionaría, pero el
        // navegador rechazaría el form antes de enviarlo — alerta visible inmediata.
        $isLive = Setting::value('redsys_environment', 'test') === 'live';
        $formAction = ["'self'", $isLive ? 'https://sis.redsys.es' : 'https://sis-t.redsys.es:25443'];

        // En local, permitir el servidor de desarrollo de Vite (HMR) si se usa `npm run dev`.
        // En producción los assets se sirven compilados desde el propio dominio ('self').
        //
        // ⚠️ El puerto se DERIVA de la configuración (`VITE_PORT`), no se quema. Estaba quemado a
        // 5173 mientras `.env` fijaba 5374 y `.env.example` 5274: tres valores a la vez, con el
        // resultado de que la propia CSP bloqueaba el HMR sin que nada lo dijera. Quemar otro
        // literal solo habría movido el problema al siguiente cambio de puerto.
        if (app()->environment('local')) {
            $devPort = (int) config('app.vite_dev_port');

            foreach (['localhost', '127.0.0.1'] as $host) {
                $script[] = "http://{$host}:{$devPort}";
                $style[] = "http://{$host}:{$devPort}";
                $connect[] = "http://{$host}:{$devPort}";
                $connect[] = "ws://{$host}:{$devPort}";
            }
        }

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            'form-action '.implode(' ', $formAction),
            "img-src 'self' data:",
            "font-src 'self' https://fonts.bunny.net data:",
            'style-src '.implode(' ', $style),
            'script-src '.implode(' ', $script),
            // challenges.cloudflare.com → widget Turnstile; www.google.com → mapa embebido de la
            // landing (#206); snapwidget/lightwidget → feed social «en directo» (#215).
            "frame-src 'self' https://challenges.cloudflare.com https://www.google.com ".SocialEmbed::cspFrameSrc(),
            'connect-src '.implode(' ', $connect),
        ];

        return implode('; ', $directives);
    }
}
