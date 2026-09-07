<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Exceptions\DependentHasReferencesException;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Fase 6 · menores a cargo — una PERSONA A CARGO declarada por un titular
 * (`docs/specs/menores-a-cargo.md` §4.1–§4.4).
 *
 * Se llama `Dependent` y no `Minor` a propósito (§4.1): un menor añadido con 5 años tiene 18 dentro
 * de trece, y la fila SOBREVIVE a la minoría de edad — la lista lo marca como «ya no cubierto»,
 * nadie lo borra por un cumpleaños. `isMinor()` se DERIVA; la edad no existe como columna (§4.2).
 *
 * Tres reglas que son la entidad entera:
 *  - **Nombre, apellidos, relación y fecha de nacimiento** (`[DECIDIDO owner]`, `DECISIONES #142`
 *    y **`#236`**). Nació como «nombre y fecha, nada más» y el owner añadió los apellidos —para
 *    identificar sin ambigüedad a quien no conoce a la familia— y la **relación** del adulto con
 *    el menor, que es lo que sostiene que pueda firmar por él.
 *    ⚠️ **La puerta enseña el NOMBRE y la edad, nunca los apellidos** (`#236`): distinguir a un
 *    niño de otro en el mostrador no necesita el apellido, y lo que no hace falta no se enseña.
 *  - **Quitar es desvincular, no borrar, si hay algo detrás** (§4.4): con un waiver firmado —o,
 *    desde la tanda 4, una entrada asignada— la fila se queda con `removed_at` y sale de todas las
 *    listas; sin referencias se borra de verdad. `deleting` lo hace cumplir venga de donde venga.
 *  - **Sigue el régimen de su waiver en `User::anonymize()`** (§5, `RGPD-01`): con firma se conserva
 *    desvinculada bajo el mismo tratamiento restringido; sin ella se borra. Y cuando la poda por
 *    plazo se lleva su última firma, `prunable()` retira la fila huérfana: PII de un menor sin nada
 *    que la justifique.
 *
 * ⚠️ **No pertenece a Booking** (§4.6): la asignación de una entrada la POSEE Identity
 * (`DependentAssignment`, tanda 4) y referencia el ítem por su id ENTERO (`ModuleBoundariesTest`:
 * Booking no ve a Identity; Identity lee las líneas por `Booking\Contracts\CheckoutLines`).
 */
#[Fillable(['user_id', 'name', 'surname', 'relationship', 'born_on'])]
class Dependent extends Model
{
    use Prunable;

    /** La edad a la que el waiver del adulto deja de cubrir a la persona a cargo (§4.1). */
    public const ADULT_AGE = 18;

    public const NAME_MAX = 120;

    public const SURNAME_MAX = 120;

    /**
     * Relación del TITULAR con la persona a cargo (`#236`, `[DECIDIDO owner]`: lista fija).
     *
     * ⚠️ Lista cerrada y no texto libre a propósito: con texto libre acaban conviviendo veinte
     * formas de escribir «madre» y deja de poder contarse ni filtrarse. Y no configurable desde el
     * panel porque sus rótulos son texto de producto en tres idiomas (`admin`/`tickets`), no un
     * dato del negocio: un parque no tiene una relación de parentesco distinta de otro.
     *
     * `other` existe para no obligar a mentir: quien no encaje elige eso y sigue adelante.
     *
     * @var array<int, string>
     */
    public const RELATIONSHIPS = ['father', 'mother', 'legal_guardian', 'grandparent', 'other'];

