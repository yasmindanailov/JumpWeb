<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * **Guardar el permiso recién concedido** (`docs/specs/google-business-profile.md` §4.2·2).
 *
 * Reconectar no es «escribir el token nuevo»: es sustituir uno por otro sin dejar el anterior vivo en
 * la cuenta de Google del parque. Los dos pasos van en este orden y no en el contrario —primero se
 * guarda, después se revoca—, porque si la revocación falla lo que queda es un permiso de más, y si
 * fallara el guardado después de revocar lo que quedaría es **una conexión muerta**.
 */
final class GoogleBusinessConnector
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Guarda el token y devuelve el ANTERIOR, si había uno distinto, para que quien llama lo revoque.
     *
     * ⚠️⚠️ **«Distinto» es la palabra que importa.** Google puede devolver **el mismo** token de
     * refresco en una reconexión de la misma cuenta; revocarlo entonces como «el anterior» mataría el
     * que se acaba de guardar, y la conexión aparecería verde en el panel y muerta en la primera
     * pasada. Es el modo de fallo que menos ruido hace de toda la tanda.
     *
     * ⚠️ **Todo bajo candado de fila** (§4.2·2): dos pestañas del panel terminando el flujo a la vez
     * dejarían media conexión —el token de una y la huella de la otra— sin que nada fallara.
     *
     * @return string|null el token anterior a revocar, o `null` si no hay nada que revocar
     */
    public function connect(#[\SensitiveParameter] string $refreshToken, int $userId): ?string
    {
        return DB::transaction(function () use ($refreshToken, $userId): ?string {
            $row = GoogleBusinessConnection::query()->lockForUpdate()->first();
            $previous = $row?->readToken();

            $atributos = [
                'status' => GoogleBusinessStatus::Connected,
                'status_changed_at' => now(),
                'refresh_token' => $refreshToken,
                'token_fingerprint' => GoogleBusinessConnection::fingerprint($refreshToken),
                'connected_by_user_id' => $userId,
                'connected_at' => now(),
            ];

            // Sobre la fila que ya tenemos bajo candado, no por `updateOrCreate`: aquélla buscaría
            // por `id`, que **no es asignable en masa** —lo cazó el caso de «el reto es de un solo
            // uso»—, y además haría una segunda consulta fuera del candado.
            $row === null
                ? GoogleBusinessConnection::create($atributos)
                : $row->update($atributos);

            return ($previous !== null && ! hash_equals($previous, $refreshToken)) ? $previous : null;
        });
    }

    /**
     * Retira un permiso en Google. **Best-effort a propósito**: el token ya no está guardado en
     * ningún sitio, así que un fallo aquí no deja nada inconsistente en casa.
     *
     * ⚠️ **Solo en producción** (§4.2·8). Fuera, los dos entornos comparten las fichas reales de los
     * parques a través del proyecto de desarrollo, y revocar retira la autorización de TODO el
     * proyecto: una prueba en local apagaría la conexión de producción de un cliente.
     *
     * ⚠️ **El token va en el CUERPO, nunca como `?token=`**: una URL acaba en el log del servidor, en
     * el del proxy y en el historial de cualquier herramienta por medio.
     */
    public function revoke(#[\SensitiveParameter] string $refreshToken): bool
    {
        if (! app()->isProduction()) {
            Log::info('google_business.revoke_skipped', ['reason' => 'no es producción']);

            return false;
        }

        try {
            return Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(GoogleBusinessOAuth::REVOKE_ENDPOINT, ['token' => $refreshToken])
                ->successful();
        } catch (\Throwable $e) {
            // Ni el token ni el cuerpo: solo que no se pudo (§4.2·6).
            Log::warning('google_business.revoke_failed', ['error' => $e::class]);

            return false;
        }
    }
}
