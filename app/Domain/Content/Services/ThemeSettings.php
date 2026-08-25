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

    /**
     * Acento SECUNDARIO de marca (`--zone-2`), para las decoraciones que acompañan al primario.
     *
     * ⚠️ Existe desde `DECISIONES #138` porque hasta entonces `--zone-2` valía `var(--jump-2)` en el
     * `:root` de `landing.css`: **el amarillo de una zona del primer cliente**, quemado para toda
     * instalación. Nada fallaba, y por eso duró.
     */
    public const DEFAULT_BRAND_SECONDARY = '#FFE14A';

    /** Defaults del mockup para los acentos por zona (landing.css), si una zona no tiene color. */
    private const ZONE_DEFAULTS = ['jump' => '#FF5B22', 'kids' => '#C6FF3A'];

    /** Color de marca global (panel, emails y elementos genéricos de la web). */
    public static function brand(): string
    {
        return self::hex(self::raw('theme.brand'), self::DEFAULT_BRAND);
    }

    /** Acento secundario de marca. Vacío ⇒ el default del mockup, nunca el de una zona. */
    public static function brandSecondary(): string
    {
        return self::hex(self::raw('theme.brand_secondary'), self::DEFAULT_BRAND_SECONDARY);
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

    /**
     * **EL ESTILO DE UNA ZONA, compuesto UNA sola vez** (`DECISIONES #138`).
     *
     * ⚠️⚠️ **Nació de una divergencia REAL, no de una simetría bonita.** El color de una zona viajaba
     * por DOS caminos: la tarjeta lo tomaba de `zones.color` —el suyo— y la pestaña de una regla CSS
     * `.zone-tab--{accent}` que leía `--kids-1`, o sea **el color de la PRIMERA zona con ese acento**.
     * Medido sobre la BD de desarrollo: las zonas `cap` y `cap2` tienen `accent=kids` y
     * `color=#FF5B22`, así que su tarjeta salía naranja y su pestaña lima. El mismo sitio, dos
     * colores, y nada fallaba.
     *
     * ▶ Y la otra mitad del defecto era peor para un producto white-label: **esas reglas solo existían
     * para `jump` y `kids`**, los acentos del primer cliente. Una zona con cualquier otro acento se
     * quedaba sin color, en silencio.
     *
     * Aquí se compone el trío que esas diez reglas re-escopaban a mano —`--zone-1`, `--zone-2` y
     * `--on-brand`— para que cada superficie lo PINTE en línea sobre su elemento, en vez de tener una
     * regla por acento. Es la misma lección de `Booking\Services\OrderLedger`: una composición, N
     * superficies que la pintan.
     *
     * @param  ?string  $color  `zones.color` — el primario de ESA zona, no el de su acento
     * @param  ?string  $secondary  `zones.color_secondary`; vacío ⇒ se usa el primario (plano, nunca prestado)
     */
    public static function zoneStyle(?string $color, ?string $secondary, string $accent): string
    {
        $primary = self::colorForAccent($color, $accent);
        $second = self::secondaryForAccent($secondary, $accent, $primary);

        return "--zone-1:{$primary};--zone-2:{$second};--on-brand:".self::onBrand($primary).';';
    }

    /**
     * El mismo trío, para una superficie que **solo conoce el acento** y no la fila de la zona.
     *
     * ⚠️ Lo usa `/servicios`, cuyas zonas salen hoy de la tabla de precios escrita a mano —sin
     * color— y no de `zones`. Consulta la BD por acento, así que **el llamante debe resolverlo una
     * vez por acento** y no dentro de un bucle anidado. Cuando la tanda C convierta esos servicios en
     * productos reales, esta variante se queda sin sujeto y se retira con ella.
     */
    public static function zoneStyleForAccent(string $accent): string
    {
        $primary = self::zoneColor($accent);

        return "--zone-1:{$primary};--zone-2:{$primary};--on-brand:".self::onBrand($primary).';';
    }

    /**
     * El SEGUNDO color de una zona, saneado.
     *
     * ⚠️ Sin valor **cae al primario**, no al amarillo de Jump: una zona sin paleta doble se pinta
     * plana —que es correcto— en vez de pedirle prestado el acento a la marca de otro cliente.
     */
    public static function secondaryForAccent(?string $value, string $accent, ?string $primary = null): string
    {
        return self::hex($value, $primary ?? self::colorForAccent(null, $accent));
    }

    /** Color primario del panel admin = marca global. */
    public static function panelPrimaryHex(): string
    {
        return self::brand();
    }

    /**
     * Declaraciones CSS para el `:root` que inyecta el layout público (sobre `landing.css`).
     *
     * **La MARCA, y solo la marca**: `--brand` / `--brand-2` y los `--zone-*` de página, que las
     * siguen. Lo genérico de la web se tiñe con la marca global; **cada zona re-escopa lo suyo en
     * línea** sobre su elemento con {@see self::zoneStyle()}.
     *
     * ⚠️⚠️ **Aquí se emitían además `--jump-1`, `--kids-1`, `--on-jump` y `--on-kids`**, o sea los
     * acentos de las DOS zonas del primer cliente, con sus nombres, para toda instalación
     * (`DECISIONES #139`). Se fueron cuando dejó de consumirlas nadie. Que este método deba conocer
     * los nombres de las zonas de alguien es la señal de que el color viaja por el sitio equivocado:
     * las zonas son DATOS y pueden ser dos, cinco o llamarse de otra forma.
     */
    public static function cssRootDeclarations(): string
    {
        $brand = self::brand();

        return "--brand:{$brand};--brand-2:".self::brandSecondary().';'
            .'--zone-1:var(--brand);--zone-2:var(--brand-2);'
            .'--on-brand:'.self::onBrand($brand).';';
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
