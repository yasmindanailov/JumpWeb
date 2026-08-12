<?php

namespace App\Support;

/**
 * Normaliza el ajuste «feed social» de la sección «en directo» (#215). La clienta
 * quiere mostrar ahí sus ÚLTIMAS publicaciones de Instagram/TikTok. Las APIs oficiales exigen
 * OAuth + tokens que caducan (frágil); la vía robusta y fácil de mantener es un **widget de
 * feed** de un servicio (SnapWidget / LightWidget): la clienta conecta su cuenta y obtiene un
 * `<iframe>` que ya muestra las últimas N fotos. Aquí lo tratamos igual que el mapa de Google
 * (#206, [[MapsEmbed]]): extraemos la URL `src` limpia y validamos el host contra una allowlist.
 *
 * Devuelve la URL solo si apunta a un proveedor de la allowlist por **https**, o `null`. Se usa
 * al GUARDAR (almacenar limpio) y al RENDERIZAR (defensa: sanea un valor ya guardado, sin volver
 * a guardarlo). El iframe SOLO carga si pasa por aquí → no hay inyección de orígenes arbitrarios.
 */
class SocialEmbed
{
    /**
     * Dominios registrables permitidos (coincidencia por sufijo → cubre subdominios como
     * `cdn.lightwidget.com`). Servicios de feed de Instagram/TikTok con inserción por iframe.
     *
     * @var array<int,string>
     */
    public const ALLOWED_DOMAINS = ['snapwidget.com', 'lightwidget.com'];

    public static function clean(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Si pegaron el <iframe> completo, quedarse con el contenido de src="…".
        if (preg_match('#\bsrc\s*=\s*["\']([^"\']+)["\']#i', $value, $m)) {
            $value = $m[1];
        }

        // Recortar cualquier cosa tras la URL (comilla de cierre, espacios, atributos, < o >).
        $value = preg_split('#["\s<>]#', $value, 2)[0] ?? '';
        $value = trim($value);

        if (! str_starts_with($value, 'https://')) {
            return null;
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        foreach (self::ALLOWED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Orígenes para la directiva `frame-src` de la CSP (apex + subdominios de cada proveedor).
     * Fuente única junto a `ALLOWED_DOMAINS` para que la validación y la CSP no diverjan.
     */
    public static function cspFrameSrc(): string
    {
        $sources = [];
        foreach (self::ALLOWED_DOMAINS as $domain) {
            $sources[] = 'https://'.$domain;
            $sources[] = 'https://*.'.$domain;
        }

        return implode(' ', $sources);
    }
}
