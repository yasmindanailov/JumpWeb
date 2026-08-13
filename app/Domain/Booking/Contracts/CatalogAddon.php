<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un COMPLEMENTO tal y como se OFRECE dentro de un producto concreto (pivote `product_addons`,
 * #87), visto desde fuera de Booking.
 *
 * La configuración es POR ENGANCHE, no global: la misma tarta puede ir incluida en un pack y ser un
 * extra de pago en otro. Por eso todo lo que describe el ofrecimiento —incluido, obligatorio,
 * por-invitado, grupo excluyente, dependencia— sale del pivote y no del producto complemento.
 *
 * **Qué NO es este DTO.** No es el modelo de vista de `AddonResolver::viewModel()`, que necesita el
 * estado de la selección (cantidades elegidas, miembro elegido de cada grupo, nº de invitados) para
 * decidir qué está activo, qué se puede subir o bajar y cuánto suma. Un catálogo no tiene ese
 * estado: describe la OFERTA. Lo que sí comparte con él —porque son reglas de dominio y no pueden
 * derivar— es qué complementos llegan siquiera a ofrecerse (un extra de PAGO sin precio para la
 * tarifa no se ofrece, porque el checkout lo rechazaría) y cuál es la selección por defecto
 * (`AddonResolver::defaultSelection()`).
 *
 * **El precio autoritativo no vive aquí.** `priceCents` es el precio unitario configurado; lo que
 * se cobra por una línea depende de las unidades incluidas, los grupos y la cantidad, y lo calcula
 * el servidor en `POST orders/quote` (paso 4). Un cliente que sume por su cuenta está
 * reimplementando `AddonResolver`, que es justo lo que el spec §4.6 prohíbe.
 */
final readonly class CatalogAddon
{
    /**
     * @param  list<string>  $features  ventajas del complemento, ya normalizadas (sin vacíos)
     */
    public function __construct(
        public int $id,
        /** Nombre en el idioma activo. */
        public string $name,
        /** @var list<string> */
        public array $features,
        /**
         * Precio unitario en céntimos para la tarifa vigente. `0` en un complemento INCLUIDO sin
         * precio propio (es gratis de verdad). Nunca es `null`: un complemento de pago sin precio
         * ni siquiera se ofrece.
         */
        public int $priceCents,
        /** Las primeras `includedQuantity` unidades van dentro del producto (sin coste). */
        public bool $included,
        /** No se puede quitar: el servidor lo inyecta aunque el cliente no lo envíe. */
        public bool $mandatory,
        /** La cantidad la fija el nº de invitados de la línea, no el cliente. */
        public bool $perGuest,
        /** ¿Se pueden pedir unidades por encima de las incluidas (a su precio)? */
        public bool $allowExtra,
        /** Unidades incluidas (gratis) cuando `included` es cierto. */
        public int $includedQuantity,
        /** Tope de unidades configurado, o null si no lo hay. */
        public ?int $maxQuantity,
        /**
         * Grupo de elección EXCLUYENTE: de todos los complementos que comparten esta clave se
         * contrata exactamente uno. `null` = complemento independiente.
         */
        public ?string $choiceGroup,
        /**
         * Id del complemento que este REQUIERE para poder contratarse («requiere»), o null. El
         * servidor poda a punto fijo los dependientes cuyo requisito no está seleccionado, así que
         * un cliente que lo ignore verá desaparecer la línea al presupuestar.
         */
        public ?int $requiresAddonId,
        /**
         * ¿Viene seleccionado de partida? Es la regla de `AddonResolver::defaultSelection()` —el
         * default de cada grupo (el incluido, o el primero) y los obligatorios sueltos—, resuelta
         * por el dominio para que ningún cliente la deduzca a ojo.
         */
        public bool $selectedByDefault,
    ) {}
}
