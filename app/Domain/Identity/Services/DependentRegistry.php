<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\DependentRemoval;
use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\DependentsLimitReachedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Fase 6 · menores a cargo — el ÚNICO escritor de `dependents` (`docs/specs/menores-a-cargo.md`
 * §4.2, §4.4, §4.5, §4.9). La capa de entrega valida la FORMA de la petición; las reglas viven aquí,
 * que es donde este proyecto las pone siempre (`SelfSignup`, `PasswordLogin`, `WaiverSigner`):
 *
 *  · **Solo menores** (§4.1): quien hoy tiene 18 o más firma su propio waiver.
 *  · **El tope es de SERVIDOR** (§4.5, `PAY-12`) y se aplica bajo el lock de la fila del titular:
 *    dos altas simultáneas leerían la misma cuenta y la número 21 entraría. Misma receta que el
 *    punto de serialización de `WaiverSigner`.
 *  · **Quitar es desvincular si hay algo detrás** (§4.4) y borrar si no; lo decide la propia fila
 *    (`Dependent::hasReferences()`), no el llamante.
 *  · **Anti-IDOR** (§4.9): un id ajeno, inexistente o ya retirado «no existe» — la misma excepción.
 *  · `RGPD-02`: la auditoría lleva el id y nunca el nombre ni la fecha de nacimiento.
 */
final class DependentRegistry
{
    /**
     * @param  string  $bornOn  `Y-m-d`, ya validada en forma por quien llama; aquí se re-comprueba
     *
     * @throws DependentNotMinorException si hoy ya tiene 18 o más
     * @throws DependentsLimitReachedException si la cuenta está en su tope
     */
    public function add(User $holder, string $name, string $bornOn): Dependent
    {
        if ($holder->isAnonymized()) {
            throw new LogicException('Una cuenta anonimizada no puede declarar personas a cargo.');
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > Dependent::NAME_MAX) {
            throw new InvalidArgumentException('El nombre de la persona a cargo tiene que tener entre 1 y '.Dependent::NAME_MAX.' caracteres.');
        }

        // Forma ANTES de parsear: `createFromFormat` lanza con basura (Carbon 3) y DESBORDA con un día
        // que no existe («2026-02-30» → 2 de marzo), así que se exige el patrón y la ida y vuelta.
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $bornOn)
            || CarbonImmutable::createFromFormat('!Y-m-d', $bornOn, 'UTC')->format('Y-m-d') !== $bornOn) {
            throw new InvalidArgumentException("La fecha de nacimiento tiene que ser Y-m-d (recibido «{$bornOn}»).");
        }

        $today = DisplayTime::today();
        if ($bornOn > $today->toDateString()) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser futura.');
        }
        if (Dependent::ageBetween($bornOn, $today) >= Dependent::ADULT_AGE) {
            throw new DependentNotMinorException;
        }

        return DB::transaction(function () use ($holder, $name, $bornOn): Dependent {
            // ⚠️ PRIMERA sentencia de la transacción: el lock de la fila del titular es lo que hace
            // que el tope sea un invariante y no una carrera (§4.5). SQLite no reproduce el lock; la
            // propiedad se mide contra MySQL como las demás de `INVARIANTES §6`.
            $locked = User::query()->whereKey($holder->getKey())->lockForUpdate()->firstOrFail();

            $max = DependentSettings::maxPerAccount();
            $active = Dependent::query()->where('user_id', $locked->getKey())->active()->count();
            if ($active >= $max) {
                throw new DependentsLimitReachedException($max);
            }

            $dependent = Dependent::create([
                'user_id' => (int) $locked->getKey(),
                'name' => $name,
                'born_on' => $bornOn,
            ]);

            AuditLogger::log('dependents.added', $locked, ['dependent_id' => (int) $dependent->getKey()]);

            return $dependent;
        });
    }

    /**
     * @throws DependentNotFoundException si no es suya, no existe o ya está retirada
     */
    public function remove(User $holder, int $dependentId): DependentRemoval
    {
        return DB::transaction(function () use ($holder, $dependentId): DependentRemoval {
            $locked = User::query()->whereKey($holder->getKey())->lockForUpdate()->firstOrFail();

            $dependent = Dependent::query()
                ->whereKey($dependentId)
                ->where('user_id', $locked->getKey())
                ->active()
                ->first();

            if ($dependent === null) {
                throw new DependentNotFoundException;
            }

            if ($dependent->hasReferences()) {
                $dependent->unlink();
                $mode = DependentRemoval::Unlinked;
            } else {
                $dependent->delete();
                $mode = DependentRemoval::Deleted;
            }

            AuditLogger::log('dependents.removed', $locked, [
                'dependent_id' => $dependentId,
                'mode' => $mode->value,
            ]);

            return $mode;
        });
    }

    /**
     * Una persona a cargo ACTIVA de este titular, o «no existe» (§4.9: ajena, inexistente y retirada
     * responden igual). Es lo que la capa de entrega resuelve antes de firmar en su nombre.
     *
     * @throws DependentNotFoundException
     */
    public function findActive(User $holder, int $dependentId): Dependent
    {
        return Dependent::query()
            ->whereKey($dependentId)
            ->where('user_id', $holder->getKey())
            ->active()
            ->first() ?? throw new DependentNotFoundException;
    }

    /**
     * Las personas a cargo que el titular VE: activas, en el orden en que las declaró.
     *
     * @return Collection<int, Dependent>
     */
    public function activeFor(User $holder): Collection
    {
        return Dependent::query()
            ->where('user_id', $holder->getKey())
            ->active()
            ->orderBy('id')
            ->get();
    }
}
