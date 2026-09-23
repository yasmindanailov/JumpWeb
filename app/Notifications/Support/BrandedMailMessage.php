<?php

namespace App\Notifications\Support;

use App\Domain\Platform\Services\Analytics\EmailUtm;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

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
 */
class BrandedMailMessage extends MailMessage
{
    /** La clave del correo (`EmailUtm::keyOf()`), o `null` si este correo no lleva UTM (los avisos al negocio). */
    private ?string $campaign = null;

    /**
     * @param  Notification|null  $notification  el correo que se está componiendo: **`$this` en `toMail()`**.
     */
    public function __construct(?Notification $notification = null)
    {
        if ($notification !== null) {
            $this->campaign(EmailUtm::keyOf($notification));
        }
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
        parent::action($text, EmailUtm::tag((string) $url, $this->campaign));

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
            'chapa' => (string) __($grupo.'.badge'),
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
}
