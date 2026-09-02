<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\Consent;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un consentimiento otorgado, visto por su titular (tanda 3 · paso 11).
 *
 * Existe porque `/mi-cuenta` lo enseña y esa página se retira: publicarlo era la condición para que
 * el borrado no le quitara al cliente la prueba visible de a qué dijo que sí (`DECISIONES #120(t)`).
 *
 * ⚠️ **No publica la IP**, igual que la página: forma parte de la prueba del art. 7.1 y viaja en el
 * export de portabilidad, que es un acto explícito del titular. En una lista que se pinta sola sería
 * un dato técnico más en pantalla sin decirle nada nuevo.
 *
 * @property-read Consent $resource
 */
class ConsentResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $consent = $this->resource;

        return [
            'type' => $consent->type,
            'type_label' => self::label((string) $consent->type),
            'accepted_at' => $consent->accepted_at?->toIso8601String(),
            // ⚠️ Con la ZONA HORARIA de la instalación, por el mismo motivo que `created_label` en un
            // pedido: `display_timezone` es un ajuste del panel que el navegador no conoce.
            'accepted_label' => DisplayTime::format($consent->accepted_at, 'd/m/Y'),
            // La RETIRADA (art. 7.3, `#344`). ⚠️ Viaja aunque sea `null`: sin el campo, un cliente no
            // podría distinguir «no se ha retirado» de «esta versión del servidor no lo sabe», y ésa
            // es justo la ambigüedad que dejaría enseñando «aceptado» un consentimiento retirado.
            'revoked_at' => $consent->revoked_at?->toIso8601String(),
            // Compuesta por el SERVIDOR y con la zona horaria de la instalación, como la de arriba: si
            // el cliente la formateara, un titular en otro huso vería una fecha distinta de la que el
            // export declara.
            'revoked_label' => $consent->revoked_at !== null ? DisplayTime::format($consent->revoked_at, 'd/m/Y') : null,
            'version' => (string) $consent->version,
        ];
    }

    /**
     * El nombre del documento, en el idioma negociado.
     *
     * ⚠️ **Un tipo desconocido devuelve su propio identificador y NUNCA cadena vacía.** `__()` sobre
     * una clave que falta devuelve la clave entera —«account.account.privacy.consent_types.foo»—, que
     * en pantalla se lee como un error; y devolver `''` sería peor: una fila con la fecha y sin
     * nombre parece que no hay nada. Es la familia de los veinte iconos vacíos (`DECISIONES #113`).
     */
    private static function label(string $type): string
    {
        $key = 'account.account.privacy.consent_types.'.$type;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $type;
    }
}
