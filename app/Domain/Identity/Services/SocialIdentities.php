<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * **Las identidades externas del titular, vistas por él** (`docs/specs/auth-con-google.md` §8, tanda
 * T3): cuáles tiene vinculadas y cómo quitarlas.
 *
 * ⚠️⚠️ **Desvincular es el CONTRAPESO del aviso de vinculación** (`#342`, contrapeso 2 y 3 de §5.2):
 * el vínculo se crea solo, no caduca y se avisa por correo — y ese aviso solo sirve de algo si quien
 * lo recibe **puede deshacerlo**. Sin esta pieza, la única salida de un vínculo no pedido era borrar
 * la cuenta.
 *
 * ⚠️ **Exige reconfirmar** (`[DECIDIDO owner]`, §8): hoy, con un código `confirm` al correo de la cuenta (A2a, `#855`;
 * el único desde la A5, `#869`). Desvincular ya no deja a nadie fuera: el correo de la cuenta siempre puede recibir el
 * código para entrar —la contraseña que había que crear antes con «he olvidado mi contraseña» ya no existe—.
 *
 * ⚠️ **El limitador es el MISMO** que el de las demás reconfirmaciones ({@see AccountCredentials::verify}):
 * cinco intentos por titular e IP. Un segundo contador aquí serían cinco intentos más por cada
 * endpoint que reconfirme, que es como se afloja `SEC-06` sin que se note.
 */
final class SocialIdentities
{
    public function __construct(private readonly AccountCredentials $credentials) {}

    /**
     * Las identidades vinculadas, de la más reciente a la más antigua.
     *
     * @return Collection<int, UserIdentity>
     */
    public function forUser(User $user): Collection
    {
        return $user->identities()->orderByDesc('linked_at')->orderByDesc('id')->get();
    }

    /**
     * Quita el vínculo con un proveedor.
     *
     * ⚠️ **Idempotente**: desvincular lo que ya no está sale bien y no escribe rastro. El titular pide
     * un ESTADO —«que no haya vínculo con Google»—, y dos clics seguidos no pueden dar respuestas
     * distintas ni dejar dos entradas de auditoría de un solo acto.
     */
    public function unlink(User $user, string $provider, string $code, string $ip): CredentialChangeResult
    {
        $verdict = $this->credentials->verify($user, $code, $ip);

        if ($verdict->failed()) {
            return $verdict;
        }

        $removed = $user->identities()->where('provider', $provider)->delete();

        if ($removed > 0) {
            AuditLogger::log('identities.unlinked', $user, ['provider' => $provider]);
            Log::info('auth.social_unlinked', ['user_id' => $user->id, 'provider' => $provider]);
        }

        return CredentialChangeResult::success();
    }
}
