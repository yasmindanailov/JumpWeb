<?php

namespace App\Domain\Platform\Services;

/**
 * **Los idiomas que sirve la parte pública de la instalación.**
 *
 * ⚠️ **Vivía en `Http\Middleware\SetLocale` y baja aquí porque NO es una regla de HTTP**: qué idiomas
 * habla el sitio es configuración de la instalación, y lo necesitan cosas que no son peticiones —la
 * validación del perfil del titular, por ejemplo—. Lo destapó `ModuleBoundariesTest` al ver un
 * servicio de dominio importando un middleware, que es exactamente el acoplamiento que esa guarda
 * existe para impedir: si el dominio depende de la capa HTTP, deja de poder usarse fuera de ella.
 *
 * ⚠️ **No confundir con `SetAdminLocale::SUPPORTED`**, que es otra lista y otra superficie: el panel
 * habla `es` y `zh_CN`, y la web pública `es`, `en` y `fr`. Unificarlas sería inventar un idioma en
 * cada lado.
 */
final class SiteLocales
{
    /** En orden de preferencia: el primero es el que gana cuando no hay nada mejor. */
    public const SUPPORTED = ['es', 'en', 'fr'];
}
