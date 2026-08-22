<?php

namespace App\Domain\Identity\Services;

use Illuminate\Validation\Rules\Password;

/**
 * **La política de contraseñas del producto, en UN solo sitio** (`specs/area-cliente.md` §9).
 *
 * ⚠️⚠️ **Nace de un hallazgo, no de una preferencia** (2026-08-22). `Password::min(8)->uncompromised()`
 * estaba escrito a mano en **seis** superficies: el registro (web y API), el restablecimiento (web y
 * API), el cambio de contraseña de «Mi cuenta» y la contraseña explícita de `app:create-admin`. Es la
 * misma familia que `DisplayTime::dayLabel()` —una regla copiada— con un agravante: **esto es
 * seguridad**. El día que la política suba a diez caracteres habrá que tocar seis sitios, y olvidar
 * uno no rompe nada visible: deja una puerta más floja que las demás, en silencio.
 *
 * ⚠️ **Lo que NO entra aquí, y es deliberado.** `confirmed` (repetir la contraseña) es del
 * FORMULARIO, no de la política: la API de registro no lo pide y la web sí, y las dos están en lo
 * cierto. Meterlo aquí obligaría a una de las dos a saltarse la fuente única.
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
     * Las reglas de una contraseña NUEVA.
     *
     * `uncompromised()` consulta el corpus de filtraciones de Have I Been Pwned por k-anonimato
     * (solo viaja el prefijo del hash, nunca la contraseña).
     *
     * @return list<string|Password>
     */
    public static function rules(): array
    {
        return ['required', 'string', Password::min(self::MIN_LENGTH)->uncompromised()];
    }
}
