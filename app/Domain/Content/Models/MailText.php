<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UN TEXTO DE CORREO DEL PARQUE (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): lo que el parque escribió en el panel
 * para UNA clave de texto (`emails.order_confirmation.intro`) en UN idioma. Sin fila, el de fábrica del producto.
 *
 * ⚠️ Nunca se escribe por aquí directamente: la única puerta es `MailTexts::guardar()`, que valida contra el texto de
 * fábrica (las variables, la negrita, los topes) y deja el rastro. El cargador de textos (`MailTextLoader`) vuelve a
 * comprobar las variables al leer, así que una fila torcida por otra puerta sale de fábrica y no rompe un correo.
 *
 * @property int $id
 * @property string $key
 * @property string $locale
 * @property string $text
 * @property int|null $updated_by
 */
class MailText extends Model
{
    protected $table = 'mail_texts';

    protected $fillable = ['key', 'locale', 'text', 'updated_by'];

    protected $casts = [
        'updated_by' => 'integer',
    ];
}
