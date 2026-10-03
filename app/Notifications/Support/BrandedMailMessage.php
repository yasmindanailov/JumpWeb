<?php

namespace App\Notifications\Support;

use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Services\Analytics\EmailClickMarks;
use App\Domain\Platform\Services\Analytics\EmailOpenMarks;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\Mime\Email;

/**
 * El MOLDE de los correos del producto (`DECISIONES #503`; artboard `Correos PJP` 1a).
 *
 * `MailMessage` de Laravel sabe de saludos, líneas y un botón. El molde de este sistema tiene dos
 * piezas más —**la cabecera en tinta** (chapa + titular + resguardo) y **el aviso**— y las dos
 * viajan en `viewData`, que es lo que la vista publicada de notificaciones sabe pintar.
 *
 * ▶ **Existe como TIPO y no como un par de asignaciones sueltas** porque son 23 correos: escribir
 * `$message->viewData['hero'] = [...]` en cada uno reparte el molde en veintitrés sitios y la
 * primera vez que alguien se deje una clave, el correo sale sin cabecera **sin que nada falle**.
 * Aquí hay una sola forma de decirlo, y las claves se derivan del grupo del diccionario.
 *
 * ⚠️ `MailMessage` **no es `Macroable`** (comprobado en el framework), así que una subclase es la
 * única forma de que esto sea encadenable — y encadenable importa: sin ello cada notificación
 * tendría que romper su `return (new MailMessage)->…` en dos.
 *
 * ▶ **Y desde la T1c de la analítica (`#678`) el molde sabe QUÉ correo es**: `new BrandedMailMessage($this)`
 * en cada `toMail()`. De la notificación sale su clave (`EmailUtm::keyOf()`), y con ella el botón, el
 * logotipo y los enlaces del pie llevan `utm_source=email&utm_medium=<clave>` — el mismo nombre con el que
 * se cuentan el envío (`email_sent`) y la vuelta (`email_clicked`). Sin `$this` no hay UTM y nada falla;
 * por eso `EmailUtmTest` lee las fuentes y exige que los veinticinco lo pasen.
 *
 * ▶▶ **Y desde la R1a del rediseño (`specs/correos-rediseno.md` §4.1.1) pinta con la PLANTILLA DEL DISEÑO**, no con el
 * Markdown de Laravel: vistas propias (`correo/html`, `correo/texto`) sobre un documento de bloques (`MailDocument`)
 * compuesto de los MISMOS datos que dan estos verbos. Los correos no cambian: cambia quién los pinta.
 */
class BrandedMailMessage extends MailMessage
{
    /** Las vistas de la plantilla: el documento y su versión de texto. */
    public const VISTAS = ['html' => 'correo.html', 'text' => 'correo.texto'];

    /** La clave del correo (`EmailUtm::keyOf()`), o `null` si este correo no lleva UTM (los avisos al negocio). */
    private ?string $campaign = null;

    /** La marca del envío en sus enlaces (`jw_e`, la C2), o `null` si sus clics no se cuentan (`EmailClickMarks`). */
    private ?string $clickMark = null;

    /**
     * @param  Notification|null  $notification  el correo que se está componiendo: **`$this` en `toMail()`**.
     */
    public function __construct(?Notification $notification = null)
    {
        // ⚠️ Asignadas, no con `view()`: `view()` VACÍA `viewData`, y lo que escriben `campaign()` y `markSend()` —la UTM,
        // la marca del envío, el píxel— se perdería sin que nada fallara. Sin `markdown`, `MailChannel` usa estas vistas.
        $this->view = self::VISTAS;
        $this->markdown = null;

        if ($notification !== null) {
            $this->campaign(EmailUtm::keyOf($notification));
            $this->markSend($notification);
        }
    }

