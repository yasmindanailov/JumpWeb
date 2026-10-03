<?php

namespace App\Notifications\Support;

use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Models\Setting;
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
     * @param  string  $titular  la clave del titular dentro del grupo: `headline`, o una de sus variantes (`headline_grupo`)
     *                           cuando el mismo correo tiene varias caras (la R2b); siempre del MISMO grupo.
     */
    public function hero(string $grupo, string $tono = 'info', array $datos = [], array $reemplazos = [], string $titular = 'headline'): static
    {
        $this->viewData['hero'] = [
            // La chapa, SOLO si el grupo la tiene (la R1c): los correos de código no llevan, como su diseño —el titular ya
            // dice el hecho—. Sin clave, `__()` devolvería la CLAVE: falla hacia invisible, como el adelanto de abajo.
            'chapa' => Lang::has($grupo.'.badge') ? (string) __($grupo.'.badge') : '',
            'tono' => $tono,
            'titulo' => (string) __($grupo.'.'.$titular, $reemplazos),
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

    // ══ EL CUERPO EN ORDEN (la R2, `specs/correos-rediseno.md` §4.3) ═══════════════════════════════════════════════════
    //
    // Los correos de la reserva ponen sus bloques donde los pone su diseño. Cada verbo de aquí abajo AÑADE un bloque al
    // cuerpo, en el orden en que se llama, entre la cabecera (`hero()`) y el pie. ⚠️ No se mezclan con los verbos de la R1
    // (`line`, `notice`, `action`, `outro`, `code`, el resguardo de filas): `MailDocument::bloques()` lo rechaza. Toda URL de
    // esta casa sale con su UTM y su marca de envío, como la del botón de siempre.

    /**
     * Los ENLACES por nombre que los textos de este correo pueden nombrar (`[escríbenos](whatsapp)`): la URL la pone el
     * correo, nunca el texto, que es editable desde el panel.
     *
     * @param  array<string, string|null>  $enlaces  nombre → URL; uno sin URL no se ofrece (su texto sale sin enlace)
     */
    public function links(array $enlaces): static
    {
        foreach ($enlaces as $nombre => $url) {
            if (is_string($url) && $url !== '') {
                $this->viewData['enlaces'][$nombre] = $this->etiquetada($url);
            }
        }

        return $this;
    }

    /**
     * EL RESGUARDO (el `resguardo()` del diseño, BookingCard en correo): la hoja del calendario, la hora grande, qué y
     * cuántos, el precio, el número; debajo, sus filas de dinero (la que queda, en negrita) y dos enlaces claros.
     *
     * @param  array{dow: string, n: string, month: string}  $dia  la hoja: «sáb», «26», «sep»
     * @param  list<array{0: string, 1: string, 2?: bool}>  $filas  rótulo, valor y si va en negrita
     * @param  list<array{0: string, 1: string, 2: string}>  $enlaces  texto, URL e icono de los enlaces claros
     */
    public function slip(array $dia, string $fecha, string $hora, string $que, ?string $precio, string $codigo, array $filas = [], array $enlaces = []): static
    {
        return $this->bloque([
            // `dia` lo distingue del resguardo DE FILAS de la R1 (el de `hero()`), que sigue con sus `filas` rótulo → valor.
            'tipo' => 'resguardo', 'dia' => $dia, 'fecha' => $fecha, 'hora' => $hora, 'que' => $que, 'precio' => $precio,
            'codigo' => $codigo,
            'dinero' => array_map(static fn (array $f): array => [(string) $f[0], (string) $f[1], (bool) ($f[2] ?? false)], $filas),
            'enlaces' => array_map(fn (array $l): array => [(string) $l[0], $this->etiquetada((string) $l[1]), (string) $l[2]], $enlaces),
        ]);
    }

    /**
     * EL QR (QrPass en correo): la imagen INCRUSTADA (`cid:`, viaja en el correo y se ve sin «cargar imágenes»), el código
     * para dictar y su botón —el principal, salvo que el correo tenga otro trabajo (`$secundario`)—.
     *
     * @param  string  $png  la imagen del QR, en bytes
     */
    public function qr(string $png, string $codigo, string $texto, string $dicta, string $boton, string $url, bool $secundario = false): static
    {
        return $this->bloque([
            'tipo' => 'qr', 'png' => $png, 'codigo' => $codigo, 'texto' => $texto, 'dicta' => $dicta,
            'boton' => $boton, 'url' => $this->etiquetada($url), 'secundario' => $secundario,
        ]);
    }

    /**
     * UNA LISTA con su icono en el círculo (ProofList en correo); la TAREA, en su aro y en negrita: lo que falta se ve sin
     * leer. Sin icono, el punto. Abre con su filete salvo `$raya = false`.
     *
     * @param  list<array{texto: string, icono?: string|null, tarea?: bool}>  $lineas
     */
    public function checklist(?string $titulo, array $lineas, bool $raya = true): static
    {
        return $this->bloque([
            'tipo' => 'lista', 'titulo' => $titulo, 'raya' => $raya,
            'lineas' => array_map(static fn (array $l): array => [
                'texto' => (string) $l['texto'], 'icono' => $l['icono'] ?? null, 'tarea' => (bool) ($l['tarea'] ?? false),
            ], $lineas),
        ]);
    }

    /** UNA SECCIÓN: su titular y una frase (con sus enlaces por nombre); con botón, uno claro debajo. */
    public function section(string $titulo, string $texto, ?string $boton = null, ?string $url = null): static
    {
        return $this->bloque([
            'tipo' => 'seccion', 'titulo' => $titulo, 'texto' => $texto,
            'boton' => $boton !== null && $url !== null ? ['texto' => $boton, 'url' => $this->etiquetada($url)] : null,
        ]);
    }

    /**
     * LOS PASOS numerados, cada uno con su botón: el del primero, el principal; los demás, claros.
     *
     * @param  list<array{texto: string, boton: string, url: string}>  $pasos
     */
    public function steps(?string $titulo, array $pasos): static
    {
        return $this->bloque([
            'tipo' => 'pasos', 'titulo' => $titulo,
            'pasos' => array_map(fn (array $p): array => [
                'texto' => (string) $p['texto'], 'boton' => (string) $p['boton'], 'url' => $this->etiquetada((string) $p['url']),
            ], $pasos),
        ]);
    }

    /** DOS BOTONES: el principal y, debajo, uno claro que no compite. */
    public function buttons(string $principal, string $principalUrl, string $secundario, string $secundarioUrl): static
    {
        return $this->bloque([
            'tipo' => 'botones',
            'principal' => ['texto' => $principal, 'url' => $this->etiquetada($principalUrl)],
            'secundario' => ['texto' => $secundario, 'url' => $this->etiquetada($secundarioUrl)],
        ]);
    }

    /**
     * UN AVISO en su tono (el `aviso()` del diseño, InfoCallout en correo): lo que falta, sin alarma —«Aún no has añadido a
     * los menores a tu cargo…»—, con su negrita y sus enlaces por nombre. ⚠️ Sin el icono del diseño: una imagen no cambia en
     * oscuro y la letra del tono no llega a 3:1 sobre su fondo oscuro; el tinte y el enlace ya dicen lo que falta (la R2d).
     *
     * @param  'aviso'|'info'|'ok'|'error'|'neutro'  $tono
     */
    public function callout(string $texto, string $tono = 'aviso', string $titulo = ''): static
    {
        return $this->bloque(['tipo' => 'aviso', 'titulo' => $titulo, 'texto' => $texto, 'tono' => $tono]);
    }

    /** EL MOTIVO (el que da el banco), en mono y aparte: se dicta igual al llamar. */
    public function reason(string $etiqueta, string $texto): static
    {
        return $this->bloque(['tipo' => 'motivo', 'etiqueta' => $etiqueta, 'texto' => $texto]);
    }

    /** Párrafos del cuerpo (el `texto` del diseño), con su negrita y sus enlaces por nombre. */
    public function paragraphs(string ...$lineas): static
    {
        return $this->bloque(['tipo' => 'texto', 'lineas' => array_values($lineas)]);
    }

    /** Una línea menor (la `linea` del diseño): lo que se lee después, más pequeño. */
    public function small(string $texto): static
    {
        return $this->bloque(['tipo' => 'linea', 'lineas' => [$texto]]);
    }

    /**
     * El HTML que el correo compone él mismo (el LIBRO del pedido, `EmailBookBlock`), en su sitio del cuerpo: el bloque de
     * marcado de la R1, con el rol de enlace en sus `<a>`. Vacío, no hay bloque.
     */
    public function markup(Htmlable $html): static
    {
        $marcado = trim($html->toHtml());

        return $marcado === '' ? $this : $this->bloque(['tipo' => 'marcado', 'html' => $marcado]);
    }

    /** El botón principal, en su sitio del cuerpo (uno por correo, `#803`). */
    public function button(string $texto, string $url): static
    {
        return $this->bloque(['tipo' => 'boton', 'texto' => $texto, 'url' => $this->etiquetada($url)]);
    }

    /**
     * «Responde a este correo…» en el pie, y que responder LLEGUE al parque: su correo del panel (`contact.email`) como
     * `replyTo`. Sin correo en el panel, ni la frase: no se promete lo que no llega.
     */
    public function replies(string $texto): static
    {
        $correo = trim((string) Setting::value('contact.email', ''));
        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return $this;
        }
        $this->replyTo($correo, Setting::businessName());
        $this->viewData['responde'] = $texto;

        return $this;
    }

    /** @param  array<string, mixed>  $b */
    private function bloque(array $b): static
    {
        $this->viewData['cuerpo'][] = $b;

        return $this;
    }

    private function etiquetada(string $url): string
    {
        return EmailUtm::tag($url, $this->campaign, $this->clickMark);
    }
}
