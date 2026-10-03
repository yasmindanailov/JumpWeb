<?php

namespace Tests\Feature\Mail;

use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * **EL CUERPO EN ORDEN Y SUS BLOQUES** (la R2a de `specs/correos-rediseno.md` §4.3): los correos de la reserva ponen sus
 * bloques donde los pone su diseño —el resguardo de campos, el QR, la lista, la sección, los pasos, dos botones, el motivo—,
 * con enlaces POR NOMBRE dentro del texto, el QR incrustado y «Responde a este correo» que llega al parque.
 *
 * Lo que fijan estos casos, cada uno con su control: el orden es el declarado y no se mezcla con la R1; el aire hasta un
 * bloque con filete es 32; un enlace solo existe si el correo lo ofrece, y su URL nunca la escribe el texto; cada bloque
 * pinta lo suyo y su gemela de texto; el QR viaja DENTRO del correo; y responder llega al parque, si el panel tiene su correo.
 */
class MailBodyTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function tipos(BrandedMailMessage $m): array
    {
        return array_map(static fn (array $b): string => $b['tipo'], MailDocument::bloques($m->data()));
    }

    public function test_the_body_goes_in_the_order_it_is_declared(): void
    {
        // El orden del 4 del diseño: la lista, la línea, el botón y DESPUÉS el QR.
        $m = (new BrandedMailMessage)->hero('emails.order_declined', 'info')
            ->checklist('Lo que queda', [['texto' => 'Uno.', 'icono' => 'users']], raya: false)
            ->small('Nada de esto impide la fiesta.')
            ->button('Repasar la fiesta', 'https://example.test/antes')
            ->qr('png', 'ABCD EFGH', 'Tu QR', 'Dicta:', 'Abrir Mi QR', 'https://example.test/qr', secundario: true);

        $this->assertSame(['cabecera', 'lista', 'linea', 'boton', 'qr', 'pie'], $this->tipos($m));

        // CONTROL: sin cuerpo, el orden fijo de la R1 sigue igual (una `line()` tras el botón es cierre).
        $r1 = (new BrandedMailMessage)->hero('emails.order_declined', 'info')->line('Antes.')->action('Ver', 'https://example.test/x')->line('Después.');
        $this->assertSame(['cabecera', 'texto', 'boton', 'linea', 'pie'], $this->tipos($r1));
    }

    public function test_an_ordered_body_refuses_the_verbs_of_the_r1(): void
    {
        $m = (new BrandedMailMessage)->hero('emails.order_declined', 'info')->line('Uno.')->paragraphs('Dos.');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('line');
        MailDocument::bloques($m->data());
    }

    /** Un bloque con filete (lista, sección, pasos) sube a 32 el aire hasta él, salvo `raya: false`. */
    public function test_the_air_up_to_a_ruled_block_is_32(): void
    {
        $aire = static fn (array $bloques): array => array_column(MailDocument::conAire($bloques), 'aire');

        $this->assertSame([24, 32, 28, 0], $aire([['tipo' => 'cabecera'], ['tipo' => 'qr'], ['tipo' => 'lista'], ['tipo' => 'pie']]));
        $this->assertSame([24, 28, 28, 0], $aire([['tipo' => 'cabecera'], ['tipo' => 'qr'], ['tipo' => 'lista', 'raya' => false], ['tipo' => 'pie']]), 'CONTROL: sin raya, 28 entre zonas');
        $this->assertSame([24, 32, 16, 28, 0], $aire([['tipo' => 'cabecera'], ['tipo' => 'texto'], ['tipo' => 'seccion'], ['tipo' => 'linea'], ['tipo' => 'pie']]));
    }

    /**
     * ❗ Los ENLACES POR NOMBRE: el texto (editable desde el panel) dice dónde va el enlace; la URL la pone el correo. Un
     * nombre que el correo no ofrece deja su texto sin enlace, y todo se escapa.
     */
    public function test_a_link_by_name_takes_the_url_the_mail_offers_and_nothing_else(): void
    {
        $m = (new BrandedMailMessage)->hero('emails.order_declined', 'info')
            ->links(['whatsapp' => 'https://wa.me/34600000000?text=a&b=c', 'tel' => 'tel:+34600000000', 'nada' => null])
            ->paragraphs('Puedes [escríbenos por WhatsApp](whatsapp), [llamarnos](tel) o [ir a <b>otro</b>](inventado).');
        $html = (string) $m->render();

        $this->assertStringContainsString('>escríbenos por WhatsApp</a>', $html);
        $this->assertStringContainsString('href="https://wa.me/34600000000?text=a&amp;b=c"', $html, 'la URL, escapada');
        $this->assertStringContainsString('>llamarnos</a>', $html);
        $this->assertStringContainsString('ir a &lt;b&gt;otro&lt;/b&gt;', $html, 'un nombre que no se ofrece: su texto, escapado y sin enlace');
        $this->assertStringNotContainsString('(inventado)', $html);
        $this->assertStringNotContainsString('href="inventado"', $html);

        $texto = (string) view(BrandedMailMessage::VISTAS['text'], $m->data())->render();
        $this->assertStringContainsString('escríbenos por WhatsApp (https://wa.me/34600000000?text=a&b=c)', $texto);
        $this->assertStringContainsString('llamarnos o ir a', $texto, 'un tel: ya se lee, sin dirección detrás');
    }

    /** Cada bloque nuevo pinta lo suyo y su gemela de texto lo dice en palabras. */
    public function test_every_new_block_paints_its_content_and_its_text_twin(): void
    {
        $m = (new BrandedMailMessage)->hero('emails.order_declined', 'ok')
            ->slip(['dow' => 'sáb', 'n' => '26', 'month' => 'sep'], 'Sábado 26 de septiembre', '17:00', 'Kids 1 hora · 2 niños', '24 € pagados',
                'Nº R-7K2P4', [['Señal pagada', '50 €'], ['El día de la fiesta', '119,50 €', true]],
                [['Cómo llegar', 'https://maps.example.test/x', 'map-pin'], ['Añadir al calendario', 'https://example.test/r.ics', 'calendar-plus']])
            ->checklist('Antes de venir', [['texto' => '**Menores a tu cargo:** añádelos.', 'icono' => 'user-round-plus', 'tarea' => true], ['texto' => 'Sin icono.']])
            ->section('Si cambian los planes', 'Puedes cambiar hasta el viernes.')
            ->steps('Ahora, dos cosas', [['texto' => 'Rellena la lista.', 'boton' => 'Rellenar', 'url' => 'https://example.test/l'], ['texto' => 'Comparte.', 'boton' => 'Compartir', 'url' => 'https://example.test/c']])
            ->buttons('Pagar con Bizum', 'https://example.test/b', 'Pagar con tarjeta', 'https://example.test/t')
            ->reason('Motivo', 'Operación denegada por tu banco');
        $html = (string) $m->render();

        foreach (['>sáb<', '>26<', '>17:00<', 'Kids 1 hora · 2 niños', 'Nº R-7K2P4', 'El día de la fiesta', 'Cómo llegar', 'Añadir al calendario',
            'Antes de venir', 'Si cambian los planes', 'Ahora, dos cosas', 'Pagar con Bizum', 'Operación denegada por tu banco'] as $dato) {
            $this->assertStringContainsString($dato, $html);
        }
        $this->assertSame(2, substr_count($html, 'class="pjm-btn"'), 'un principal en los pasos y otro en los dos botones');
        $this->assertSame(1, preg_match('#<td[^>]*class="pjm-strong"[^>]*>(?:(?!</td>).)*Menores a tu cargo#s', $html), 'la tarea, en negrita');
        $this->assertStringContainsString('class="pjm-dot"', $html, 'sin icono, el punto');

        $texto = (string) view(BrandedMailMessage::VISTAS['text'], $m->data())->render();
        foreach (['Sábado 26 de septiembre · 17:00', 'El día de la fiesta: 119,50 €', 'Añadir al calendario: https://example.test/r.ics',
            '- Menores a tu cargo: añádelos.', '1. Rellena la lista.', '   Compartir: https://example.test/c',
            'Pagar con tarjeta: https://example.test/t', 'Motivo: Operación denegada por tu banco'] as $linea) {
            $this->assertStringContainsString($linea, $texto);
        }
    }

    /**
     * ❗❗ EL QR VIAJA DENTRO DEL CORREO (`cid:`), y además va adjunto cuando el correo lo adjunta: con las imágenes remotas
     * bloqueadas se ve igual. Por el canal de correo de verdad (`array`), no por `render()`, que no lleva mensaje.
     */
    public function test_the_qr_is_embedded_in_the_sent_mail(): void
    {
        config(['mail.default' => 'array']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);

        Notifications::route('mail', 'ana@example.com')->notifyNow(new class((string) $png) extends Notification
        {
            public function __construct(private string $png) {}

            /** @return list<string> */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): BrandedMailMessage
            {
                return (new BrandedMailMessage)->subject('QR')->hero('emails.order_declined', 'ok')
                    ->qr($this->png, 'ABCD EFGH', 'Tu QR', 'Dicta:', 'Abrir Mi QR', 'https://example.test/qr');
            }
        });

        $enviado = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()?->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $enviado);
        $this->assertMatchesRegularExpression('#<img src="cid:[^"]+"[^>]*alt="QR ABCD EFGH"#', (string) $enviado->getHtmlBody());
        $incrustadas = array_filter($enviado->getAttachments(), static fn ($p): bool => $p->getMediaType().'/'.$p->getMediaSubtype() === 'image/png' && $p->hasContentId());
        $this->assertCount(1, $incrustadas, 'la imagen, incrustada');

        // Y en la VISTA PREVIA (`render()`, la del panel) el QR se VE: Laravel cambia el `cid:` por la imagen en línea.
        $previa = (string) (new BrandedMailMessage)->hero('emails.order_declined', 'ok')
            ->qr((string) $png, 'ABCD EFGH', 'Tu QR', 'Dicta:', 'Abrir Mi QR', 'https://example.test/qr')->render();
        $this->assertStringNotContainsString('cid:', $previa);
        $this->assertMatchesRegularExpression('#<img src="data:image/png;base64,[^"]+"[^>]*alt="QR ABCD EFGH"#', $previa);
    }

    /** Responder LLEGA al parque —su correo del panel, como `replyTo`— y el pie lo dice; sin correo en el panel, ni la frase. */
    public function test_replies_reach_the_park_only_when_the_panel_has_its_email(): void
    {
        Setting::updateOrCreate(['key' => 'contact.email'], ['value' => 'hola@parque.test', 'group' => 'contact']);
        Setting::flushMemo();
        $m = (new BrandedMailMessage)->hero('emails.order_declined', 'ok')->paragraphs('Uno.')->replies('Responde a este correo si tienes cualquier duda.');

        $this->assertSame([['hola@parque.test', Setting::businessName()]], $m->replyTo);
        $this->assertStringContainsString('Responde a este correo si tienes cualquier duda.', (string) $m->render());

        // CONTROL: sin correo del parque, ni `replyTo` ni la frase.
        Setting::where('key', 'contact.email')->delete();
        Setting::flushMemo();
        $sin = (new BrandedMailMessage)->hero('emails.order_declined', 'ok')->paragraphs('Uno.')->replies('Responde a este correo si tienes cualquier duda.');
        $this->assertSame([], $sin->replyTo);
        $this->assertStringNotContainsString('Responde a este correo', (string) $sin->render());
    }
}
