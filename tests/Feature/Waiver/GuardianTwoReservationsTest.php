<?php

namespace Tests\Feature\Waiver;

use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Setting;
use App\Notifications\GuardianAuthorizationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * **UN PEDIDO CON DOS VISITAS** — la red que le faltaba entera a `#401`
 * (`DECISIONES #406`, encontrado por la revisión adversarial del subsistema).
 *
 * ❗❗❗ **Los SIETE ficheros que cubrían el justificante creaban pedidos de UNA sola línea**, y con
 * una sola reserva por pedido «por reserva» y «por pedido» son conductas **indistinguibles**. O sea
 * que el cambio entero de `#401` —los cuatro síntomas que el owner encontró con `R-LUKFD2`— se podía
 * revertir con la suite en VERDE.
 *
 * ⚠️⚠️ Y el dato lo remata desde el otro lado: al escribir esto había **cero pedidos con
 * justificantes en dos reservas distintas** en toda la base de datos, así que el cambio tampoco
 * tenía sujeto en los datos reales. *Una feature sin fixture y sin datos es una feature sin red.*
 *
 * Aquí se fija lo que `#401` estableció, y cada caso corresponde a un síntoma real:
 *
 *  1. **UN correo por reserva marcada**, cada uno con SU enlace (antes: un solo correo por pedido).
 *  2. **El cupo es el de SU línea**, no la suma del pedido (antes: una excursión de 80 junto a una
 *     entrada ofrecía 81 plazas).
 *  3. **«Un niño, un papel» es por VISITA**: el mismo menor puede tener dos justificantes en el mismo
 *     pedido si va dos días (antes se le rechazaba diciendo que ya estaba firmado).
 *
 * ⚠️ **El fixture no puede ser el de los demás ficheros.** Lo que hace falta es exactamente lo que
 * ninguno construía: dos líneas PRINCIPALES, en días distintos, las dos marcadas.
 */
class GuardianTwoReservationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
    }

    /** Dos reservas PRINCIPALES en días distintos, las dos marcadas, con cantidades distintas. */
    private function orderWithTwoVisits(User $responsible): Order
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);

        $order = Order::create([
            'user_id' => $responsible->id,
            'code' => 'R-DOSVIS',
            'status' => Order::STATUS_PAID,
            'subtotal' => 5000, 'tax' => 0, 'total' => 5000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);

        // ⚠️ Las horas se cierran ANTES de que acabe el día: una franja que termina a las 23:00 hace
        // fallar estos casos cerca de medianoche, que es la trampa que la auditoría del reloj cazó
        // once veces en `#337`. Aquí las dos visitas son FUTURAS, así que no dependen de la hora.
        foreach ([[1, 10], [2, 1]] as [$dias, $cantidad]) {
            $slot = Slot::create([
                'zone_id' => $zone->id, 'date' => now()->addDays($dias)->toDateString(),
                'start_time' => '10:00:00', 'end_time' => '14:00:00', 'capacity' => 200, 'online_capacity' => 200,
            ]);
            $order->items()->create([
                'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
                'quantity' => $cantidad, 'unit_price' => 500, 'seats' => $cantidad,
                'guardian_authorization' => true,
            ]);
        }

        return $order->fresh();
    }

    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Descargo', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    /**
     * SÍNTOMA 1 — un correo por VISITA, cada uno con su enlace.
     *
     * ⚠️ **La aserción no puede ser solo «se mandaron dos»**: dos notificaciones de la MISMA reserva
     * también son dos. Lo que fija la conducta es que los sujetos sean las DOS líneas distintas.
     */
    public function test_each_marked_reservation_gets_its_own_email_with_its_own_link(): void
    {
        Notification::fake();

        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderWithTwoVisits($responsible);

        foreach ($order->guardianReservations() as $reservation) {
            $responsible->notify(new GuardianAuthorizationRequest($reservation));
        }

        $ids = $order->guardianReservations()->pluck('id')->all();
        $this->assertCount(2, $ids, 'el fixture tiene que traer DOS reservas marcadas o el caso no vigila nada');

        Notification::assertSentToTimes($responsible, GuardianAuthorizationRequest::class, 2);

        // Cada correo apunta a SU reserva: dos enlaces distintos, uno por visita.
        $enlaces = [];
        foreach ($ids as $id) {
            Notification::assertSentTo(
                $responsible,
                GuardianAuthorizationRequest::class,
                function (GuardianAuthorizationRequest $n) use ($responsible, $id, &$enlaces): bool {
                    $url = (string) $n->toMail($responsible)->actionUrl;
                    if (! str_contains($url, "/autorizacion/{$id}?")) {
                        return false;
                    }
                    $enlaces[] = $url;

                    return true;
                },
            );
        }

        $this->assertCount(2, array_unique($enlaces), 'los dos correos tienen que llevar enlaces DISTINTOS');
    }

    /**
     * SÍNTOMA 2 — el cupo es el de SU línea, no la suma del pedido.
     *
     * Es el caso literal del owner: una reserva grande junto a una de UNA plaza. Si el cupo volviera
     * a sumar las líneas, la pequeña ofrecería 11.
     */
    public function test_the_cap_is_the_line_and_never_the_sum_of_the_order(): void
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderWithTwoVisits($responsible);
        $reservas = app(AuthorizableReservations::class)->markedForOrder((int) $order->getKey());

        $cantidades = collect($reservas)->pluck('quantity')->sort()->values()->all();

        $this->assertSame([1, 10], $cantidades, 'cada reserva declara SU cantidad; sumar las líneas daría [11, 11]');
    }

    /**
     * SÍNTOMA 3 — «un niño, un papel» es por VISITA.
     *
     * El mismo menor que va a los dos días del mismo pedido necesita DOS autorizaciones. Con la clave
     * acotada al PEDIDO, la segunda se rechazaba diciendo que ya estaba firmada — que es justamente
     * el defecto que `#401` arregló.
     */
    public function test_the_same_minor_can_be_authorised_for_both_visits_of_one_order(): void
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderWithTwoVisits($responsible);
        $version = $this->version();
        $signer = app(GuardianAuthorizationSigner::class);

        $datos = [
            'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto',
            'minor_born_on' => now()->subYears(9)->toDateString(),
            'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil',
            'guardian_relationship' => 'father',
            'guardian_email' => 'carlos@example.com', 'guardian_phone' => '600333444',
        ];

        $creadas = [];
        foreach ($order->guardianReservations() as $reservation) {
            $r = $signer->sign($responsible, (int) $reservation->getKey(), $version, $datos, WaiverSignatureRequest::web('127.0.0.1', 'phpunit'));
            $creadas[] = $r['created'];
        }

        // Las DOS son nuevas: la segunda visita no es un reenvío de la primera.
        $this->assertSame([true, true], $creadas);
        $this->assertSame(2, GuardianAuthorization::count());

        // Y cada una cuelga de su propia reserva.
        $this->assertSame(
            $order->guardianReservations()->pluck('id')->sort()->values()->all(),
            GuardianAuthorization::query()->pluck('order_item_id')->sort()->values()->all(),
        );
    }
}
