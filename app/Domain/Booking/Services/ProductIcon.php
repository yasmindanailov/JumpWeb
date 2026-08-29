<?php

namespace App\Domain\Booking\Services;

/**
 * **QUÉ ICONO LLEVA UN PRODUCTO, decidido en un solo sitio** (`DECISIONES #140`).
 *
 * ⚠️⚠️ **Antes lo decidía un booleano, escrito TRES veces.** `if ($isPack) tarta; else entrada;`
 * vivía en `components/icons/product.blade.php` —que además no tenía ni un llamante— y, repetido a
 * mano con la geometría entera dentro, en `CartStep.vue` y en `SummaryLine.vue`. Con esa regla, un
 * catálogo se reparte en **dos dibujos**: la tirolina, la tarta y los calcetines son «no-pack», así
 * que los tres salen como un ticket.
 *
 * ## Por qué una lista CURADA y no un fichero subido
 *
 * Decisión del owner (`specs/landing-white-label.md` §4.6). Un SVG subido por el operador es
 * **código ejecutable** que habría que sanear, traería trazos y tamaños dispares —el catálogo se ve
 * descuidado a la tercera subida— y, sobre todo, **rompería la única guarda que tenemos sobre los
 * dibujos**: `SidebarIconParityTest` exige que cada geometría que emite el cajón sea, byte a byte
 * tras normalizar, la de un componente `<x-icons.*>`. Con dibujos arbitrarios esa comparación deja
 * de existir.
 *
 * ▶ El precio aceptado: **añadir un icono nuevo es un despliegue**. A cambio, cada cliente puede
 * llevar su propio set en su paquete de tema.
 */
final class ProductIcon
{
    /**
     * Los iconos ofrecidos como MARCADOR DE PRODUCTO.
     *
     * ⚠️ Es un subconjunto deliberado del set: solo los **iconos de marca del catálogo**. El resto
     * (`calendar`, `lock`, `user`, las flechas…) es cromo de interfaz, y ofrecerlo aquí invitaría a
     * marcar un producto con un candado.
     *
     * @var list<string>
     */
    public const CHOICES = [
        // Los seis originales: ILUSTRACIONES en su propia escala (40×40 · 50×32 · 60×36).
        'ic-b1', 'ic-b7', 'ic-e2', 'ic-e5', 'ticket-tear-off', 'socks',
        // ── Los cinco del set del artboard (`#258`), en la rejilla de 24 ────────────────────────
        // ⚠️⚠️ **Se AÑADEN, no sustituyen, y el motivo es de datos**: `forProduct()` trata una clave
        // desconocida como ausente, así que retirar una de las seis de arriba **degradaría en
        // silencio** todo producto que la tuviera guardada en `ticket_types.icon` — pasaría a la
        // tarta o al ticket sin que nadie lo pidiera ni se enterara.
        // ⚠️ `pack`, `party` y `school-trip` salen del LOTE 2 del artboard, que el cliente dibujó y
        // **nunca cerró**; la variante la elige su propio texto, no nosotros (ver cada componente).
        'ticket', 'gift', 'pack', 'party', 'school-trip',
    ];

    /** El de un pack cuando no ha elegido: la tarta. Es el aspecto que el catálogo ya tenía. */
    public const DEFAULT_PACK = 'ic-b1';

    /** Y el de todo lo demás: la entrada troquelada. */
    public const DEFAULT_OTHER = 'ticket-tear-off';

    /**
     * La clave de icono de un producto. **Nunca devuelve `null`**: una superficie que pinta un
     * marcador necesita siempre uno, y hacer que cada una resuelva su propio respaldo es cómo se
     * llegó a tener la regla escrita tres veces.
     *
     * ⚠️ Una clave que no esté en la lista se trata como ausente. Es defensivo a propósito, igual
     * que `ThemeSettings`: un valor corrupto en BD —escrito saltándose el panel, o superviviente de
     * un set anterior— **degrada al icono por tipo** en vez de servir un `<svg>` vacío.
     */
    public static function forProduct(?string $icon, bool $isPack): string
    {
        $icon = is_string($icon) ? trim($icon) : '';

        if (in_array($icon, self::CHOICES, true)) {
            return $icon;
        }

        return $isPack ? self::DEFAULT_PACK : self::DEFAULT_OTHER;
    }
}
