<?php

namespace App\Domain\Booking\Contracts;

/**
 * La FICHA COMPLETA de un producto del catálogo: su resumen de lista más lo que solo hace falta
 * cuando alguien abre ese producto concreto.
 *
 * **Compone en vez de heredar** ({@see CatalogProduct} es `final`): un detalle *tiene* un resumen,
 * y así hay una sola definición de los campos comunes. Que el JSON de la API lo aplane —el cliente
 * ve un único objeto— es decisión de la capa HTTP, no de la forma del dominio.
 *
 * La separación lista/detalle no es estética: cargar complementos y campos de evento cuesta una
 * consulta por producto, y el catálogo se lista entero en la primera pantalla del flujo de compra.
 */
final readonly class CatalogProductDetail
{
    /**
     * @param  list<CatalogEventField>  $eventFields  campos del evento de la etapa `booking` (vacío si no es pack o no define ninguno)
     * @param  list<CatalogAddon>  $addons  complementos OFRECIBLES, en el orden configurado
     */
    public function __construct(
        public CatalogProduct $product,
        /**
         * Cantidad mínima contratable: los invitados mínimos de un pack, o 1 en una entrada. Es la
         * misma regla que aplica `OrderCreator` al admitir la línea, así que un cliente que la
         * respete no puede construir un pedido que el servidor vaya a rechazar por cantidad.
         */
        public int $minQuantity,
        /** Tope contratable configurado (packs), o null si no lo hay. El aforo es otro límite, y lo da disponibilidad. */
        public ?int $maxQuantity,
        /** @var list<CatalogEventField> */
        public array $eventFields,
        /** @var list<CatalogAddon> */
        public array $addons,
        /**
         * El JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.2):
         * `none` · `optional` · `required`.
         *
         * ⚠️ Va en el DETALLE y no en el resumen de lista a propósito: la casilla se pinta al elegir
         * la franja y la cantidad de UN producto, no al recorrer el catálogo — y el catálogo entero
         * se sirve en la primera pantalla del flujo, donde cada campo se paga en todas las filas.
         *
         * ⚠️⚠️ Es informativo para pintar. **Lo que se guarda lo decide `OrderCreator`** con el
         * catálogo delante: un cliente que mienta aquí no cambia nada.
         */
        public string $guardianAuthorization,
        /**
         * Qué es este producto, en el idioma activo, o `null` si la instalación no lo escribió
         * (`#632` P1, T6 del menú de hechos).
         *
         * ⚠️ Va en el DETALLE y no en el resumen de lista, por lo mismo que `guardianAuthorization`
         * y que la foto al revés: es PROSA —medidas ~340 bytes en las 6 que la tienen— y se lee al
         * abrir un producto, no al recorrer el catálogo. Medido además el 19-09: hoy la pinta un
         * solo sitio del producto, el `<meta name="description">` de `/cumpleanos`.
         *
         * `null` y no `''`: lo que la instalación no rellenó no viaja.
         */
        public ?string $description,
    ) {}
}
