<?php

namespace App\Console\Commands;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Services\GoogleBusinessApi;
use App\Domain\Platform\Services\GoogleBusinessConnectionState;
use App\Domain\Platform\Services\GoogleBusinessLocations;
use Illuminate\Console\Command;

/**
 * **¿FUNCIONA DE VERDAD LA CONEXIÓN CON LA FICHA?** (`specs/google-business-profile.md` §4.2·10).
 *
 * Existe porque **el panel dice lo que hay guardado y esto dice lo que contesta Google**, y no son lo
 * mismo: una conexión puede aparecer «conectada» y estar muerta desde ayer —permiso retirado desde la
 * cuenta de Google, rol perdido sobre la ficha, API aún sin aprobar— sin que nada en casa cambie hasta
 * la siguiente pasada. Es el comando que se corre **después de conectar en una instalación nueva** y
 * el primero cuando alguien dice «no salen las reseñas».
 *
 * ⚠️⚠️ **NO IMPRIME NI UN SECRETO, A NINGÚN NIVEL DE DETALLE** (§4.2·6 y §4.2·10). Ni el token de
 * refresco, ni el de acceso, ni el secreto del cliente, ni el cuerpo de una respuesta de Google. Su
 * salida acaba en el log de un despliegue y en la captura de pantalla que alguien pega en un chat; lo
 * único que sale de aquí son hechos que se pueden enseñar.
 *
 * ⚠️ **No escribe nada.** Ni el estado, ni la última pasada: es un diagnóstico, y un diagnóstico que
 * cambia lo que mide deja de servir para medirlo. Marcar estados es de la pasada (§4.2·7), que sabe
 * con qué token llamó.
 */
class VerifyGoogleBusiness extends Command
{
    protected $signature = 'business-profile:verify';

    protected $description = 'Comprueba contra Google que la conexión con la ficha funciona. No escribe nada y no imprime secretos.';

    public function handle(GoogleBusinessApi $api, GoogleBusinessLocations $locations): int
    {
        $conexion = GoogleBusinessConnection::current();
        $estado = GoogleBusinessConnectionState::of($conexion);

        $this->line('Estado guardado: <info>'.$estado->value.'</info>');

        if ($estado !== GoogleBusinessStatus::Connected) {
            // Los estados que no llaman no se prueban llamando: sería un 401 garantizado y un
            // mensaje de error que no dice lo que pasa.
            $this->warn('No hay una conexión viva, así que no se llama a Google. '.$this->queHacer($estado));

            return self::FAILURE;
        }

        // ⚠️ **Aquí NO se vuelve a comprobar que el token se lee**, y es a propósito: llegar a
        // «conectada» ya exige que `GoogleBusinessConnectionState` lo haya leído —un token ilegible
        // sale de ahí como «caducada»—. Escribir la comprobación otra vez sería una guarda que
        // ninguna prueba puede poner en rojo, que es ruido y no defensa (`#704`). Lo destapó este
        // caso: la rama existía y era inalcanzable.
        $token = (string) $conexion?->readToken();

        try {
            $cuentas = $api->accounts($token);
        } catch (GoogleBusinessApiException $e) {
            return $this->fallo('accounts.list', $e);
        }

        $this->line('Cuentas que administra el permiso: <info>'.count($cuentas).'</info>');

        try {
            $fichas = $locations->available($token);
        } catch (GoogleBusinessApiException $e) {
            return $this->fallo('locations.list', $e);
        }

        $this->line('Fichas alcanzables: <info>'.count($fichas).'</info>');

        // La revalidación del §4.2·4, corrida en vivo: la pregunta que de verdad importa no es
        // «¿hay fichas?» sino «¿sigue estando la NUESTRA?».
        $elegida = $conexion?->location_name;
        $sigue = $elegida !== null && $locations->revalidate($token, $elegida) !== null;

        $this->line('Ficha conectada: <info>'.($conexion?->location_title ?: '(ninguna elegida)').'</info>');
        $this->line('¿Sigue en el listado de este permiso?: '.($sigue ? '<info>sí</info>' : '<comment>NO</comment>'));

        if (! $sigue) {
            $this->warn($elegida === null
                ? 'Falta elegir la ficha en Ajustes → Contenido web → Ficha de Google.'
                : 'La ficha conectada ya no la administra esta cuenta: vuelve a elegirla.');

            return self::FAILURE;
        }

        // Sin `?->`: llegar aquí exige que `$sigue` sea cierto, y eso exige que haya fila con ficha.
        $parent = $conexion->reviewsParent();

        if ($parent === null) {
            // Una conexión elegida antes de la T1·5 no guardó la cuenta, y las reseñas se piden por
            // otra API que la exige. Se dice, en vez de fallar con un 404 que despista.
            $this->warn('Esta conexión no guardó la CUENTA de la ficha, así que no se pueden pedir sus reseñas. Vuelve a elegir la ficha y se rellena sola.');

            return self::FAILURE;
        }

        try {
            $resumen = $api->reviewSummary($token, $parent);
        } catch (GoogleBusinessApiException $e) {
            return $this->fallo('reviews.list', $e);
        }

        $this->line('Reseñas en Google: <info>'.$resumen['totalReviewCount'].'</info>'
            .($resumen['averageRating'] !== null ? ' · media <info>'.number_format($resumen['averageRating'], 1).'</info>' : ''));

        $this->info('Las tres APIs contestan y la ficha es la que está conectada.');

        return self::SUCCESS;
    }

