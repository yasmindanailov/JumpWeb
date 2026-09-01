<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use Carbon\CarbonInterface;

/**
 * Fase 6 · waiver — «¿este titular tiene waiver, y de qué versión?», respondido según el MODO de la
 * instalación (`specs/waiver-probatorio.md` §4.1, §4.8). Es lo que consultan la puerta, la ficha del
 * panel y la API.
 *
 *  - `desactivado`: no hay pregunta (`isEnabled()` false).
 *  - `externo`: manda el sello `users.waiver_accepted_at`, como siempre; no hay versiones.
 *  - `interno`: manda el REGISTRO firmado. Un sello sin registro (datos de un sistema externo
 *    anterior) NO cuenta: al cambiar a interno, quien no firmó aquí tiene que firmar.
 *    `isCurrent` dice si la firma es de la versión vigente; si no, está `outdated` — se SEÑALA y se
 *    deja pasar (§4.8): la re-firma se pide en la siguiente compra o inicio de sesión.
 *
 * Y la misma pregunta para un MENOR a cargo (`forDependent()`, `menores-a-cargo.md` §4.3): solo tiene
 * respuesta en modo interno —en externo el sistema del parque no sabe de dependientes— y la da su
 * propia cadena de firmas, nunca el sello del titular.
 */
final class WaiverStatus
{
    /** Los tres estados de la exención de un MENOR tal como los nombran las superficies del operador. */
    public const MINOR_CURRENT = 'current';

    public const MINOR_OUTDATED = 'outdated';

    public const MINOR_MISSING = 'missing';

    public function __construct(
        public readonly string $mode,
        public readonly bool $signed,
        public readonly ?CarbonInterface $acceptedAt,
        public readonly ?int $version,
        public readonly ?string $locale,
        public readonly bool $isCurrent,
        /** La firma que responde (solo en interno): lo que el PDF propio necesita. */
        public readonly ?int $signatureId = null,
    ) {}

    public static function for(User $user): self
    {
        $mode = WaiverSettings::mode();

        if ($mode === WaiverSettings::MODE_OFF) {
            return new self($mode, false, null, null, null, false);
        }

        if ($mode === WaiverSettings::MODE_EXTERNAL) {
            $acceptedAt = $user->waiver_accepted_at;

            return new self($mode, $acceptedAt !== null, $acceptedAt, null, null, $acceptedAt !== null);
        }

        $last = WaiverSignature::query()
            ->where('user_id', $user->getKey())
            ->where('subject_type', WaiverSignature::SUBJECT_HOLDER)
            ->with('version')
            ->orderByDesc('id')
            ->first();

        return self::fromLastSignature($mode, $last);
    }

    public static function forDependent(Dependent $dependent): self
    {
        $mode = WaiverSettings::mode();

        if ($mode !== WaiverSettings::MODE_INTERNAL) {
            return new self($mode, false, null, null, null, false);
        }

        $last = WaiverSignature::query()
            ->where('user_id', $dependent->user_id)
            ->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT)
            ->where('subject_id', $dependent->getKey())
            ->with('version')
            ->orderByDesc('id')
            ->first();

