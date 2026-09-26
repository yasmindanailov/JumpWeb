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
        // ⚠️ `g\d`: desde F4 la página lleva además la ficha PLANTILLA (`data-fila="g__I__"`, dentro de un `<template>`).
        $this->assertSame(6, preg_match_all('#data-fila="g\d#', $html), 'una fila por plaza de la reserva (6)');
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
            'family_words' => 'Traed ganas de saltar',
            'gift_hints' => 'Le encantan los libros de animales',
            'show_host_phone' => '1',
        ])->assertRedirect();

        $fresh = $invitation->fresh();
        $this->assertSame(PartyInvitation::THEME_SERENO, $fresh->theme, 'el tema entra por el Guardar de la lista');
        $this->assertSame('Te invita Marta y Pedro', $fresh->host_line);
        $this->assertSame('Traed ganas de saltar', $fresh->family_words, 'las palabras de la familia (F1a)');
        $this->assertSame('Le encantan los libros de animales', $fresh->gift_hints, 'y las pistas para el regalo');
        $this->assertTrue((bool) $fresh->show_host_phone);
        $this->assertSame('Ana', $reservation->fresh()->guest_data[0]['name'] ?? null, 'y la lista se guardó igual');

        // Y la página las pinta: en Personalizar (con su tope) y en la vista previa de la tarjeta.
        $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();
        $this->assertStringContainsString('name="family_words"', $html);
        $this->assertStringContainsString('name="gift_hints"', $html);
        $this->assertStringContainsString('maxlength="'.PartyInvitation::FAMILY_WORDS_MAX.'"', $html);
        $this->assertStringContainsString('Traed ganas de saltar</blockquote>', $html, 'la burbuja de la tarjeta');
        $this->assertStringContainsString(__('fiesta.invitacion.gifts').': Le encantan los libros de animales', $html, 'la línea del regalo');
    }

    /**
     * **La vista previa de «Personalizar» es EN VIVO** (F2): la tarjeta lleva sus marcas para que `lista.js` la reescriba
     * al teclear, y una plantilla por tema con la tarjeta entera. Lo que hoy no se ve —la burbuja sin palabras, la
     * línea del regalo sin pistas, «Llamar» sin marcar— va ya en la página, OCULTO, para aparecer al teclear.
     */
    public function test_the_preview_is_live_with_a_template_per_theme_and_what_may_appear_hidden(): void
    {
        ['reservation' => $reservation, 'host' => $host] = $this->mountParty();

        $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<div class="pli-inv-vista">(.*?)</div>\s*<div class="pli-inv-acc">#s', $html, $vista), 'el bloque de la vista previa');
        $this->assertMatchesRegularExpression('#^\s*<article data-inv-vivo data-inv-tema="confeti" data-resto-con="[^"]*:age[^"]*" data-resto-sin="[^"]+"#', $vista[1], 'la tarjeta visible es la viva, con los dos titulares');
        foreach (PartyInvitation::THEMES as $tema) {
            $this->assertSame(1, preg_match('#<template data-inv-plantilla="'.$tema.'"><article data-inv-vivo data-inv-tema="'.$tema.'"#', $vista[1]), "la plantilla del tema {$tema}");
        }
        // Sin palabras ni pistas (la invitación recién montada): la burbuja y la línea del regalo, ocultas y listas.
        $this->assertStringContainsString('<span data-inv-con-palabras hidden ', $vista[1]);
        $this->assertStringContainsString('<p data-inv-pistas-linea hidden ', $vista[1]);
        // Quien invita sin palabras: el pie en su forma corta, visible; la larga, oculta.
        $this->assertStringContainsString('<figcaption data-inv-pie="sin">', $vista[1]);
        $this->assertStringContainsString('<figcaption data-inv-pie="con" hidden ', $vista[1]);
        // «Llamar», oculto hasta marcar la casilla, con el teléfono de la cuenta.
        $this->assertStringContainsString('<span data-inv-llamar hidden ', $vista[1]);
        // Y las miniaturas del tema llevan la chapa de la edad con su marca.
        $this->assertMatchesRegularExpression('#<div data-inv-vivo style="[^"]*padding-bottom: 22px;#', $html, 'las miniaturas del selector, vivas');
        $this->assertStringContainsString('data-inv-edad', $html);
    }

    public function test_words_or_hints_with_a_link_are_rejected_and_the_rest_is_saved(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();
        $invitation->forceFill(['family_words' => 'Traed ganas de saltar'])->save();

        // Texto libre publicado (§7.2·R9): un enlace en las palabras se RECHAZA (se queda lo de antes) y se dice; las
        // pistas, limpias, entran igual.
        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'expected_version' => PostFormAddons::versionOf($reservation),
            'guests' => [['name' => 'Ana', 'age' => '8']],
            'family_words' => 'Paga el regalo en https://regalos.example/x',
            'gift_hints' => 'Le gustan los dinosaurios',
        ])->assertRedirect()->assertSessionHas('status', 'invitation-text-rejected');

        $fresh = $invitation->fresh();
        $this->assertSame('Traed ganas de saltar', $fresh->family_words, 'el enlace no se publica: se queda lo de antes');
        $this->assertSame('Le gustan los dinosaurios', $fresh->gift_hints);
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
