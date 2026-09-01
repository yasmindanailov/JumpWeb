<?php

namespace App\Filament\Resources\Users\Support;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Orders\Support\AssignedDependents;

/**
 * Fase 6 · menores a cargo, tanda 5 (D·3) — el READ-MODEL de «qué menores tiene declarados este
 * titular» que enseña la FICHA DEL CLIENTE (`docs/specs/menores-a-cargo.md` §9.10, §4.1–§4.4).
 *
 * Es el gemelo de {@see AssignedDependents} —el read-model de la ficha del PEDIDO— y comparte con él
 * el vocabulario de la exención (`WAIVER_CURRENT` / `WAIVER_OUTDATED` / `WAIVER_MISSING`) a
 * propósito: las dos pantallas del panel dicen lo mismo con las mismas palabras. Lo que cambia es la
 * pregunta, y por eso son dos clases y no una:
 *
 *  - allí la edad es la del DÍA DE LA VISITA (es la que importa en la puerta, D13); **aquí es la de
 *    HOY** (`Dependent::age()`, hoy del parque): la ficha del cliente no habla de ninguna visita.
 *  - allí solo salen los menores ASIGNADOS a una entrada; aquí salen **todos los declarados**, en
 *    orden de alta (`id`), incluidos los DESVINCULADOS (`removed_at`) — igual que la ficha del
 *    pedido los sigue enseñando marcados: una fila retirada tiene un waiver firmado detrás y el
 *    operador necesita verla para entender la cuenta (§4.4).
 *
 * Tres reglas que son la clase entera:
 *
 *  - **Cuenta ANONIMIZADA: no se lista nada** (`RGPD-01`, §5). Las filas que sobreviven a
 *    `User::anonymize()` lo hacen bajo el régimen RESTRINGIDO de su firma, y su sitio es la acción
 *    «Registro del waiver» —con permiso propio y cada apertura auditada—, no una sección abierta a
 *    cualquiera con `users.view`. La vista también lo comprueba; esto es la guarda de la guarda: si
 *    un día alguien pinta esta lista desde otro sitio, tampoco filtra.
 *  - **El estado de la exención solo existe en modo INTERNO** (`waiver-probatorio.md` §4.1): en
 *    externo el sistema del parque no sabe de menores y en desactivado no hay pregunta. Fuera de
 *    interno el campo vale `null` y la columna no se pinta ({@see showsWaiver()}).
 *  - **Cada celda se compone AQUÍ, no en la vista**: Livewire intercala marcadores de bloque en cada
 *    `@if`, y un rótulo partido en tres no se puede afirmar (ni leer) de una pieza — la lección que
 *    ya pagó `AssignedDependents::label()`.
 *
 * Presupuesto de consultas MEDIDO (su test): **1 fuera de interno** (los menores) y **≤4 en
 * interno** (los menores + las firmas + sus versiones + la versión vigente), sea cual sea el número
 * de menores. Los ajustes no cuentan: `Setting::value()` memoiza todos en una lectura.
 */
final class HolderDependents
{
    /**
     * ¿Se enseña la columna de la exención? La vista pregunta AQUÍ y no a los ajustes: si la cabecera
     * de la tabla y el read-model consultaran cada uno por su lado, bastaría con cambiar uno para
     * pintar una columna siempre vacía (o esconder un dato que sí existe).
     */
    public static function showsWaiver(): bool
    {
        return WaiverSettings::isInternal();
    }

    /**
     * @return list<array{id:int, name:string, relationship:?string, years:int, age:string, waiver:?string, waiver_label:?string, since:string, removed:?string, is_removed:bool}>
     */
    public static function forHolder(User $holder): array
    {
        // Régimen restringido: ver el comentario de clase. Va ANTES de la consulta, no después.
        if ($holder->isAnonymized()) {
            return [];
        }

        $rows = $holder->dependents()->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $statuses = self::showsWaiver() ? WaiverStatus::forDependents($rows) : [];

        return $rows->map(function (Dependent $dependent) use ($statuses): array {
            $status = $statuses[(int) $dependent->getKey()] ?? null;
            $state = $status?->minorState();
            $years = $dependent->age();

            return [
                'id' => (int) $dependent->getKey(),
                // `#236`: en el PANEL sí va el nombre completo —el operador atiende una incidencia
                // y necesita identificar sin ambigüedad—, y la relación del titular con el menor.
                // La pantalla de PUERTA es la que se queda solo con el nombre de pila.
                'name' => $dependent->fullName(),
                'relationship' => $dependent->relationship !== null
                    ? (string) __('admin.users.dependents.relationship_'.$dependent->relationship)
                    : null,
                // El número en crudo NO es para pintarlo: es lo que afirman los tests y lo que lleva
                // el `data-` de la celda, porque el rótulo depende de una traducción y la edad no.
                'years' => $years,
                // §4.1: la fila SOBREVIVE a la mayoría de edad —nadie la borra por un cumpleaños— y
                // deja de estar cubierta por el waiver del adulto. Decirlo es el trabajo de la ficha.
                'age' => $dependent->isMinor()
                    ? (string) __('admin.users.dependents.age_today', ['age' => $years])
                    : (string) __('admin.users.dependents.adult'),
                'waiver' => $state,
                // Mismos rótulos que la ficha del pedido (`admin.orders.dependents.waiver_*`): el
                // operador no debería tener que traducir dos vocabularios entre dos pantallas.
                // `#320`: y la misma regla —solo la EXCEPCIÓN lleva rótulo—. El estado sigue viajando
                // entero en `waiver` (el `data-` de la celda), aunque `current` ya no se pinte.
                'waiver_label' => WaiverStatus::minorStateIsNoteworthy($state)
                    ? (string) __('admin.orders.dependents.waiver_'.$state)
                    : null,
                'since' => DisplayTime::format($dependent->created_at, 'd/m/Y'),
                'removed' => $dependent->isRemoved()
                    ? (string) __('admin.users.dependents.removed_on', [
                        'date' => DisplayTime::format($dependent->removed_at, 'd/m/Y'),
                    ])
                    : null,
                'is_removed' => $dependent->isRemoved(),
            ];
        })->all();
    }
}
