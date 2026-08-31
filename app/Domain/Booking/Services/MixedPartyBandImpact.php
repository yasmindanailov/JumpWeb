<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * ¿A qué fiestas YA VENDIDAS afectaría este cambio de tramos, y por cuánto dinero?
 * (`docs/specs/cumple-mixto.md` §17.8, `[DECIDIDO owner, 2026-08-30]`).
 *
 * ## El problema que contesta
 *
 * El suplemento de una fiesta mixta se deriva de los TRAMOS de edad del catálogo. Cambiarlos no es
 * una preferencia estética: **mueve dinero de reservas ya vendidas y comunicadas**, y en las dos
 * direcciones. Medido sobre el catálogo real (Kids 1–6 a 11,00 € · Jump 7–99 a 15,00 €), con una
 * fiesta Kids de 8 invitados de 6 años:
 *  - **bajar el corte** («desde los 6 ya van a Jump») le crea **32,00 €** de cargo que nadie le
 *    comunicó, en cuanto el cliente vuelva a tocar su formulario;
 *  - **subirlo otra vez** se los quita.
 *
 * ⚠️ **El PRECIO ya no hace falta vigilarlo aquí**: desde `#270` cada línea escrita lleva su recibo
 * y hereda el unitario comunicado, así que retocar una tarifa no mueve lo vendido. El único eje que
 * sigue moviéndolo es el TRAMO — que es exactamente lo que esta clase mide.
 *
 * ## Por qué SIMULA en vez de escribir y revertir
 *
 * Escribir el cambio, medir y hacer rollback sería fiel pero dispararía eventos de modelo y de
 * auditoría por un cálculo que solo sirve para pintar un número. En su lugar se clonan los productos
 * de la familia EN MEMORIA con los valores propuestos y se le siembran al lector
 * ({@see GuestAgeMixReader::pretendFamilyIs}), que deriva con **su mismo recorrido de siempre**.
 *
 * ⚠️⚠️ Eso es lo que garantiza que el número del aviso y el que se escribirá después salgan de la
 * MISMA regla. Una copia de la aritmética aquí daría un aviso que envejece solo, y el operador
 * decidiría mirando una cifra que el reconciliador no va a respetar.
 */
class MixedPartyBandImpact
{
    public function __construct(private RateResolver $rates, private MixedPartySurcharge $surcharge) {}

    /**
     * El impacto de dejar `$product` con esa familia y ese tramo.
     *
     * @return array{reservations:int, created_cents:int, removed_cents:int}
     */
    public function of(TicketType $product, ?string $family, ?int $min, ?int $max): array
    {
        $nada = ['reservations' => 0, 'created_cents' => 0, 'removed_cents' => 0];

        // Las familias en juego son DOS cuando el cambio saca al producto de la suya: las reservas de
        // sus antiguos hermanos dejan de tener con quién compararse, y eso también mueve dinero.
        $familias = array_values(array_filter(array_unique([$product->guestAgeFamily(), $family])));
        if ($familias === []) {
            return $nada;
        }

        $simulado = $this->simulatedProducts($product, $family, $min, $max, $familias);
        $reservas = $this->liveReservations($familias);
        if ($reservas->isEmpty()) {
            return $nada;
        }

        $creado = 0;
        $retirado = 0;
        $tocadas = 0;

        foreach ($reservas as $reserva) {
            $escrito = $this->surcharge->written($reserva)['cents'];
            $futuro = $this->simulatedCharge($reserva, $simulado, $familias);

            if ($futuro === $escrito) {
                continue;
            }
            $tocadas++;
            $creado += max(0, $futuro - $escrito);
            $retirado += max(0, $escrito - $futuro);
        }

        return ['reservations' => $tocadas, 'created_cents' => $creado, 'removed_cents' => $retirado];
    }

    /** ¿Hay algo que avisar? Un cambio que no mueve dinero de nadie no interrumpe a nadie. */
    public function isWorthWarning(array $impact): bool
    {
        return $impact['reservations'] > 0;
    }

