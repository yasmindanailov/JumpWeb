<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use App\Domain\Platform\Exceptions\GoogleBusinessException;
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

    public function __construct(private readonly GoogleBusinessLocations $locations) {}

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
     * **Elegir la ficha del parque** (§4.2·4), con sus tres guardas en el orden que importa.
     *
     *  1. **Revalidar contra Google**: la ficha tiene que estar en el listado de ESE token. El
     *     identificador viaja por el navegador, así que sin esto bastaría cambiarlo a mano para
     *     apuntar la portada del parque a una ficha ajena.
     *  2. **El host de su web tiene que ser el del sitio**, o es la ficha equivocada.
     *  3. **Si cambia la ficha, se pregunta**: no es un ajuste, es cambiar de qué negocio son las
     *     reseñas que el parque publica.
     *
     * @param  bool  $confirmed  el admin ya ha dicho que sí al cambio de ficha
     *
     * @throws GoogleBusinessException|GoogleBusinessApiException
     */
    public function chooseLocation(
        #[\SensitiveParameter] string $refreshToken,
        string $name,
        int $userId,
        bool $confirmed = false,
    ): GoogleBusinessChoice {
        $ficha = $this->locations->revalidate($refreshToken, $name);

        if ($ficha === null) {
            throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_NOT_YOURS);
        }

        $this->assertHost($ficha);

        return DB::transaction(function () use ($ficha, $userId, $confirmed): GoogleBusinessChoice {
            $row = GoogleBusinessConnection::query()->lockForUpdate()->first();

            if ($row === null) {
                // No se puede elegir ficha sin conexión: el flujo entra por «Conectar».
                throw GoogleBusinessException::because(GoogleBusinessException::NOT_CONFIGURED);
            }

            $anterior = $row->place_id;
            $tituloAnterior = $row->location_title;
            $cambia = is_string($anterior) && $anterior !== '' && $anterior !== $ficha->placeId;

            if ($cambia && ! $confirmed) {
                throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_CHANGED);
            }

            $row->update([
                'location_name' => $ficha->name,
                'location_title' => $ficha->title,
                'place_id' => $ficha->placeId,
                'maps_uri' => $ficha->mapsUri,
                'new_review_uri' => $ficha->newReviewUri,
            ]);

            // Rastro sin dato personal: una ficha es un negocio, no una persona. Lo que importa
            // registrar es QUE cambió y quién lo hizo, que es lo que nadie recuerda después.
            AuditLogger::log('google_business.location_chosen', $row, [
                'location' => $ficha->name,
                'changed' => $cambia,
                'by' => $userId,
            ]);

            // ⚠️ Se CUENTA lo que pasó en vez de avisar: el correo a los admins necesita `User`, que
            // vive en Identity, y Platform no puede mirar a ningún módulo. Avisa la entrega.
            return new GoogleBusinessChoice($ficha, $cambia, is_string($tituloAnterior) ? $tituloAnterior : null);
        });
    }

    /**
     * **Desconectar** (§4.2·8): se borra lo de casa y se devuelve el token para retirarlo en Google.
     *
     * ⚠️⚠️ **Se borra la FILA entera, no solo el token.** Media conexión —sin llave pero con la ficha
     * y el `placeId` dentro— es un estado que nadie sabe leer y que la próxima pasada intentaría usar.
     * Sin fila, el estado efectivo vuelve a «lista para conectar», que es exactamente la verdad.
     *
     * ⚠️ **Primero se borra y DESPUÉS se revoca**, como al reconectar: si la revocación falla lo que
     * queda es un permiso de más en la cuenta de Google —que el parque puede retirar a mano—, y no una
     * llave viva guardada en una instalación que se creía desconectada.
     *
     * ▶ **La T2 engancha aquí**: cuando existan las reseñas sincronizadas y sus ficheros, se borran en
     * esta misma transacción (§4.2·8). Hoy no hay nada más que borrar y se deja dicho para que no se
     * quede fuera.
     *
     * @return string|null el token que hay que revocar, o `null` si no había conexión
     */
    public function disconnect(int $userId): ?string
    {
        return DB::transaction(function () use ($userId): ?string {
            $row = GoogleBusinessConnection::query()->lockForUpdate()->first();

            if ($row === null) {
                return null;
            }

            $token = $row->readToken();

            // El rastro se escribe ANTES de borrar: después no habría fila a la que apuntar, y este
            // es justo el gesto del que alguien preguntará dentro de un mes.
            AuditLogger::log('google_business.disconnected', $row, ['by' => $userId]);

            $row->delete();

            return $token;
        });
    }

    /**
     * La comprobación de host: **dura en producción, aviso fuera**.
     *
     * ⚠️⚠️ **No es una guarda a medias, es la única forma de que exista.** En desarrollo el sitio es
     * `localhost` y la ficha del parque apunta a su dominio real, así que la comprobación **nunca**
     * casaría: dejarla dura en todas partes obligaría a saltársela para poder trabajar, y una guarda
     * que se salta a diario acaba desactivada en el sitio donde sí importa. Donde el error es caro
     * —producción, la portada que ven los clientes— la comprobación manda.
     *
     * @throws GoogleBusinessException
     */
    private function assertHost(GoogleBusinessLocation $ficha): void
    {
        if ($ficha->matchesHost(GoogleBusinessLocation::siteHost())) {
            return;
        }

        if (app()->isProduction()) {
            throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_HOST_MISMATCH);
        }

        Log::info('google_business.host_mismatch_allowed', [
            'reason' => 'no es producción',
            'sitio' => GoogleBusinessLocation::siteHost(),
        ]);
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
