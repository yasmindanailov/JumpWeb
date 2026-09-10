<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Notifications\MixedPartySurchargeChanged;
use App\Notifications\OrderItemRefunded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA FORMA del correo del suplemento de fiesta mixta (`DECISIONES #507`).
 *
 * Es **el único correo del producto cuyo trabajo entero es decir UN NÚMERO**, y el artboard
 * `Correos PJP` lo marcó como el único RECHAZADO que quedaba de su inventario de 23: *«nueve
 * frases para un solo importe; es el correo más difícil de leer del producto»*. Medido antes de
 * la tanda: **13 frases y 150 palabras**, con los TRES datos del resguardo repetidos debajo y la
 * cifra saliendo como un párrafo más, del mismo cuerpo y del mismo color que los otros doce.
 *
 * ❗❗❗ **LO QUE ESTA GUARDA VIGILA ES LA JERARQUÍA, NO LA LONGITUD.** El correo no se acortó
 * mucho —el LIBRO se queda entero, que es `[DECIDIDO owner]` en `#503`— y contar caracteres no
 * diría si se lee mejor. Lo que cambió es **qué se mira primero**: la cifra está en la caja de
 * tinte del molde y por encima del libro.
 *
 * ⚠️ **Las SIETE frases del desenlace no entran aquí y no se tocan**: están razonadas en el código
 * —fundirlas dejaría la retirada diciendo «ahora es 0,00 €»— y `cumple-mixto.md` §24.5 las cita
 * como el patrón de referencia para separar la línea del IMPORTE de la del CANAL. Lo que se movió
 * es dónde se pintan.
 */
class MixedPartyMailShapeTest extends TestCase
{
    use RefreshDatabase;

