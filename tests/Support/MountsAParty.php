<?php

namespace Tests\Support;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * **Una fiesta entera para los tests de la analítica de la fiesta** (`docs/specs/analitica-fiesta.md` §6): un pack
 * con formulario, invitación y justificante; un pedido pagado con su franja a `DAYS_BEFORE` días del parque; la
 * invitación materializada; y el texto del justificante publicado en modo interno.
 *
 * ⚠️ La fecha de la fiesta sale de `DisplayTime::today()` (el día del PARQUE), no de `now()`: cerca de medianoche
 * no son el mismo día, y `days_before` se asevera EXACTO.
 */
trait MountsAParty
{
    protected const DAYS_BEFORE = 12;

    /** @return array{order: Order, reservation: OrderItem, invitation: PartyInvitation, host: User, document: LegalDocumentVersion} */
    protected function mountParty(): array
    {
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::query()->updateOrCreate(['key' => 'business.name'], ['value' => 'Parque Verify']);
        Setting::flushMemo();

        /** @var LegalDocumentVersion $document */
        $document = app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención de responsabilidad', 'body' => [
                ['h' => 'Riesgo asumido', 'p' => 'Saltar en camas elásticas implica riesgos.'],
            ]],
        ])->first();

        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños Jump'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => true,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true,
                    'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
            ],
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id,
            'date' => DisplayTime::today()->addDays(self::DAYS_BEFORE)->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '19:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);

        $host = User::factory()->create(['name' => 'Marta Anfitriona', 'phone' => '600111222', 'email_verified_at' => now()]);
        $order = Order::create([
            'user_id' => $host->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $reservation = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 6, 'unit_price' => 500, 'seats' => 6,
            'event_data' => ['celebrant' => 'Lucía'],
        ]);
        $reservation = $reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation;

        $invitation = app(PartyInvitations::class)->forReservation($reservation);
        $invitation->forceFill(['honoree_age' => 8, 'host_line' => 'Te invita Marta'])->save();

        return [
            'order' => $order,
            'reservation' => $reservation,
            'invitation' => $invitation,
            'host' => $host,
            'document' => $document,
        ];
    }

    /** Un «sí» ya contestado desde la invitación. */
    protected function replyOf(PartyInvitation $invitation, OrderItem $reservation, string $child = 'Hugo Ruiz'): InvitationReply
    {
        return InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $reservation->getKey(),
            'attending' => true,
            'child_name' => $child,
            'child_key' => PersonNameKey::for($child),
        ]);
    }

    protected function signedAuthorizationStoreUrl(OrderItem $reservation): string
    {
        return URL::temporarySignedRoute('reservation.authorization.store', now()->addDays(14), ['reservation' => $reservation]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function authorizationPayload(LegalDocumentVersion $document, array $overrides = []): array
    {
        return array_merge([
            'document_id' => $document->getKey(),
            'accept_waiver' => '1',
            'minor_name' => 'Ana',
            'minor_surname' => 'Gómez Ruiz',
            'minor_born_on' => now()->subYears(8)->toDateString(),
            'guardian_name' => 'Marta',
            'guardian_surname' => 'Ruiz Díaz',
            'guardian_relationship' => 'mother',
            'guardian_email' => 'marta@example.com',
            'guardian_phone' => '600111222',
        ], $overrides);
    }
}
