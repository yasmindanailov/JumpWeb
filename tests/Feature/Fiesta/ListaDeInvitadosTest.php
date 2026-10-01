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
    }

    /**
     * F8 (`fiesta-sistema-nuevo.md` §4.14, `#753`): **una acción por tarea**. Enviar es UN botón, el mismo antes y después;
     * las cifras son un resumen (no filtran); el recordatorio sale junto a ellas solo con la invitación ENVIADA y alguien
     * de la lista sin contestar, y abre WhatsApp en una pestaña nueva; «Invitar a más», un solo rótulo.
     */
    /**
     * ❗❗ **LA LISTA DEL OWNER** (`[DECIDIDO owner]` `#805`, 29-09; sustituye en parte a `#753`): una acción por tarea para
     * la invitación —enviar, el MISMO botón antes y después—, y NADA de cifras ni de «Recordárselo»; todo el de la lista está
     * confirmado —el añadido a mano, sin chapa «Sin contestar»—; quien invita no ve la firma de los invitados (ni la leyenda
     * ni el «Firmada · Falta» de las filas); y el «No podemos», APARTE: en su bloque suave y fuera de `[data-filas]`.
     *
     * ⚠️ Los rótulos van escritos A MANO: una aserción con `__()` pasa también con la clave vacía (`#734`).
     */
    public function test_the_owners_list_all_confirmed_no_figures_no_foreign_signatures_and_the_noes_apart(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();
        $reservation->forceFill(['guest_data' => [['name' => 'Ana Soler'], ['name' => 'Iris Vela']]])->save();
        $html = fn (): string => $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        foreach (['antes de enviar' => $html(), 'enviada' => ($invitation->forceFill(['shared_at' => now()])->save() ? $html() : '')] as $cuando => $pagina) {
            // Un solo «Enviar» A LA VISTA (`#753`): el de la isla de enlace (`#814`) es una PLANTILLA que su JavaScript pone
            // solo con la zona 1 fuera de la vista, y solo mientras la invitación no ha salido.
            $visible = (string) preg_replace('#<template\b[^>]*>.*?</template>#s', '', $pagina);
            $this->assertSame(1, substr_count($visible, 'data-envio="whatsapp" data-envio-donde="invitation"'), "{$cuando}: enviar, un botón");
            $this->assertSame($cuando === 'antes de enviar' ? 1 : 0, substr_count($pagina, '<template data-isla-plantilla="enviar">'), "{$cuando}: la isla ofrece enviar SOLO antes de enviar");
            if ($cuando === 'antes de enviar') {
                $this->assertMatchesRegularExpression('#<template data-isla-plantilla="enviar">.*?<span data-isla-sub-texto>La invitación de Lucía</span>#s', $pagina, 'con la línea exacta: de quién es la invitación');
            }
            // ⚠️ El MARCADO se mira en el HTML y el TEXTO en lo que se lee (sin etiquetas): la pieza de la fila le pasa a su
            // JS TODOS sus rótulos en un atributo —«Sin contestar» incluido, porque es un port del diseño—, y ese JSON no lo
            // lee nadie. Medido al escribir este caso: la aguja casaba ahí con la página correcta.
            $texto = strip_tags($pagina);
            foreach (['pli-tally' => $pagina, 'data-cuenta=' => $pagina, 'data-recordatorio' => $pagina, 'fiesta-recordatorio' => $pagina,
                'pli-leyenda' => $pagina, 'Recordárselo' => $texto, 'sin contestar' => $texto, 'Sin contestar' => $texto,
                'Autorización:' => $texto] as $fuera => $en) {
                $donde = strpos($en, $fuera);
                $this->assertFalse($donde, "{$cuando}: «{$fuera}» no está en la lista del owner; aparece en: "
                    .($donde === false ? '' : substr($en, max(0, $donde - 120), 240)));
            }
        }

        // Los añadidos a mano, confirmados y SIN estado de firma: ni «Firmada» ni «Falta» en sus filas.
        $pagina = $html();
        // …y en la frase del número CUENTAN como confirmados: «Seréis 2: los 2 confirmados.», sin «que añadiste».
        $this->assertSame(1, preg_match('#<span data-numero-frase>([^<]*)</span>#', $pagina, $frase), 'la frase del número');
        $this->assertStringContainsString('los 2 confirmados', $frase[1], 'los añadidos a mano son confirmados');
        $this->assertStringNotContainsString('añadiste', $frase[1]);
        foreach (['g0', 'g1'] as $fila) {
            $this->assertStringNotContainsString('circle-dashed', $this->filaDe($pagina, $fila), "{$fila}: sin «Falta»");
            $this->assertStringNotContainsString('>Falta<', $this->filaDe($pagina, $fila), "{$fila}: sin «Falta»");
        }

        // El enlace de cada canal dice su canal.
        $this->assertStringContainsString(rawurlencode('?c=wa'), $pagina, 'el de WhatsApp');
        $this->assertStringContainsString('data-valor="'.e(route('invitation.show', ['token' => $invitation->token, 'c' => 'copia'])).'"', $pagina, 'el copiado');
        $this->assertStringContainsString('data-envio-url=', $pagina, 'a dónde avisan los botones');

        // «No podemos», APARTE: su bloque, con su nombre, y fuera de la lista (`[data-filas]`). CONTROL: antes, sin él.
        $this->assertStringNotContainsString('data-no-vienen', $pagina, 'CONTROL: sin un «no», no hay bloque');
        $this->replyOf($invitation, $reservation, 'Pablo Gil', false);
        $conNo = $html();
        $this->assertSame(1, preg_match('#<div class="pli-no" data-no-vienen>(.*?)</ul>\s*</div>#s', $conNo, $bloque), 'el bloque de los que no pueden venir');
        $this->assertStringContainsString('Pablo Gil', $bloque[1]);
        $this->assertStringContainsString('No viene', $bloque[1], 'su cabecera, suave');
        $this->assertStringContainsString('puedes bajar el número', $bloque[1], 'CONTROL: la nota del plazo está');
        $this->assertStringNotContainsString('..', strip_tags($bloque[1]), 'la fecha abreviada ya trae su punto: sin doble punto');
        $this->assertSame(1, preg_match('#<ul class="pli-ul" data-filas>(.*?)</ul>#s', $conNo, $lista));
        $this->assertStringNotContainsString('Pablo Gil', $lista[1], 'el «no» no está en la lista');
    }

    public function test_invite_more_only_while_the_number_can_still_grow(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation, 'host' => $host] = $this->mountParty();
        $invitation->forceFill(['shared_at' => now()])->save();
        $tope = (int) $reservation->ticketType->max_qty;
        // ⚠️ La zona 3 lleva sus TRES estados en el HTML (el JS enseña el que toca): se mira DENTRO del bloque de cada uno.
        // Medido: contar en la página entera daba 4 (dos bloques, y la marca sale `data-invitar-mas="data-invitar-mas"`).
        $bloque = function (string $estado) use ($host, $reservation): string {
            $html = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();
            $this->assertSame(1, preg_match('#data-numero-estado="'.$estado.'"(.*?)data-numero-estado=#s', $html, $m), "el bloque «{$estado}»");

            return $m[1];
        };

        // «Seréis N» por debajo del tope: se puede invitar a más, con el MISMO rótulo que con plazas libres.
        $reservation->forceFill(['quantity' => 2, 'seats' => 2, 'guest_data' => [['name' => 'Ana'], ['name' => 'Iris']]])->save();
        $listo = $bloque('listo');
        $this->assertStringContainsString('data-invitar-mas', $listo);
        $this->assertStringContainsString(e(__('fiesta.lista.numero.invitar')), $listo, 'un solo rótulo');
        $this->assertStringContainsString('data-invitar-mas', $bloque('libres'));

        // En el tope: invitar a quien no cabe es lo contrario de convertir.
        $llena = array_map(static fn (int $i): array => ['name' => 'Niño '.$i], range(1, $tope));
        $reservation->forceFill(['quantity' => $tope, 'seats' => $tope, 'guest_data' => $llena])->save();
        $this->assertStringNotContainsString('data-invitar-mas', $bloque('listo'), "con {$tope} de {$tope}, no");
        // CONTROL: el bloque de las plazas libres lo sigue teniendo (quitar a alguien deja una plaza que llenar).
        $this->assertStringContainsString('data-invitar-mas', $bloque('libres'));
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

    /**
     * El «no» empareja con su ficha por su clave de nombre, y sigue viajando escondido para que el guardado no la borre.
     * ⚠️ Desde `#805` la FIRMA de un invitado no se enseña a quien invita, aunque la haya (aquí, Ana): este caso asevera
     * que el justificante existe y aun así su fila no dice «Firmada».
     */
    public function test_the_no_pairs_by_name_key_and_the_host_does_not_see_the_guests_signatures(): void
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
        $this->assertSame(1, GuardianAuthorization::query()->where('order_item_id', $reservation->getKey())->count(), 'CONTROL: Ana tiene justificante');
        $this->assertStringNotContainsString('circle-check', $this->filaDe($html, 'g0'), 'quien invita no ve la firma de Ana (`#805`)');
        $this->assertStringNotContainsString('>Firmada<', $this->filaDe($html, 'g0'));
        // Un «no» se pinta apagado (el diseño: «No puede venir» y nada más), y su ficha sigue viajando escondida para que el
        // guardado no la borre.
        $this->assertStringContainsString(__('fiesta.fila.no'), $this->filaDe($html, 'g1'), 'Leo: «No puede venir»');
        $this->assertStringNotContainsString('circle-check', $this->filaDe($html, 'g1'));
        $this->assertMatchesRegularExpression('#data-fila="g1"[^>]*data-respuesta="no"#', $html, 'el «no» de Leo empareja con su ficha');
        $this->assertStringContainsString('type="hidden" name="guests[1][name]" value="Leo Sánchez"', $html);
        // Y va APARTE (`#805`): en el bloque de los que no pueden venir, fuera de `[data-filas]`, y su ficha viaja igual.
        $this->assertSame(1, preg_match('#data-no-vienen>(.*?)</ul>\s*</div>#s', $html, $aparte), 'el bloque de los que no vienen');
        $this->assertStringContainsString('data-fila="g1"', $aparte[1], 'el «no» emparejado va aparte');
        $this->assertSame(1, preg_match('#<ul class="pli-ul" data-filas>(.*?)</ul>#s', $html, $lista));
        $this->assertStringNotContainsString('data-fila="g1"', $lista[1], 'y no en la lista');
        $this->assertStringContainsString('data-fila="g0"', $lista[1], 'CONTROL: Ana sí está en la lista');
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
