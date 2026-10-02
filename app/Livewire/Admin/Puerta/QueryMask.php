<?php

namespace App\Livewire\Admin\Puerta;

/**
 * **«Resultado para», enmascarado** (`docs/specs/puerta-nueva.md` §4.2·D5): la cola ve la pantalla, así que el eco de lo
 * tecleado nunca enseña el correo ni el teléfono ENTEROS. Se enmascara en el SERVIDOR, al guardar el eco en el estado de
 * Livewire: hacerlo en el navegador dejaría el dato entero en el DOM y en el snapshot.
 *
 * Lo justo para que el empleado reconozca su búsqueda: las dos primeras letras del usuario y el dominio
 * («an•••@correo.es»), o las tres últimas cifras («••• ••• 678»).
 */
final class QueryMask
{
    public static function email(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($user, 0, 2).'•••@'.$domain;
    }

    public static function phone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        return '••• ••• '.substr($digits, -3);
    }
}
