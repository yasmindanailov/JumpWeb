<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Ajustes del catálogo del sidebar de compra (editables desde el panel, #226).
 *
 * Helper DEFENSIVO (mismo patrón que `PaymentSettings`/`PuertaSettings`/`ThemeSettings`): lee el
 * setting con tipo + rango + fallback no destructivo. Si la fila falta o el valor está corrupto,
 * devuelve el default sin lanzar — ninguna superficie depende de un valor válido en BD.
 */
class CatalogSettings
{
    /** Clave del umbral (nº de productos) a partir del cual aparece el buscador del catálogo. */
    public const SEARCH_MIN_ITEMS_KEY = 'catalog.search_min_items';

    /** Default: catálogo pequeño por diseño (≈10 productos) → sin buscador. */
    public const SEARCH_MIN_ITEMS_DEFAULT = 12;

    /** 0 → el buscador aparece en cuanto hay ≥1 producto (siempre visible). */
    public const SEARCH_MIN_ITEMS_MIN = 0;

    /** Tope sano: un umbral altísimo equivale a «nunca mostrar el buscador». */
    public const SEARCH_MIN_ITEMS_MAX = 100;

    /**
     * Umbral del buscador del catálogo: el buscador se muestra si el nº total de productos
     * vendibles SUPERA este valor. Clamp a [MIN, MAX]; fallback al default si falta o está corrupto.
     */
    public static function searchMinItems(): int
    {
        $raw = Setting::value(self::SEARCH_MIN_ITEMS_KEY);

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return self::SEARCH_MIN_ITEMS_DEFAULT;
        }

        return max(self::SEARCH_MIN_ITEMS_MIN, min(self::SEARCH_MIN_ITEMS_MAX, (int) $raw));
    }
}
