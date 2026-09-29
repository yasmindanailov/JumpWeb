<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Inicio de sesión por CONTRASEÑA (Fase 3 · paso 3b, `docs/specs/api-v1.md` §4.2 y §4.6.3).
 *
 * Reúne lo que era de dominio dentro de `Livewire\Auth\Login`: los **dos** limitadores de `SEC-06`,
 * la comprobación de credenciales, el sello de `last_login_at` y el rastro en el log. Lo hace para
 * que la API de este mismo paso no tenga que reescribirlo — y sobre todo para que no reescriba solo
 * una parte: una API que copiara el limitador por (email, IP) y se dejara el de IP sola volvería a
 * abrir el credential-stuffing distribuido que el segundo cubre, sin que nada lo delatara.
 *
 * **Qué NO hace, a propósito**: regenerar la sesión, decidir a dónde va el usuario después, ni
 * tocar la cesta de la compra. Eso son efectos de la sesión WEB y los pone quien atiende la
 * petición (spec §4.6.3): el anti-cesta-cruzada del sidebar no tiene sentido en un cliente de API.
 *
 * `Auth::attempt()` sigue siendo quien autentica: no se reimplementa la comprobación del hash, y
 * así el guard, el `remember` y los eventos del framework funcionan igual en las dos superficies.
 *
 * ▶ **Su núcleo —los dos limitadores, el sello y el rastro— vive desde la A1 del acceso con código en
 * {@see LoginGate}** (`DECISIONES #853`), movido tal cual: el código al correo lo comparte con esta clase, y
 * la contraseña de los clientes se retira en la A5 (`specs/acceso-con-codigo.md` §4.5).
 */
class PasswordLogin
{
    /** Los topes de `SEC-06`, que son de {@see LoginGate}: se nombran aquí porque las pruebas del login los leen. */
    public const MAX_ATTEMPTS = LoginGate::MAX_ATTEMPTS;

    public const MAX_ATTEMPTS_PER_IP = LoginGate::MAX_ATTEMPTS_PER_IP;

    public function __construct(private readonly LoginGate $gate) {}

    public function attempt(string $email, string $password, bool $remember, string $ip): LoginResult
    {
        return $this->gate->guarded($email, $ip, 'auth.login', static function (string $email) use ($password, $remember): ?User {
            if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
                return null;
            }

            /** @var User */
            return Auth::user();
        });
    }

    /**
     * Comprueba las credenciales **sin abrir sesión** (F4, `docs/specs/token-bearer.md` §4.2): es la
     * puerta del emisor de tokens Bearer, que atiende a quien no tiene sesión ni la quiere.
     *
     * ⚠️ No puede llamar a {@see attempt()}: `Auth::attempt()` inicia sesión en el guard `web` y
     * encola la cookie `remember`, que en una petición sin `StartSession` es estado a medias. Aquí
     * se usa `validate()`, que comprueba el hash por el mismo proveedor y no toca la sesión.
     *
     * ⚠️⚠️ Y **no es una segunda puerta para un atacante**: comparte con `attempt()` el núcleo
     * {@see LoginGate::guarded()} —los DOS limitadores, con las MISMAS claves—, así que cinco fallos en el login
     * bloquean también la emisión de tokens, y al revés. Dos cubos separados habrían duplicado los
     * intentos que `SEC-06` concede.
     */
    public function verify(string $email, string $password, string $ip): LoginResult
    {
        return $this->gate->guarded($email, $ip, 'auth.credentials_verified', static function (string $email) use ($password): ?User {
            // Por el GUARD y no por el proveedor a pelo: `validate()` comprueba el hash dentro de la
            // misma caja de tiempo que `attempt()`, así que la puerta nueva tampoco delata por el
            // reloj qué correos existen (`SEC-06`).
            if (! Auth::guard('web')->validate(['email' => $email, 'password' => $password])) {
                return null;
            }

            // Las credenciales ya casaron: es la misma búsqueda que acaba de hacer el proveedor.
            return User::query()->where('email', $email)->first();
        });
    }
}
