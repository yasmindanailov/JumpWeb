<?php

namespace App\Notifications\Support;

use Illuminate\Notifications\Messages\MailMessage;

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
 */
class BrandedMailMessage extends MailMessage
{
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
