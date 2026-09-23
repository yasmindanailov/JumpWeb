<?php

namespace App\Domain\Platform\Services\Analytics;

/**
 * **Lo único que el libro se queda del user agent**: la clase de dispositivo y si es un robot. El UA
 * entero no se guarda (spec §4.1): es dato personal y la exención no lo cubre.
 *
 * ⚠️ `isBot()` es una lista de rastreadores CONOCIDOS más `navigator.webdriver` (que manda el emisor),
 * no una detección perfecta: basta para que Googlebot, los verificadores de enlaces de las plataformas
 * de anuncios y la sonda no inflen el denominador del embudo (spec §7.1, medicion-6, rendimiento-5).
 * La sesión se guarda IGUAL, marcada `is_bot`: el cuadro la excluye por defecto y la enseña aparte.
 *
 * ⚠️ Cuidado con las palabras genéricas: el navegador dentro de la app de TikTok no es su rastreador
 * (`Bytespider`/`TikTokSpider`), y el de Facebook no es `facebookexternalhit`. Se casan nombres exactos.
 */
final class Device
{
    public const MOBILE = 'mobile';

    public const TABLET = 'tablet';

    public const DESKTOP = 'desktop';

    private const BOT_RE = '/googlebot|adsbot|mediapartners|google-read-aloud|bingbot|slurp|duckduckbot|baiduspider|yandexbot|applebot|petalbot|semrushbot|ahrefsbot|mj12bot|screaming frog|bytespider|tiktokspider|facebookexternalhit|facebot|twitterbot|linkedinbot|whatsapp\/|telegrambot|discordbot|slackbot|embedly|pinterestbot|lighthouse|headlesschrome|phantomjs|pingdom|uptimerobot|statuscake|python-requests|curl\/|wget\/|go-http-client|okhttp|java\/|libwww|(?<![a-z])bot(?![a-z])|crawler|spider/i';

    public static function classify(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        if ($ua === '') {
            return self::DESKTOP;
        }

        // Las tabletas van ANTES: un iPad y muchos Android de tableta también dicen «mobile» o no.
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'))) {
            return self::TABLET;
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return self::MOBILE;
        }

        return self::DESKTOP;
    }

    public static function isBot(?string $userAgent): bool
    {
        $ua = (string) $userAgent;

        return $ua === '' || preg_match(self::BOT_RE, $ua) === 1;
    }
}
