<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AddonChoiceGroup;
use App\Domain\Booking\Contracts\AddonOffer;
use App\Domain\Booking\Contracts\ResolvedAddon;
use App\Domain\Booking\Contracts\ResolvedAddons;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Carbon;

/**
 * Read-model de COMPLEMENTOS RESUELTOS ({@see AddonOffer}).
 *
 * **No reimplementa nada, y eso es lo que lo hace seguro.** Todo el trabajo lo sigue haciendo
 * `AddonResolver`, que es la autoridad que ya comparten la compra web, el alta manual del panel y
 * `OrderCreator`. Lo que se añade aquí es la forma: convertir el modelo de vista —arrays pensados
 * para un Blade— en DTOs que un contrato público pueda publicar sin atarse a las claves de una
 * plantilla.
 *
 * ⚠️ **Por eso la compra web NO se cambió en este paso.** En la extracción anterior (4.0b·6) la
 * regla vivía dentro del componente Livewire y había que sacarla; aquí ya vivía en el dominio y las
 * dos superficies ya la comparten, así que hacer pasar al sidebar por estos DTOs solo cambiaría el
 * tipo de dato que consume su plantilla —la del paso con más clics del embudo— sin retirar ninguna
 * duplicación. No hay copia que unificar: hay una fuente que publicar.
 *
 * **La fecha es HOY, no la de la línea.** Medido (spec §4.4.0, punto 2): los cuatro llamantes de
 * producción pasan `Carbon::today()`, incluido `CartPricer` con el comentario explícito de que «los
 * complementos no tienen fecha propia». Pasar aquí la fecha reservada inventaría una divergencia con
 * lo que el checkout va a cobrar.
 */
class AddonOfferReader implements AddonOffer
{
    public function __construct(private AddonResolver $resolver) {}

    public function resolve(int $productId, int $quantity, array $quantities = [], array $choices = []): ?ResolvedAddons
    {
        $product = $this->selectableProduct($productId);

        if ($product === null) {
            return null;
        }

        $offered = $product->addons;
        $guests = max(0, $quantity);

        // Un grupo sin elegir no es un estado que exista: siempre hay uno activo. Se completan solo
        // los grupos que el cliente no mandó, para no pisar su elección.
        $choices += AddonResolver::defaultSelection($offered)['groups'];

        $view = $this->resolver->viewModel(
            $offered,
            $quantities,
            $choices,
            $guests,
            $product->isPack(),
            Carbon::today(),
        );

        return new ResolvedAddons(
            groups: array_map(static fn (array $group): AddonChoiceGroup => new AddonChoiceGroup(
                key: (string) $group['key'],
                label: (string) $group['label'],
                options: array_map(self::toDto(...), $group['options']),
            ), $view['groups']),
            singles: array_map(self::toDto(...), $view['singles']),
            totalCents: (int) $view['total'],
            // La misma autoridad que el modelo de vista (`resolveSelectedIds`), así que lo que se
            // guarda es exactamente lo que se enseña: obligatorios inyectados y dependientes
            // huérfanos fuera.
            selection: array_values(AddonResolver::buildSelection($offered, $quantities, $choices, $guests)),
        );
    }

    /**
     * El producto BASE por id, o `null`.
     *
     * Mismo filtro que el catálogo y la disponibilidad —en venta, zona operativa y seleccionable—,
     * y por el mismo motivo: que no exista, que no se venda o que su zona esté apagada dan la misma
     * respuesta para no convertir el endpoint en un oráculo.
     *
     * `addons.prices.rateType` va eager-loaded porque el modelo de vista recorre cada complemento;
     * el precio del día lo resuelve igualmente `RateResolver` con una consulta por complemento
     * —pendiente conocida y medida en `DEUDA.md`—, y `ApiOverheadTest` impide que empeore.
     */
    private function selectableProduct(int $productId): ?TicketType
    {
        return TicketType::sellable()
            ->inOperationalZone()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->with('addons.prices.rateType')
            ->whereKey($productId)
            ->first();
    }

    /**
     * Fila del modelo de vista → DTO. Traducción pura de claves: si aquí se decidiera algo, sería la
     * segunda fuente de verdad que todo el contrato existe para no crear.
     *
     * @param  array<string, mixed>  $row
     */
    private static function toDto(array $row): ResolvedAddon
    {
        return new ResolvedAddon(
            productId: (int) $row['id'],
            name: (string) $row['name'],
            priceCents: (int) $row['price'],
            note: (string) $row['note'],
            isIncluded: (bool) $row['is_included'],
            isMandatory: (bool) $row['is_mandatory'],
            perGuest: (bool) $row['per_guest'],
            allowExtra: (bool) $row['allow_extra'],
            badge: $row['badge'] !== null ? (string) $row['badge'] : null,
            features: array_values(array_map(strval(...), $row['features'])),
            selected: (bool) $row['selected'],
            available: (bool) $row['available'],
            requiresName: $row['requires_name'] !== null ? (string) $row['requires_name'] : null,
            quantity: (int) $row['qty'],
            freeQuantity: (int) $row['free'],
            chargedCents: (int) $row['charged'],
            minQuantity: (int) $row['min'],
            maxQuantity: $row['max'] !== null ? (int) $row['max'] : null,
            canToggle: (bool) $row['can_toggle'],
            canIncrease: (bool) $row['can_inc'],
            canDecrease: (bool) $row['can_dec'],
        );
    }
}
