<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La identidad EXTERNA de una cuenta: «esta persona es también este `sub` de este proveedor»
 * (`docs/specs/auth-con-google.md` §6.2).
 *
 * ⚠️ **No es una credencial.** Con lo que hay en esta fila no se entra a ninguna parte: entrar exige
 * que Google diga, en una petición servidor-a-servidor autenticada con nuestro secreto, que quien
 * está al otro lado es ese `sub` — eso lo comprueba el servicio `GoogleOAuth`, y aquí se nombra en
 * prosa a propósito: una anotación resoluble haría que Pint la convirtiera en un `use`, y un modelo
 * no importa un servicio para una cita (la trampa de `#320`). La fila solo dice a qué cuenta
 * pertenece esa afirmación cuando llega.
 *
 * ⚠️ **La clave es el `sub`, jamás el correo** (§6.1): el correo de una cuenta de Google se puede
 * cambiar, y una identidad que dependiera de él cambiaría de dueño con él. `email_at_link` está aquí
 * como COPIA probatoria —con qué dirección se estableció el vínculo—, no como forma de buscar.
 *
 * La fila **no se edita**: no tiene `updated_at` y ningún servicio la actualiza. Desvincular es
 * borrarla; volver a vincular es crear otra, y entonces `linked_at` vuelve a decir la verdad.
 */
#[Fillable(['user_id', 'provider', 'provider_id', 'email_at_link', 'linked_via', 'linked_at'])]
class UserIdentity extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'linked_at';

    /** El único proveedor de hoy. La columna es genérica para que Apple no exija otra migración. */
    public const PROVIDER_GOOGLE = 'google';

    /** El vínculo nació con el alta que Google originó (§5.3). */
    public const VIA_SIGNUP = 'signup';

    /** El vínculo nació al entrar: la cuenta ya existía con ese correo (§5.2, vinculación automática). */
    public const VIA_LOGIN = 'login';

    /** El titular lo pidió desde su cuenta (T3). */
    public const VIA_ACCOUNT = 'account';

    /** @var list<string> */
    public const VIAS = [self::VIA_SIGNUP, self::VIA_LOGIN, self::VIA_ACCOUNT];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
