<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un complemento ya RESUELTO frente a la selección del cliente ({@see AddonOffer}).
 *
 * Distinto de {@see CatalogAddon}, y la diferencia es la razón de ser de todo el contrato: aquél
 * publica la **configuración** del enganche (incluido, obligatorio, por invitado, grupo, requisito)
 * y éste publica el **resultado de aplicarla** a lo que el cliente lleva elegido. Reimplementar ese
 * paso en el cliente es exactamente lo que `CE-4` prohíbe, y no es una traducción trivial: la poda
 * de dependencias «requiere» es a punto fijo (si cae C, cae B, cae A) y un complemento de pago sin
 * tarifa ese día **no se ofrece siquiera**.
 *
 * **`selected` no se deduce de `quantity`.** Un miembro de un grupo excluyente está seleccionado
 * porque es el elegido del grupo, no porque tenga cantidad; y un dependiente huérfano queda **no
 * seleccionado** aunque el cliente lo hubiera marcado. Es el mismo veredicto que aplicará el cobro.
 */
final readonly class ResolvedAddon
{
    /**
     * @param  int  $productId  id del complemento
     * @param  string  $name  nombre ya resuelto al idioma activo (vive en BD)
     * @param  int  $priceCents  precio unitario del día para este complemento (0 si es gratis)
     * @param  string  $note  la línea de precio ya compuesta y traducida («Incluido», «5,00 € por
     *                        invitado»…). Viaja hecha porque mezcla textos de `lang/` con importes
     *                        formateados, y hoy la SPA no tiene canal de i18n propio (spec §4.5)
     * @param  bool  $isIncluded  va incluido en el producto (sus primeras unidades no se cobran)
     * @param  bool  $isMandatory  el producto lo exige: no se puede quitar
     * @param  bool  $perGuest  la cantidad la fija el nº de invitados, no el cliente
     * @param  bool  $allowExtra  se pueden añadir unidades por encima de las incluidas
     * @param  string|null  $badge  `included` | `free` | `null` — la etiqueta que distingue «va con
     *                              el pack» de «no cuesta nada», que no son lo mismo
     * @param  list<string>  $features  ventajas para el desplegable «Más info»
     * @param  bool  $selected  veredicto AUTORITATIVO tras aplicar grupos, obligatorios y la poda
     * @param  bool  $available  su requisito («requiere X») está elegido. Si es `false` el
     *                           complemento se enseña pero no se puede activar
     * @param  string|null  $requiresName  nombre del complemento que le falta, para el aviso
     *                                     «Requiere: X». `null` cuando `available`
     * @param  int  $quantity  cantidad efectiva con la que entraría
     * @param  int  $freeQuantity  cuántas de esas van sin cargo
     * @param  int  $chargedCents  lo que suma esta fila: `(quantity − freeQuantity) × priceCents`
     * @param  int  $minQuantity  suelo (un obligatorio suelto no baja de sus unidades incluidas)
     * @param  int|null  $maxQuantity  techo configurado en el enganche, o `null`
     * @param  bool  $canToggle  se activa con un interruptor y no con un contador: es el caso
     *                           por-invitado opcional, donde la cantidad la decide el aforo
     * @param  bool  $canIncrease  admite subir una unidad más
     * @param  bool  $canDecrease  admite bajar una unidad
     */
    public function __construct(
        public int $productId,
        public string $name,
        public int $priceCents,
        public string $note,
        public bool $isIncluded,
        public bool $isMandatory,
        public bool $perGuest,
        public bool $allowExtra,
        public ?string $badge,
        public array $features,
        public bool $selected,
        public bool $available,
        public ?string $requiresName,
        public int $quantity,
        public int $freeQuantity,
        public int $chargedCents,
        public int $minQuantity,
        public ?int $maxQuantity,
        public bool $canToggle,
        public bool $canIncrease,
        public bool $canDecrease,
    ) {}
}
