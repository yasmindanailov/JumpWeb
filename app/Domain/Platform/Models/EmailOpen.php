<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * **Una vez que se pidió el píxel de un envío** (`specs/correos-salientes.md` §4.12, `#797`, la C3): cuándo, desde dónde
 * (su ORIGEN) y si cuenta. La escribe SOLO `Services\Analytics\EmailOpens`; por eso no tiene lista de rellenables.
 *
 * ⚠️⚠️ **Una apertura no es una lectura** y el origen dice cuánto se parece:
 * - {@see SOURCE_APPLE}: Apple Mail descarga las imágenes AL ENTREGAR, desde sus servidores y con el agente `Mozilla/5.0` a
 *   secas; la lectura de verdad sale de la memoria del aparato y su hora no se puede saber. Nunca cuenta.
 * - {@see SOURCE_GMAIL}: el proxy de Gmail pide la imagen al abrirse (la primera hora es real) y la guarda: las reaperturas
 *   no llegan.
 * - {@see SOURCE_DIRECT}: el resto, con la clase del aparato si se sabe.
 * Fuentes y medidas: spec §4.10 (b).
 */
class EmailOpen extends Model
{
    public const SOURCE_APPLE = 'apple';

    public const SOURCE_GMAIL = 'gmail';

    public const SOURCE_DIRECT = 'direct';

    /** De Apple: de máquina, al entregar. */
    public const VERDICT_APPLE = 'apple';

    /** Antes de que nadie pudiera abrirlo: {@see EARLY_SECONDS} tras el envío. */
    public const VERDICT_EARLY = 'early';

    /** Otra petición dentro de {@see WINDOW_SECONDS} de una que contó: la misma lectura (el gestor repinta el correo). */
    public const VERDICT_REPEAT = 'repeat';

    /** Los veredictos de una máquina (lo que el panel enseña aparte, como «automáticas»). */
    public const AUTOMATIC_VERDICTS = [self::VERDICT_APPLE, self::VERDICT_EARLY];

    /** Segundos tras el envío en los que una apertura es de una máquina. */
    public const EARLY_SECONDS = 10;

    /** Segundos en los que otra petición es la misma lectura. */
    public const WINDOW_SECONDS = 60;

    /** Tope de peticiones apuntadas por envío y minuto: lo de más no se apunta (el píxel no tiene limitador por IP). */
    public const FLOOD_PER_MINUTE = 20;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'email_send_id' => 'integer',
        'opened_at' => 'datetime',
    ];

    /** El origen por el agente de usuario: Apple (a secas), el proxy de Gmail o el gestor directamente. */
    public static function sourceOf(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);

        return match (true) {
            $ua === 'Mozilla/5.0' => self::SOURCE_APPLE,
            str_contains($ua, 'GoogleImageProxy') => self::SOURCE_GMAIL,
            default => self::SOURCE_DIRECT,
        };
    }

    /** @return BelongsTo<EmailSend, $this> */
    public function send(): BelongsTo
    {
        return $this->belongsTo(EmailSend::class, 'email_send_id');
    }
}
