<?php

namespace App\Domain\Platform\Services;

use Illuminate\Support\Str;

/**
 * **La clave con la que dos escrituras del mismo nombre se reconocen entre sí**
 * (`docs/specs/celebracion-e-invitacion.md` §4.4, T4).
 *
 * Nació dentro de `Identity\Models\GuardianAuthorization::keyFor()` para que «un niño, un papel»
 * (`specs/waiver-por-reserva.md` §4.8) se cumpliera **igual en MySQL y en SQLite**. Sube a Platform
 * porque desde la invitación digital la misma pregunta se hace en **Booking** —emparejar lo que
 * contesta un padre con una ficha del post-form— y **Booking no puede mirar a Identity**
 * (`ModuleBoundariesTest`). Platform es la base que los dos pueden leer.
 *
 * ⚠️⚠️ **No se puede poner un `UNIQUE` sobre los nombres crudos, y está medido**: todas las tablas
 * del proyecto son `utf8mb4_unicode_ci`, donde `'Perez' = 'Pérez'` y `'ana' = 'Ana'` dan **1**; en
 * SQLite —donde corre la suite— la comparación es byte a byte y dan **0**. Una guarda escrita sobre
 * la columna cruda mediría una conducta en el test y la contraria en producción. La salida no es
 * elegir motor: es no depender de ninguno, normalizando en PHP.
 *
 * ⚠️ **El respaldo cuando `Str::ascii()` deja la cadena vacía no es decorativo**: esos nombres se
 * convertirían en `''` y **todos** esos niños colisionarían en la misma clave. Con el respaldo se
 * normaliza lo que se pueda y se conserva el original.
 *
 * ⚠️⚠️ **Y «alfabeto no latino» NO es el criterio, aunque así lo dijera la prosa heredada.** Medido el
 * 2026-09-17: `Str::ascii()` **sí** transitera el cirílico («Александр Петров» → `aleksandr petrov`),
 * el griego y el árabe, así que con ellos el respaldo no entra nunca. Los que de verdad se vacían son
 * **chino, japonés, coreano, tailandés, hebreo y emoji**. La distinción importa porque un caso escrito
 * con el ejemplo equivocado deja el respaldo sin probar — pasó aquí, y lo cazó el arnés de mutación.
 *
 * ⚠️ Es un value object PURO —sin BD ni facades—, así que su prueba vive en `tests/Unit`
 * (`CONVENCIONES §3.ter`).
 */
final class PersonNameKey
{
    /**
     * El ancho de las columnas que la guardan (`guardian_authorizations.minor_key`,
     * `invitation_replies.child_key`). Se corta aquí y no en cada escritor: una clave más larga que
     * su columna la truncaría **la base de datos** —en MySQL con error, en SQLite en silencio— y dos
     * niños distintos acabarían con la misma clave según el motor.
     */
    public const MAX = 255;

    /** La clave de un nombre completo. Cadena vacía si no queda nada que normalizar. */
    public static function for(string $fullName): string
    {
        $full = trim($fullName);
        $ascii = Str::ascii($full);
        // Si al quitar tildes no queda NADA (alfabeto no latino), se normaliza el original.
        $base = self::collapse($ascii) !== '' ? $ascii : $full;

        return mb_substr(mb_strtolower(self::collapse($base)), 0, self::MAX);
    }

    /**
     * **¿La clave de una FICHA nombra al mismo niño que una clave completa?** (T6·4.)
     *
     * El caso real, y no un adorno: el anfitrión pega la lista de la clase con nombres de pila
     * («Mateo») y al padre se le piden **nombre y apellidos** —«Mateo Ruiz»—, porque es lo que
     * distingue a dos niños que se llaman igual. Empareja la clave entera o **su primera palabra**.
     *
     * ⚠️⚠️ **La dirección importa y por eso no es simétrica**: la corta es la de la FICHA. Hacerla
     * simétrica ampliaría el emparejado de la adopción —que está cerrado desde la T4 y tiene su
     * arnés— sin que nadie lo hubiera pedido.
     *
     * ▶ Vive aquí porque la hacen **dos módulos**: Booking al proponer una respuesta sobre una ficha
     * y la PUERTA al cruzar un justificante firmado con la lista del anfitrión. Repetida en los dos,
     * divergiría en el primer arreglo.
     */
    public static function cardMatches(string $cardKey, string $fullKey): bool
    {
        if ($cardKey === '') {
            return false;
        }

        // `explode()` nunca devuelve lista vacía: sin `?? ''`, que afirmaría lo contrario.
        return $cardKey === $fullKey || $cardKey === self::for(explode(' ', $fullKey)[0]);
    }

    /** Espacios colapsados a uno y recortados por los extremos. */
    private static function collapse(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
