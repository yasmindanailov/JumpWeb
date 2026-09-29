<?php

namespace Tests\Feature\Mail;

use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * **EL DOCUMENTO de un correo** (la R1a, `specs/correos-rediseno.md` §4.1.1): los bloques de la plantilla del diseño,
 * compuestos de los MISMOS datos que dan los verbos del molde, con el aire calculado por el bloque siguiente y cada bloque
 * pintado dos veces (HTML y texto).
 *
 * ⚠️ Lo esperado va escrito A MANO: calcularlo con `MailDocument` probaría la clase contra sí misma.
 */
class MailDocumentTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> los tipos de bloque, en orden */
    private function tipos(BrandedMailMessage $m): array
    {
        return array_map(static fn (array $b): string => $b['tipo'], MailDocument::bloques($m->data()));
    }

    public function test_the_blocks_come_in_the_order_the_mold_always_painted(): void
    {
        $m = (new BrandedMailMessage)
            ->hero('emails.order_declined', 'err', ['Cuándo' => 'Sáb 26', 'Pedido' => 'R-1'])
            ->line('Uno.')
            ->notice('Ojo', 'Esto importa.', 'warn')
            ->action('Ver', 'https://example.test/x')
            ->line('Después del botón.')
            ->salutation('Firma propia');

        $this->assertSame(['cabecera', 'resguardo', 'texto', 'aviso', 'boton', 'linea', 'linea', 'pie'], $this->tipos($m));
    }

    /**
     * El aire lo pone la plantilla por el bloque SIGUIENTE (el `aire()` del diseño): 24 bajo la cabecera, 16 dentro de
     * una zona, 28 entre zonas, nada bajo el último. Quitar un bloque no deja hueco.
     */
    public function test_the_air_under_each_block_depends_on_the_next_one(): void
    {
        $bloques = MailDocument::conAire([
            ['tipo' => 'cabecera'], ['tipo' => 'resguardo'], ['tipo' => 'texto'], ['tipo' => 'aviso'],
            ['tipo' => 'linea'], ['tipo' => 'boton'], ['tipo' => 'pie'],
        ]);

        $this->assertSame([24, 16, 28, 16, 28, 28, 0], array_column($bloques, 'aire'));
        $this->assertSame([24, 0], array_column(MailDocument::conAire([['tipo' => 'cabecera'], ['tipo' => 'pie']]), 'aire'));
    }

    /**
     * Las líneas seguidas van en UN bloque; un `Htmlable` (el libro, la ficha, un enlace de baja) en el suyo, porque una
     * tabla dentro de un párrafo no es HTML válido. Uno vacío (el libro que no se pudo componer) no deja bloque.
     */
    public function test_consecutive_lines_share_a_block_and_markup_gets_its_own(): void
    {
        $m = (new BrandedMailMessage)
            ->hero('emails.order_declined', 'err')
            ->line('a')->line('b')
            ->line(new HtmlString('<table data-x><tr><td>libro</td></tr></table>'))
            ->line(new HtmlString(''))
            ->line('c');

        $bloques = MailDocument::bloques($m->data());

        $this->assertSame(['cabecera', 'texto', 'marcado', 'texto', 'pie'], array_column($bloques, 'tipo'));
        $this->assertSame(['a', 'b'], $bloques[1]['lineas']);
        $this->assertSame(['c'], $bloques[3]['lineas']);
    }

    /** Sin datos en la cabecera, sin resguardo: los correos de CUENTA no tienen reserva. */
    public function test_a_header_without_rows_has_no_slip_and_the_tones_are_the_designs(): void
    {
        $tonos = [];
        foreach (['ok' => 'ok', 'warn' => 'aviso', 'err' => 'error', 'info' => 'info', 'neutro' => 'neutro'] as $molde => $diseno) {
            $b = MailDocument::bloques((new BrandedMailMessage)->hero('emails.order_declined', $molde)->data());
            $this->assertSame(['cabecera', 'pie'], array_column($b, 'tipo'));
            $tonos[$molde] = $b[0]['tono'];
        }

        $this->assertSame(['ok' => 'ok', 'warn' => 'aviso', 'err' => 'error', 'info' => 'info', 'neutro' => 'neutro'], $tonos);
    }

    /**
     * ⚠️ Todo se ESCAPA, y el único formato es la negrita `**así**` (la de `CustomerAccountCreated`): antes cada línea
     * pasaba por Markdown y un `_` o un `*` de un dato del cliente podía volverse cursiva.
     */
    public function test_a_line_is_escaped_and_only_bold_is_understood(): void
    {
        $html = (string) (new BrandedMailMessage)
            ->hero('emails.order_declined', 'info')
            ->line('**Correo:** a_b_c@x.test <script> & _no_ [no](http://x)')
            ->render();

        $this->assertStringContainsString('<strong class="pjm-strong"', $html);
        $this->assertStringContainsString('Correo:</strong> a_b_c@x.test &lt;script&gt; &amp; _no_ [no](http://x)', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<em>', $html);
    }

    /** El HTML que compone un correo (un enlace de baja) gana el ROL de enlace; uno que ya trae estilo, no se toca. */
    public function test_markup_links_take_the_link_role_unless_they_bring_their_own_style(): void
    {
        $html = (string) (new BrandedMailMessage)
            ->hero('emails.order_declined', 'info')
            ->outro(new HtmlString('Porque sí. <a href="https://x.test/baja">Darse de baja</a> <a style="color:#000" href="https://x.test/b">B</a>'))
            ->render();

        $this->assertMatchesRegularExpression('#<a class="pjm-link" style="color:\#[0-9A-F]{6};[^"]*" href="https://x.test/baja">#', $html);
        $this->assertStringContainsString('<a style="color:#000" href="https://x.test/b">', $html);
    }

    /**
     * Un HTML en texto: una fila por línea con las celdas separadas —hoy el libro salía con las celdas pegadas—, los
     * enlaces con su dirección (salvo `tel:` y `mailto:`) y las entidades decodificadas.
     */
    public function test_markup_becomes_readable_plain_text(): void
    {
        $texto = MailDocument::textoDe(
            '<table><tr><td>Entrada &middot; 12 sep</td><td>+24,00&nbsp;€</td></tr><tr><td>Total</td><td>24,00 €</td></tr></table>'
            .'<p>Escríbenos a <a href="mailto:a@b.test">a@b.test</a> o <a href="https://x.test/?a=1&amp;b=2">aquí</a>.</p>'
        );

        $this->assertSame("Entrada · 12 sep +24,00 €\nTotal 24,00 €\nEscríbenos a a@b.test o aquí (https://x.test/?a=1&b=2).", $texto);
    }

    /**
     * La versión de TEXTO que se envía: cada bloque por su gemela, sin la línea de adelanto, sin escapar (un `&` es un
     * `&`), con el botón y su dirección, y sin la firma de fábrica: el diseño no la tiene y el pie nombra al parque.
     */
    public function test_the_plain_text_part_is_every_block_in_words(): void
    {
        $m = (new BrandedMailMessage)
            ->hero('emails.order_declined', 'err', ['Pedido' => 'R-1'])
            ->line('**Tom** & Jerry <3')
            ->notice('Ojo', 'Esto importa.', 'warn')
            ->action('Ver mi pedido', 'https://example.test/p?a=1&b=2');

        $texto = (string) view(BrandedMailMessage::VISTAS['text'], $m->data())->render();

        $this->assertStringContainsString('Pedido: R-1', $texto);
        $this->assertStringContainsString('Tom & Jerry <3', $texto);
        $this->assertStringContainsString("Ojo\nEsto importa.", $texto);
        $this->assertStringContainsString('Ver mi pedido: https://example.test/p?a=1&b=2', $texto);
        $this->assertStringNotContainsString('&amp;', $texto);
        $this->assertStringNotContainsString('**', $texto);
        $this->assertStringNotContainsString(__('Regards,'), $texto);
        $this->assertStringContainsString(__('emails.order_declined.headline'), $texto);
    }

    /** La firma de fábrica se va del HTML también; la PROPIA de un correo se queda como una línea. */
    public function test_only_a_mail_own_salutation_survives(): void
    {
        $sin = (string) (new BrandedMailMessage)->hero('emails.order_declined', 'info')->render();
        $con = (string) (new BrandedMailMessage)->hero('emails.order_declined', 'info')->salutation('Gracias, de verdad')->render();

        $this->assertStringNotContainsString(__('Regards,'), $sin);
        $this->assertStringContainsString('Gracias, de verdad', $con);
    }
}
