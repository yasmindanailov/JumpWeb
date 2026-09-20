<?php

namespace App\Domain\Platform\Services;

use Illuminate\Http\Request;

/**
 * **El HONEYPOT del formulario de contacto** — el campo que un humano no ve y un bot rellena
 * (F5 · T2b, `DECISIONES #654`).
 *
 * ❗❗ **Por qué existe: el nombre del campo era un contrato escrito DOS veces** —en línea dentro de
 * `pages/contact.blade.php` y en `ContactController`—, y esa vista se va a la instancia. Una landing que
 * lo re-escribiera con otro nombre dejaría de filtrar bots **sin que nada fallara**: el correo llegaría
 * igual, solo que también el de los bots. Ahora el nombre vive aquí, la vista lo pinta por el componente
 * `<x-site.honeypot>` y el controlador pregunta por {@see tripped}.
 *
 * ⚠️ **No se llama `website` por capricho**: ese sí lo rellenan los gestores de contraseñas
 * (`waiver-por-reserva.md` §8.2). Se conserva el nombre histórico porque cambiarlo invalidaría el filtro
 * del servidor, que es lo único que lo lee. El alta por API (`AuthRegistrationController`) usa el mismo
 * nombre por contrato de la API, no por esta constante.
 */
final class Honeypot
{
    public const FIELD = 'website';

    /** ¿Ha caído un bot? Un humano no ve el campo (`display:none`) y no lo rellena. */
    public static function tripped(Request $request): bool
    {
        return filled($request->input(self::FIELD));
    }
}
