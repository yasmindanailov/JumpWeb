<?php

namespace App\Domain\Payments\Services;

use App\Domain\Platform\Models\Setting;

/**
 * **Las marcas de pago que enseña esta instalación** (`isla-y-landing-nueva.md` §4.14, `DECISIONES #784`).
 *
 * Qué formas de pago acepta un parque es un dato de SU negocio —su terminal de Redsys acepta unas tarjetas y puede tener
 * Bizum activo—, así que se elige en el panel (`payment.marks`) y no se escribe en el código. El producto trae los
 * FICHEROS oficiales de cada marca (`public/images/providers/pago/<id>.svg`, bajados de la fuente de su dueño y sin
 * tocar el dibujo; su procedencia, en la cabecera de cada uno) y solo enseña las elegidas que tengan el suyo: una
 * elegida sin fichero no se pinta rota, no se pinta. Sin venta online, ninguna (no hay dónde pagar).
 *
 * ⚠️ Helper DEFENSIVO, el patrón de `PaymentSettings`: la fila que falta o un texto corrupto son «ninguna». Nunca lanza.
 */
class MarcasDePago
{
    /** Una lista separada por comas (`bizum,visa`). Sin fila, NINGUNA: qué acepta un parque no se da por supuesto. */
    public const KEY = 'payment.marks';

    /** Las que el producto conoce, en el orden en que se enseñan (el del diseño: Bizum, Visa, Mastercard). */
    public const CONOCIDAS = ['bizum', 'visa', 'mastercard'];

    /** Su nombre, para el texto alternativo y el panel: son marcas, no se traducen. */
    public const NOMBRES = ['bizum' => 'Bizum', 'visa' => 'Visa', 'mastercard' => 'Mastercard'];

    /** Las elegidas en el panel, las que el producto conoce y en su orden (sin mirar si hay fichero). */
    public static function elegidas(): array
    {
        $raw = rescue(fn (): mixed => Setting::value(self::KEY), null, false);
        $ids = is_string($raw) ? array_map('trim', explode(',', strtolower($raw))) : [];

        return array_values(array_intersect(self::CONOCIDAS, $ids));
    }

    /** Si el producto trae el fichero oficial de esa marca. */
    public static function tieneFichero(string $id): bool
    {
        return in_array($id, self::CONOCIDAS, true) && is_file(public_path("images/providers/pago/{$id}.svg"));
    }

    /**
     * Las que se ENSEÑAN, con su imagen: `[['id' => 'bizum', 'nombre' => 'Bizum', 'src' => '…/bizum.svg'], …]`.
     *
     * @return list<array{id: string, nombre: string, src: string}>
     */
    public static function activas(): array
    {
        if (! PaymentSettings::onlineSalesEnabled()) {
            return [];
        }

        return array_values(array_map(
            fn (string $id): array => ['id' => $id, 'nombre' => self::NOMBRES[$id], 'src' => asset("images/providers/pago/{$id}.svg")],
            array_filter(self::elegidas(), fn (string $id): bool => self::tieneFichero($id)),
        ));
    }

    /**
     * Las mismas, como las rutas que ya viajan en el arranque de la compra (`urls.mark_<id>`): su presencia ES el
     * interruptor, como `urls.google`.
     *
     * @return array<string, string>
     */
    public static function urls(): array
    {
        $urls = [];
        foreach (self::activas() as $m) {
            $urls['mark_'.$m['id']] = $m['src'];
        }

        return $urls;
    }
}