    /**
     * La marca del envío (`specs/correos-salientes.md` §4.6, `#794`): la cabecera {@see EmailSend::HEADER} con el id de la
     * notificación —uno por destinatario y el mismo en cada reintento de la cola—, que `RecordEmailSend` lee del mensaje que
     * salió. Solo en los correos AL CLIENTE (los que llevan clave) y solo si el framework ya le dio un id: un `toMail()`
     * llamado a mano (una prueba, una vista previa) no es un envío y no se apunta.
     */
    private function markSend(Notification $notification): void
    {
        // El framework declara el id como texto, pero hasta que lo fija el que envía es NULO: un `toMail()` a mano no lo tiene.
        $send = (string) $notification->id;

        if ($this->campaign === null || $send === '') {
            return;
        }

        $this->withSymfonyMessage(static function (Email $message) use ($send): void {
            $message->getHeaders()->addTextHeader(EmailSend::HEADER, $send);
        });

        // La marca de sus ENLACES (§4.8, la C2): solo si `EmailClickMarks` la anotó al enviar (a una cuenta que no se opuso,
        // con el interruptor encendido). Viaja a la vista para el logotipo y el pie, como la UTM.
        $this->clickMark = EmailClickMarks::for($notification);
        $this->viewData['clickMark'] = $this->clickMark;

        // Y el PÍXEL de apertura (§4.12, la C3): solo si `EmailOpenMarks` lo anotó al enviar (con su interruptor y el
        // consentimiento de la cuenta). Viaja a la vista, que lo pone al final del cuerpo.
        $this->viewData['openMark'] = EmailOpenMarks::for($notification);
    }

    /**
     * Los datos de la vista, con el DOCUMENTO del correo (`$correo`) compuesto de ellos. Es el punto por el que pasan los
     * dos caminos —el envío (`MailChannel`) y `render()`—, así que el documento sale igual en los dos: el tema, el pie y el
     * horario de hoy se leen AL PINTAR, no al construir el mensaje (un correo en cola se pinta cuando sale).
     *
     * @return array<string, mixed>
     */
    public function data()
    {
        $data = parent::data();
        $data['correo'] = MailDocument::desde($data);

        return $data;
    }

    /**
     * La clave con la que se etiquetan los enlaces de este correo. Una clave que no sea de un correo al cliente
     * (`EmailUtm::isCustomerKey()`) deja el correo SIN UTM: el equipo no es audiencia.
     */
    public function campaign(?string $key): static
    {
        $this->campaign = $key !== null && EmailUtm::isCustomerKey($key) ? $key : null;
        // Viaja a la vista: el logotipo de la cabecera y los cuatro enlaces del pie se etiquetan allí.
        $this->viewData['utm'] = $this->campaign;

        return $this;
    }

    /**
     * El botón, con el UTM pegado si el enlace es de esta casa. Una URL FIRMADA (post-form, justificante,
     * verificación) sigue siendo válida: `EmailUtm` explica por qué se pega DESPUÉS de firmar.
     *
     * @param  string  $text
     * @param  string  $url
     */
    public function action($text, $url): static
    {
        parent::action($text, EmailUtm::tag((string) $url, $this->campaign, $this->clickMark));

        return $this;
    }

    /**
     * LA CABECERA EN TINTA: chapa de estado, titular y —si se le pasan— las filas del resguardo.
     *
     * @param  string  $grupo  el grupo del diccionario, p. ej. `emails.order_cancelled`. De ahí
     *                         salen `.badge` y `.headline`: **una convención, no dos parámetros**,
     *                         para que no se puedan desparejar.
     * @param  string  $tono  `ok` · `warn` · `err` · `info` · `neutro`. El último es el de las
     *                        DEVOLUCIONES: «una devolución no es un color, es un signo y una fecha».
     * @param  array<string,string>  $datos  el resguardo, normalmente de `EmailSlip`. Vacío = sin él,
     *                                       que es lo correcto en los correos de CUENTA: ahí no hay reserva.
     */
    public function hero(string $grupo, string $tono = 'info', array $datos = [], array $reemplazos = []): static
    {
        $this->viewData['hero'] = [
            // La chapa, SOLO si el grupo la tiene (la R1c): los correos de código no llevan, como su diseño —el titular ya
            // dice el hecho—. Sin clave, `__()` devolvería la CLAVE: falla hacia invisible, como el adelanto de abajo.
            'chapa' => Lang::has($grupo.'.badge') ? (string) __($grupo.'.badge') : '',
            'tono' => $tono,
            'titulo' => (string) __($grupo.'.headline', $reemplazos),
            'datos' => $datos,
        ];

        // ⚠️ La cabecera SUSTITUYE al saludo, no se suma: el artboard no tiene «¡Hola!» en ninguno
        // de los cuatro que dibuja — lo primero que se lee es el estado y el titular. Se anula aquí
        // además de retirarlo de cada notificación, para que un `->greeting()` escrito después no
        // devuelva dos aperturas al correo.
        $this->greeting = null;

        // ❗❗ LA LÍNEA DE ADELANTO viaja con la cabecera, y eso es la decisión (`#506`): los 21 ya
        // llaman a `hero()`, así que derivarla del MISMO grupo la pone en los veintiuno sin tocar
        // ni una notificación — y no se puede olvidar en uno. Es la convención de `.badge` y
        // `.headline`, aplicada a la tercera pieza.
        //
        // ⚠️⚠️ Y SOLO SI LA CLAVE EXISTE. `__()` devuelve la CLAVE cuando no la encuentra, así que
        // sin esta guarda un grupo sin `preheader` anunciaría el correo en la bandeja con el texto
        // «emails.order_cancelled.preheader». No es hipotético: `#504` dejó `badge` y `headline`
        // fuera de su sitio en el correo de identidad social y el cliente recibió exactamente eso.
        // ▶ Sin clave, el correo sale SIN línea de adelanto: falla hacia invisible, no hacia feo.
        if (Lang::has($grupo.'.preheader')) {
            $this->viewData['preheader'] = (string) __($grupo.'.preheader');
        }

        return $this;
    }

