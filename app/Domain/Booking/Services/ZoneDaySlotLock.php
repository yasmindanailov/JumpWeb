<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Slot;
use Illuminate\Support\Collection;

/**
 * La receta anti-sobreventa de `AFORO-01` / `AFORO-05`, en UN solo sitio
 * (extracción 4b del desmontaje de `ViewOrder`, spec §8.9 y §9.6·4): hasta
 * hoy vivía dos veces —`OrderCreator::lockSlots` para la compra y
 * `ViewOrder::lockZoneDaySlots` para las ediciones del panel—, con el
 * porqué documentado solo en el dominio. Si alguien corregía una, la otra
 * derivaba en silencio.
 *
 * Bloquea (`FOR UPDATE`) TODAS las franjas de las zonas/días implicados,
 * no solo la franja destino: la ocupación se cuenta por TRAMO (`#60`), así
 * que dos operaciones con tramos SOLAPADOS en franjas distintas de la misma
 * zona/día no compartirían fila bloqueada y podrían sobrellenar una franja
 * intermedia común (el bug L3 que motivó `lockZoneDaySlots`; el escenario
 * `panel-edit` de `purchase:verify-oversell` lo reproduce). Bloquear la
 * zona/día entera serializa compra↔compra, panel↔panel y web↔panel, porque
 * los tres bloquean este MISMO conjunto.
 *
 * Las tres piezas de la receta, y por qué cada una:
 *  - **`zoneIds` y `dates` llegan como LITERALES**, resueltos FUERA de la
 *    transacción, NUNCA como subconsulta ni como lectura previa. Bajo
 *    REPEATABLE READ (el default de MySQL) el snapshot de las lecturas
 *    consistentes se fija en la PRIMERA de ellas; una subconsulta dentro
 *    del propio `SELECT … FOR UPDATE` —o cualquier SELECT anterior— fija
 *    ese snapshot ANTES de que el lock serialice, y el recuento de aforo
 *    posterior lee un estado anterior al rival → SOBREVENTA REAL (`#246`,
 *    reproducida con fork sobre InnoDB). El «lock primero» con subconsulta
 *    fue un fix INSUFICIENTE; solo el literal es correcto.
 *  - **`orderBy('id')`**: orden estable de adquisición → sin interbloqueos
 *    entre dos transacciones que bloquean el mismo conjunto.
 *  - **Debe ser la PRIMERA sentencia de la transacción** que lo llama. Eso
 *    no lo puede garantizar este helper: es la obligación del llamante, y
 *    la suite (SQLite, sin `FOR UPDATE`) no la ve — la ven los
 *    verificadores sobre MySQL (`SUITE-04`). Por eso este fichero está en el
 *    `CRITICAL_RE` del `pre-push`.
 *
 * Bloquea por zona×fecha (superset de los pares exactos zona|fecha: si una
 * cesta mezcla varias zonas y fechas se bloquea alguna franja de más, nunca
 * de menos — irrelevante para la corrección y de contención mínima).
 */
class ZoneDaySlotLock
{
    /**
     * @param  array<int,int>  $zoneIds  literales, resueltos ANTES de abrir la transacción
     * @param  array<int,string>  $dates  fechas civiles `Y-m-d` (sin hora: la columna es DATE y
     *                                    así la comparación es exacta en MySQL y en SQLite)
     * @return Collection<int, Slot> las franjas bloqueadas, en orden de `id`
     */
    public function acquire(array $zoneIds, array $dates): Collection
    {
        if ($zoneIds === [] || $dates === []) {
            return collect();
        }

        return Slot::query()
            ->whereIn('zone_id', array_values($zoneIds))   // LITERAL (no subconsulta) → el FOR UPDATE no fija el snapshot
            ->whereIn('date', array_values($dates))
            ->orderBy('id')                                 // orden estable → evita interbloqueos
            ->lockForUpdate()
            ->get();
    }
}