    /**
     * ⚠️ **Solo el código y la razón corta de Google.** El cuerpo de una respuesta de error puede
     * traer de vuelta trozos de lo que se le mandó, y esta salida se pega en sitios.
     */
    private function fallo(string $llamada, GoogleBusinessApiException $e): int
    {
        if ($e->reason === GoogleBusinessApiException::UNREACHABLE) {
            // Sin respuesta no hay HTTP que enseñar, y «HTTP 0» despista más que ayuda (`#733`).
            $this->error("«{$llamada}» no ha llegado a Google: sin red o tiempo agotado.");
            $this->warn('Comprueba la salida a internet de este servidor. No cambia el estado de la conexión.');

            return self::FAILURE;
        }

        $this->error("«{$llamada}» ha fallado: HTTP {$e->httpStatus} · {$e->reason}");

        if ($e->status !== null) {
            $this->warn('Eso deja la conexión en «'.$e->status->value.'». '.$this->queHacer($e->status));
        } else {
            $this->warn('Es un fallo pasajero de Google: no cambia el estado y se reintentará solo.');
        }

        return self::FAILURE;
    }

    /** Qué hacer con cada estado, que es lo único accionable de un diagnóstico. */
    private function queHacer(GoogleBusinessStatus $estado): string
    {
        return match ($estado) {
            GoogleBusinessStatus::Unconfigured => 'Faltan las credenciales de JumpSystem en esta instalación (app:set-setting, por CLI).',
            GoogleBusinessStatus::ReadyToConnect => 'Falta conectar desde Ajustes → Contenido web → Ficha de Google.',
            // ⚠️ «Caducada» son DOS cosas y las dos se arreglan reconectando: Google retiró el
            // permiso, o el token está guardado y no se puede descifrar porque rotó la APP_KEY sin
            // APP_PREVIOUS_KEYS. La segunda no se le ocurre a nadie a las once de la noche.
            GoogleBusinessStatus::Expired => 'El permiso ya no vale: hay que volver a conectar. Si acaba de cambiar la APP_KEY sin APP_PREVIOUS_KEYS, es eso.',
            GoogleBusinessStatus::Forbidden => 'La cuenta conectada ya no administra la ficha: devuélvele el acceso o conecta con otra.',
            GoogleBusinessStatus::LocationLost => 'La ficha ya no existe: vuelve a conectar y elígela de nuevo.',
            GoogleBusinessStatus::NoApiAccess => 'Google aún no ha aprobado el acceso de JumpSystem a la API: no se arregla desde el parque.',
            GoogleBusinessStatus::Connected => '',
        };
    }
}