    /**
     * Los packs de las familias en juego, con los valores PROPUESTOS ya puestos en el que se edita.
     *
     * Se clonan (`replicate` conservando la clave) para no tocar las instancias que el resto de la
     * petición pueda tener cargadas: esto es una simulación, y una simulación que muta el estado
     * compartido deja de serlo.
     *
     * @param  list<string>  $familias
     * @return list<TicketType>
     */
    private function simulatedProducts(TicketType $product, ?string $family, ?int $min, ?int $max, array $familias): array
    {
        $miembros = TicketType::query()
            ->where('type', TicketType::TYPE_PACK)
            ->where(fn ($q) => $q->whereIn('guest_age_family', $familias)->orWhere('id', $product->getKey()))
            ->get()
            ->map(function (TicketType $p) use ($product, $family, $min, $max): TicketType {
                $copia = clone $p;
                if ((int) $p->id === (int) $product->id) {
                    $copia->guest_age_family = $family;
                    $copia->guest_age_min = $min;
                    $copia->guest_age_max = $max;
                }

                return $copia;
            })
            ->all();

        // Mismo orden que `GuestAgeMixReader::family()`: manda el de menor edad, y el desempate por
        // id. Si esto ordenara distinto, el aviso diría una cosa y el reconciliador haría otra.
        usort($miembros, function (TicketType $a, TicketType $b): int {
            $am = $a->guest_age_min ?? -1;
            $bm = $b->guest_age_min ?? -1;

            return $am <=> $bm ?: ((int) $a->id <=> (int) $b->id);
        });

        return $miembros;
    }

    /**
     * Lo que costaría el suplemento de esa reserva con los tramos propuestos.
     *
     * ⚠️ El lector es una instancia PROPIA y de un solo uso: `GuestAgeMixReader` va en `scoped`, y
     * sembrarle una familia inventada a la del contenedor dejaría al resto de la petición —la ficha
     * del pedido, el post-form— derivando contra un catálogo que no existe.
     *
     * @param  list<TicketType>  $simulado
     * @param  list<string>  $familias
     */
    private function simulatedCharge(OrderItem $reserva, array $simulado, array $familias): int
    {
        $suProducto = null;
        foreach ($simulado as $p) {
            if ((int) $p->id === (int) $reserva->ticket_type_id) {
                $suProducto = $p;
            }
        }
        if ($suProducto === null) {
            return $this->surcharge->written($reserva)['cents']; // no es de estas familias: intacta
        }

        $copia = clone $reserva;
        $copia->setRelation('ticketType', $suProducto);

        $lector = new GuestAgeMixReader($this->rates);
        foreach ($familias as $clave) {
            $lector->pretendFamilyIs($clave, array_values(array_filter(
                $simulado,
                fn (TicketType $p): bool => $p->guestAgeFamily() === $clave,
            )));
        }
        $futura = $suProducto->guestAgeFamily();
        if ($futura !== null && ! in_array($futura, $familias, true)) {
            $lector->pretendFamilyIs($futura, array_values(array_filter(
                $simulado,
                fn (TicketType $p): bool => $p->guestAgeFamily() === $futura,
            )));
        }

        $mix = $lector->for($copia);

        // Se compara contra lo que el reconciliador ESCRIBIRÍA, no contra el veredicto crudo: los
        // destinos sin diferencia o sin precio no llegan a ser línea (`MixedPartySurcharge`).
        $total = 0;
        foreach ($mix->upgrades as $upgrade) {
            $unit = $upgrade['unit_cents'];
            if ($unit === null || $unit <= 0 || $upgrade['count'] <= 0) {
                continue;
            }
            $total += $unit * (int) $upgrade['count'];
        }

        return $total;
    }

    /**
     * Las reservas que este cambio puede mover: vivas, de un pedido vivo y **sin celebrar**.
     *
     * ⚠️ Las finalizadas quedan fuera y no es una optimización: su cargo ya se da por resuelto
     * (`Order::itemGateResolved`), el post-form es de solo lectura y el panel bloquea editarlas, así
     * que ninguna reconciliación las va a tocar. Contarlas asustaría al operador con un número que
     * no puede pasar.
     *
     * @param  list<string>  $familias
     * @return Collection<int, OrderItem>
     */
    private function liveReservations(array $familias)
    {
        return OrderItem::query()
            ->whereNull('cancelled_at')
            ->whereNull('parent_item_id')
            ->whereHas('ticketType', fn ($q) => $q->whereIn('guest_age_family', $familias))
            ->whereHas('order', fn ($q) => $q->whereIn('status', [Order::STATUS_PAID, Order::STATUS_PENDING]))
            ->with(['ticketType', 'slot', 'order.adjustments', 'children'])
            ->get()
            ->reject(fn (OrderItem $i): bool => $i->isFinishedInPractice())
            ->values();
    }
}
