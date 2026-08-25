<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Exceptions\ImmutableRecordException;
use App\Domain\Identity\Services\WaiverSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fase 6 · waiver — el REGISTRO PROBATORIO de una aceptación (`docs/specs/waiver-probatorio.md`
 * §4.3): quién (el titular; o el titular en nombre de un menor a cargo), qué versión exacta del
 * texto, cuándo y con qué zona horaria, desde dónde (ip/user-agent), por qué canal y —si es un alta
 * presencial— qué operador la DECLARÓ (§8.4: no finge ser una firma del titular).
 *
 * Tres propiedades que son el subsistema entero:
 *  - **Append-only**: `updating`/`deleting` lanzan. Las únicas salidas son la poda por plazo, que
 *    `pruning()` autoriza fila a fila (`Prunable`), y la limpieza de go-live por `DB::table`.
 *  - **Hash canónico + cadena POR TITULAR** (§4.7, §8.5): `hash` cubre todos los campos de la
 *    prueba en un orden fijo; `prev_hash` enlaza con la firma anterior del MISMO titular. La
 *    serialización la pone `WaiverSigner` (lock de la fila del titular). Que la cadena no se
 *    bifurque bajo concurrencia lo mide `waiver:verify-chain` sobre MySQL real.
 *  - **Sobrevive a `User::anonymize()`** (§4.6, `RGPD-01`): conservación con tratamiento
 *    restringido — fuera de toda superficie normal, con permiso propio y consulta auditada.
 *
 * ⚠️ No se persiste «retención_hasta»: el plazo es un ajuste por instalación y retroactivo, y una
 * columna que haya que reescribir no cabe en una fila que no se actualiza. Se aplica en `prunable()`.
 */
class WaiverSignature extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    public const SUBJECT_HOLDER = 'holder';

    public const SUBJECT_DEPENDENT = 'dependent';

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_API = 'api';

    public const CHANNEL_PANEL = 'panel';

    public const CHANNELS = [self::CHANNEL_WEB, self::CHANNEL_API, self::CHANNEL_PANEL];

    /** Versión del esquema canónico del hash. Subirla obliga a seguir verificando con la anterior. */
    public const CANONICAL_VERSION = 1;

    /**
     * Campos que entran en el hash, EN ESTE ORDEN. Fijados desde el primer commit (spec §4.7):
     * cambiarlos invalida la verificación de todo lo ya firmado.
     *
     * @var list<string>
     */
    public const HASHED_FIELDS = [
        'user_id', 'subject_type', 'subject_id', 'legal_document_version_id', 'document_hash',
        'accepted_at', 'accepted_tz', 'ip', 'user_agent', 'channel', 'declared_by_user_id', 'prev_hash',
    ];

    protected $guarded = [];

    protected $casts = [
        'accepted_at' => 'datetime',
        'subject_id' => 'integer',
        'declared_by_user_id' => 'integer',
    ];

    /** Solo la poda por plazo puede borrar; lo enciende `pruning()` justo antes de `delete()`. */
    private bool $pruneAuthorised = false;

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            throw ImmutableRecordException::for(self::class, 'update');
        });
        static::deleting(function (self $row): void {
            if (! $row->pruneAuthorised) {
                throw ImmutableRecordException::for(self::class, 'delete');
            }
        });
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LegalDocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_user_id');
    }

    public function isDeclaredByOperator(): bool
    {
        return $this->declared_by_user_id !== null;
    }

    public function isForHolder(): bool
    {
        return $this->subject_type === self::SUBJECT_HOLDER;
    }

    // ─── Hash canónico ────────────────────────────────────────────────────────

    public function verifyHash(): bool
    {
        return hash_equals((string) $this->hash, self::computeHash($this->getAttributes()));
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public static function computeHash(array $attributes): string
    {
        return hash('sha256', self::canonical($attributes));
    }

    /**
     * Serialización canónica: `v` + los `HASHED_FIELDS` en orden, con `accepted_at` en UTC ISO-8601
     * (segundos) y los ids como enteros — una fila leída de BD trae strings y una recién construida
     * trae ints, y las dos tienen que dar el mismo hash.
     *
     * @param  array<string,mixed>  $attributes
     */
    public static function canonical(array $attributes): string
    {
        $payload = ['v' => self::CANONICAL_VERSION];
        foreach (self::HASHED_FIELDS as $field) {
            $value = $attributes[$field] ?? null;
            $payload[$field] = match (true) {
                $value === null => null,
                $field === 'accepted_at' => Carbon::parse($value)->utc()->format('Y-m-d\TH:i:s\Z'),
                in_array($field, ['user_id', 'subject_id', 'legal_document_version_id', 'declared_by_user_id'], true) => (int) $value,
                default => (string) $value,
            };
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    // ─── Poda por plazo (§4.6) ────────────────────────────────────────────────

    /**
     * Sin plazo fijado NO se poda nada: la conservación sin plazo se decide, no se improvisa
     * (`waiver.retention_months`, `[PENDIENTE: owner]`). Con plazo: las firmas del TITULAR más
     * antiguas que él. Las de menores a cargo esperan a que su plazo —que puede empezar a contar a
     * los 18— exista en `menores-a-cargo.md`.
     *
     * @return Builder<WaiverSignature>
     */
    public function prunable(): Builder
    {
        $months = WaiverSettings::retentionMonths();
        if ($months === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->where('subject_type', self::SUBJECT_HOLDER)
            ->where('accepted_at', '<', now()->subMonths($months));
    }

    protected function pruning(): void
    {
        $this->pruneAuthorised = true;
    }
}