    /**
     * UNA LÍNEA DE CIERRE — lo que va DESPUÉS del aviso.
     *
     * ❗❗❗ Existe porque **el orden de las llamadas no es el orden de la pintura**, y eso no se ve
     * leyendo la notificación: `notifications::email` pinta **todas** las `introLines` juntas y el
     * aviso DESPUÉS, así que un `->notice()` escrito antes de tres `->line()` acaba el ÚLTIMO.
     * Pasó al construir `#507`: la cifra del suplemento —que es el correo entero— quedó debajo del
     * libro del pedido y de «puedes seguir editando». *Se ve renderizando, no leyendo.*
     *
     * ▶ Lo que va aquí es CIERRE de verdad —el libro a día de hoy, la nota de que aún se puede
     * editar—, no cuerpo: si se lee antes que el aviso, el aviso deja de ser lo que se mira.
     */
    public function outro(Htmlable|string $texto): static
    {
        // ⚠️⚠️ EL TIPO `Htmlable` TIENE QUE SOBREVIVIR HASTA AQUÍ, y por eso la firma no es `string`.
        // `{{ $line }}` no escapa un `Htmlable` —de ahí que el LIBRO del pedido, que devuelve un
        // `HtmlString`, se pinte como tabla—, pero un type hint `string` lo convierte a texto al
        // pasarlo y deja de serlo. Medido al construir `#507` con la firma en `string`: el correo
        // pasó de 914 a **2.873 caracteres** de texto y en el cuerpo se leía el CSS del libro
        // —«border-collapse:separate»— como si fuera una frase. **Nada falló.**
        //
        // ⚠️ Y `formatLine()` no es opcional: colapsa los saltos de línea, sin lo cual Markdown lee
        // cada línea del bloque como su propio párrafo. Es lo que hace `->line()`, y esto tiene que
        // hacer lo mismo — un atajo `$this->outroLines[] = $texto` se salta las dos cosas.
        $this->outroLines[] = $this->formatLine($texto);

        return $this;
    }

    /**
     * EL AVISO — la caja de tinte con su punto: lo que hay que saber antes de venir, el motivo de un
     * pago denegado, la frase de que un enlace se puede repartir.
     *
     * ⚠️ Va entre el cuerpo y el botón, que es el orden del artboard: se lee **antes** de decidir.
     */
    public function notice(string $titulo, string $texto, string $tono = 'info'): static
    {
        $this->viewData['notice'] = [
            'titulo' => $titulo,
            'tono' => $tono,
            'texto' => $texto,
        ];

        return $this;
    }

    /**
     * EL CÓDIGO de un solo uso (la R1c, `specs/correos-rediseno.md` §4.1.4; el `codigo()` del diseño, el 8 del zip (6)):
     * grande, en la familia mono y sobre el sutil, con su etiqueta y la nota de su caducidad. Es la ACCIÓN de su correo —se
     * escribe donde se pidió—, así que va donde iría el botón, y ninguno lleva los dos.
     *
     * ⚠️ El código NO es un texto del parque: no se edita y nadie lo puede quitar del correo. La etiqueta (`*_label`, sin
     * negrita) y la nota (un párrafo: con ella) sí, desde el panel (R1·T).
     */
    public function code(string $etiqueta, string $codigo, ?string $nota = null): static
    {
        $this->viewData['code'] = [
            'etiqueta' => $etiqueta,
            'codigo' => $codigo,
            'nota' => $nota,
        ];

        return $this;
    }
}
