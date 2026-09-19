<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Setting;
use LogicException;

/**
 * **EL MENÚ DE HECHOS SE SIRVE POR LISTA BLANCA** (F5 · T1, `docs/specs/instancia-y-landing-fuera.md` §4.1,
 * `DECISIONES #631`).
 *
 * La API pública de lectura existe para que una landing que no es del producto pueda pintar precios, horario,
 * normas o el teléfono sin teclearlos a mano. El peligro no es que falte un dato: es que sobre.
 *
 * ⚠️⚠️ **Medido (spec §1.3): la tabla `settings` son 71 filas y ahí está `redsys_secret_key` al lado de
 * `contact.email`.** Cinco de esas filas son secretos —las claves de Redsys y el secreto de cliente de
 * Google—. Un recurso público que lea `settings` «con cuidado» filtra el día que alguien añada una clave y
 * nadie se acuerde de este comentario. Por eso un hecho público no se lee con `Setting::value()`: se lee por
 * aquí, con la lista de lo que ese recurso PUEDE mirar declarada en el propio recurso.
 *
 * Dos redes, y la segunda existe porque la primera es humana:
 *
 *  1. **La lista blanca**: pedir una clave que el recurso no declaró es un `LogicException`, no un `null`.
 *     Falla cerrado y falla en la primera petición, no en producción seis meses después.
 *  2. **La negación de secretos**: aunque alguien ESCRIBA `redsys_secret_key` en su lista blanca, aquí no
 *     sale. Una lista blanca protege del olvido; no protege del error de copiar y pegar, y el coste de ese
 *     error concreto es la clave con la que se firman los cobros.
 *
 * ▶ `SEC-01` y `RGPD-04` siguen mandando donde mandaban: esto no decide quién puede llamar, decide qué puede
 * salir.
 */
class PublicFacts
{
    /**
     * Lo que NO sale por la API pública ni declarándolo, por FAMILIA y no por nombre exacto.
     *
     * ⚠️ Por familia a propósito: `redsys_secret_key` es de hoy, y la lista tiene que cubrir la clave que
     * alguien añada mañana. Un `*_secret`, un `*_key` o cualquier cosa de Redsys no es un hecho público, y
     * si alguna vez lo fuera, se discute — no se cuela por un patrón que no miraba.
     */
    private const SECRETOS = [
        '/(^|\.|_)secret($|\.|_)/i',
        '/(^|\.|_)(api_)?key($|\.|_)/i',
        '/(^|\.|_)token($|\.|_)/i',
        '/(^|\.|_)password($|\.|_)/i',
        '/^redsys_/i',
    ];

    /** @param list<string> $permitidas */
    private function __construct(private readonly array $permitidas) {}

    /**
     * El lector de un recurso, con lo que ese recurso declaró que puede mirar.
     *
     * @param  list<string>  $permitidas
     */
    public static function allowing(array $permitidas): self
    {
        foreach ($permitidas as $clave) {
            if (self::esSecreto($clave)) {
                throw new LogicException(
                    "«{$clave}» es un SECRETO y no puede estar en una lista blanca pública. ".
                    'Si de verdad hace falta ese dato fuera, no se saca de `settings`: se decide.'
                );
            }
        }

        return new self($permitidas);
    }

    /**
     * El valor de un hecho público, o su respaldo.
     *
     * ⚠️ **Un ajuste que no existe devuelve el respaldo; uno que no está en la lista REVIENTA.** No es lo
     * mismo: lo primero es una instalación que no ha rellenado un campo —normal—, y lo segundo es un recurso
     * leyendo algo que nadie revisó.
     */
    public function get(string $clave, mixed $respaldo = null): mixed
    {
        if (! in_array($clave, $this->permitidas, true)) {
            throw new LogicException(
                "El recurso público pidió «{$clave}», que no está en su lista blanca. ".
                'Se añade a la lista del recurso —y se revisa al añadirla—, o no se sirve.'
            );
        }

        return Setting::value($clave, $respaldo);
    }

    /**
     * Varios hechos de una vez, ya sin los vacíos.
     *
     * ⚠️ **Se quitan los vacíos porque un menú dice lo que HAY**: una instalación sin TikTok no tiene que
     * publicar `"tiktok": ""` y hacer que cada landing escriba la misma condición. Lo que no está, no viaja
     * — y eso es exactamente lo que `#631` quiso decir con «todo es opcional».
     *
     * @param  array<string, string>  $mapa  nombre en la API => clave del ajuste
     * @return array<string, string>
     */
    public function compact(array $mapa): array
    {
        $salida = [];

        foreach ($mapa as $nombre => $clave) {
            $valor = $this->get($clave);

            if (is_string($valor) && trim($valor) !== '') {
                $salida[$nombre] = trim($valor);
            }
        }

        return $salida;
    }

    private static function esSecreto(string $clave): bool
    {
        foreach (self::SECRETOS as $patron) {
            if (preg_match($patron, $clave) === 1) {
                return true;
            }
        }

        return false;
    }
}
