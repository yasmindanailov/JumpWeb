<?php

namespace App\Domain\Booking\Contracts;

/**
 * Un producto SELECCIONABLE del catálogo (entrada o pack), tal y como lo describe Booking para
 * cualquier consumidor: la web SSR, la SPA de Fase 4 y la app móvil de Fase 6.
 *
 * Es la ficha de LISTA. Lo que solo hace falta al abrir un producto —mínimos y máximos, campos del
 * evento, complementos— vive en {@see CatalogProductDetail} para que listar el catálogo no arrastre
 * una consulta por producto.
 *
 * **Dominio, no presentación.** Este DTO no lleva la cadena `search` normalizada ni el `zone_anchor`
 * que hoy calcula `Livewire\Tickets\Purchase`: la primera es el índice del buscador que la web
 * filtra en cliente y el segundo es el ancla de scroll de su blade. Los dos son artefactos de una
 * interfaz concreta y se construyen a partir de estos datos (`name`, `features`, `zone`), no al
 * revés. La regla que separa uno de otro: si otro cliente con otra interfaz lo necesitaría igual,
 * es dominio; si solo lo necesita el que lo pintó así, es presentación.
 *
 * Sobre los textos: `name`, `badge`, `features` y `periodLabel` llegan ya resueltos al idioma
 * activo, igual que hace {@see UpcomingReservation} con `productName`. Traducir es cosa del
 * dominio (los textos viven en BD, no en `lang/`); FORMATEAR —unir features con un separador,
 * componer «desde X €»— es de quien pinta.
 */
final readonly class CatalogProduct
{
    /**
     * Los dos tipos que el catálogo ofrece. Se declaran AQUÍ, y no se reexporta `TicketType::TYPE_*`,
     * porque estos valores son contrato público: cambiar el valor de la constante del modelo no
     * puede cambiar en silencio lo que lee la app móvil. El mapeo modelo → contrato lo hace el
     * read-model con un `match` explícito, que es donde se ve si alguien añade un tipo nuevo.
     */
    public const TYPE_ENTRY = 'entry';

    public const TYPE_PACK = 'pack';

    /**
     * @param  'entry'|'pack'  $type  entrada o pack; los complementos (`addon`) no son seleccionables
     * @param  list<string>  $features  ventajas del producto, ya normalizadas (sin vacíos)
     */
    public function __construct(
        public int $id,
        public string $type,
        /** Nombre en el idioma activo. */
        public string $name,
        /** Distintivo comercial («Novedad»), o null si no lo lleva. */
        public ?string $badge,
        /** @var list<string> */
        public array $features,
        /**
         * Precio MÍNIMO configurado, en céntimos. Es un «precio desde», **no un precio real**: el
         * que se cobra lo decide la tarifa del día (`RateResolver`) y puede ser mayor. `null` = el
         * producto no tiene ningún precio configurado.
         */
        public ?int $fromPriceCents,
        /**
         * ¿Hay tarifas con importes distintos? Si es `true`, anunciar el precio sin el «desde»
         * mentiría. Lo decide el dominio para que no lo deduzca cada cliente por su cuenta.
         */
        public bool $priceVaries,
        /**
         * Etiqueta de la SEÑAL configurada («30,00 €», «30 %») o null si el producto se cobra
         * entero online. Viene formateada porque la componen las reglas de `TicketType::depositLabel()`
         * (importe fijo vs porcentaje), que son dominio.
         */
        public ?string $depositLabel,
        /** Unidad de precio configurable («por niño», «por persona»), o null. */
        public ?string $periodLabel,
        /** Destacado por configuración del panel. */
        public bool $featured,
        /** Zona operativa del producto, o null si no tiene (nunca en un producto con franjas). */
        public ?CatalogZone $zone,
    ) {}

    public function isPack(): bool
    {
        return $this->type === self::TYPE_PACK;
    }
}
