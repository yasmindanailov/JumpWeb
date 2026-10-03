<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_each_cake_is_a_card_and_no_cake_is_a_checkbox(): void
    {
        // K2 de §4.17 (`[DECIDIDO owner]` `#807`): varias tartas a la vez. Una tarjeta por tarta del panel —su foto, sus
        // raciones, su precio, su tope— con su cantidad en `addons[i]`; «Sin tarta», una casilla con su 0 oculto DELANTE.
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $tarta = $this->extra($this->tipo($r), 'La nuestra', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE, 'max_qty' => 3], ['serves' => 12, 'image' => 'productos/tarta.webp']);
        $traemos = $this->extra($this->tipo($r), 'Traemos la nuestra', 1000, ['postform_block' => ProductAddon::BLOCK_CAKE], ['features' => ['es' => ['Se cobra el cubierto']]]);

        $z4 = $this->zona4($this->pagina($r, $host));

        $this->assertStringContainsString('<h4 class="pli-h4">¿La tarta?</h4>', $z4);
        foreach ([$tarta, $traemos] as $t) {
            $this->assertMatchesRegularExpression('#name="addons\[\d+\]\[product_id\]" value="'.$t->id.'"#', $z4, 'cada tarta, una tarjeta con su cantidad');
        }
        $this->assertStringContainsString('De 12 raciones', $z4);
        $this->assertStringContainsString('Se cobra el cubierto', $z4, 'sin raciones, su primera línea');
        $this->assertStringContainsString('/uploads/productos/tarta.webp', $z4, 'la foto de cada tarta, en su tarjeta');
        $this->assertMatchesRegularExpression('#data-serves="12"\s+data-max="3"\s+data-nombre="La nuestra"#', $z4, 'lo que `lista.js` necesita para las raciones');
        $this->assertMatchesRegularExpression('#data-serves="0"\s+data-max="10"\s+data-nombre="Traemos la nuestra"#', $z4, 'sin raciones: la cuenta no se sabe');
        $this->assertStringNotContainsString('name="cake"', $z4, 'ya no es una pregunta de una respuesta');
        $this->assertStringNotContainsString('cake_quantity', $z4);
        // «Sin tarta»: el 0 oculto va DELANTE de la casilla (marcada, manda el 1; desmarcada, el 0).
        $oculto = strpos($z4, '<input type="hidden" name="cake_declined" value="0">');
        $this->assertNotFalse($oculto);
        $this->assertSame(1, preg_match('#id="pli-sin-tarta" type="checkbox"\s+name="cake_declined"\s+value="1"(?!\s+checked)#', $z4, $m, PREG_OFFSET_CAPTURE), 'sin decidir, desmarcada');
        $this->assertLessThan($m[0][1], $oculto);
        // La tarta es lo primero de «Para los niños».
        $this->assertLessThan(strpos($z4, 'data-tarta '), strpos($z4, '<h3 class="pli-h3">Para los niños</h3>'));
    }

    public function test_two_cakes_at_once_come_back_with_their_quantities_and_closed_only_the_ordered(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $nata = $this->extra($this->tipo($r), 'Tarta de nata', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE, 'max_qty' => 3], ['serves' => 12]);
        $chocolate = $this->extra($this->tipo($r), 'Tarta de chocolate', 2800, ['postform_block' => ProductAddon::BLOCK_CAKE, 'max_qty' => 2], ['serves' => 12]);
        $fila = static fn (TicketType $t, int $n): array => ['product_id' => $t->id, 'quantity' => $n];

        $this->guardar($r, $host, ['addons' => [$fila($nata, 2), $fila($chocolate, 1)], 'cake_declined' => '0']);
        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertMatchesRegularExpression('#id="x-'.$nata->id.'" name="addons\[\d+\]\[quantity\]" value="2"#', $z4, 'las dos, cada una con lo suyo');
        $this->assertMatchesRegularExpression('#id="x-'.$chocolate->id.'" name="addons\[\d+\]\[quantity\]" value="1"#', $z4);
        $this->assertStringContainsString('50,00 € en total', $z4);
        $this->assertStringContainsString('28,00 € en total', $z4);
        $this->assertStringNotContainsString('Lo cambias hasta', $z4, 'pedida, la tarjeta no repite el plazo: es el de la lista (`#912`)');

        // «Sin tarta» (con las tartas a 0, lo que hace el JS): vuelve marcada.
        $this->guardar($r, $host, ['addons' => [$fila($nata, 0), $fila($chocolate, 0)], 'cake_declined' => '1']);
        $this->assertMatchesRegularExpression('#id="pli-sin-tarta" type="checkbox"\s+name="cake_declined"\s+value="1"\s+checked#', $this->zona4($this->pagina($r, $host)));

        // Cerrado el plazo: solo las PEDIDAS, cerradas y con lo pedido; ni casilla ni campos que se envíen para cambiarlas.
        $this->guardar($r, $host, ['addons' => [$fila($nata, 2)], 'cake_declined' => '0']);
        $this->plazoDeLaLista(24 * 30);
        $cerrada = $this->zona4($this->pagina($r, $host));
        $this->assertStringContainsString('Tarta de nata', $cerrada);
        $this->assertStringNotContainsString('Tarta de chocolate', $cerrada, 'una tarta que ya no se puede pedir no es una opción');
        $this->assertStringNotContainsString('name="cake_declined"', $cerrada);
        $this->assertStringNotContainsString('id="x-'.$nata->id.'"', $cerrada, 'cerrada, su cantidad no se cambia (viaja oculta, tal cual)');
        $this->assertStringNotContainsString('pasó', $cerrada, 'que la lista cerró lo dice su cabecera, no la tarta (`#912`)');
        $this->assertStringNotContainsString('pli-tarta-fija', $cerrada, 'con una pedida, no se dice «Sin tarta»');

        // Cerrada y sin nada pedido: «Sin tarta», sin «pasó» (lo dice la cabecera).
        $this->plazoDeLaLista(24);
        $this->guardar($r, $host, ['addons' => [$fila($nata, 0)], 'cake_declined' => '1']);
        $this->plazoDeLaLista(24 * 30);
        $sinNada = $this->zona4($this->pagina($r, $host));
        $this->assertStringContainsString('<p class="pli-tarta-fija">Sin tarta</p>', $sinNada);
        $this->assertStringNotContainsString('pasó', $sinNada);
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

        $this->assertStringContainsString('<h3 class="pli-h3">Para los adultos</h3>', $z4);
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

        // CONTROL: con lo de los padres CERRADO (la lista, `#912`), «¿Cuántos adultos…?» vuelve a los campos generales (sin perderse).
        $this->plazoDeLaLista(24 * 30);
        $html = $this->pagina($r, $host);
        $this->assertSame(1, substr_count($html, 'name="general[adultos]"'));
        $this->assertStringNotContainsString('name="general[adultos]"', $this->zona4($html));
    }

    /**
     * `[DECIDIDO owner]` `#912`, P1·b: la tarta NO tiene aviso ni texto de plazo propio, ni cerrando mañana y sin decidir (el caso
     * en el que el `PliAvisoTarta` del diseño salía arriba y la barra decía «La tarta se guarda hasta…»). La hora, una vez, en la
     * cabecera. Hasta P1·b, este mismo caso aseveraba el aviso en ese escenario, y pasaba: con el aviso, esto es rojo.
     */
    public function test_the_cake_has_no_notice_nor_deadline_of_its_own_even_closing_tomorrow(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE], ['serves' => 12]);

        // Dentro de dos días, la lista cierra MAÑANA a las 17:00, con la tarta sin decidir.
        $r->slot?->forceFill(['date' => DisplayTime::today()->addDays(2)->toDateString()])->save();
        $html = $this->pagina($r, $host);

        $this->assertStringContainsString('data-tarta', $html, 'el instrumento: la tarta está en la página');
        $this->assertStringNotContainsString('data-aviso-tarta', $html);
        $this->assertStringNotContainsString('data-guardar-texto', $html, 'ni el texto de la tarta para la barra');
        $this->assertStringContainsString('Puedes cambiar la lista hasta mañana a las 17:00.', $html);
        $this->assertSame(1, substr_count($html, 'mañana a las 17:00'), 'la hora, una vez: en la cabecera');
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
        // ⚠️ A mediodía del PARQUE: con el reloj de verdad, de 22:00 a medianoche las dos horas de abajo caían en «ayer»
        //    (visto el 26-09 a las 22:03).
        $this->travelTo(DisplayTime::today()->setTime(12, 0)->shiftTimezone(DisplayTime::timezone()));
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Cubo', 1600);

        $antes = $this->pagina($r, $host);
        $this->assertStringContainsString('Nada que guardar todavía', $this->barra($antes));
        $this->assertStringNotContainsString('data-aviso-sin-js', $antes, 'sin guardar, ningún aviso de guardado');
        // El Guardar vive en la isla de enlace (`#814`): su cara de SERVIDOR es el botón de enviar de la lista (sin JavaScript
        // guarda igual), la isla no está recién guardada y la página deja su hueco al final.
        $this->assertStringContainsString('<div data-isla-cara="barra"><button type="submit" form="fiesta-form" class="fi-isla-barra"', $antes);
        $this->assertStringContainsString('data-recien="0"', $antes);
        $this->assertStringContainsString('data-isla-hueco', $antes);

        $this->guardar($r, $host, []);
        $hora = now()->setTimezone(DisplayTime::timezone())->format('H:i');
        $html = $this->pagina($r, $host);
        $this->assertStringContainsString('Guardado hoy a las '.$hora, $this->barra($html));
        // Y al volver del Guardar, la isla lo confirma un momento con el MISMO texto (L2 de la isla de enlace, `#814`).
        $this->assertStringContainsString('data-recien="1" data-guardado="Guardado hoy a las '.$hora.'"', $html);
        // …y el aviso de arriba, el de siempre, queda SOLO para sin JavaScript (con él lo dice la isla: regla 3).
        $this->assertMatchesRegularExpression('/<aside role="status" [^>]*data-aviso-sin-js="1"[^>]*>.*?Formulario guardado/s', $html);

        // Lo que guarda el parque no es «Guardado» del titular: la hora no se mueve.
        $this->travel(2)->hours();
        $r->fresh(['ticketType'])?->submitGuestForm(null, null, 'panel', User::factory()->create());
        $this->assertStringContainsString('Guardado hoy a las '.$hora, $this->barra($this->pagina($r, $host)));
    }

    /** El pie dice CÓMO se pagan y ya no hasta cuándo (`#912`): el plazo es el de la lista y lo dice la cabecera, una vez. */
    public function test_the_footer_says_how_they_are_paid_and_no_deadline(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE]);
        $this->extra($this->tipo($r), 'Cubo de 6', 1600, ['postform_block' => ProductAddon::BLOCK_ADULTS]);

        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertStringContainsString('Se pagan el día de la fiesta, en el parque.', $z4);
        $this->assertStringNotContainsString('La tarta, hasta', $z4);
        $this->assertStringNotContainsString('lo de los padres, hasta', $z4);
    }

    public function test_a_card_without_a_photo_paints_no_photo_placeholder(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Piñata', 1500, [], ['serves' => 4]);

        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertStringContainsString('Para 4 niños', $z4, 'en «Para los niños», «para cuántos» se cuenta en niños (K1)');
        $this->assertStringNotContainsString(__('fiesta.pieza.hueco_foto'), $z4, '«Hueco de foto» es un marcador del diseño, no para un cliente');

        // CONTROL: con foto, su foto.
        $this->extra($this->tipo($r), 'Photocall', 2000, [], ['image' => 'productos/photocall.webp']);
        $this->assertStringContainsString('/uploads/productos/photocall.webp', $this->zona4($this->pagina($r, $host)));
    }

    public function test_the_zone_goes_in_two_blocks_kids_first_and_an_empty_block_is_not_painted(): void
    {
        // K1 de §4.17 (`[DECIDIDO owner]` `#806`/`#807`): «Para los niños» (la tarta y lo que no dice bloque) y «Para los
        // adultos» (lo de los padres), en ese orden.
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Tarta', 2500, ['postform_block' => ProductAddon::BLOCK_CAKE], ['serves' => 12]);
        $calcetines = $this->extra($this->tipo($r), 'Calcetines', 200, ['max_qty' => 20], ['serves' => 1]);
        $combo = $this->extra($this->tipo($r), 'Combo café', 3900, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6, 'family' => ['es' => 'Combos']]);

        $z4 = $this->zona4($this->pagina($r, $host));
        $ninos = strpos($z4, '<h3 class="pli-h3">Para los niños</h3>');
        $adultos = strpos($z4, '<h3 class="pli-h3">Para los adultos</h3>');
        $this->assertNotFalse($ninos);
        $this->assertNotFalse($adultos);
        $this->assertLessThan($adultos, $ninos, 'los niños, primero');
        $this->assertGreaterThan($ninos, strpos($z4, 'data-tarta '), 'la tarta es de los niños…');
        $this->assertLessThan($adultos, strpos($z4, 'data-tarta '));
        $this->assertGreaterThan($ninos, strpos($z4, 'value="'.$calcetines->id.'"'), '…y lo que no dice bloque también');
        $this->assertLessThan($adultos, strpos($z4, 'value="'.$calcetines->id.'"'));
        $this->assertGreaterThan($adultos, strpos($z4, 'value="'.$combo->id.'"'), 'lo de los padres, en los adultos');

        // CONTROL: con solo lo de los padres, no hay bloque de los niños (ni su título); y al revés.
        ['reservation' => $soloPadres, 'host' => $h2] = $this->mountParty();
        $this->extra($this->tipo($soloPadres), 'Cubo de 6', 1600, ['postform_block' => ProductAddon::BLOCK_ADULTS], ['serves' => 6]);
        $z4 = $this->zona4($this->pagina($soloPadres, $h2));
        $this->assertStringNotContainsString('data-ninos', $z4);
        $this->assertStringNotContainsString('Para los niños', $z4);
        $this->assertStringContainsString('data-padres', $z4);

        ['reservation' => $soloNinos, 'host' => $h3] = $this->mountParty();
        $this->extra($this->tipo($soloNinos), 'Calcetines', 200, ['max_qty' => 20], ['serves' => 1]);
        $z4 = $this->zona4($this->pagina($soloNinos, $h3));
        $this->assertStringContainsString('data-ninos', $z4);
        $this->assertStringNotContainsString('data-padres', $z4);
        $this->assertStringNotContainsString('Para los adultos', $z4);
    }

    public function test_each_loose_kids_extra_is_its_own_group_counted_in_children(): void
    {
        // «Uno para cada niño» (K1): lo pinta `lista.js` con los niños de la fiesta; el servidor da el grupo, su chapa y lo que
        // la sugerencia necesita. Dos «para 1» sueltos NO se cubren el uno al otro: cada uno, su grupo (y su sugerencia).
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $r->forceFill(['quantity' => 14])->save();
        $calcetines = $this->extra($this->tipo($r), 'Calcetines', 200, ['max_qty' => 20], ['serves' => 1]);
        $this->extra($this->tipo($r), 'Cono de chuches', 150, ['max_qty' => 20], ['serves' => 1]);
        $this->extra($this->tipo($r), 'Bolsa pequeña', 100, [], ['serves' => 1, 'family' => ['es' => 'Bolsas']]);
        $this->extra($this->tipo($r), 'Bolsa grande', 180, [], ['serves' => 2, 'family' => ['es' => 'Bolsas']]);
        $this->extra($this->tipo($r), 'Piñata', 1500, [], ['serves' => 4]);

        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertStringContainsString('data-ninos data-sois="14"', $z4, 'la cuenta de los niños, para sin JavaScript en el número');
        $this->assertSame(4, substr_count($z4, 'data-familia>'), 'tres sueltos, cada uno solo, y una familia junta');
        $this->assertSame(1, substr_count($z4, '<h4 class="pli-h4">Bolsas</h4>'), 'la familia lleva su título; los sueltos, no');
        $this->assertSame(3, substr_count($z4, 'Para 1 niño<'), 'la chapa, en niños (calcetines, cono y la bolsa pequeña)');
        $this->assertStringContainsString('Para 2 niños', $z4);
        $this->assertMatchesRegularExpression('#data-serves="1"\s+data-max="20"\s+data-nombre="Calcetines"#', $z4, 'lo que `lista.js` necesita');
        $this->assertStringContainsString('value="'.$calcetines->id.'"', $z4);
        // Los dos sueltos «para 1», «Uno para cada niño» (el `Tag` de la calculadora, con la cifra); la familia, la sugerencia
        // de siempre. Los dos, escondidos: los pinta el JS con lo que se teclea.
        $this->assertSame(2, substr_count($z4, 'data-uno hidden'));
        $this->assertSame(2, preg_match_all('#data-uno-boton(="data-uno-boton")? aria-pressed="false">Uno para cada niño<span class="pz-etiqueta__cuenta">14</span>#', $z4));
        $this->assertSame(2, substr_count($z4, 'data-familia-sug hidden'), 'la familia (dos variantes) y la piñata «para 4» llevan la sugerencia de siempre');

        // CONTROL: cerrada la lista (`#912`: los dos cierran a la vez), «Uno para cada niño» no sale (no hay nada que poner).
        $this->plazoDeLaLista(24 * 30);
        $this->assertSame(0, substr_count($this->zona4($this->pagina($r, $host)), 'data-uno hidden'));
    }

    /**
     * P2 de §4.20 (`[DECIDIDO owner]` `#913`): en la rejilla de dos, los sueltos van de dos en dos entre los grupos con título, y
     * el que se queda SOLO en su fila va a lo ancho (`pli-fam--ancha`), sin hueco al lado. Calcetines y cono hacen pareja; la
     * familia ocupa su fila; la piñata, sola detrás, a lo ancho. De CONTROL, una pareja sola no lleva la marca.
     */
    public function test_a_loose_extra_left_alone_in_its_row_takes_the_whole_width(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Calcetines', 200, ['max_qty' => 20], ['serves' => 1]);
        $this->extra($this->tipo($r), 'Cono de chuches', 150, ['max_qty' => 20], ['serves' => 1]);
        $this->extra($this->tipo($r), 'Bolsa pequeña', 100, [], ['serves' => 1, 'family' => ['es' => 'Bolsas']]);
        $this->extra($this->tipo($r), 'Piñata', 1500, [], ['serves' => 4]);

        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertSame(1, substr_count($z4, 'pli-fam--ancha'), 'solo la que se queda sola en su fila');
        $this->assertStringContainsString('data-nombre="Piñata"', (string) substr($z4, (int) strpos($z4, 'pli-fam--ancha')), 'y es la piñata, detrás de la familia');

        // Tres sueltos seguidos: la pareja, y el TERCERO a lo ancho (no el primero).
        ['reservation' => $tres, 'host' => $h3] = $this->mountParty();
        $this->extra($this->tipo($tres), 'Calcetines', 200, ['max_qty' => 20], ['serves' => 1]);
        $this->extra($this->tipo($tres), 'Cono de chuches', 150, ['max_qty' => 20], ['serves' => 1]);
        $this->extra($this->tipo($tres), 'Piñata', 1500, [], ['serves' => 4]);
        $z4 = $this->zona4($this->pagina($tres, $h3));
        $this->assertSame(1, substr_count($z4, 'pli-fam--ancha'));
        $this->assertStringNotContainsString('data-nombre="Calcetines"', (string) substr($z4, (int) strpos($z4, 'pli-fam--ancha')), 'el último de la racha, no el primero');

        // CONTROL: calcetines y cono, una pareja, sin la marca.
        ['reservation' => $pareja, 'host' => $h2] = $this->mountParty();
        $this->extra($this->tipo($pareja), 'Calcetines', 200, ['max_qty' => 20], ['serves' => 1]);
        $this->extra($this->tipo($pareja), 'Cono de chuches', 150, ['max_qty' => 20], ['serves' => 1]);
        $this->assertStringNotContainsString('pli-fam--ancha', $this->zona4($this->pagina($pareja, $h2)));
    }

    /**
     * ❗ P1 de §4.20 (`[DECIDIDO owner]` `#912`: «1 plazo solamente… el plazo es por lista»). Cada complemento se cierra con
     * la LISTA (`packs.guest_count_cutoff_hours`) diga lo que diga su enganche —la tarta con 48 h y el cono con 0 h, a la
     * vez—, y el plazo se dice UNA vez, en la cabecera: ninguna tarjeta ni el pie lo repiten. De CONTROL, al mover el ajuste
     * de la lista se mueven los dos.
     */
    public function test_every_extra_closes_with_the_list_and_the_deadline_is_said_once(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $tarta = $this->extra($this->tipo($r), 'Tarta', 2500, ['postform_cutoff_hours' => 48]);
        $cono = $this->extra($this->tipo($r), 'Cono de chuches', 150, ['postform_cutoff_hours' => 0]);
        $r = $r->fresh(['slot', 'ticketType']) ?? $r;
        $pivote = fn (TicketType $a): ProductAddon => ProductAddon::query()->where('product_id', $r->ticket_type_id)->where('addon_id', $a->id)->sole();
        $lista = app(GuestCountPolicy::class)->deadlineFor($r);
        $this->assertNotNull($lista);
        foreach ([$tarta, $cono] as $addon) {
            $this->assertEquals($lista, PostFormAddons::deadlineFor($r, $pivote($addon)), "«{$addon->tr('name')}» cierra con su lista");
        }

        $html = $this->pagina($r, $host);
        $this->assertSame(1, substr_count($html, 'data-plazo-lista'), 'el plazo, una vez');
        $this->assertStringContainsString('Puedes cambiar la lista hasta', $html);
        $this->assertStringContainsString('a las 17:00', $html, 'la cabecera dice la hora del plazo');
        $this->assertStringNotContainsString('a las 17:00', $this->zona4($html), 'ninguna tarjeta ni el pie la repiten');

        // CONTROL: el ajuste de la lista mueve el plazo de los dos complementos a la vez.
        Setting::query()->updateOrCreate(['key' => GuestCountPolicy::SETTING_CUTOFF_HOURS], ['value' => '30', 'group' => 'packs']);
        Setting::flushMemo();
        foreach ([$tarta, $cono] as $addon) {
            $this->assertEquals($lista->copy()->subHours(6), PostFormAddons::deadlineFor($r, $pivote($addon)));
        }
    }

    /** Cerrada la lista, la cabecera lo dice y las tarjetas callan (ningún aviso por tarjeta); de CONTROL, un minuto antes, abierta. */
    public function test_once_the_list_closes_the_header_says_so_and_the_cards_keep_quiet(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->extra($this->tipo($r), 'Cono de chuches', 150, ['postform_cutoff_hours' => 0]);
        $cierre = app(GuestCountPolicy::class)->deadlineFor($r->fresh(['slot']) ?? $r);
        $this->assertNotNull($cierre);

        $this->travelTo($cierre->copy()->addMinute());
        $html = $this->pagina($r, $host);
        $this->assertStringContainsString('La lista se cerró', $html);
        $this->assertStringContainsString('pli-plazo-lista--cerrada', $html);
        $this->assertSame(1, preg_match('#<article[^>]*data-nombre="Cono de chuches".*?</article>#s', $this->zona4($html), $tarjeta), 'la tarjeta sigue a la vista');
        $this->assertStringNotContainsString('<aside', $tarjeta[0], 'sin aviso en la tarjeta: lo dice la cabecera');

        // CONTROL: un minuto antes, abierta (el cono con 0 h no la alarga).
        $this->travelTo($cierre->copy()->subMinute());
        $this->assertStringContainsString('Puedes cambiar la lista hasta', $this->pagina($r, $host));
    }

    // ── `#914` (P3·3 de §4.21): un GRUPO DE OPCIONES es UNA pregunta de una respuesta ─────────────────────────────────────

    public function test_a_choice_group_is_one_question_with_one_answer_and_says_what_is_missing(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        [$sandwich, $pizza] = $this->merienda($r, required: true);

        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertSame(1, preg_match('#<div class="pli-pregunta" data-pregunta="merienda"[^>]*>(.*?)</fieldset>#s', $z4, $pregunta), 'la pregunta');
        $this->assertStringContainsString('¿Qué merienda?', $pregunta[1]);
        $this->assertSame(2, substr_count($pregunta[1], 'name="choices[merienda]"'), 'una respuesta entre dos opciones');
        $this->assertStringContainsString('Falta elegir', $pregunta[1]);
        $this->assertStringContainsString('Uno para cada niño (6).', $pregunta[1]);
        $this->assertStringContainsString('Incluido', $pregunta[1], 'no «0,00 €» ni su tarifa');
        $this->assertStringNotContainsString('No, gracias', $pregunta[1], 'hay que elegir: sin «ninguna»');
        $this->assertStringNotContainsString('value="'.$sandwich->id.'" checked', $pregunta[1], 'sin marcada de serie');
        foreach ([$sandwich, $pizza] as $opcion) {
            $this->assertDoesNotMatchRegularExpression('#name="addons\[\d+\]\[product_id\]" value="'.$opcion->id.'"#', $z4, 'no se pinta también como tarjeta');
        }

        // Se elige desde la página: la línea, una para cada niño; y ya no falta nada.
        $this->guardar($r, $host, ['choices' => ['merienda' => (string) $pizza->id]]);
        $this->assertSame(6, $this->cantidades($r)[$pizza->id] ?? null);
        $z4 = $this->zona4($this->pagina($r, $host));
        $this->assertMatchesRegularExpression('#value="'.$pizza->id.'" checked#', $z4);
        $this->assertStringNotContainsString('Falta elegir', $z4);

        // Y cambiar de opción es la otra respuesta: la de antes se va.
        $this->guardar($r, $host, ['choices' => ['merienda' => (string) $sandwich->id]]);
        $this->assertSame(6, $this->cantidades($r)[$sandwich->id] ?? null);
        $this->assertSame(0, $this->cantidades($r)[$pizza->id] ?? null);
    }

    public function test_an_optional_group_offers_no_thanks_marked_by_default(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->merienda($r, required: false);

        $z4 = $this->zona4($this->pagina($r, $host));

        $this->assertMatchesRegularExpression('#value="none" checked#', $z4, '«No, gracias», la de serie');
        $this->assertStringContainsString('No, gracias', $z4);
        $this->assertStringNotContainsString('Falta elegir', $z4, 'no hay que elegir');
    }

    public function test_closed_without_a_choice_the_question_says_the_park_decides(): void
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $this->merienda($r, required: true);
        $this->plazoDeLaLista(24 * 30);

        $z4 = $this->zona4($this->pagina($r, $host));

        $this->assertStringContainsString('Sin elegir: lo decide el parque.', $z4);
        $this->assertStringNotContainsString('Falta elegir', $z4, 'cerrada, ya no se pide');
        $this->assertSame(2, preg_match_all('#name="choices\[merienda\]"[^>]* disabled#', $z4), 'los radios, deshabilitados: no se envían');
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * La merienda como GRUPO DE OPCIONES (`#914`): «¿Qué merienda?», Sándwich y Pizza, incluidas y una por niño.
     *
     * ⚠️ El grupo ya estaba al VENDERSE la fiesta: un grupo creado después no se le pide (`appliesToSaleAt`, `#914`), y
     * crearlo aquí, tras el pedido de `mountParty()`, con la hora del reloj, lo dejaba fuera cuando el segundo cambiaba entre
     * los dos (sin la zona 4; rojo en el gate del 03-10, bajo carga; reproducido con `travel(1)->seconds()` en medio).
     *
     * @return array{0: TicketType, 1: TicketType}
     */
    private function merienda(OrderItem $r, bool $required): array
    {
        $tipo = $this->tipo($r);
        $vendida = $r->order?->created_at;
        $this->assertNotNull($vendida);
        AddonChoiceGroup::create([
            'product_id' => $tipo->id, 'key' => 'merienda', 'title' => ['es' => '¿Qué merienda?'], 'is_required' => $required,
            'created_at' => $vendida->copy()->subMinute(),
        ]);
        $opcion = ['choice_group' => 'merienda', 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_included' => true];

        return [
            $this->extra($tipo, 'Sándwich', 250, $opcion, ['family' => ['es' => 'Merienda']]),
            $this->extra($tipo, 'Pizza', 250, $opcion, ['family' => ['es' => 'Merienda']]),
        ];
    }

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

    /**
     * La LÍNEA de lo guardado, al pie de la zona 5 (desde la L2 de la isla de enlace, `#814`, el Guardar vive en la isla):
     * «Guardado hoy a las 16:05» o «Nada que guardar todavía», acotada a su marca, no a una ventana de texto.
     */
    private function barra(string $html): string
    {
        $this->assertSame(1, preg_match('#<p class="pli-guardado" data-guardado-linea>(.*?)</p>#s', $html, $linea), 'sin la línea de lo guardado');

        return trim(strip_tags($linea[1]));
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
