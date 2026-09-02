<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Setting;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\ApiTestCase;

/**
 * `GET /api/v1/orders/{code}/guest-minors` — **lo que ve el RESPONSABLE** de una reserva
 * (`specs/waiver-por-reserva.md` §4.10, tanda T3), contra el CONTRATO.
 *
 * ❗ Hereda de `ApiTestCase`, así que cada respuesta se valida contra `openapi/v1.yaml` con Spectator.
 * Eso convierte al contrato en la SEGUNDA guarda de lo que aquí no puede salir: su esquema declara
 * `additionalProperties: false` y solo `minor` y `waiver` por fila, así que **si algún día alguien
 * añadiera el nombre o el correo del otro padre al roster, la respuesta dejaría de encajar** y este
 * fichero se pondría rojo aunque nadie hubiera escrito una aserción sobre ello.
 */
class OrderGuestMinorsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
    }

    private function scenario(bool $withAuthorization = true, ?string $date = null): array
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => $date ?? now()->addMonth()->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $order = Order::create([
            'user_id' => $responsible->id, 'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        // ⚠️ La línea nace MARCADA: desde `#401` el endpoint devuelve una entrada por RESERVA
        // marcada, así que una línea sin marca no tiene enlace que ofrecer — y es lo correcto: a un
        // pedido normal no se le reparte nada.
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 7, 'unit_price' => 500, 'seats' => 7,
            'guardian_authorization' => true,
        ]);

        $version = app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();

        if ($withAuthorization) {
            app(GuardianAuthorizationSigner::class)->sign(
                $responsible,
                // ⚠️ El sujeto es la RESERVA desde `#401`, no el pedido.
                (int) $order->items()->whereNull('parent_item_id')->orderBy('id')->value('id'),
                $version, [
                    'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto', 'minor_born_on' => '2016-11-20',
                    'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil', 'guardian_relationship' => 'father',
                    'guardian_email' => 'carlos@example.com', 'guardian_phone' => '600333444',
                ], WaiverSignatureRequest::web('10.0.0.1', 'UA'));
        }

        return [$responsible, $order];
    }

    public function test_the_responsible_gets_one_entry_per_reservation_with_its_own_link(): void
    {
        [$responsible, $order] = $this->scenario();
        Sanctum::actingAs($responsible);

        $response = $this->getJson(self::ROOT."/orders/{$order->code}/guest-minors");

        $response->assertValidResponse(200);
        // ⚠️ **Una entrada por RESERVA desde `#401`**: el justificante cuelga de la visita, no de la
        // compra. Un pedido con dos visitas trae dos enlaces y dos fechas.
        $response->assertJsonCount(1, 'data.reservations');
        $response->assertJsonPath('data.reservations.0.minors.0.minor', 'Luis Pérez Soto');
        $response->assertJsonPath('data.reservations.0.minors.0.waiver', 'current');
        // Las plazas LIBRES de esa reserva: sus 7 unidades menos el justificante ya firmado.
        $response->assertJsonPath('data.reservations.0.places', 6);
        $response->assertJsonPath('data.reservations.0.product_name', 'Entrada');
        $this->assertStringContainsString('/autorizacion/', (string) $response->json('data.reservations.0.link'));
    }

    public function test_it_never_carries_anything_of_the_other_parents(): void
    {
        [$responsible, $order] = $this->scenario();
        Sanctum::actingAs($responsible);

        $body = $this->getJson(self::ROOT."/orders/{$order->code}/guest-minors")->assertOk()->getContent();

        // Aserción sobre el CUERPO, no sobre la intención: ni el adulto, ni su relación, ni su
        // contacto. Y el contrato lo respalda con `additionalProperties: false`.
        foreach (['Carlos', 'Pérez Gil', 'carlos@example.com', '600333444', 'father'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "el roster del responsable filtró «{$leak}»");
        }
    }

    public function test_the_link_is_null_once_the_visit_has_passed(): void
    {
        // Repartir el enlace entonces sería mandar a un padre a una pantalla que le dirá que no.
        //
        // ⚠️ La visita se mueve al pasado DESPUÉS de firmar: el dominio no deja autorizar sobre una
        // visita ya pasada, y un fixture que lo intentara probaría un mundo que no existe. Es el
        // orden real de los hechos —se firma antes, la visita ocurre después—.
        [$responsible, $order] = $this->scenario();
        Slot::query()->whereKey($order->items()->value('slot_id'))
            ->update(['date' => now()->subDays(3)->toDateString()]);
        Sanctum::actingAs($responsible);

        $response = $this->getJson(self::ROOT."/orders/{$order->code}/guest-minors");

        $response->assertValidResponse(200);
        $response->assertJsonPath('data.reservations.0.link', null);
        // CONTROL: los justificantes ya firmados SIGUEN saliendo — la visita pasada no los borra.
        $response->assertJsonCount(1, 'data.reservations.0.minors');
    }

    public function test_without_free_places_the_link_is_null_too(): void
    {
        // ❗ El caso del owner, encontrado con la sonda: compró UNA entrada, se la asignó a su hija y
        // la pantalla seguía ofreciendo el enlace. Repartirlo era mandar a un padre a una pantalla
        // que le iba a decir que no — la misma razón por la que se apaga con la visita pasada.
        [$responsible, $order] = $this->scenario(withAuthorization: false);
        $item = $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail();
        $item->update(['quantity' => 1]);
        DependentAssignment::create([
            'dependent_id' => Dependent::create([
                'user_id' => $responsible->id, 'name' => 'Hija', 'surname' => 'Del Titular',
                'born_on' => now()->subYears(8)->toDateString(), 'relationship' => 'father',
            ])->id,
            'order_item_id' => $item->id,
        ]);
        Sanctum::actingAs($responsible);

        $response = $this->getJson(self::ROOT."/orders/{$order->code}/guest-minors");

        $response->assertValidResponse(200);
        $response->assertJsonPath('data.reservations.0.places', 0);
        $response->assertJsonPath('data.reservations.0.link', null);
    }

    public function test_an_order_of_someone_else_is_a_404_not_a_403(): void
    {
        // Un 403 le confirmaría a un desconocido que ese pedido existe, y quien pregunta por él está
        // preguntando por datos de menores.
        [, $order] = $this->scenario();
        Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

        $this->getJson(self::ROOT."/orders/{$order->code}/guest-minors")->assertNotFound();
    }

    public function test_an_order_without_authorisations_answers_an_empty_roster(): void
    {
        // CONTROL de que la lista vacía es una RESPUESTA y no un fallo: el enlace sigue viniendo,
        // que es justo lo que el responsable necesita cuando todavía no ha firmado nadie.
        [$responsible, $order] = $this->scenario(withAuthorization: false);
        Sanctum::actingAs($responsible);

        $response = $this->getJson(self::ROOT."/orders/{$order->code}/guest-minors");

        $response->assertValidResponse(200);
        $response->assertJsonPath('data.reservations.0.minors', []);
        $this->assertNotNull($response->json('data.reservations.0.link'));
    }
}
