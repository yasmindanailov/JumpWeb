<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\PersonNameKey;
use App\Http\Cuenta\AntesDeVenir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * **«Antes de venir» de una reserva** (T5c de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #776`):
 * `GET /api/v1/me/reservations/{id}/before-visit`, lo que Mi cuenta pinta en su bloque y la isla de las páginas en su
 * punto. Lo que estos casos garantizan:
 *
 *  · las tareas de una fiesta, en su orden y con el ÚNICO plazo de la fiesta (`#766`): «hasta el jueves 24» solo mientras
 *    sea verdad;
 *  · la invitación sigue PENDIENTE hasta que los «sí» llegan al número de invitados (`#776`, el owner: como el mockup), y
 *    se comparte con el MISMO mensaje que la lista de invitados —sin nombre de quien cumple, se ofrece crearla—;
 *  · los extras en plazo, cada uno con el suyo, agrupados por el día en que cierran;
 *  · las autorizaciones sin denominador inventado (`waiver-por-reserva.md` §4.10);
 *  · y una reserva ajena es un 404; una sin pagar, cancelada o celebrada, una lista vacía.
 */
class MeReservationBeforeVisitTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    private const ROOT = '/api/v1/me/reservations/';

    public function test_a_party_lists_the_form_and_the_invitation_with_the_single_deadline_of_766(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $dia = DisplayTime::dayInSentence(app(GuestCountPolicy::class)->deadlineFor($r));
        $lista = route('reservation.guests', ['reservation' => $r]);

        $tareas = $this->tareas($host, $r);

        $this->assertSame(['guest_form', 'invitation'], array_column($tareas, 'kind'));
        $this->assertSame([
            'kind' => 'guest_form', 'type' => 'task', 'done' => false,
            'title' => 'Formulario de invitados', 'note' => "Hasta el {$dia}",
            'text' => "Formulario de invitados, hasta el {$dia}: quién viene, edades y alergias.",
            'due' => null, 'action' => ['label' => 'Rellenar', 'url' => $lista, 'via' => 'link'],
        ], $tareas[0]);
        $this->assertSame('0 de 6 confirmados', $tareas[1]['note']);
        $this->assertSame("Para el {$dia}", $tareas[1]['due'], 'la invitación cierra con el MISMO plazo (`#766`)');
        $this->assertSame('whatsapp', $tareas[1]['action']['via']);
        $this->assertStringStartsWith('https://wa.me/?text=', $tareas[1]['action']['url']);

        // La forma es la del CONTRATO, clave a clave y en su orden (`BeforeVisitTask`, 1.37.0).
        $esquema = Yaml::parseFile(base_path('openapi/v1.yaml'))['components']['schemas']['BeforeVisitTask'];
        foreach ($tareas as $tarea) {
            $this->assertSame($esquema['required'], array_keys($tarea));
            $this->assertSame($esquema['properties']['action']['required'], array_keys($tarea['action']));
        }
    }

    public function test_the_whatsapp_message_is_exactly_the_one_of_the_guest_list(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();

        $url = $this->tareas($host, $r)[1]['action']['url'];
        $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $r]))->assertOk()->getContent();

        // La paridad con la pantalla del SPA (`Fiesta\ListaDeInvitados::invitacion`): si una cambia su mensaje, este caso
        // lo dice antes que un padre reciba dos invitaciones distintas para la misma fiesta.
        $this->assertStringContainsString('href="'.e($url).'"', $html);
        $this->assertStringContainsString(rawurlencode('Lucía'), $url);
    }

    public function test_the_invitation_is_pending_until_the_yes_replies_reach_the_guests(): void
    {
        ['reservation' => $r, 'host' => $host, 'invitation' => $inv] = $this->mountParty();
        foreach (['Hugo Ruiz', 'Ana Gil', 'Pau Soler', 'Iris Mas', 'Leo Vidal'] as $nino) {
            $this->replyOf($inv, $r, $nino);
        }
        // Un «no» no confirma a nadie.
        InvitationReply::query()->create(['party_invitation_id' => $inv->getKey(), 'order_item_id' => $r->getKey(),
            'attending' => false, 'child_name' => 'Noa Pons', 'child_key' => PersonNameKey::for('Noa Pons')]);

        $invitacion = $this->tareas($host, $r)[1];
        $this->assertFalse($invitacion['done']);
        $this->assertSame('5 de 6 confirmados', $invitacion['note']);

        $this->replyOf($inv, $r, 'Mar Riera');

        $invitacion = $this->tareas($host, $r)[1];
        $this->assertTrue($invitacion['done'], 'con seis «sí» para seis invitados, la tarea está hecha');
        $this->assertSame('Invitación: los padres confirman y firman ellos. 6 de 6 confirmados.', $invitacion['text']);
    }

    public function test_with_the_honoree_row_the_one_who_turns_years_is_not_waited_for(): void
    {
        ['reservation' => $r, 'host' => $host, 'invitation' => $inv] = $this->mountParty();
        // El sello de `#747`: la ficha 0 es la de quien cumple, que no contesta a su propia invitación.
        $r->forceFill(['honoree_row' => true])->save();
        $this->assertTrue($r->fresh()?->hasHonoreeRow(), 'el instrumento: la reserva lleva la ficha de quien cumple');

        $this->assertSame('0 de 5 confirmados', $this->tareas($host, $r)[1]['note']);

        foreach (['Hugo Ruiz', 'Ana Gil', 'Pau Soler', 'Iris Mas', 'Leo Vidal'] as $nino) {
            $this->replyOf($inv, $r, $nino);
        }
        $this->assertTrue($this->tareas($host, $r)[1]['done'], 'cinco «sí» para cinco invitados: hecha, aunque la reserva sea de seis');
    }

    public function test_without_the_honoree_name_the_invitation_offers_to_create_it(): void
    {
        ['reservation' => $r, 'host' => $host, 'invitation' => $inv] = $this->mountParty();
        $inv->forceFill(['honoree_name' => ''])->save();

        $this->assertSame([
            'label' => 'Crear la invitación',
            'url' => route('reservation.guests', ['reservation' => $r]).'#gf-invite',
            'via' => 'link',
        ], $this->tareas($host, $r)[1]['action']);
    }

    public function test_a_complete_form_is_done_and_offers_to_see_or_change_it(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $r->forceFill(['guest_data' => array_fill(0, 6, $this->fichaCompleta($r))])->save();
        $this->assertSame('ok', $r->fresh()?->guestFormStatus(), 'el instrumento: el formulario está completo');

        $formulario = $this->tareas($host, $r)[0];

        $this->assertTrue($formulario['done']);
        $this->assertSame('Ver o cambiar', $formulario['action']['label']);
    }

    public function test_past_the_deadline_the_form_stays_without_a_date_and_the_invitation_goes(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        Carbon::setTestNow(app(GuestCountPolicy::class)->deadlineFor($r)?->copy()->addMinute());

        $tareas = $this->tareas($host, $r);

        $this->assertSame(['guest_form'], array_column($tareas, 'kind'), 'con las respuestas cerradas, la invitación ya no es una tarea');
        $this->assertNull($tareas[0]['note']);
        $this->assertSame('Formulario de invitados: quién viene, edades y alergias.', $tareas[0]['text']);
    }

    public function test_the_extras_in_time_are_grouped_by_the_day_they_close(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        // En el orden del catálogo, el que cierra el mismo día va PRIMERO: la frase tiene que ordenarlos por cierre.
        $this->extra($r, 'Cubo', 2);
        $this->extra($r, 'Tarta', 72);
        $this->extra($r, 'Combo', 0);
        // Fuera de plazo (cierra antes de hoy): no se ofrece.
        $this->extra($r, 'Piñata', 24 * 30);

        $extras = collect($this->tareas($host, $r))->firstWhere('kind', 'extras');
        $tarta = DisplayTime::dayInSentence(Carbon::parse($r->slot?->date?->format('Y-m-d').' 17:00:00', DisplayTime::timezone())->subHours(72));

        $this->assertSame('optional', $extras['type']);
        $this->assertSame("Y si quieres: Tarta, hasta el {$tarta}; Cubo y Combo, hasta el mismo día. Se pagan el día de la fiesta.", $extras['text']);
        $this->assertSame(['label' => 'Añadir extras', 'url' => route('reservation.guests', ['reservation' => $r]), 'via' => 'link'], $extras['action']);
    }

    public function test_with_more_than_three_extras_the_sentence_says_how_many_and_when_the_first_closes(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        foreach (['Tarta', 'Combo', 'Cubo', 'Tapas'] as $nombre) {
            $this->extra($r, $nombre, 48);
        }
        $cierra = fn (int $horas): string => DisplayTime::dayInSentence(Carbon::parse($r->slot?->date?->format('Y-m-d').' 17:00:00', DisplayTime::timezone())->subHours($horas));
        $texto = fn (): string => collect($this->tareas($host, $r))->firstWhere('kind', 'extras')['text'];

        $this->assertSame("Y si quieres: 4 extras para la fiesta, que se añaden hasta el {$cierra(48)}. Se pagan el día de la fiesta.", $texto());

        // Con plazos distintos, el primero que cierra.
        $this->extra($r, 'Piñata', 96);
        $this->assertSame("Y si quieres: 5 extras para la fiesta, cada uno con su plazo; el primero cierra el {$cierra(96)}. Se pagan el día de la fiesta.", $texto());
    }

    public function test_the_authorizations_never_invent_a_denominator(): void
    {
        ['reservation' => $r, 'host' => $host, 'invitation' => $inv, 'document' => $doc] = $this->mountParty();
        TicketType::query()->whereKey($r->ticket_type_id)->update(['guardian_authorization' => TicketType::GUARDIAN_OPTIONAL]);

        $this->assertSame('Autorizaciones: aún no hay ninguna firmada. Se firman con la invitación o en la puerta.', $this->autorizaciones($host, $r));

        // Dos firmas y ninguna respuesta: no hay sobre cuántos decirlo.
        foreach (['Ana', 'Leo'] as $menor) {
            $this->post($this->signedAuthorizationStoreUrl($r), $this->authorizationPayload($doc, ['minor_name' => $menor]))->assertSessionHasNoErrors();
        }
        $this->assertSame('Autorizaciones: 2 firmadas. Las que falten se firman en la puerta.', $this->autorizaciones($host, $r));

        // Con tres «sí», sí se sabe: los que vienen.
        foreach (['Hugo Ruiz', 'Ana Gil', 'Pau Soler'] as $nino) {
            $this->replyOf($inv, $r, $nino);
        }
        $this->assertSame('Autorizaciones: 2 de 3 firmadas. Las que falten se firman en la puerta.', $this->autorizaciones($host, $r));
    }

    public function test_another_customers_reservation_is_a_404_like_one_that_does_not_exist(): void
    {
        ['reservation' => $r] = $this->mountParty();

        $this->actingAs(User::factory()->create())->getJson(self::ROOT.$r->getKey().'/before-visit')->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson(self::ROOT.'999999/before-visit')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::ROOT.$r->getKey().'/before-visit')->assertUnauthorized();
    }

    public function test_a_cancelled_or_celebrated_party_has_nothing_pending(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();

        $r->forceFill(['cancelled_at' => now()])->save();
        $this->assertSame([], $this->tareas($host, $r));

        $r->forceFill(['cancelled_at' => null])->save();
        Carbon::setTestNow(Carbon::parse($r->slot?->date?->format('Y-m-d').' 19:30:00', DisplayTime::timezone()));
        $this->assertTrue($r->fresh()?->isFinishedInPractice(), 'el instrumento: la fiesta ya se celebró');
        $this->assertSame([], $this->tareas($host, $r));
    }

    public function test_the_texts_are_in_the_language_of_the_request(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();

        $en = $this->actingAs($host)->withHeader('Accept-Language', 'en')->getJson(self::ROOT.$r->getKey().'/before-visit')->json('data.tasks');

        $this->assertSame(['Guest form', 'Invitation'], array_column($en, 'title'));
        $this->assertSame('0 of 6 confirmed', $en[1]['note']);
    }

    // ─── La isla de las páginas ──────────────────────────────────────────────────────

    public function test_the_page_isla_gets_the_first_pending_task_of_the_next_reservation_already_written(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $dia = DisplayTime::dayInSentence(app(GuestCountPolicy::class)->deadlineFor($r));
        $lista = route('reservation.guests', ['reservation' => $r]);

        $this->assertSame([
            'pending' => true,
            'pendingText' => 'Siguiente: Formulario de invitados',
            'task' => ['text' => "Rellena el formulario de invitados, hasta el {$dia}.", 'action' => ['label' => 'Rellenar el formulario', 'href' => $lista]],
            'bookingToday' => null,
        ], app(AntesDeVenir::class)->paraLaIsla($host));

        // Con el formulario hecho, la siguiente es la invitación, y en la isla va a su sitio en la lista: su enlace (la
        // credencial con la que se contesta) no se escribe en el HTML de ninguna página.
        $r->forceFill(['guest_data' => array_fill(0, 6, $this->fichaCompleta($r))])->save();
        $isla = app(AntesDeVenir::class)->paraLaIsla($host);
        $this->assertSame('Siguiente: Invitación', $isla['pendingText']);
        $this->assertSame(['text' => 'Comparte la invitación: 0 de 6 confirmados.', 'action' => ['label' => 'Compartir la invitación', 'href' => $lista.'#gf-invite']], $isla['task']);
    }

    public function test_with_every_task_done_what_is_optional_does_not_light_the_dot(): void
    {
        ['reservation' => $r, 'host' => $host, 'invitation' => $inv] = $this->mountParty();
        $r->forceFill(['guest_data' => array_fill(0, 6, $this->fichaCompleta($r))])->save();
        foreach (['Hugo Ruiz', 'Ana Gil', 'Pau Soler', 'Iris Mas', 'Leo Vidal', 'Mar Riera'] as $nino) {
            $this->replyOf($inv, $r, $nino);
        }
        $this->extra($r, 'Tarta', 72);
        TicketType::query()->whereKey($r->ticket_type_id)->update(['guardian_authorization' => TicketType::GUARDIAN_OPTIONAL]);

        // El instrumento: quedan los extras (opcionales) y las autorizaciones (un estado), y nada que HACER.
        $this->assertSame(['guest_form', 'invitation', 'extras', 'authorizations'], array_column($this->tareas($host, $r), 'kind'));

        $this->assertSame(['pending' => false, 'pendingText' => null, 'task' => null, 'bookingToday' => null], app(AntesDeVenir::class)->paraLaIsla($host));
    }

    public function test_on_the_day_the_page_isla_says_today_at_its_time(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        Carbon::setTestNow(Carbon::parse($r->slot?->date?->format('Y-m-d').' 10:00:00', DisplayTime::timezone()));

        $this->assertSame(['text' => 'Hoy a las 17:00'], app(AntesDeVenir::class)->paraLaIsla($host)['bookingToday']);

        // Terminada la visita ya no es la próxima: ni «hoy», ni tarea.
        Carbon::setTestNow(Carbon::parse($r->slot?->date?->format('Y-m-d').' 19:30:00', DisplayTime::timezone()));
        $this->assertSame(['pending' => false, 'pendingText' => null, 'task' => null, 'bookingToday' => null], app(AntesDeVenir::class)->paraLaIsla($host));
    }

    public function test_without_a_session_the_page_isla_knows_nothing_of_an_account(): void
    {
        $this->assertNull(app(AntesDeVenir::class)->paraLaIsla(null));
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    private function tareas(User $host, OrderItem $r): array
    {
        return $this->pedir($host, $r)->assertOk()->assertJsonPath('data.reservation_id', (int) $r->getKey())->json('data.tasks');
    }

    private function pedir(User $host, OrderItem $r): TestResponse
    {
        return $this->actingAs($host)->getJson(self::ROOT.$r->getKey().'/before-visit');
    }

    private function autorizaciones(User $host, OrderItem $r): string
    {
        $t = collect($this->tareas($host, $r))->firstWhere('kind', 'authorizations');
        $this->assertSame('status', $t['type']);

        return $t['text'];
    }

    /** Una ficha con todas las columnas obligatorias del pack rellenas. */
    private function fichaCompleta(OrderItem $r): array
    {
        $ficha = [];
        foreach ($r->ticketType?->guestFields() ?? [] as $campo) {
            $ficha[$campo['key']] = ($campo['type'] ?? 'text') === 'age' ? '7' : 'Ana';
        }

        return $ficha;
    }

    /** Un extra de venta posterior del pack, con su precio y su plazo en horas antes del inicio. */
    private function extra(OrderItem $r, string $nombre, int $horas): void
    {
        $normal = RateType::query()->firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $extra = TicketType::create([
            'name' => ['es' => $nombre], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 20 + $horas,
        ]);
        Price::create(['priceable_type' => $extra->getMorphClass(), 'priceable_id' => $extra->id, 'rate_type_id' => $normal->id, 'amount_cents' => 1200, 'currency' => 'EUR']);
        $r->ticketType?->configurableAddons()->attach($extra->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'stage' => ProductAddon::STAGE_POSTFORM, 'postform_cutoff_hours' => $horas, 'max_qty' => 10,
        ]);
        $r->ticketType?->refresh();
    }
}
