<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AttachesPartyExtras;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * F5b y F5c de `specs/fiesta-sistema-nuevo.md` (§4.11, `[DECIDIDO owner]` `#749`): LA ZONA 4 DE LA LISTA como el mockup
 * (`PliZona4`, `PliAvisoTarta`, la línea de los padres de `PliZona3`) y «Guardado hoy a las…» (`PliZona5`), con el dato
 * del catálogo. Lo que se afirma es el MARCADO que funciona sin JavaScript (los radios de la tarta, los campos numéricos,
 * dónde va «¿Cuántos adultos se quedan?») y lo que el servidor decide (qué bloque, qué texto, cuándo el aviso), no la
 * piel. Lo que se repinta al teclear (la sugerencia de cada familia, «Añadir otra tarta») es `lista.js`: su lógica pura
 * en `logica.test.js` y su recorrido en la sonda `sonda-f5.mjs`.
 */
class ExtrasDeLaFiestaListaTest extends TestCase
{
    use AttachesPartyExtras;
    use MountsAParty;
    use RefreshDatabase;

    public function test_the_cake_is_a_question_with_its_photo_its_options_and_no_cake(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $tarta = $this->extra($this->tipo($r), 'La nuestra', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE, 'max_qty' => 3], ['serves' => 12, 'image' => 'productos/tarta.webp']);
        $traemos = $this->extra($this->tipo($r), 'Traemos la nuestra', 1000, ['postform_block' => ProductAddon::BLOCK_CAKE], ['features' => ['es' => ['Se cobra el cubierto']]]);

        $z4 = $this->zona4($this->pagina($r, $host));

        $this->assertStringContainsString('<legend class="pz-opciones__legend">¿La tarta?</legend>', $z4);
        foreach ([(string) $tarta->id, (string) $traemos->id, 'none'] as $valor) {
            $this->assertStringContainsString('name="cake" value="'.$valor.'"', $z4, 'una opción por tarta, y «Sin tarta»');
        }
        $this->assertStringContainsString('De 12 raciones', $z4);
        $this->assertStringContainsString('Se cobra el cubierto', $z4, 'sin «para cuántas», su primera línea');
        $this->assertStringContainsString('Sin tarta', $z4);
        $this->assertStringContainsString('/uploads/productos/tarta.webp', $z4, 'la foto de la tarta, grande');
        $this->assertStringContainsString('name="cake_quantity" value="1"', $z4);
        $this->assertStringNotContainsString('][product_id]" value="'.$tarta->id.'"', $z4, 'la tarta viaja como la pregunta, no como una tarjeta más');

