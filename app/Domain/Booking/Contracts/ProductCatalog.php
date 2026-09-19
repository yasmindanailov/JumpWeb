<?php

namespace App\Domain\Booking\Contracts;

/**
 * El CATÁLOGO de venta, para quien vive fuera de Booking (Fase 3 · paso 1b, `docs/specs/api-v1.md`
 * §4.6.4: «read-model de catálogo → Booking»).
 *
 * Qué entra en el catálogo —productos en venta online, de zona operativa, seleccionables— es una
 * regla de Booking, y hasta ahora vivía dentro de `Livewire\Tickets\Purchase`: cualquier otro
 * cliente (la SPA de Fase 4, la app de Fase 6) habría tenido que reescribirla, y dos definiciones
 * de «qué se vende» divergen en cuanto una de las dos se toca. Ahora la definición es esta, la web
 * la consume igual que la API, y la suite de la web es el testigo de que no derivan.
 *
 * **Es solo lectura y sin estado**: describe lo que se ofrece, no lo que un cliente concreto ha
 * elegido. Lo que depende de la elección —cuántas plazas quedan, cuánto suma una cesta— llega en
 * los pasos 4 y siguientes (`availability/*`, `orders/quote`), y llega por servidor precisamente
 * para que ningún cliente recalcule reglas de dinero o de aforo.
 *
 * Los textos vienen resueltos al idioma activo (los guarda la BD, no `lang/`); el FORMATO —unir
 * ventajas con un separador, componer «desde 12 €»— lo pone quien pinta.
 *
 * Implementación actual: `App\Domain\Booking\Services\CatalogReader` (bind en
 * `BookingServiceProvider`) — se nombra en prosa y no con `{@see}` para que una interfaz no
 * importe a su implementación.
 */
interface ProductCatalog
{
    /**
     * Zonas que OPERAN (`zones.is_active`), en el orden configurado, **con su ficha**.
     *
     * Una zona desactivada no vende: sus productos ya quedan fuera de `products()`, y listarla
     * igualmente ofrecería al cliente un filtro que nunca tiene contenido.
     *
     * ⚠️ Devuelve {@see CatalogZoneDetail} y no {@see CatalogZone} desde la T6 del menú de hechos
     * (`#632` P1): aquí la ficha es el punto —son cuatro filas y es SU endpoint—, mientras que la
     * zona anidada en cada producto sigue siendo solo identidad, porque ahí se paga en todas las
     * filas.
     *
     * @return list<CatalogZoneDetail>
     */
    public function zones(): array;

    /**
     * Productos SELECCIONABLES: entradas y packs en venta online cuya zona opera, en el orden
     * configurado en el panel (`position`).
     *
     * Los complementos (`addon`) NO están: no se eligen sueltos, viven dentro de un producto y se
     * consultan en {@see product()}.
     *
     * `is_sellable` y `is_active` son ejes INDEPENDIENTES (P3): un producto oculto de la landing
     * puede seguir vendiéndose, así que el catálogo mira lo primero y no lo segundo.
     *
     * @param  CatalogProduct::TYPE_*|null  $type  filtra a un tipo, o null para todos
     * @return list<CatalogProduct>
     */
    public function products(?string $type = null): array;

    /**
     * Ficha completa de un producto seleccionable, o `null` si ese id no está en el catálogo —
     * porque no existe, porque no está en venta o porque su zona no opera. Las tres razones dan la
     * misma respuesta a propósito: distinguirlas le contaría a un desconocido qué hay en la BD.
     */
    public function product(int $id): ?CatalogProductDetail;
}
