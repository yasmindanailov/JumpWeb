<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * **Una visita que llegó con la marca de un envío** (`specs/correos-salientes.md` §4.8, la C2): qué enlace del correo y
 * cuándo. La escribe SOLO `Services\Analytics\EmailClicks`; por eso no tiene lista de rellenables.
 *
 * ⚠️ **Cuenta la que no lleva veredicto.** Los escáneres de correo (Safe Links, Mimecast, Proofpoint…) visitan TODOS los
 * enlaces al entregarse el correo y con un agente de navegador falso, así que se reconocen por el RITMO, no por quién dicen
 * ser: {@see VERDICT_EARLY}, {@see VERDICT_SWEEP} y {@see VERDICT_REPEAT} (§4.8 (6), con sus fuentes).
 */
class EmailClick extends Model
{
    /** El mismo enlace del mismo envío dentro de la ventana: el mismo clic (un doble clic, el doble paso de Safe Links). */
    public const VERDICT_REPEAT = 'repeat';

    /** Antes de que nadie pudiera abrir el correo y pulsar: {@see EARLY_SECONDS} tras el envío. */
    public const VERDICT_EARLY = 'early';

    /** La ráfaga de un escáner: {@see SWEEP_HITS} visitas o más al mismo envío dentro de la ventana. */
    public const VERDICT_SWEEP = 'sweep';

    /** Los veredictos de un escáner (lo que el panel enseña como «de escáner»). */
    public const SCANNER_VERDICTS = [self::VERDICT_EARLY, self::VERDICT_SWEEP];

    /** Segundos de la ventana en la que se miran la ráfaga y la repetición. */
    public const WINDOW_SECONDS = 30;

    /** Segundos tras el envío en los que una visita es de una máquina. */
    public const EARLY_SECONDS = 10;

    /** Visitas al mismo envío dentro de la ventana que hacen una ráfaga. */
    public const SWEEP_HITS = 3;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'email_send_id' => 'integer',
        'clicked_at' => 'datetime',
    ];

    /** @return BelongsTo<EmailSend, $this> */
    public function send(): BelongsTo
    {
        return $this->belongsTo(EmailSend::class, 'email_send_id');
    }
}
