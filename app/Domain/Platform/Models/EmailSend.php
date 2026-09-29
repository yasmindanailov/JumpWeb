<?php

namespace App\Domain\Platform\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * **Un correo AL CLIENTE que salió, o que intentó salir** (`specs/correos-salientes.md` §4.1, `DECISIONES #794`, la C1).
 *
 * Lo escribe SOLO `Platform\Listeners\RecordEmailSend` a partir de los eventos del framework, nunca una petición: por eso no
 * tiene lista de rellenables. La clave (`send_key`) es el id de la notificación, uno por destinatario y el mismo en cada
 * reintento de la cola.
 *
 * ⚠️ **Dos plazos** (`#794`, `[DECIDIDO owner]`): la COPIA de lo que salió (`html`, `subject`, `attachments`) se borra a los
 * {@see COPY_MONTHS} meses —tres correos llevan el nombre de un menor— y la fila, con lo que dice (qué correo, a quién, cuándo,
 * si falló), a los {@see RETENTION_MONTHS}. Y la supresión del titular (`RGPD-01`) se lleva lo personal en el acto:
 * {@see forgetPerson()}.
 *
 * @property bool $tracks_clicks si salió con la marca del envío en sus enlaces (la C2)
 * @property bool $tracks_opens si salió con el píxel de apertura (la C3)
 * @property-read int|null $clicks_counted con {@see scopeWithClickCounts()}: los clics de una persona
 * @property-read int|null $clicks_scanner con {@see scopeWithClickCounts()}: las visitas de un escáner
 * @property-read int|null $opens_counted con {@see scopeWithOpenCounts()}: las aperturas que cuentan
 * @property-read int|null $opens_automatic con {@see scopeWithOpenCounts()}: las de una máquina (Apple, antes de poder leerlo)
 */
class EmailSend extends Model
{
    use MassPrunable;

    /**
     * La cabecera que dice QUÉ ENVÍO es un correo: el id de la notificación, sin nada de la persona. La pone el molde
     * (`BrandedMailMessage`) y la lee `RecordEmailSend` del mensaje que salió.
     */
    public const HEADER = 'X-JumpWeb-Send';

    /** Meses que vive la fila con sus cifras. */
    public const RETENTION_MONTHS = 24;

    /** Meses que vive la COPIA del correo (lo que permite verlo tal cual). */
    public const COPY_MONTHS = 6;

    protected $guarded = [];

    protected $casts = [
        'user_id' => 'integer',
        'attachments' => 'array',
        'failures' => 'integer',
        'tracks_clicks' => 'boolean',
        'tracks_opens' => 'boolean',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'copy_purged_at' => 'datetime',
    ];

    /**
     * La cuenta que lo recibió, si la hay (sin FK a propósito: ver la migración).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Las visitas que llegaron con su marca (la C2, §4.8): cuentan las que no llevan veredicto.
     *
     * @return HasMany<EmailClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(EmailClick::class);
    }

    /**
     * Con sus dos recuentos de clics (la C2): `clicks_counted`, los de una persona, y `clicks_scanner`, los de un escáner
     * (`EmailClick::SCANNER_VERDICTS`). Una sola forma de contarlos para la lista, la ficha y el export.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithClickCounts(Builder $query): Builder
    {
        return $query->withCount([
            'clicks as clicks_counted' => static fn (Builder $clicks) => $clicks->whereNull('verdict'),
            'clicks as clicks_scanner' => static fn (Builder $clicks) => $clicks->whereIn('verdict', EmailClick::SCANNER_VERDICTS),
        ]);
    }

    /**
     * Las veces que se pidió su píxel (la C3, §4.12): cuentan las que no llevan veredicto.
     *
     * @return HasMany<EmailOpen, $this>
     */
    public function opens(): HasMany
    {
        return $this->hasMany(EmailOpen::class);
    }

    /**
     * Con sus dos recuentos de aperturas (la C3): `opens_counted`, las que cuentan, y `opens_automatic`, las de una máquina
     * (`EmailOpen::AUTOMATIC_VERDICTS`). Las repeticiones de una misma lectura no están en ninguno.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithOpenCounts(Builder $query): Builder
    {
        return $query->withCount([
            'opens as opens_counted' => static fn (Builder $opens) => $opens->whereNull('verdict'),
            'opens as opens_automatic' => static fn (Builder $opens) => $opens->whereIn('verdict', EmailOpen::AUTOMATIC_VERDICTS),
        ]);
    }

    /** ¿Salió? (Pudo fallar antes y salir en un reintento: entonces también.) */
    public function wasSent(): bool
    {
        return $this->sent_at !== null;
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    /** ¿Se puede ver todavía tal cual salió? */
    public function hasCopy(): bool
    {
        return $this->html !== null && $this->html !== '';
    }

    /**
     * Borra la COPIA de los correos de más de {@see COPY_MONTHS} meses; la fila y sus cifras se quedan. Devuelve cuántas.
     * Con `toBase()`: es mantenimiento, no un cambio del envío.
     */
    public static function purgeOldCopies(): int
    {
        return static::query()
            ->whereNull('copy_purged_at')
            ->where('created_at', '<', now()->subMonths(self::COPY_MONTHS))
            ->toBase()
            ->update(['html' => null, 'subject' => null, 'attachments' => null, 'copy_purged_at' => now(), 'updated_at' => now()]);
    }

    /**
     * **La supresión del titular** (`RGPD-01`): sus envíos pierden lo personal —la copia, el asunto y la dirección— y a él;
     * las cifras quedan, sin nadie. Lo llama `User::anonymize()` dentro de su transacción.
     */
    public static function forgetPerson(int $userId): void
    {
        DB::table('email_sends')->where('user_id', $userId)->update([
            'user_id' => null, 'recipient' => null, 'subject' => null, 'html' => null, 'attachments' => null,
            'copy_purged_at' => now(), 'updated_at' => now(),
        ]);
    }
}
