<?php

namespace App\Domain\Content\Services;

use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Platform\Models\Setting;

/**
 * Fase 7.10 (iter. 2) — Color de marca white-label. Fuente ÚNICA y DEFENSIVA del color con el
 * que se tematizan la web, el panel y los emails.
 *
 * Modelo (decisión de la clienta): un **color de marca GLOBAL** (`theme.brand`) para lo
 * genérico, y el **color de cada zona** (`zones.color`, #210) para los contextos de esa zona.
 * En la landing, lo global usa el color de marca y las secciones de zona usan el color de su
 * zona; el panel y los emails usan el color de marca (un email ligado a una zona puede teñir
 * ese detalle con el color de la zona).
 *
 * Todos los getters son **defensivos** ([[feedback_settings_defensive_helpers]]): validan que el
 * valor sea un hex `#RRGGBB` y, si falta/es inválido o la BD no está disponible (p. ej. durante
 * `migrate`), devuelven el color por defecto histórico — nunca lanzan ni rompen el render.
 */
class ThemeSettings
{
    /** Acento histórico de la web (mockup `--zone-1` por defecto = Jump). */
    public const DEFAULT_BRAND = '#FF5B22';

    /** Defaults del mockup para los acentos por zona (landing.css), si una zona no tiene color. */
    private const ZONE_DEFAULTS = ['jump' => '#FF5B22', 'kids' => '#C6FF3A'];

    /** Color de marca global (panel, emails y elementos genéricos de la web). */
    public static function brand(): string
    {
        return self::hex(self::raw('theme.brand'), self::DEFAULT_BRAND);
    }

    /** Color de una zona por su `accent` (jump/kids/…), con fallback al default del mockup. */
    public static function zoneColor(string $accent): string
    {
        return self::colorForAccent(
            rescue(fn (): ?string => app(ZonePalette::class)->colorFor($accent), null, false),
            $accent,
        );
    }

    /**
     * Valida un color de zona YA cargado (sin consulta), con fallback al default de su `accent`.
     * Lo usa el render de la landing para sanear `zones.color` antes de inyectarlo en el DOM
     * (defensa en el punto de salida, coherente con `brand()`), sin re-consultar la zona.
     */
    public static function colorForAccent(?string $value, string $accent): string
    {
        return self::hex($value, self::ZONE_DEFAULTS[$accent] ?? self::DEFAULT_BRAND);
    }

    /** Color primario del panel admin = marca global. */
    public static function panelPrimaryHex(): string
    {
        return self::brand();
    }

    /**
     * Declaraciones CSS para el `:root` que inyecta el layout público (sobre `landing.css`):
     *  - `--brand` y `--zone-1 = var(--brand)`: lo genérico de la web sigue la marca global.
     *  - `--jump-1`/`--kids-1`: los acentos por zona derivan de `zones.color` (las tarjetas de
     *    la sección «Zonas» los consumen). El swap de la sección «Atracciones» se hace aparte
     *    (scoped a `#rides`), no aquí.
     */
    public static function cssRootDeclarations(): string
    {
        $brand = self::brand();
        $onBrand = self::onBrand($brand);
        $jump = self::zoneColor('jump');
        $kids = self::zoneColor('kids');
        $onJump = self::onBrand($jump);
        $onKids = self::onBrand($kids);

        return "--brand:{$brand};--zone-1:var(--brand);--on-brand:{$onBrand};"
            ."--jump-1:{$jump};--kids-1:{$kids};--on-jump:{$onJump};--on-kids:{$onKids};";
    }

    /**
     * Texto legible SOBRE un fondo de `$hex` (token `--on-brand`), elegido por luminancia:
     * texto oscuro (`--fg`) sobre marcas claras, crema (`--bg`) sobre marcas oscuras. Garantiza
     * el contraste de los CTAs sea cual sea el color de marca elegido en el panel (white-label).
     */
    public static function onBrand(string $hex): string
    {
        // Preferimos BLANCO sobre el acento (look de marca pedido por la clienta). Solo si el
        // acento es tan CLARO que el blanco no alcanza AA-grande (3:1) — p. ej. la lima de Kids —
        // usamos texto oscuro (--fg), que entonces sí contrasta. Robusto para cualquier color.
        $whiteContrast = 1.05 / (self::luminance($hex) + 0.05);

        // Literales (no var(--fg)) → el token --on-brand es un color autocontenido, así
        // `color-mix(var(--on-brand) …)` (los --on-brand-mute/-line) resuelve sin doble indirección.
        return $whiteContrast >= 3.0 ? '#FFFFFF' : '#14130F';
    }

    /** Luminancia relativa WCAG de un hex `#RRGGBB`. */
    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $lin = static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lin($r / 255) + 0.7152 * $lin($g / 255) + 0.0722 * $lin($b / 255);
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [(int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2))];
    }

    private static function raw(string $key): ?string
    {
        return rescue(fn (): ?string => Setting::value($key), null, false);
    }

    private static function hex(?string $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtoupper($value) : $fallback;
    }
}
