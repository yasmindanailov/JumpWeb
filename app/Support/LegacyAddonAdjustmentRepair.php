<?php

namespace App\Support;

use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Reparación de datos (#193): re-atribuye al COMPLEMENTO (child) los ajustes `extra_due`
 * de complementos que el código PREVIO al fix #193 ataba al producto PRINCIPAL.
 *
 * Por qué: desde #193 el cobro en puerta de un complemento se ata a su propio `order_item`
 * (child), de modo que al cancelarlo (p. ej. un cambio de menú lo sustituye) su cargo se
 * ANULA solo. Los pedidos editados ANTES del fix tienen el ajuste atado al principal, así
 * que cancelar el complemento NO anulaba el cargo → la incoherencia "a cobrar" + "pendiente
 * de reembolso" del mismo importe (caso real JJ-KDKD1W). Re-apuntar el ajuste a su child lo
 * arregla de forma permanente (el resto de la lógica de #193 ya lo trata bien).
 *
 * CONSERVADOR: solo re-apunta cuando el match es INEQUÍVOCO. Solo procesa ajustes de UN
 * complemento (los agregados pre-#193 con varios complementos en un ajuste no se pueden
 * partir y se dejan intactos). Idempotente: los ajustes ya atados a un child no se tocan.
 */
class LegacyAddonAdjustmentRepair
{
    /**
     * @return array{repointed:int, skipped:int}
     */
    public static function run(): array
    {
        $repointed = 0;
        $skipped = 0;

        $adjustments = OrderAdjustment::query()
            ->where('type', OrderAdjustment::TYPE_EXTRA_DUE)
            ->whereNotNull('context')
            ->whereNotNull('order_item_id')
            ->with('orderItem.children.ticketType')
            ->get();

        foreach ($adjustments as $adj) {
            $current = $adj->orderItem;

            // Solo los atados a un PRINCIPAL: los ya atados a un child son post-#193 (correctos).
            if ($current === null || $current->parent_item_id !== null) {
                continue;
            }

            $entry = self::singleAddonEntry($adj);
            if ($entry === null) {
                // Sin addon_change, o agregado de varios complementos → no se puede re-atribuir.
                $skipped++;

                continue;
            }

            $child = self::matchChild($current->children, $entry, (int) $adj->created_at->getTimestamp());
            if ($child === null) {
                $skipped++;

                continue;
            }

            $adj->order_item_id = $child->id;
            $adj->save();
            $repointed++;
        }

        return ['repointed' => $repointed, 'skipped' => $skipped];
    }

    /**
     * Si el ajuste corresponde a UN solo complemento (un `added` o un `updated`), devuelve
     * su nombre y cantidad (qty solo en los `added`); si no, null.
     *
     * @return array{name:string, qty:?int}|null
     */
    private static function singleAddonEntry(OrderAdjustment $adj): ?array
    {
        $ctx = is_array($adj->context) ? $adj->context : [];
        $change = $ctx['addon_change'] ?? null;
        if (! is_array($change)) {
            return null;
        }

        $entries = [];
        foreach ($change['added'] ?? [] as $a) {
            if (! empty($a['name'])) {
                $entries[] = ['name' => (string) $a['name'], 'qty' => isset($a['qty']) ? (int) $a['qty'] : null];
            }
        }
        foreach ($change['updated'] ?? [] as $u) {
            if (! empty($u['name'])) {
                $entries[] = ['name' => (string) $u['name'], 'qty' => null];
            }
        }

        return count($entries) === 1 ? $entries[0] : null;
    }

    /**
     * Busca el child del principal que corresponde a este ajuste, de forma inequívoca:
     * nombre del producto = nombre del complemento; si hay varios, desempata por cantidad
     * y por proximidad temporal al ajuste (el child y su ajuste nacen en el mismo edit).
     *
     * @param  Collection<int,OrderItem>  $children
     * @param  array{name:string, qty:?int}  $entry
     */
    private static function matchChild(Collection $children, array $entry, int $adjTimestamp): ?OrderItem
    {
        $matches = $children->filter(fn (OrderItem $c) => self::nameMatches($c, $entry['name']));

        if ($matches->count() > 1 && $entry['qty'] !== null) {
            $byQty = $matches->filter(fn (OrderItem $c) => (int) $c->quantity === $entry['qty']);
            if ($byQty->isNotEmpty()) {
                $matches = $byQty;
            }
        }

        if ($matches->count() > 1) {
            // Mismo edit ⇒ created_at casi idéntico al del ajuste: el más cercano gana.
            $matches = $matches
                ->sortBy(fn (OrderItem $c) => abs((int) $c->created_at->getTimestamp() - $adjTimestamp))
                ->take(1);
        }

        return $matches->first();
    }

    /** ¿El nombre del producto del child coincide con el nombre guardado en el contexto? */
    private static function nameMatches(OrderItem $child, string $name): bool
    {
        $tt = $child->ticketType;
        if ($tt === null) {
            return false;
        }

        foreach (['es', 'en', 'fr'] as $locale) {
            if ((string) ($tt->tr('name', $locale) ?? '') === $name) {
                return true;
            }
        }

        return (string) ($tt->tr('name') ?? '') === $name;
    }
}