        // Con más niños que raciones, la opción lo dice (sin tarta grande: «Añadir otra tarta» la pone `lista.js`).
        $r->forceFill(['quantity' => 14])->save();
        $this->assertStringContainsString('De 12 raciones: no llega para 14', $this->zona4($this->pagina($r, $host)));
    }

    public function test_the_saved_answer_comes_back_checked_with_its_quantity(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $tarta = $this->extra($this->tipo($r), 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE, 'max_qty' => 3], ['serves' => 12]);

        $this->guardar($r, $host, ['cake' => (string) $tarta->id, 'cake_quantity' => '2']);
        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertMatchesRegularExpression('#name="cake" value="'.$tarta->id.'"\s+checked#', $z4);
        $this->assertStringContainsString('name="cake_quantity" value="2"', $z4);
        $this->assertStringContainsString('Lo cambias hasta', $z4, 'elegida, la pista dice hasta cuándo se cambia');

        $this->guardar($r, $host, ['cake' => 'none']);
        $this->assertMatchesRegularExpression('#name="cake" value="none"\s+checked#', $this->zona4($this->pagina($r, $host)));
    }

    public function test_the_parents_block_groups_by_family_and_asks_how_many_adults_stay(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->conCamposGenerales($r);
        $cafe = $this->extra($this->tipo($r), 'Combo café', 3900, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6, 'family' => ['es' => 'Combos']]);
        $picoteo = $this->extra($this->tipo($r), 'Combo picoteo', 5900, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 10, 'family' => ['es' => 'Combos']]);
        $cubo = $this->extra($this->tipo($r), 'Cubo de 6', 1600, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6, 'family' => ['es' => 'Cubos de bebidas']]);

        $html = $this->pagina($r, $host);
        $z4 = $this->zona4($html);

        $this->assertStringContainsString('Para los padres, mientras saltan', $z4);
        $this->assertStringContainsString('¿Cuántos adultos se quedan?', $z4);
        $this->assertStringContainsString('name="general[adultos]"', $z4, 'la pregunta de los adultos ES el campo general de tipo `adults`');
        $this->assertSame(1, substr_count($html, 'name="general[adultos]"'), 'y sale de los campos generales: se pregunta una vez');
        $this->assertStringContainsString('name="general[obs]"', $html, 'los demás campos generales siguen en su sitio');
        $this->assertLessThan(strpos($z4, '<h4 class="pli-h4">Cubos de bebidas</h4>'), strpos($z4, '<h4 class="pli-h4">Combos</h4>'), 'una familia por `family`, en su orden');
        $this->assertStringContainsString('Para 6 adultos', $z4);
        $this->assertStringContainsString('Para 10 adultos', $z4);
        foreach ([$cafe, $picoteo, $cubo] as $a) {
            $this->assertStringContainsString('name="addons[', $z4);
            $this->assertStringContainsString('value="'.$a->id.'"', $z4);
        }
        $this->assertStringContainsString('data-serves="10"', $z4, 'lo que `lista.js` necesita para sugerir');

        // CONTROL: con lo de los padres CERRADO, «¿Cuántos adultos…?» vuelve a los campos generales (sin perderse).
        DB::table('product_addons')->whereIn('addon_id', [$cafe->id, $picoteo->id, $cubo->id])->update(['postform_cutoff_hours' => 24 * 30]);
        $html = $this->pagina($r, $host);
        $this->assertSame(1, substr_count($html, 'name="general[adultos]"'));
        $this->assertStringNotContainsString('name="general[adultos]"', $this->zona4($html));
    }

    public function test_the_cake_notice_shows_only_while_undecided_and_closing_soon(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $tarta = $this->extra($this->tipo($r), 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE], ['serves' => 12]);

        // CONTROL: la fiesta es dentro de 12 días; la tarta cierra 48 h antes, lejos: sin aviso.
        $this->assertStringNotContainsString('data-aviso-tarta', $this->pagina($r, $host));

        // Dentro de tres días, cierra MAÑANA: el aviso, arriba.
        $r->slot?->forceFill(['date' => DisplayTime::today()->addDays(3)->toDateString()])->save();
        $html = $this->pagina($r, $host);
        $this->assertStringContainsString('data-aviso-tarta', $html);
        $this->assertStringContainsString('¿La tarta? Se elige hasta mañana a las 17:00.', $html);
        // ⚠️ La cabecera también lleva `data-zona="1"`: el ancla es su cierre y la sección de la invitación.
        $this->assertGreaterThan(strpos($html, '</header>'), strpos($html, 'data-aviso-tarta'), 'bajo la cabecera…');
        $this->assertLessThan(strpos($html, 'id="gf-invite"'), strpos($html, 'data-aviso-tarta'), '…y antes de la invitación');

        // Decidida (también «Sin tarta»), se va: depende de lo GUARDADO.
        $this->guardar($r, $host, ['cake' => 'none']);
        $this->assertStringNotContainsString('data-aviso-tarta', $this->pagina($r, $host));
        $this->guardar($r, $host, ['cake' => (string) $tarta->id]);
        $this->assertStringNotContainsString('data-aviso-tarta', $this->pagina($r, $host));
    }

    public function test_zone_three_points_to_the_parents_while_nothing_is_saved_for_them(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $combo = $this->extra($this->tipo($r), 'Combo café', 3900, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6, 'family' => ['es' => 'Combos']]);
        $this->extra($this->tipo($r), 'Cubo de 6', 1600, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6, 'family' => ['es' => 'Cubos de bebidas']]);

        $html = $this->pagina($r, $host);
        $this->assertStringContainsString('data-padres-linea', $html);
        $this->assertStringContainsString('¿Algo para los padres mientras saltan?', $html);
        $this->assertStringContainsString('Ver combos y cubos de bebidas', $html, 'el enlace, con las familias del dato');

        $this->guardar($r, $host, ['addons' => [['product_id' => $combo->id, 'quantity' => 1]]]);
        $this->assertStringNotContainsString('data-padres-linea', $this->pagina($r, $host), 'con algo pedido para ellos, se va');
    }

    public function test_the_save_bar_says_when_the_host_saved(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Cubo', 1600);

        $this->assertStringContainsString('Nada que guardar todavía', $this->barra($this->pagina($r, $host)));

        $this->guardar($r, $host, []);
        $hora = now()->setTimezone(DisplayTime::timezone())->format('H:i');
        $barra = $this->barra($this->pagina($r, $host));
        $this->assertStringContainsString('data-estado="saved"', $barra);
        $this->assertStringContainsString('Guardado hoy a las '.$hora, $barra);

        // Lo que guarda el parque no es «Guardado» del titular: la hora no se mueve.
        $this->travel(2)->hours();
        $r->fresh(['ticketType'])?->submitGuestForm(null, null, 'panel', User::factory()->create());
        $this->assertStringContainsString('Guardado hoy a las '.$hora, $this->barra($this->pagina($r, $host)));
    }

    public function test_the_footer_says_until_when_each_open_block_can_be_changed(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE, 'postform_cutoff_hours' => 48]);
        $this->extra($this->tipo($r), 'Cubo de 6', 1600, ['postform_block' => ProductAddon::BLOCK_ADULTS, 'postform_cutoff_hours' => 0]);

        $dia = DisplayTime::dayInSentence(DisplayTime::today()->addDays(self::DAYS_BEFORE - 2));
        $this->assertStringContainsString(
            'Se pagan el día de la fiesta, en el parque. La tarta, hasta el '.$dia.'; lo de los padres, hasta el mismo día.',
            $this->zona4($this->pagina($r, $host)),
        );
    }

    public function test_a_card_without_a_photo_paints_no_photo_placeholder(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Piñata', 1500, [], ['serves' => 4]);

        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertStringContainsString('Para 4 personas', $z4, 'en la rejilla, «para cuántas» también se dice');
        $this->assertStringNotContainsString(__('fiesta.pieza.hueco_foto'), $z4, '«Hueco de foto» es un marcador del diseño, no para un cliente');

        // CONTROL: con foto, su foto.
        $this->extra($this->tipo($r), 'Photocall', 2000, [], ['image' => 'productos/photocall.webp']);
        $this->assertStringContainsString('/uploads/productos/photocall.webp', $this->zona4($this->pagina($r, $host)));
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    private function tipo(OrderItem $r): TicketType
    {
        $tipo = $r->fresh(['ticketType'])?->ticketType;
        $this->assertNotNull($tipo);

        return $tipo;
    }

    private function pagina(OrderItem $r, User $host): string
    {
        return (string) $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $r]))->assertOk()->getContent();
    }

    /** @param  array<string, mixed>  $datos */
    private function guardar(OrderItem $r, User $host, array $datos): void
    {
        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $r]), $datos)->assertRedirect();
    }

    private function zona4(string $html): string
    {
        $desde = strpos($html, 'data-zona="4"');
        $this->assertNotFalse($desde, 'sin zona 4');

        return substr($html, $desde, (int) strpos($html, '</section>', $desde) - $desde);
    }

    /** El estado y el texto de la barra: «saved · Guardado hoy a las 16:05» (acotado a sus marcas, no a una ventana de texto). */
    private function barra(string $html): string
    {
        $this->assertSame(1, preg_match('#data-barra data-estado="([a-z]+)"#', $html, $estado), 'sin barra');
        $this->assertSame(1, preg_match('#data-barra-estado>([^<]*)<#', $html, $texto));

        return 'data-estado="'.$estado[1].'" · '.$texto[1];
    }

    /** El pack con los dos campos generales del post-form de PlayJump: los adultos (de tipo `adults`) y las observaciones. */
    private function conCamposGenerales(OrderItem $r): void
    {
        $tipo = $this->tipo($r);
        $tipo->forceFill(['event_fields' => array_merge($tipo->event_fields ?? [], [
            ['key' => 'adultos', 'type' => TicketType::FIELD_TYPE_ADULTS, 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Adultos']],
            ['key' => 'obs', 'type' => TicketType::FIELD_TYPE_TEXTAREA, 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM, 'label' => ['es' => 'Observaciones']],
        ])])->save();
    }
}