        return self::fromLastSignature($mode, $last);
    }

    /**
     * La misma pregunta que {@see forDependent()} para VARIOS menores, en UNA consulta de firmas (más la
     * versión vigente, una vez): lo que necesita una ficha que enseña varios a la vez (el pedido en el
     * panel, el modal de asignar). Paridad con `forDependent()` probada menor a menor.
     *
     * @param  iterable<Dependent>  $dependents
     * @return array<int, self> por id de menor
     */
    public static function forDependents(iterable $dependents): array
    {
        $list = collect($dependents)->values();
        if ($list->isEmpty()) {
            return [];
        }

        $mode = WaiverSettings::mode();
        $out = [];

        if ($mode !== WaiverSettings::MODE_INTERNAL) {
            foreach ($list as $dependent) {
                $out[(int) $dependent->getKey()] = new self($mode, false, null, null, null, false);
            }

            return $out;
        }

        $signatures = WaiverSignature::query()
            ->whereIn('user_id', $list->map(fn (Dependent $d): int => (int) $d->user_id)->unique()->all())
            ->where('subject_type', WaiverSignature::SUBJECT_DEPENDENT)
            ->whereIn('subject_id', $list->map(fn (Dependent $d): int => (int) $d->getKey())->all())
            ->with('version')
            ->orderByDesc('id')
            ->get();
        $latest = LegalDocuments::latestVersionNumber(WaiverSettings::SLUG);

        foreach ($list as $dependent) {
            // La ÚLTIMA firma de ESE par (titular, sujeto): la lista viene por id descendente.
            $last = $signatures->first(fn (WaiverSignature $s): bool => (int) $s->subject_id === (int) $dependent->getKey()
                && (int) $s->user_id === (int) $dependent->user_id);
            $out[(int) $dependent->getKey()] = self::build($mode, $last, $latest);
        }

        return $out;
    }

    private static function fromLastSignature(string $mode, ?WaiverSignature $last): self
    {
        return self::build($mode, $last, $last === null ? null : LegalDocuments::latestVersionNumber(WaiverSettings::SLUG));
    }

    /** Un estado a partir de la última firma y de la versión vigente ya resuelta (o `null` si no hay versiones). */
    private static function build(string $mode, ?WaiverSignature $last, ?int $latest): self
    {
        if ($last === null) {
            return new self($mode, false, null, null, null, false);
        }

        return new self(
            $mode,
            true,
            $last->accepted_at,
            (int) $last->version->version,
            (string) $last->version->locale,
            $latest !== null && (int) $last->version->version === $latest,
            (int) $last->getKey(),
        );
    }

    public function isEnabled(): bool
    {
        return $this->mode !== WaiverSettings::MODE_OFF;
    }

    /** Firmado, pero de una versión anterior a la vigente: señalar y dejar pasar (§4.8). */
    public function isOutdated(): bool
    {
        return $this->signed && ! $this->isCurrent;
    }

    /**
     * El estado de la exención de un menor a cargo, en la forma que consumen la ficha del pedido, la
     * del titular y la puerta.
     *
     * Vive AQUÍ y no en cada superficie porque hasta `#320` el mismo trío se derivaba por triplicado
     * —`AssignedDependents::waiverState()`, `HolderDependents::waiverState()` y un ternario dentro de
     * `GateProfile::minor()`—, y tres copias de la misma regla son tres sitios donde puede divergir.
     *
     * `null` cuando el modo NO responde por menores (`externo` y `desactivado`: el sistema del parque
     * no sabe de dependientes, §4.3). Es distinto de «firmada y vigente»: aquello es una respuesta,
     * esto es que no hay pregunta — y por eso el dato viaja al DOM aunque no se pinte
     * ({@see minorStateIsNoteworthy()}).
     *
     * @return self::MINOR_*|null
     */
    public function minorState(): ?string
    {
        if ($this->mode !== WaiverSettings::MODE_INTERNAL) {
            return null;
        }

        if (! $this->signed) {
            return self::MINOR_MISSING;
        }

        return $this->isOutdated() ? self::MINOR_OUTDATED : self::MINOR_CURRENT;
    }

    /**
     * ¿Este estado lleva RÓTULO en pantalla? (`#320`, `[DECIDIDO owner]`)
     *
     * **Solo se pinta la EXCEPCIÓN.** La firma vigente no se anuncia: es la condición para que el
     * menor esté asignado —`DependentAssigner::hasCurrentWaiver()` no deja asignar sin ella en modo
     * interno—, así que rotular «exención ✓» junto a cada menor es repetir en cada fila algo que el
     * sistema ya garantiza. Silencio = todo bien.
     *
     * ⚠️ **Y por eso `outdated` y `missing` SIGUEN pintándose**: el argumento de arriba cubre el
     * instante de la asignación y nada más. Una firma vigente **caduca sola** cuando se publica una
     * versión nueva del documento (`isOutdated()`), sin que nadie toque la reserva; y fuera del
     * embudo hay vías —el modo de la instalación puede cambiar— que dejan menores sin firma vigente.
     * Ocultar también la excepción cegaría al operador ante una deriva que él no provocó, que es
     * justo lo que la puerta necesita ver: `admin.puerta.validar.profile.minor_waiver_outdated` no
     * es un adorno, dice literalmente «versión anterior — deja pasar».
     *
     * Recibe el estado ya derivado (y no `self`) para que la puerta pueda preguntarlo sobre el valor
     * que ya lleva en su read-model, sin rehacer el `WaiverStatus`.
     */
    public static function minorStateIsNoteworthy(?string $state): bool
    {
        return $state !== null && $state !== self::MINOR_CURRENT;
    }
}