    /**
     * Nombre y apellidos, con el espacio SOLO si hay apellidos (`#236`).
     *
     * ⚠️ Existe para que nadie concatene a mano: las fichas de antes de `#236` no tienen apellidos,
     * y un `$name.' '.$surname` suelto deja un espacio final que luego aparece en un PDF probatorio
     * o en el rótulo de un desplegable.
     */
    public function fullName(): string
    {
        $surname = trim((string) $this->surname);

        return $surname !== '' ? trim((string) $this->name).' '.$surname : trim((string) $this->name);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'born_on' => 'immutable_date',
            'removed_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        // §4.4 como GUARDA y no como convención: una fila con un waiver firmado detrás no se borra
        // desde ningún sitio —ni el titular, ni `anonymize()`, ni un `delete()` suelto—. Quien
        // quiera retirarla la DESVINCULA (`unlink()`).
        static::deleting(function (self $row): void {
            if ($row->hasReferences()) {
                throw DependentHasReferencesException::for($row);
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
     * Las firmas del waiver hechas EN SU NOMBRE por el titular (`waiver-probatorio.md` §4.3: el
     * «sujeto» de la firma). Las escribe `WaiverSigner` desde la tanda 2 (`#198`).
     *
     * @return HasMany<WaiverSignature, $this>
     */
    public function waiverSignatures(): HasMany
    {
        return $this->hasMany(WaiverSignature::class, 'subject_id')
            ->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT);
    }

    /**
     * Las ENTRADAS que le han asignado (tanda 4, §4.7–§4.10): la otra referencia que conserva la fila.
     * Las escribe `DependentAssigner`.
     *
     * @return HasMany<DependentAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DependentAssignment::class);
    }

    /**
     * Las filas con ALGO detrás que obliga a conservarlas: una firma del waiver o una entrada asignada.
     *
     * ⚠️ **Es el ÚNICO predicado de «qué cuenta como referencia», y por eso es un scope**: hasta la
     * tanda 4 `hasReferences()` y `prunable()` lo escribían cada uno por su lado, y bastaba con añadir
     * la asignación en uno y no en el otro para que la poda diaria abortara a mitad (`delete()` lanza)
     * o para que `remove()` BORRARA un menor con entradas asignadas (spec §9.9.1·5). Con un solo scope,
     * `remove()`, `anonymize()` y `model:prune` coinciden por construcción.
     *
     * @param  Builder<Dependent>  $query
     */
    public function scopeReferenced(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereExists(self::signaturesSubquery(...))
            ->orWhereExists(self::assignmentsSubquery(...)));
    }

    /**
     * El complemento exacto de {@see scopeReferenced()}: ni firma ni entrada asignada.
     *
     * @param  Builder<Dependent>  $query
     */
    public function scopeUnreferenced(Builder $query): void
    {
        $query
            ->whereNotExists(self::signaturesSubquery(...))
            ->whereNotExists(self::assignmentsSubquery(...));
    }

    private static function signaturesSubquery(QueryBuilder $q): void
    {
        $q->selectRaw('1')
            ->from('waiver_signatures')
            ->whereColumn('waiver_signatures.subject_id', 'dependents.id')
            ->where('waiver_signatures.subject_type', WaiverSignature::SUBJECT_DEPENDENT);
    }

    private static function assignmentsSubquery(QueryBuilder $q): void
    {
        $q->selectRaw('1')
            ->from('dependent_assignments')
            ->whereColumn('dependent_assignments.dependent_id', 'dependents.id');
    }

    /**
     * Las que el titular ve y puede usar: sin `removed_at`.
     *
     * @param  Builder<Dependent>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    // ─── La edad, DERIVADA (§4.2) ─────────────────────────────────────────────

    /**
     * Años cumplidos el día dado por quien nació en `$bornOn` (`Y-m-d`).
     *
     * ⚠️ Se compara FECHA con FECHA, sin horas ni zona: `born_on` no tiene hora y «hoy» es el del
     * parque (`DisplayTime::today()`, doctrina `AFORO-09`). Restar la medianoche de Madrid de una
     * medianoche UTC daría **17 años el mismo día del 18.º cumpleaños** — medido en su test.
     */
    public static function ageBetween(string $bornOn, CarbonInterface $day): int
    {
        $born = CarbonImmutable::createFromFormat('!Y-m-d', $bornOn, 'UTC');
        $on = CarbonImmutable::createFromFormat('!Y-m-d', $day->toDateString(), 'UTC');

        if ($born === null || $on === null || $on->lessThan($born)) {
            return 0;
        }

        return (int) floor($born->diffInYears($on));
    }

    public function ageOn(CarbonInterface $day): int
    {
        return self::ageBetween($this->born_on->toDateString(), $day);
    }

    public function age(): int
    {
        return $this->ageOn(DisplayTime::today());
    }

    public function isMinorOn(CarbonInterface $day): bool
    {
        return $this->ageOn($day) < self::ADULT_AGE;
    }

    public function isMinor(): bool
    {
        return $this->isMinorOn(DisplayTime::today());
    }

    /** El día en que cumple 18: desde entonces «ya no está cubierto» por el waiver del adulto (§4.1). */
    public function adultFrom(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->born_on->toDateString(), 'UTC')
            ->addYears(self::ADULT_AGE);
    }

    // ─── Referencias y retirada (§4.4) ────────────────────────────────────────

    /**
     * ¿Hay algo detrás que obligue a conservar la fila? Sus firmas del waiver, o —desde la tanda 4— una
     * entrada asignada. Es lo que decide «borrar o desvincular», y lo decide con el MISMO predicado que
     * la poda ({@see scopeReferenced()}).
     */
    public function hasReferences(): bool
    {
        return static::query()->whereKey($this->getKey())->referenced()->exists();
    }

    /**
     * Desvincular: fuera de la lista del titular y de la pantalla de puerta; el waiver y el
     * histórico siguen apuntando aquí. Idempotente.
     */
    public function unlink(): void
    {
        if ($this->removed_at === null) {
            // ⚠️ `#441` · con la fila se retira su ACEPTACIÓN RETENIDA: una aceptación de un menor
            // que ya no está no puede convertirse en firma cuando el titular verifique su correo
            // —firmaría por alguien retirado— y su IP no tiene por qué quedarse guardada. Lo que se
            // conserva es lo FIRMADO, que es prueba; lo pendiente no lo es todavía.
            $this->forceFill([
                'removed_at' => now(),
                'waiver_pending_document_id' => null,
                'waiver_pending_channel' => null,
                'waiver_pending_ip' => null,
                'waiver_pending_user_agent' => null,
            ])->save();
        }
    }

    // ─── Poda (§4.4 + §5) ─────────────────────────────────────────────────────

    /**
     * Las filas DESVINCULADAS que ya no tienen ninguna referencia: la poda por plazo se llevó su
     * última firma —o la cascada del pedido purgado, su última entrada— y lo que queda es el nombre y
     * la fecha de nacimiento de un menor sin nada que lo justifique. Una fila activa nunca se poda (es
     * del titular) y una desvinculada CON referencia tampoco: la guarda de `deleting` lanzaría, y la
     * consulta la excluye ANTES para que `model:prune` no aborte a mitad — con el mismo predicado que
     * `hasReferences()`, a propósito ({@see scopeUnreferenced()}).
     *
     * @return Builder<Dependent>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('removed_at')
            ->unreferenced();
    }
}
