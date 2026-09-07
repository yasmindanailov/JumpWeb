<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\DependentRemoval;
use App\Domain\Identity\Exceptions\DependentNotFoundException;
use App\Domain\Identity\Exceptions\DependentNotMinorException;
use App\Domain\Identity\Exceptions\DependentsLimitReachedException;
use App\Domain\Identity\Exceptions\DependentWaiverRequiredException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
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
 *  · `RGPD-02`: la auditoría lleva el id y nunca el nombre, los apellidos ni la fecha de nacimiento.
 */
final class DependentRegistry
{
    public function __construct(private readonly WaiverSigner $signer) {}

    /**
     * ❗❗❗ **DECLARAR Y ACEPTAR SON UN SOLO GESTO** (`#441`, `[DECIDIDO owner, 2026-09-06]`,
     * `specs/firma-al-declarar-menor.md` §4.3). Donde la exención se gestiona dentro y hay texto
     * publicado, **no existe un menor declarado sin aceptación**: la transacción se deshace entera y
     * el menor no se crea.
     *
     * ⚠️⚠️ **Y si el correo del titular no está verificado, la aceptación se RETIENE en la fila del
     * menor en vez de firmarse.** No es una relajación de `#179`: es su mecanismo. La primera versión
     * del diseño proponía firmar igual y la revisión adversarial reprodujo el daño —un tercero
     * declara veinte menores REALES con la cuenta de otra persona, los firma, y al reclamarla la
     * víctima se los queda para siempre porque `remove()` con firma detrás solo desvincula—. Con la
     * retención el gesto sigue siendo uno y **ninguna firma nace sobre un buzón sin demostrar**: lo
     * que se aplaza es el efecto probatorio, nunca la aceptación.
     *
     * ⚠️ **La condición la comprueba el ESCRITOR, no el llamante.** El llamante resuelve el texto
     * (es quien lo sirvió y quien puede decir cuál leyó la persona), pero si hay algo que aceptar y
     * no llega, aquí se lanza. Un llamante nuevo que se lo salte no escribe una ficha huérfana.
     *
     * @param  string  $bornOn  `Y-m-d`, ya validada en forma por quien llama; aquí se re-comprueba
     * @param  LegalDocumentVersion|null  $waiver  el texto que la pantalla SIRVIÓ, ya resuelto contra
     *                                             la versión vigente por quien llama
     * @param  WaiverSignatureRequest|null  $acceptance  canal, IP y navegador del momento de aceptar;
     *                                                   su SUJETO lo pone este método
     *
     * @throws DependentNotMinorException si hoy ya tiene 18 o más
     * @throws DependentsLimitReachedException si la cuenta está en su tope
     * @throws DependentWaiverRequiredException si hay exención que aceptar y no llega
     * @throws WaiverDocumentStaleException si el texto se republicó antes de escribir (bajo el lock)
     */
    public function add(
        User $holder,
        string $name,
        string $bornOn,
        string $surname = '',
        ?string $relationship = null,
        ?LegalDocumentVersion $waiver = null,
        ?WaiverSignatureRequest $acceptance = null,
    ): Dependent {
        if ($holder->isAnonymized()) {
            throw new LogicException('Una cuenta anonimizada no puede declarar personas a cargo.');
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > Dependent::NAME_MAX) {
            throw new InvalidArgumentException('El nombre de la persona a cargo tiene que tener entre 1 y '.Dependent::NAME_MAX.' caracteres.');
        }

        // Apellidos y relación (`#236`). ⚠️ Llegan con valor por defecto y NO son obligatorios aquí:
        // las fichas dadas de alta antes de esta tanda no los tienen y no hay de dónde sacarlos, así
        // que la regla «hacen falta» vive en la validación del ALTA NUEVA y no en el escritor, que
        // también sirve a los seeders y a los tests de lo viejo. Lo que sí se cierra aquí es que la
        // relación, si viene, sea una de las del catálogo: es lo que sostiene que ese adulto pueda
        // firmar por el menor, y una cadena inventada no lo sostiene.
        $surname = trim($surname);
        if (mb_strlen($surname) > Dependent::SURNAME_MAX) {
            throw new InvalidArgumentException('Los apellidos no pueden pasar de '.Dependent::SURNAME_MAX.' caracteres.');
        }

        if ($relationship !== null && ! in_array($relationship, Dependent::RELATIONSHIPS, true)) {
            throw new InvalidArgumentException("«{$relationship}» no es una relación conocida.");
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

        // ⚠️ Fuera de `interno`, o sin versión publicada, **no hay nada que aceptar y el alta funciona
        // exactamente como siempre**. Es la doctrina de `WaiverSettings` («un valor ausente cae al
        // comportamiento histórico») y la de `#348` con las condiciones: una instalación sin waiver
        // publicado no puede quedarse sin poder declarar menores.
        $exigible = WaiverSettings::isInternal()
            && LegalDocuments::latestVersionNumber(WaiverSettings::SLUG) !== null;

        if ($exigible && $waiver === null) {
            throw new DependentWaiverRequiredException;
        }

        return DB::transaction(function () use ($holder, $name, $bornOn, $surname, $relationship, $exigible, $waiver, $acceptance): Dependent {
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
                'surname' => $surname !== '' ? $surname : null,
                'relationship' => $relationship,
                'born_on' => $bornOn,
            ]);

            // ⚠️ El menor tiene que EXISTIR antes de firmar: `waiver_signatures.subject_id` tiene FK
            // dura a `dependents` y rechaza de verdad (comprobado con control: `FOREIGN KEY constraint
            // failed`). De ahí este orden y no el contrario.
            if ($exigible && $waiver !== null) {
                $peticion = ($acceptance ?? WaiverSignatureRequest::web(request()?->ip(), request()?->userAgent()))
                    ->forDependent((int) $dependent->getKey());

                if ($locked->email_verified_at !== null) {
                    // ⚠️⚠️ `WaiverSigner` re-comprueba la vigencia BAJO EL LOCK y lanza desde dentro
                    // (S-3 de `#181`). Si el texto se republicó entre que la pantalla lo sirvió y este
                    // punto, la transacción entera se deshace y **el menor no se crea** — que es la
                    // conducta correcta: no se firma un texto que no se ha leído.
                    $this->signer->sign($locked, $waiver, $peticion);
                } else {
                    // La aceptación RETENIDA, hermana exacta de la del titular (`#179`): qué texto, por
                    // qué canal, y la IP y el navegador DE ESTE MOMENTO —no los de la petición que
                    // verifique, que en pay-first puede ser la notificación S2S de Redsys (S-1)—.
                    $dependent->forceFill([
                        'waiver_pending_document_id' => (int) $waiver->getKey(),
                        'waiver_pending_channel' => $peticion->channel,
                        'waiver_pending_ip' => $peticion->ip !== null ? mb_substr($peticion->ip, 0, 45) : null,
                        'waiver_pending_user_agent' => $peticion->userAgent !== null ? mb_substr($peticion->userAgent, 0, 512) : null,
                    ])->save();
                }
            }

            // ⚠️ La auditoría, al FINAL de la sección crítica: `AuditLogger` se traga cualquier
            // `Throwable`, y en mitad de la transacción un error suyo podría dejar un 201 con la fila
            // a medio escribir.
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
