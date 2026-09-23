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
 * que calculaba `Livewire\Tickets\Purchase` (retirado en 4.7·2b·3): la primera es el índice del
 * buscador que la web
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
     * @param  list<string>  $gifts  regalos del producto (`#589`), ya normalizados (sin vacíos)
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
         * Lo que el parque da SIN COBRAR (`#589`), aparte de lo que incluye: se pinta distinto —cada
         * regalo en su etiqueta—, y por eso no viaja mezclado en `features`.
         *
         * @var list<string>
         */
        public array $gifts,
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
        /**
         * La CLAVE del marcador de producto, ya resuelta (`Booking\Services\ProductIcon`).
         *
         * ⚠️⚠️ **Nunca es `null` y nunca se deduce en el cliente** (`#259`). El catálogo del cajón
         * elegía su dibujo con `v-if="item.is_pack"` —el patrón exacto que `#140` retiró de las
         * otras dos superficies— porque este campo no viajaba: sin él, el cliente no tenía nada más
         * que mirar. Con la clave aquí, cambiar el icono de un producto es un desplegable del panel
         * y no tocar Vue.
         */
        public string $icon,
        /** Zona operativa del producto, o null si no tiene (nunca en un producto con franjas). */
        public ?CatalogZone $zone,
        /**
         * URL ABSOLUTA de la foto del producto, o `null` si esta instalación no subió ninguna
         * (`#632` P1, T6 del menú de hechos).
         *
         * ⚠️ **Va en la LISTA y su descripción no**, y no es un descuido: un catálogo de venta se
         * recorre mirando fotos —la app de F6 no tiene landing y pinta tarjetas con lo que dé
         * esto—, mientras que la prosa se lee al abrir un producto. Una URL son ~60 bytes por fila;
         * una descripción, ~340. Es la regla que `CatalogProductDetail` ya escribió para
         * `guardianAuthorization`: cada campo de la lista se paga en todas las filas.
         *
         * Absoluta y no la ruta guardada, por el mismo motivo que en {@see CatalogZoneDetail}: la
         * resuelve `TicketType::imageUrl()` contra el disco de subidas.
         */
        public ?string $imageUrl,
        /**
         * Edad MÍNIMA del invitado, en años, o `null` si el producto no la declara (`#676`).
         *
         * ⚠️ **Entra en la LISTA y no en la ficha, con la medida delante**: quien las necesita son
         * las tarjetas de una página —la portada pinta seis de un tirón—, y sacarlas de la ficha
         * costaría una petición por tarjeta. **Medido en vivo el 23-09**: el payload de
         * `/catalog/products` pasa de **4.079 a 4.607 bytes (+13 %)** con los nueve productos que
         * publica. Lejos del **+47 %** que hizo que la ficha de ZONA no se anidara aquí.
         * ▶ La estimación previa decía +8 % y se quedó corta: se contó sobre los 24 productos
         * VENDIBLES, y la lista publica 9 —los complementos no salen—, así que cada uno pesa más
         * en el total. *Una estimación sobre el censo equivocado no es una medida.*
         *
         * ⚠️⚠️ **Es lo que el producto DECLARA, no lo que el checkout comprueba.** Sirve para
         * escribir «de 4 a 7 años» en una tarjeta; la puerta y el embudo miran sus propias reglas.
         */
        public ?int $guestAgeMin = null,
        /** Edad MÁXIMA del invitado, en años, o `null`. Mismo régimen que la mínima. */
        public ?int $guestAgeMax = null,
        /**
         * Duración en MINUTOS, o `null` si no la declara.
         *
         * ⚠️ No es `periodLabel`: aquél es el rótulo que escribe el panel («5 × 60 min») y éste es
         * la cifra con la que se puede calcular. Los dos viajan porque responden preguntas distintas.
         */
        public ?int $durationMin = null,
    ) {}

    public function isPack(): bool
    {
        return $this->type === self::TYPE_PACK;
    }
}
