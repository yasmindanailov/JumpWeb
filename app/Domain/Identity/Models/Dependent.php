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
 *  - **Nombre y fecha de nacimiento, nada más** (`[DECIDIDO owner]`, `DECISIONES #142`). El nombre
 *    es la etiqueta del titular («el que use en casa»); la puerta no lo enseña.
 *  - **Quitar es desvincular, no borrar, si hay algo detrás** (§4.4): con un waiver firmado —o,
 *    desde la tanda 4, una entrada asignada— la fila se queda con `removed_at` y sale de todas las
 *    listas; sin referencias se borra de verdad. `deleting` lo hace cumplir venga de donde venga.
 *  - **Sigue el régimen de su waiver en `User::anonymize()`** (§5, `RGPD-01`): con firma se conserva
 *    desvinculada bajo el mismo tratamiento restringido; sin ella se borra. Y cuando la poda por
 *    plazo se lleva su última firma, `prunable()` retira la fila huérfana: PII de un menor sin nada
 *    que la justifique.
 *
 * ⚠️ **No pertenece a Booking** (§4.6): la asignación de una entrada la poseerá Identity y
 * referenciará el ítem por su id ENTERO (`ModuleBoundariesTest`: Booking no ve a Identity).
 */
#[Fillable(['user_id', 'name', 'born_on'])]
class Dependent extends Model
{
    use Prunable;

    /** La edad a la que el waiver del adulto deja de cubrir a la persona a cargo (§4.1). */
    public const ADULT_AGE = 18;

    public const NAME_MAX = 120;

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
     * «sujeto» de la firma). Hoy no las produce ningún escritor: llegan en la tanda 2.
     *
     * @return HasMany<WaiverSignature, $this>
     */
    public function waiverSignatures(): HasMany
    {
        return $this->hasMany(WaiverSignature::class, 'subject_id')
            ->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT);
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
     * ¿Hay algo detrás que obligue a conservar la fila? Hoy, sus firmas del waiver.
     * ▶ Tanda 4: aquí entra también la asignación de entradas (la tabla que poseerá Identity,
     * §4.6/§4.10). Es el ÚNICO sitio que decide «borrar o desvincular».
     */
    public function hasReferences(): bool
    {
        return $this->waiverSignatures()->exists();
    }

    /**
     * Desvincular: fuera de la lista del titular y de la pantalla de puerta; el waiver y el
     * histórico siguen apuntando aquí. Idempotente.
     */
    public function unlink(): void
    {
        if ($this->removed_at === null) {
            $this->forceFill(['removed_at' => now()])->save();
        }
    }

    // ─── Poda (§4.4 + §5) ─────────────────────────────────────────────────────

    /**
     * Las filas DESVINCULADAS que ya no tienen ninguna referencia: la poda por plazo se llevó su
     * última firma y lo que queda es el nombre y la fecha de nacimiento de un menor sin nada que lo
     * justifique. Una fila activa nunca se poda (es del titular) y una desvinculada CON firma
     * tampoco: la guarda de `deleting` lanzaría, y la consulta la excluye ANTES para que
     * `model:prune` no aborte a mitad.
     *
     * @return Builder<Dependent>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('removed_at')
            ->whereNotExists(fn (QueryBuilder $q) => $q
                ->selectRaw('1')
                ->from('waiver_signatures')
                ->whereColumn('waiver_signatures.subject_id', 'dependents.id')
                ->where('waiver_signatures.subject_type', WaiverSignature::SUBJECT_DEPENDENT));
    }
}
