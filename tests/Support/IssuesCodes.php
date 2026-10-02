<?php

namespace Tests\Support;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LoginCodes;

/**
 * **Los códigos al correo, fabricados para una prueba** (A5 de `specs/acceso-con-codigo.md` §4.12, `#869`).
 *
 * Desde que el cliente no tiene contraseña, entrar y reconfirmar se hacen con un CÓDIGO. Muchas pruebas usaban la
 * contraseña como INTERMEDIARIO de lo que de verdad prueban —la supresión del art. 17, el cambio de correo, cerrar las
 * demás sesiones, las sesiones atadas—: se re-apuntan aquí (`CONVENCIONES` §3.quater, la tercera categoría).
 *
 * ▶ **No se salta el dominio**: el código lo emite `LoginCodes::issue()`, el mismo que usan la puerta y la
 * reconfirmación, así que caducidad, propósito, huella y un-solo-uso se ejercen igual. Lo único que se ahorra es leerlo
 * del correo, que prueban `AuthCodeTest` y `MeConfirmationCodeTest`.
 */
trait IssuesCodes
{
    /** Un código para ENTRAR (`POST /auth/login`, `POST /auth/tokens`) con la cuenta de `$user`. */
    protected function loginCodeFor(User $user, string $ip = '127.0.0.1'): string
    {
        return app(LoginCodes::class)->issue((string) $user->email, LoginCode::PURPOSE_LOGIN, $ip);
    }

    /** Un código para CONFIRMAR una acción sensible (`POST /me/confirm-code`) de la cuenta de `$user`. */
    protected function confirmCodeFor(User $user, string $ip = '127.0.0.1'): string
    {
        return app(LoginCodes::class)->issue((string) $user->email, LoginCode::PURPOSE_CONFIRM, $ip);
    }

    /** Un código que NO es `$code` (el siguiente, de seis cifras): el «no» de una prueba, sin adivinar ninguno. */
    protected function wrongCode(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
