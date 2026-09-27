<?php

namespace App\Domain\Payments\Services;

use App\Domain\Platform\Models\Setting;

/**
 * **Las marcas de pago que enseña esta instalación** (`isla-y-landing-nueva.md` §4.14, `DECISIONES #784`).
 *
 * Qué formas de pago acepta un parque es un dato de SU negocio —su terminal de Redsys acepta unas tarjetas y puede tener
 * Bizum activo—, así que se elige en el panel (`payment.marks`) y no se escribe en el código. El producto trae los
 * FICHEROS oficiales de cada marca (`public/images/providers/pago/<id>.svg` y, para fondos oscuros, `<id>-tinta.svg`,
 * bajados de la fuente de su dueño y sin tocar el dibujo; su procedencia, en la cabecera de cada uno) y solo enseña las
 * elegidas que tengan el suyo: una elegida sin fichero no se pinta rota, no se pinta. Sin venta online, ninguna (no hay
 * dónde pagar). Salen en la compra (bajo «Pagar» y bajo «Reservar y pagar») y, como hecho del sitio, en el pie (`#786`).
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

    /** Si el producto trae el fichero oficial de esa marca (el de fondo CLARO, `<id>.svg`: el que decide si se enseña). */
    public static function tieneFichero(string $id): bool
    {
        return in_array($id, self::CONOCIDAS, true) && is_file(public_path("images/providers/pago/{$id}.svg"));
    }

    /**
     * La versión oficial para fondo OSCURO (`<id>-tinta.svg`, `#786`: la isla es `data-surface="ink"`), o la de siempre
     * si la marca usa la misma en los dos (el símbolo de Mastercard, que su guía da por bueno sobre oscuro).
     */
    private static function srcTinta(string $id): string
    {
        return is_file(public_path("images/providers/pago/{$id}-tinta.svg"))
            ? asset("images/providers/pago/{$id}-tinta.svg")
            : asset("images/providers/pago/{$id}.svg");
    }

    /**
     * Las que se ENSEÑAN, con sus dos imágenes: `[['id' => 'bizum', 'nombre' => 'Bizum', 'src' => '…/bizum.svg',
     * 'srcTinta' => '…/bizum-tinta.svg'], …]`.
     *
     * @return list<array{id: string, nombre: string, src: string, srcTinta: string}>
     */
    public static function activas(): array
    {
        if (! PaymentSettings::onlineSalesEnabled()) {
            return [];
        }

        return array_values(array_map(
            fn (string $id): array => [
                'id' => $id, 'nombre' => self::NOMBRES[$id], 'src' => asset("images/providers/pago/{$id}.svg"), 'srcTinta' => self::srcTinta($id),
            ],
            array_filter(self::elegidas(), fn (string $id): bool => self::tieneFichero($id)),
        ));
    }

    /**
     * Las mismas, como las rutas que ya viajan en el arranque de la compra (`urls.mark_<id>` y, para dentro de la isla,
     * `urls.mark_<id>_ink`): su presencia ES el interruptor, como `urls.google`.
     *
     * @return array<string, string>
     */
    public static function urls(): array
    {
        $urls = [];
        foreach (self::activas() as $m) {
            $urls['mark_'.$m['id']] = $m['src'];
            $urls['mark_'.$m['id'].'_ink'] = $m['srcTinta'];
        }

        return $urls;
    }

    /**
     * Las mismas, como HECHO del sitio (`GET /site`, `payment_marks`; `#786`): la landing de una instancia las pinta en su
     * pie. Las claves, las del contrato.
     *
     * @return list<array{id: string, name: string, src: string, src_ink: string}>
     */
    public static function hechos(): array
    {
        return array_map(
            fn (array $m): array => ['id' => $m['id'], 'name' => $m['nombre'], 'src' => $m['src'], 'src_ink' => $m['srcTinta']],
            self::activas(),
        );
    }
}
