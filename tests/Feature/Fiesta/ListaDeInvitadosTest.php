<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * LA LISTA DE INVITADOS del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.1 y §4.2, `#743`): la página lee SOLO el
 * modelo de página, y el modelo dice lo que el formulario viejo decía en Blade.
 *
 * ⚠️ Se afirma el MARCADO que la página necesita para funcionar sin JavaScript (los `name` posicionales, `adopt[]`, el
 * testigo, el número) y lo que el modelo calcula (propuesta, firma, «no viene», la primera pantalla), no subcadenas de
 * texto que pasarían con cualquier implementación (`#553`).
 */
class ListaDeInvitadosTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_the_page_is_the_positional_form_with_one_row_per_place(): void
    {
        ['reservation' => $reservation, 'host' => $host] = $this->mountParty();

        $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        $this->assertStringContainsString('data-lista', $html, 'la página es la del sistema nuevo');
        $this->assertSame(6, substr_count($html, 'data-fila="g'), 'una fila por plaza de la reserva (6)');
        $this->assertStringContainsString('name="guests[0][name]"', $html, 'el formulario sigue siendo posicional');
        $this->assertStringContainsString('name="guests[5][allergy]"', $html);
        $this->assertStringContainsString('name="guest_count"', $html, 'el número viaja por su campo de siempre');
        $this->assertStringContainsString('name="expected_version"', $html, 'el testigo de la reserva');
        $this->assertStringContainsString('Los invitados de Lucía', $html);
        // Las columnas del pack que la ficha no dibuja viajan escondidas, con su valor: un guardado no las borra.
        $this->assertStringContainsString('type="hidden" name="guests[0][notes]"', $html);
        $this->assertStringContainsString('type="hidden" name="guests[0][special_menu]"', $html);
        $this->assertStringNotContainsString('gf-form', $html, 'la vista vieja ya no se pinta');
        // «Escribir el recordatorio» envía SU formulario (`form=`): un solo `type`, y es `submit` (la pieza `enlace`
        // ponía `type="button"` delante y el navegador se quedaba con ése: no enviaba nunca, T1b).
        $this->assertSame(1, preg_match('#<button[^>]*data-recordatorio-escribir[^>]*>#', $html, $boton), 'el recordatorio es un botón');
        $this->assertSame(1, substr_count($boton[0], 'type='), $boton[0]);
        $this->assertStringContainsString('type="submit"', $boton[0]);
        $this->assertStringContainsString('form="fiesta-recordatorio"', $boton[0]);
    }

    public function test_a_pending_reply_is_proposed_on_its_row_with_the_badge_and_the_adopt_id(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();
        $reply = $this->replyOf($invitation, $reservation, 'Hugo Ruiz');

        $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        $this->assertStringContainsString('name="adopt[]" value="'.$reply->getKey().'"', $html, 'la marca de adopción, FUERA de la fila');
        $this->assertStringContainsString('value="Hugo Ruiz"', $html, 'el nombre que escribió el padre, propuesto en la ficha');
        $this->assertStringContainsString('data-repasar', $html, 'la respuesta va en «Por repasar»');
        $this->assertStringContainsString('form="fiesta-descartar" name="reply" value="'.$reply->getKey().'"', $html, '«No lo apuntes» va por su formulario');
    }

    public function test_the_model_says_who_signed_by_name_key_and_who_said_no(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host, 'document' => $document] = $this->mountParty();
        $reservation->forceFill(['guest_data' => [['name' => 'Ana Gómez Ruiz', 'age' => '8'], ['name' => 'Leo Sánchez']]])->save();
        GuardianAuthorization::query()->forceCreate([
            'order_item_id' => $reservation->getKey(),
            'minor_name' => 'Ana', 'minor_surname' => 'Gómez Ruiz', 'minor_key' => GuardianAuthorization::keyFor('Ana', 'Gómez Ruiz'),
            'minor_born_on' => now()->subYears(8)->toDateString(),
            'guardian_name' => 'Marta', 'guardian_surname' => 'Ruiz', 'guardian_relationship' => 'mother',
            'guardian_phone' => '600111222',
        ]);
        $this->assertNotNull($document);
        InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(), 'order_item_id' => $reservation->getKey(),
            'attending' => false, 'child_name' => 'Leo Sánchez', 'child_key' => PersonNameKey::for('Leo Sánchez'),
        ]);

        $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#data-fila="g0"[^>]*data-respuesta=""#', $html);
        $this->assertStringContainsString('circle-check', $this->filaDe($html, 'g0'), 'Ana tiene justificante: «Firmada»');
        // Un «no» se pinta apagado y sin su autorización (el diseño: «No puede venir» y nada más), y su ficha sigue
        // viajando escondida para que el guardado no la borre.
        $this->assertStringContainsString(__('fiesta.fila.no'), $this->filaDe($html, 'g1'), 'Leo: «No puede venir»');
        $this->assertStringNotContainsString('circle-check', $this->filaDe($html, 'g1'));
        $this->assertMatchesRegularExpression('#data-fila="g1"[^>]*data-respuesta="no"#', $html, 'el «no» de Leo empareja con su ficha');
        $this->assertStringContainsString('type="hidden" name="guests[1][name]" value="Leo Sánchez"', $html);
    }

    public function test_without_the_honoree_name_the_page_is_only_the_first_question(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();
        $invitation->forceFill(['honoree_name' => ''])->save();

        $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        $this->assertStringContainsString('data-primero', $html);
        $this->assertStringNotContainsString('data-lista', $html, 'sin nombre no hay lista que enseñar');
        $this->assertStringContainsString('name="honoree_name"', $html);
    }

    public function test_saving_the_list_also_personalizes_the_invitation(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();

        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'expected_version' => PostFormAddons::versionOf($reservation),
            'guests' => [['name' => 'Ana', 'age' => '8']],
            'theme' => PartyInvitation::THEME_SERENO,
            'honoree_name' => 'Lucía',
            'host_line' => 'Te invita Marta y Pedro',
            'show_host_phone' => '1',
        ])->assertRedirect();

        $fresh = $invitation->fresh();
        $this->assertSame(PartyInvitation::THEME_SERENO, $fresh->theme, 'el tema entra por el Guardar de la lista');
        $this->assertSame('Te invita Marta y Pedro', $fresh->host_line);
        $this->assertTrue((bool) $fresh->show_host_phone);
        $this->assertSame('Ana', $reservation->fresh()->guest_data[0]['name'] ?? null, 'y la lista se guardó igual');
    }

    public function test_the_old_post_without_invitation_keys_touches_nothing_of_the_invitation(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();

        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'expected_version' => PostFormAddons::versionOf($reservation),
            'guests' => [['name' => 'Ana']],
        ])->assertRedirect();

        $this->assertSame($invitation->theme, $invitation->fresh()->theme);
        $this->assertSame('Te invita Marta', $invitation->fresh()->host_line);
    }

    private function filaDe(string $html, string $id): string
    {
        $inicio = strpos($html, 'data-fila="'.$id.'"');
        $this->assertNotFalse($inicio, 'existe la fila '.$id);
        $fin = strpos($html, '</li>', $inicio);

        return substr($html, $inicio, $fin - $inicio);
    }
}
