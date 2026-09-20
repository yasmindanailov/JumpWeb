<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Exceptions\GoogleBusinessApiException;

/**
 * **Qué fichas alcanza el permiso, y si la elegida sigue siendo una de ellas**
 * (`docs/specs/google-business-profile.md` §4.2·4).
 *
 * ⚠️⚠️ **`revalidate()` no es una comodidad, es la guarda.** El identificador de la ficha viaja por
 * el navegador cuando el admin elige, y volverá a hacer falta **antes de cada escritura** en la ficha
 * (§4.4·4). Comprobarlo contra el listado que devuelve *ese* token es lo único que impide que un
 * valor cambiado a mano —o un permiso que se retiró desde ayer— apunte la portada del parque a una
 * ficha que no es suya.
 *
 * ⚠️ Se pregunta a Google **cada vez**, sin caché: una caché aquí significaría revalidar contra lo
 * que era verdad hace un rato, que es exactamente lo que esta comprobación existe para no hacer.
 */
final readonly class GoogleBusinessLocations
{
    public function __construct(private GoogleBusinessApi $api) {}

    /**
     * Las fichas que el token alcanza, ya saneadas y sin las que no traen nombre de recurso.
     *
     * @return list<GoogleBusinessLocation>
     *
     * @throws GoogleBusinessApiException
     */
    public function available(#[\SensitiveParameter] string $refreshToken): array
    {
        $fichas = [];

        // ⚠️⚠️ **El recorrido vive AQUÍ y no en el cliente HTTP**, y no es orden por el orden: al
        // listar es el único momento en que se sabe **de qué cuenta** cuelga cada ficha, y esa cuenta
        // hace falta para pedirle sus reseñas —son dos APIs que la nombran distinto (§4.2·10)—. Un
        // `allLocations()` que devolviera fichas sueltas la tiraría por el camino.
        foreach ($this->api->accounts($refreshToken) as $cuenta) {
            $nombre = $cuenta['name'] ?? null;

            if (! is_string($nombre) || trim($nombre) === '') {
                continue;
            }

            foreach ($this->api->locations($refreshToken, $nombre) as $fila) {
                $ficha = GoogleBusinessLocation::fromApi($fila, $nombre);

                if ($ficha !== null) {
                    $fichas[] = $ficha;
                }
            }
        }

        return $fichas;
    }

    /**
     * La ficha, **solo si sigue estando en el listado de este token**. `null` si no.
     *
     * @throws GoogleBusinessApiException
     */
    public function revalidate(#[\SensitiveParameter] string $refreshToken, string $name): ?GoogleBusinessLocation
    {
        foreach ($this->available($refreshToken) as $ficha) {
            // Comparación exacta del nombre de recurso: es un identificador, no un texto que
            // normalizar. `hash_equals` porque decide un control de acceso.
            if (hash_equals($ficha->name, $name)) {
                return $ficha;
            }
        }

        return null;
    }
}
