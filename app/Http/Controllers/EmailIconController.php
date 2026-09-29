<?php

namespace App\Http\Controllers;

use App\Notifications\Support\MailIcons;
use Symfony\Component\HttpFoundation\Response;

/**
 * **Los iconos de los correos** (la R1b, `specs/correos-rediseno.md` §4.1.3): `GET /correo/i/{v}/{color}/{nombre}.png`, el
 * icono de Lucide teñido del color de su rol (`MailIcons`).
 *
 * ⚠️ Como el píxel de apertura (`#797`), la ruta va FUERA de la sesión, de las cookies y del visitante (`routes/web.php`), y
 * sin limitador por IP: el proxy de Gmail pide las imágenes de miles de personas desde las mismas IP. Pero, a diferencia del
 * píxel, no apunta NADA: la URL es la misma para todos y no dice quién abre.
 *
 * ⚠️ **Sale con `no-store`, y no es un olvido**: lo pone `NoStoreWebResponses`, GLOBAL e incondicional por `RGPD-04`, y
 * aquí no se abre una excepción en un middleware de seguridad por unos iconos de 2 kB (el mismo criterio que las fotos de
 * las reseñas, `#524`). El coste —cada apertura vuelve a pedirlos— va con `PERF-02`; la versión de las máscaras ya viaja en
 * la URL (`{v}`, y una vieja se sirve igual: un correo enviado no se rompe), así que cachearlos el día que se decida es
 * quitar la cabecera, sin miedo a servir una máscara rancia.
 */
final class EmailIconController
{
    public function __invoke(string $v, string $color, string $nombre): Response
    {
        $png = MailIcons::png($nombre, $color);
        abort_if($png === null, 404);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