    private OrderItem $item;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['locale' => 'es']);
        $zone = Zone::create(['name' => ['es' => 'Jump'], 'slug' => 'jump', 'is_active' => true]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'zone_id' => $zone->id, 'type' => 'pack',
            'is_sellable' => true, 'duration_min' => 120, 'seats_per_unit' => 1,
            'guest_age_family' => 'cumple', 'guest_age_min' => 7, 'guest_age_max' => 99,
        ]);
        // ⚠️ Sin tarifas ni precios a propósito: este correo NO tarifica nada — recibe el neto viejo
        // y el nuevo ya calculados y sólo los formatea. Sembrar el catálogo aquí ataría el caso a
        // una maquinaria que no ejercita.
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addDays(10)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'capacity' => 30, 'online_capacity' => 30,
        ]);
        $order = Order::create([
            'user_id' => $this->customer->id, 'code' => 'R-MIXTA1',
            'status' => Order::STATUS_PENDING, 'subtotal' => 7500, 'total' => 7500, 'currency' => 'EUR',
        ]);
        $this->item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $pack->id, 'slot_id' => $slot->id,
            'quantity' => 10, 'unit_price' => 7500, 'seats' => 10,
        ]);
        $this->item = $this->item->fresh(['ticketType', 'slot', 'order']);
    }

    private function render(int $old, int $new): string
    {
        return (string) (new MixedPartySurchargeChanged($this->item, $old, $new))
            ->toMail($this->customer)->render();
    }

    private function mail(int $old, int $new)
    {
        return (new MixedPartySurchargeChanged($this->item, $old, $new))->toMail($this->customer);
    }

    /**
     * ❗❗❗ **LA CIFRA VA EN EL AVISO Y POR ENCIMA DEL LIBRO**, que es todo el arreglo.
     *
     * ⚠️⚠️ Y el orden **no lo decide el orden de las llamadas**: `notifications::email` pinta TODAS
     * las `introLines` juntas y el aviso DESPUÉS, así que un `->notice()` escrito antes de tres
     * `->line()` acaba el último. Pasó al construir esto: la cifra quedó debajo del libro y de
     * «puedes seguir editando». *Se ve renderizando, no leyendo* — por eso este caso mide
     * POSICIONES en el HTML y no el orden en que se llama a nada.
     */
    public function test_the_amount_is_read_before_the_book(): void
    {
        $html = $this->render(0, 400);

        $aviso = strpos($html, 'class="notice');
        $libro = strpos($html, 'data-book');
        $cabecera = strpos($html, 'class="hero');

        $this->assertNotFalse($cabecera, 'CONTROL: sin cabecera este caso no mide una jerarquía');
        $this->assertNotFalse($libro, 'CONTROL: sin libro no hay nada por encima de lo que estar');
        $this->assertNotFalse($aviso, 'la cifra no llega al aviso');

        $this->assertLessThan($aviso, $cabecera, 'la cabecera abre el correo');
        $this->assertLessThan($libro, $aviso, 'la cifra tiene que leerse ANTES que el libro del pedido');
    }

    /**
     * ❗❗ **EL LIBRO SIGUE SIENDO UNA TABLA.** Va por `outro()`, y ahí hay dos formas de romperlo en
     * silencio: perder el `Htmlable` —un type hint `string` lo convierte a texto y `{{ }}` lo
     * escapa— o saltarse `formatLine()`, que colapsa los saltos de línea. Medido con la firma en
     * `string`: el correo pasó de 914 a **2.873 caracteres** de texto y se leía «border-collapse:
     * separate» como si fuera una frase. **Nada falló.**
     */
    public function test_the_book_still_renders_as_a_table(): void
    {
        $html = $this->render(0, 400);

        $this->assertGreaterThan(0, preg_match_all('/<tr[^>]*data-book/', $html), 'el libro no pinta sus filas');
        $this->assertStringNotContainsString('border-collapse', strip_tags($html), 'el HTML del libro se está leyendo como texto');
    }

    /**
     * ❗❗ **EL TONO LO PONE EL SIGNO DEL NETO**, y sale de UNA derivación para la chapa y el aviso.
     * Antes era `warn` fijo, así que un descuento llegaba teñido de «falta algo». Con dos fuentes,
     * la chapa podía decir una cosa y el importe otra.
     *
     * ⚠️ `info` y no `ok`: teñir de verde una rebaja la vendería como una celebración, y esto es un
     * dato del dinero. Es la misma regla dura que creó el quinto tono en `#503` — *una devolución
     * no es un color, es un signo y una fecha*.
     */
    public function test_the_tone_follows_the_sign(): void
    {
        foreach ([[0, 400, 'warn'], [400, 700, 'warn'], [0, -400, 'info'], [-400, 0, 'info'], [400, -300, 'info']] as [$old, $new, $tono]) {
            $mail = $this->mail($old, $new);

            $this->assertSame($tono, $mail->viewData['notice']['tono'] ?? null, "neto $new: el aviso");
            $this->assertSame($tono, $mail->viewData['hero']['tono'] ?? null, "neto $new: la chapa, que no puede contradecir al aviso");
        }
    }

    /**
     * ❗❗❗ **NADA DEL RESGUARDO SE REPITE EN EL CUERPO.** La cabecera ya dice Cuándo, Qué y Pedido, y
     * este correo repetía los TRES: el código y el producto en la entradilla, y producto, día y
     * hora en una tarjeta de producto. Es el mismo recorte que `#503` hizo en la confirmación.
     *
     * ⚠️ Se asevera sobre el CUERPO acotado, no sobre el HTML entero: el resguardo también vive en
     * el documento y buscar el código en toda la página saldría verde siempre.
     */
    public function test_the_body_does_not_repeat_what_the_stub_already_says(): void
    {
        $mail = $this->mail(0, 400);
        $cuerpo = collect($mail->introLines)->map(fn ($l) => (string) $l)->implode(' ');

        $this->assertNotSame('', trim($cuerpo), 'CONTROL: sin cuerpo este caso no mide nada');
        $this->assertStringNotContainsString('R-MIXTA1', $cuerpo, 'el código ya está en el resguardo');
        $this->assertStringNotContainsString('Cumpleaños Jump', $cuerpo, 'el producto ya está en el resguardo');

        $datos = $mail->viewData['hero']['datos'] ?? [];
        $this->assertContains('R-MIXTA1', $datos, 'CONTROL: el resguardo tiene que decir el código');
    }

    /**
     * Y NO VUELVE LA TARJETA DE PRODUCTO — es la pieza cuya retirada libera los otros dos datos.
     * Se comprueba por su marcado, no por su ausencia de texto: el nombre del producto podría
     * desaparecer del cuerpo por cualquier otra razón y esta propiedad seguiría rota.
     *
     * ⚠️⚠️ **Con CONTROL, y hacía falta**: la primera versión de este caso buscaba
     * `data-product-card`, un atributo que **no existe en el producto** — o sea que habría pasado
     * en verde con la tarjeta puesta. Aquí se comprueba primero que la aguja sabe encontrar una
     * tarjeta de verdad.
     */
    public function test_the_product_card_does_not_come_back(): void
    {
        $aguja = 'class="product-card"';

        $conTarjeta = (string) (new OrderItemRefunded(
            $this->item->order, $this->item, 500,
        ))->toMail($this->customer)->render();
        $this->assertStringContainsString($aguja, $conTarjeta, 'CONTROL: la aguja no sabe encontrar una tarjeta de producto');

        $this->assertStringNotContainsString($aguja, $this->render(0, 400));
    }
}
