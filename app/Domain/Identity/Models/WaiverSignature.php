<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Exceptions\ImmutableRecordException;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Services\DisplayTime;
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
 *  - **Hash canónico + cadena POR (TITULAR, SUJETO)** (§4.7, §8.5; `DECISIONES #197`): `hash` cubre
 *    todos los campos de la prueba en un orden fijo; `prev_hash` enlaza con la firma anterior del
 *    MISMO sujeto —el titular, cada menor a su cargo, o cada menor invitado autorizado—, así que la
 *    poda de un sujeto nunca deja agujeros en la cadena de otro. **Qué sujeto es cada fila lo dice
 *    {@see chainKey()}, que es el ÚNICO sitio donde vive esa regla** (§4.4 de
 *    `specs/waiver-por-reserva.md`: estaba escrita a mano en tres y los tres se cruzaban con el
 *    sujeto nuevo). La serialización la pone `WaiverSigner` (lock de la fila del titular). Que dos
 *    firmas simultáneas del mismo sujeto den UNA fila lo mide `waiver:verify-chain` sobre MySQL real.
 *  - **La identidad del SUJETO viaja en la firma**: `holder_name`/`holder_email` (v2, `#161`) y, para
 *    un menor a cargo, `subject_name`/`subject_born_on` (v3, `#197`), copiados al firmar y dentro del
 *    hash. `subject_id` es FK RESTRICT a `dependents` (`menores-a-cargo.md` §4.4).
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

    /**
     * Fase 6 · el JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §4.1): un menor que
     * **no es menor a cargo** de quien reservó, autorizado por un adulto SIN cuenta desde un enlace.
     *
     * ⚠️ En estas filas `user_id` **no es quien firma**: es el RESPONSABLE, el que hizo la reserva
     * (`[DECIDIDO owner]`). Quien firma viaja en `signer_*`. Es lo que permite que `user_id` siga
     * `NOT NULL` y que la cadena siga anclada a una cuenta.
     */
    public const SUBJECT_GUEST_MINOR = 'guest_minor';

    /**
     * Los sujetos que son **de la propia cuenta**: el titular y sus menores a cargo.
     *
     * ❗ **Es la lista que evita una fuga, no una comodidad.** Con el responsable en `user_id`, las
     * firmas de menores invitados también apuntan a su cuenta — pero **no son suyas**: llevan el
     * nombre del hijo de otra familia y los datos de otro adulto. Todo lo que signifique «las firmas
     * de este titular» tiene que acotarse con esto (`User::waiverSignatures()` ya lo hace).
     *
     * @var list<string>
     */
    public const SUBJECTS_OF_HOLDER = [self::SUBJECT_HOLDER, self::SUBJECT_DEPENDENT];

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_API = 'api';

    public const CHANNEL_PANEL = 'panel';

    public const CHANNELS = [self::CHANNEL_WEB, self::CHANNEL_API, self::CHANNEL_PANEL];

    /**
     * Versión VIGENTE del esquema canónico del hash: la que escribe `WaiverSigner`. Cada fila guarda
     * la suya en `canonical_version` y se verifica con ella, así que subirla NO invalida lo firmado
     * (spec §4.7): solo añade una entrada a `HASHED_FIELDS_BY_VERSION`. Nunca se edita una entrada
     * existente.
     */
    public const CANONICAL_VERSION = 4;

    /**
     * Campos que entran en el hash, EN ESTE ORDEN, por versión del esquema.
     *  - v1 (2026-08-25): la fila probatoria original.
     *  - v2 (2026-08-26, `DECISIONES #161`): + la IDENTIDAD del firmante tal y como estaba al firmar
     *    (`holder_name`, `holder_email`), para que la prueba siga identificando a la persona después
     *    de `User::anonymize()`.
     *  - v3 (2026-08-27, `DECISIONES #197`): + la IDENTIDAD del SUJETO cuando es un menor a cargo
     *    (`subject_name`, `subject_born_on`; `null` en las firmas del titular), por la misma razón.
     *  - v4 (2026-09-01, `specs/waiver-por-reserva.md` §4.3): + el SUJETO nuevo
     *    (`subject_authorization_id`) y la IDENTIDAD DE QUIEN FIRMA (`signer_*`), que en un
     *    justificante de menor invitado **no es el titular de la cuenta**. `subject_name` y
     *    `subject_born_on` se reutilizan tal cual: significan lo mismo —el menor— en las dos clases.
     *
     * ⚠️ **No entra `signer_user_id`, y no es un olvido**: una columna con `ON DELETE SET NULL` no
     * puede vivir dentro de un hash que se verifica. Está MEDIDO en este mismo repo con
     * `declared_by_user_id` —borrar la fila del operador lo pone a `NULL` y `verifyHash()` pasa de
     * `true` a `false` sobre una firma que nadie tocó (`DEUDA.md`)—. El vínculo con la cuenta del
     * firmante, si algún día hace falta, va FUERA del hash o no va.
     *
     * @var array<int, list<string>>
     */
    public const HASHED_FIELDS_BY_VERSION = [
        1 => [
            'user_id', 'subject_type', 'subject_id', 'legal_document_version_id', 'document_hash',
            'accepted_at', 'accepted_tz', 'ip', 'user_agent', 'channel', 'declared_by_user_id', 'prev_hash',
        ],
        2 => [
            'user_id', 'subject_type', 'subject_id', 'legal_document_version_id', 'document_hash',
            'accepted_at', 'accepted_tz', 'ip', 'user_agent', 'channel', 'declared_by_user_id', 'prev_hash',
            'holder_name', 'holder_email',
        ],
        3 => [
            'user_id', 'subject_type', 'subject_id', 'legal_document_version_id', 'document_hash',
            'accepted_at', 'accepted_tz', 'ip', 'user_agent', 'channel', 'declared_by_user_id', 'prev_hash',
            'holder_name', 'holder_email', 'subject_name', 'subject_born_on',
        ],
        4 => [
            'user_id', 'subject_type', 'subject_id', 'subject_authorization_id', 'legal_document_version_id',
            'document_hash', 'accepted_at', 'accepted_tz', 'ip', 'user_agent', 'channel',
            'declared_by_user_id', 'prev_hash', 'holder_name', 'holder_email', 'subject_name',
            'subject_born_on', 'signer_name', 'signer_email', 'signer_phone', 'signer_relationship',
        ],
    ];

    /**
     * Los campos de la versión vigente.
     *
     * @var list<string>
     */
    public const HASHED_FIELDS = self::HASHED_FIELDS_BY_VERSION[self::CANONICAL_VERSION];

    protected $guarded = [];

    protected $casts = [
        'accepted_at' => 'datetime',
        'subject_born_on' => 'immutable_date',
        'subject_id' => 'integer',
        'subject_authorization_id' => 'integer',
        'declared_by_user_id' => 'integer',
        'canonical_version' => 'integer',
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

    /**
     * El menor a cargo en cuyo nombre se firmó (`null` en las firmas del titular). La fila existe
     * mientras exista la firma (FK RESTRICT); su identidad de entonces está copiada en la propia fila.
     *
     * @return BelongsTo<Dependent, $this>
     */
    public function dependent(): BelongsTo
    {
        return $this->belongsTo(Dependent::class, 'subject_id');
    }

    /**
     * La autorización del menor INVITADO que esta firma prueba (`null` en las otras dos clases). La
     * fila existe mientras exista la firma (FK RESTRICT); su identidad de entonces está copiada en la
     * propia fila, igual que la del titular y la del menor a cargo.
     *
     * @return BelongsTo<GuardianAuthorization, $this>
     */
    public function authorization(): BelongsTo
    {
        return $this->belongsTo(GuardianAuthorization::class, 'subject_authorization_id');
    }

    public function isDeclaredByOperator(): bool
    {
        return $this->declared_by_user_id !== null;
    }

    public function isForHolder(): bool
    {
        return $this->subject_type === self::SUBJECT_HOLDER;
    }

    /** ¿Es el justificante de un menor INVITADO, o sea una firma que NO es del titular de la cuenta? */
    public function isForGuestMinor(): bool
    {
        return $this->subject_type === self::SUBJECT_GUEST_MINOR;
    }

    // ─── La CLAVE DE CADENA — un solo sitio (§4.4) ────────────────────────────

    /**
     * La clave que identifica **a qué cadena pertenece** esta firma.
     *
     * ❗❗ **Existe porque hasta esta tanda estaba escrita a mano en tres sitios** —`WaiverSigner`,
     * `WaiverChain` y `VerifyWaiverChainConcurrency`, los dos últimos con el literal duplicado— **y
     * los tres se cruzaban con el sujeto nuevo**, que no usa `subject_id`:
     *
     *  - `WaiverSigner` buscaba la firma anterior con `where('subject_id', $id)`; con `null` Laravel
     *    genera `is null`, así que **todas** las autorizaciones del mismo responsable compartían
     *    búsqueda y la idempotencia por versión devolvía **la firma de OTRO menor**: el segundo padre
     *    veía la pantalla de «hecho», recibía su correo y **su hijo se quedaba sin justificante**, sin
     *    fallo y sin aviso.
     *  - `WaiverChain` agrupaba por `subject_type.':'.($subject_id ?? '')`, así que todos los menores
     *    invitados caían en `guest_minor:` y el verificador declaraba **ROTA una cadena sana**.
     *
     * ▶ *Que un mecanismo admita un caso nuevo no es que lo admita: hay que mirar de qué columna
     * cuelga cada decisión que ya toma.*
     *
     * El formato de `holder` y `dependent` **no cambia** a propósito: la salida de los verificadores
     * sigue siendo la misma cadena de texto que antes.
     */
    public function chainKey(): string
    {
        return self::chainKeyFor($this->subject_type, $this->subject_id, $this->subject_authorization_id);
    }

    public static function chainKeyFor(string $subjectType, ?int $subjectId, ?int $authorizationId): string
    {
        return $subjectType === self::SUBJECT_GUEST_MINOR
            ? self::SUBJECT_GUEST_MINOR.':a'.(int) $authorizationId
            : $subjectType.':'.($subjectId ?? '');
    }

    /**
     * El lado CONSULTA de {@see chainKey()}: acota a las firmas de ESA cadena.
     *
     * Va aquí, junto a la clave, porque separar «cómo se agrupa» de «cómo se busca» es exactamente
     * cómo se produjo el defecto de arriba: eran la misma regla escrita dos veces.
     *
     * @param  Builder<WaiverSignature>  $query
     */
    public function scopeInChain(Builder $query, string $subjectType, ?int $subjectId, ?int $authorizationId): void
    {
        $query->where('subject_type', $subjectType);

        if ($subjectType === self::SUBJECT_GUEST_MINOR) {
            $query->where('subject_authorization_id', $authorizationId);

            return;
        }

        $query->where('subject_id', $subjectId);
    }

    /** El nombre del firmante TAL Y COMO ESTABA al firmar (v2); las filas v1 caen a la cuenta. */
    public function holderName(): ?string
    {
        return $this->holder_name ?? $this->user?->name;
    }

    public function holderEmail(): ?string
    {
        return $this->holder_email ?? $this->user?->email;
    }

    /** El nombre del menor TAL Y COMO ESTABA al firmar (v3); `null` en las firmas del titular. */
    public function subjectName(): ?string
    {
        if ($this->isForHolder()) {
            return null;
        }

        // Un justificante de menor invitado SIEMPRE nace con el nombre copiado (v4): no hay respaldo
        // que buscar, y buscarlo en `dependents` con `subject_id = null` daría siempre `null`.
        if ($this->isForGuestMinor()) {
            return $this->subject_name;
        }

        return $this->subject_name ?? $this->dependent?->name;
    }

    /** Quién FIRMÓ, cuando no es el titular de la cuenta (v4): el padre, la madre o el tutor. */
    public function signerName(): ?string
    {
        return $this->signer_name;
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
     * Serialización canónica: `v` + los campos de ESA versión en orden, con `accepted_at` en UTC
     * ISO-8601 (segundos) y los ids como enteros — una fila leída de BD trae strings y una recién
     * construida trae ints, y las dos tienen que dar el mismo hash. La versión sale de la propia
     * fila (`canonical_version`); sin ella, la vigente.
     *
     * @param  array<string,mixed>  $attributes
     */
    public static function canonical(array $attributes): string
    {
        $version = (int) ($attributes['canonical_version'] ?? self::CANONICAL_VERSION);
        $fields = self::HASHED_FIELDS_BY_VERSION[$version] ?? null;
        if ($fields === null) {
            throw new \InvalidArgumentException("Versión canónica desconocida: {$version}.");
        }

        $payload = ['v' => $version];
        foreach ($fields as $field) {
            $value = $attributes[$field] ?? null;
            $payload[$field] = match (true) {
                $value === null => null,
                $field === 'accepted_at' => Carbon::parse($value)->utc()->format('Y-m-d\TH:i:s\Z'),
                // Una fecha SIN hora: la fila leída de SQLite trae «Y-m-d 00:00:00», la de MySQL «Y-m-d» y
                // la recién construida un Carbon o la cadena; las tres tienen que dar «Y-m-d».
                $field === 'subject_born_on' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : mb_substr((string) $value, 0, 10),
                in_array($field, ['user_id', 'subject_id', 'subject_authorization_id', 'legal_document_version_id', 'declared_by_user_id'], true) => (int) $value,
                default => (string) $value,
            };
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    // ─── Poda por plazo (§4.6) ────────────────────────────────────────────────

    /**
     * Sin plazo fijado NO se poda nada: la conservación sin plazo se decide, no se improvisa
     * (`[PENDIENTE: owner]` los dos valores). Dos plazos, uno por clase de sujeto (`DECISIONES #197`):
     *  - **titular** (`waiver.retention_months`): las firmas más antiguas que N meses desde su fecha;
     *  - **menor a cargo** (`waiver.dependent_retention_months`): N meses DESPUÉS de su 18.º cumpleaños
     *    —un niño de 3 puede implicar conservar 15 años—, calculado sobre la fecha de nacimiento copiada
     *    en la propia firma: `subject_born_on <= hoy − 18 años − N meses`.
     * Como la cadena es por (titular, sujeto), podar una clase nunca rompe la cadena de la otra.
     *
     * @return Builder<WaiverSignature>
     */
    public function prunable(): Builder
    {
        $holderMonths = WaiverSettings::retentionMonths();
        $dependentMonths = WaiverSettings::dependentRetentionMonths();

        if ($holderMonths === null && $dependentMonths === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where(function (Builder $query) use ($holderMonths, $dependentMonths): void {
            if ($holderMonths !== null) {
                $query->orWhere(fn (Builder $holder) => $holder
                    ->where('subject_type', self::SUBJECT_HOLDER)
                    ->where('accepted_at', '<', now()->subMonths($holderMonths)));
            }
            if ($dependentMonths !== null) {
                $cutoffBornOn = DisplayTime::today()->subYears(Dependent::ADULT_AGE)->subMonths($dependentMonths)->toDateString();
                // ⚠️ Las DOS clases de menor comparten plazo, y no es pereza: la razón jurídica es la
                // misma —el sujeto es un menor, así que se cuenta desde sus 18— y un segundo ajuste
                // sería un valor más que el owner tendría que decidir para obtener el mismo resultado.
                // Como la cadena es por sujeto, podar una clase nunca deja agujeros en la otra.
                $query->orWhere(fn (Builder $minor) => $minor
                    ->whereIn('subject_type', [self::SUBJECT_DEPENDENT, self::SUBJECT_GUEST_MINOR])
                    ->whereNotNull('subject_born_on')
                    ->where('subject_born_on', '<=', $cutoffBornOn));
            }
        });
    }

    protected function pruning(): void
    {
        $this->pruneAuthorised = true;
    }
}
