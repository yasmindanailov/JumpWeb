<?php

namespace App\Domain\Identity\Services;

use Illuminate\Validation\Rules\Password;

/**
 * **La política de contraseñas del producto, en UN solo sitio** (`specs/area-cliente.md` §9).
 *
 * ▶ **Desde la A5 de `specs/acceso-con-codigo.md` (`#869`) es la del PERSONAL**: los clientes entran con un código al
 * correo y no tienen contraseña. La aplican la página del panel donde el personal crea la suya
 * (`App\Filament\Auth\PanelPassword`, `#870`) y la contraseña explícita de `app:create-admin`.
 *
 * ⚠️⚠️ **Nace de un hallazgo, no de una preferencia** (2026-08-22). `Password::min(8)->uncompromised()`
 * estaba escrito a mano en **seis** superficies (el registro, el restablecimiento y el cambio de «Mi cuenta», en la web
 * y en la API, y `app:create-admin`). Es la misma familia que `DisplayTime::dayLabel()` —una regla copiada— con un
 * agravante: **esto es seguridad**. El día que la política suba a diez caracteres habrá que tocar un solo sitio; con
 * copias, olvidar una no rompe nada visible: deja una puerta más floja que las demás, en silencio.
 *
 * ⚠️ **Lo que NO entra aquí, y es deliberado.** `confirmed` (repetir la contraseña) es del FORMULARIO, no de la
 * política: la página del panel lo pide con su segundo campo, y el comando no.
 *
 * ⚠️ Y la contraseña **generada** de `app:create-admin` sigue fuera a propósito: son 24 caracteres
 * aleatorios, que entran en la política por construcción, y comprobarlos contra Have I Been Pwned
 * metería una llamada de red en el camino feliz del despliegue. Su comentario ya lo explicaba; esto
 * no lo cambia.
 *
 * Lo vigila `PasswordPolicySingleSourceTest`.
 */
final class PasswordPolicy
{
    /** Longitud mínima. Se expone para que un test o un mensaje puedan citarla sin repetir el número. */
    public const MIN_LENGTH = 8;

    /**
     * Las reglas de una contraseña NUEVA: **longitud mínima y nada más**.
     *
     * ⚠️⚠️ **NO lleva `uncompromised()`, y es una DECISIÓN del owner con su coste delante**
     * (`[DECIDIDO owner, 2026-09-02]`, `DECISIONES #351`): *«vamos a quitar ese estricto requerimiento
     * para la contraseña, hay mucha fricción»*. Hasta hoy se consultaba el corpus de filtraciones de
     * Have I Been Pwned y se rechazaba **cualquier** contraseña que apareciera en él, aunque fuera una
     * sola vez — y eso, en un alta, es un «no» que el cliente no sabe cómo arreglar.
     *
     * ▶ **Lo que se gana**: el alta deja de rechazar contraseñas por un motivo que el usuario no
     * controla, y el camino feliz pierde una **llamada de red a un tercero** que estaba dentro de la
     * validación.
     *
     * ▶ **Lo que se pierde, dicho sin rodeos porque es seguridad**: `12345678` pasa a ser una
     * contraseña válida. Se le ofreció al owner la vía intermedia —`uncompromised(500)`, que rechaza
     * solo las MUY comunes y deja pasar el resto— y eligió retirarla entera. *Queda escrito para que
     * quien la reponga sepa que revierte una decisión, no que arregla un descuido.*
     *
     * ⚠️ **Lo que NO se toca**: el mínimo de 8 y los limitadores. La defensa contra el relleno de
     * credenciales pasa a apoyarse en ésos (desde la A5, `#869`, solo queda la contraseña del panel:
     * su login limita los intentos y los administradores piden además el código de su app, `#851`).
     *
     * @return list<string|Password>
     */
    public static function rules(): array
    {
        return ['required', 'string', Password::min(self::MIN_LENGTH)];
    }
}
