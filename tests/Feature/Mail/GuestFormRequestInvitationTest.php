<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Notifications\GuestFormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **EL CORREO DEL POST-FORM, CUANDO EL PRODUCTO TIENE INVITACIÓN** (T7·1,
 * `docs/specs/celebracion-e-invitacion.md` §4.9 y §10.14; `DECISIONES #714`).
 *
 * ❗❗ **Es el ÚNICO correo que lleva a ese formulario**, así que es el único sitio donde se puede
 * decir que la mitad del trabajo la hacen los padres. Pedirle al anfitrión que escriba veinte
 * nombres cuando puede repartir un enlace es construir la feature entera y no venderla — la misma
 * razón por la que D15 obligó a nombrar los extras en este mismo correo.
 *
 * Lo que estos casos garantizan:
 *
 *  · con invitación, la llamada es **compartir** y el enlace aterriza EN el bloque (`#gf-invite`);
 *  · **sin** invitación el correo no cambia ni una palabra — es el CONTROL, sin el cual la primera
 *    aserción pasaría aunque el correo dijera siempre lo mismo;
 *  · el ancla **no rompe la firma** —no es un parámetro, y un parámetro sí la invalidaría—;
 *  · el asunto y la línea de adelanto **no** tienen variante: el censo de la bandeja lee el grupo de
 *    una cadena literal y uno por variable dejaría este correo fuera de su guarda;
 *  · y las piezas nuevas existen **en los tres idiomas**, que es lo que `MailInboxLineTest` no puede
 *    comprobar por ellas (solo mira las de la bandeja).
 */
class GuestFormRequestInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_the_invitation_on_the_call_is_to_share_it(): void
    {
        [$item, $user] = $this->reservation(invitation: true);

        $mail = (new GuestFormRequest($item))->toMail($user);
        $html = $mail->render();

        $this->assertStringContainsString(__('emails.guest_form.action_invite'), $html);
        $this->assertStringContainsString(e(__('emails.guest_form.intro_invite', [
            'product' => 'Cumpleaños', 'code' => (string) $item->order?->code,
        ])), $html);
        // Y deja de pedir lo que pedía: la llamada vieja no puede quedarse debajo.
        $this->assertStringNotContainsString(__('emails.guest_form.action'), $html);
    }

    public function test_the_button_lands_on_the_invitation_block_and_the_signature_survives(): void
    {
        [$item, $user] = $this->reservation(invitation: true);

        $url = $this->actionUrl((new GuestFormRequest($item))->toMail($user));
        $plain = $item->guestFormSignedUrl();

        // ⚠️ El ancla va al FINAL y no toca la query: un `?algo=` pegado a una URL firmada la
        // invalidaría —la firma cubre la query—, y un `#fragmento` ni siquiera se envía al servidor.
        $this->assertSame($plain.'#gf-invite', $url);

        // El instrumento primero: que el enlace sin ancla efectivamente abre. Sin esto, la aserción
        // de arriba sería una comparación de cadenas que no prueba que el enlace sirva para nada.
        $this->get($plain)->assertOk();
    }

    public function test_without_the_invitation_the_email_does_not_change_a_word(): void
    {
        // EL CONTROL. Un pack idéntico con el interruptor apagado.
        [$item, $user] = $this->reservation(invitation: false);

        $html = (new GuestFormRequest($item))->toMail($user)->render();

        $this->assertStringContainsString(__('emails.guest_form.action'), $html);
        $this->assertStringNotContainsString(__('emails.guest_form.action_invite'), $html);
        $this->assertStringNotContainsString('#gf-invite', $html);
    }

    public function test_a_pack_without_a_name_column_is_not_an_invitation_pack(): void
    {
        // El interruptor encendido NO basta (`TicketType::offersGuestInvitation()`): sin columna de
        // nombre no hay con qué emparejar lo que conteste un padre, así que el correo no promete
        // algo que la pantalla no podrá dar.
        [$item, $user] = $this->reservation(invitation: true, nameColumn: false);

        $html = (new GuestFormRequest($item))->toMail($user)->render();

        $this->assertStringContainsString(__('emails.guest_form.action'), $html);
        $this->assertStringNotContainsString(__('emails.guest_form.action_invite'), $html);
    }

    public function test_the_inbox_line_has_no_variant(): void
    {
        [$con, $userCon] = $this->reservation(invitation: true);
        [$sin, $userSin] = $this->reservation(invitation: false);

        $mailCon = (new GuestFormRequest($con))->toMail($userCon);
        $mailSin = (new GuestFormRequest($sin))->toMail($userSin);

        // El mismo asunto y la misma chapa: lo que cambia es el cuerpo, no lo que se lee en la lista.
        // Si algún día se quiere cambiar, hay que darle su grupo — y entonces el censo tiene que verlo.
        // ⚠️ Sin el código del pedido, que es aleatorio por reserva y no es lo que se compara.
        $sinCodigo = static fn (?string $asunto, ?string $code): string => str_replace((string) $code, '', (string) $asunto);
        $this->assertSame(
            $sinCodigo($mailSin->subject, $sin->order?->code),
            $sinCodigo($mailCon->subject, $con->order?->code),
        );
        $this->assertStringContainsString(__('emails.guest_form.headline'), $mailCon->render());
        $this->assertStringContainsString(__('emails.guest_form.preheader'), $mailCon->render());
    }

    public function test_the_new_pieces_exist_in_the_three_languages(): void
    {
        foreach (['es', 'en', 'fr'] as $locale) {
            foreach (['intro_invite', 'body_invite', 'action_invite', 'outro_invite'] as $pieza) {
                // ⚠️⚠️ El TERCER parámetro es el que hace que esto mida algo: por defecto `Lang::has()`
                // cae al idioma de respaldo, así que una clave que faltara en francés pero estuviera
                // en inglés pasaría en verde y el correo saldría en el idioma equivocado.
                $this->assertTrue(
                    Lang::has("emails.guest_form.$pieza", $locale, false),
                    "falta `emails.guest_form.$pieza` en `$locale`",
                );
            }
        }
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: User} */
    private function reservation(bool $invitation, bool $nameColumn = true): array
    {
        RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], [
            'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);

        $zone = Zone::firstOrCreate(['slug' => 'cumpleanos'], [
            'name' => ['es' => 'Cumpleaños'], 'is_active' => true,
            'max_per_slot' => 3, 'max_guests_per_slot' => 40, 'prep_blocks_cupo' => false, 'position' => 1,
        ]);

        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => $invitation && $nameColumn,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Quién cumple']],
            ],
            'guest_fields' => $nameColumn
                ? [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]]
                : [['key' => 'age', 'type' => 'age', 'required' => true, 'label' => ['es' => 'Edad']]],
        ]);

        // ⚠️⚠️ **La combinación imposible se monta por el CONSTRUCTOR DE CONSULTAS, y es el escenario
        // real**: el guard de `saving()` impide guardarla por el modelo (lo comprueba su propio test),
        // así que una fila así solo puede venir de una importación o de un `update()` a mano — que es
        // exactamente contra lo que `offersGuestInvitation()` defiende. Con `TicketType::create()`
        // este caso no existiría: la excepción salta antes de llegar al correo.
        if ($invitation && ! $nameColumn) {
            TicketType::query()->whereKey($pack->getKey())->update(['guest_invitation' => true]);
            $pack->refresh();
        }

        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => Carbon::today()->addDays(10)->toDateString(),
            // Una franja por reserva y sin chocar con el único de `(zona, día, hora)`: un caso que
            // monta dos reservas —el del control— pedía dos veces las 15:00.
            'start_time' => sprintf('%02d:00:00', 9 + Slot::query()->count()),
            'end_time' => sprintf('%02d:00:00', 11 + Slot::query()->count()),
            'capacity' => 100, 'online_capacity' => 100,
        ]);

        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'total' => 8 * 1495, 'currency' => 'EUR',
        ]);
        $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => 8,
            'unit_price' => 1495, 'seats' => 8, 'event_data' => ['celebrant' => 'Lucía'],
            'guest_data' => [],
        ]);

        $item = $order->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();

        $this->assertSame($invitation && $nameColumn, $pack->offersGuestInvitation(), 'el fixture no monta lo que dice montar');
        $this->assertSame(0, PartyInvitation::query()->where('order_item_id', $item->getKey())->count(), 'el correo NO materializa la invitación');

        return [$item, $user];
    }

    /** La URL del botón de acción del correo, sin escarbar en el HTML. */
    private function actionUrl(MailMessage $mail): string
    {
        return (string) $mail->actionUrl;
    }
}
