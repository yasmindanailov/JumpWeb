<?php

namespace App\Http\Middleware;

use App\Domain\Content\Services\SocialEmbed;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use App\Domain\Platform\Services\Analytics\Pixels;
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
        // ⚠️ **No pisar un `Referrer-Policy` que una respuesta concreta haya fijado** (`#521`), con el
        // mismo criterio que la CSP de abajo: el suelo global sigue siendo `strict-origin-when-cross-origin`
        // y una pantalla puede endurecerlo, nunca relajarlo — aquí solo se respeta lo ya puesto.
        //
        // ▶ Quien lo usa: la página de la INVITACIÓN, que lleva un TOKEN en la URL y ofrece un enlace
        // externo a un mapa. Medido: el valor global no filtraría ese token —manda solo el origen, sin
        // path— pero sí diría de qué parque viene el visitante, y §4.6 pide `no-referrer`.
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
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
        // 📜 Aquí entraba `lh3.googleusercontent.com` por las fotos de autor de las reseñas de PLACES (`#491`), que
        // relajaba la CSP del sitio entero. Salió con Places (`#771`, §4.3·12 de `google-business-profile.md`): las
        // imágenes de reseñas —del Perfil de Empresa y las copiadas de la ficha— se sirven desde este servidor.
        // `SecurityHeadersTest::test_img_src_does_not_name_google` impide que vuelva sin decidirlo.
        $img = ["'self'", 'data:'];

        // **La herramienta de análisis** (`specs/analitica.md` §4.3, T3a·2): sus orígenes viven en CÓDIGO
        // (`Drivers::csp()`, por driver y directiva) y entran SOLO con el driver activo y completo. El gate
        // real es no inyectar su script sin la categoría `analytics` (`COOKIES.md` D8); esta es la segunda
        // cerradura, y sin driver no se abre para nadie.
        // **Los píxeles de anuncios** (T3b·1) siguen la misma regla con `Pixels::csp()`: sus orígenes viven en
        // código, por plataforma y directiva, y entran SOLO con el píxel configurado; el gate real es no
        // inyectar su script sin la categoría `marketing`.
        foreach (array_merge_recursive(Drivers::csp(), Pixels::csp()) as $directive => $origins) {
            match ($directive) {
                'script-src' => array_push($script, ...$origins),
                'connect-src' => array_push($connect, ...$origins),
                'img-src' => array_push($img, ...$origins),
                default => null,
            };
        }

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
            'img-src '.implode(' ', $img),
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
