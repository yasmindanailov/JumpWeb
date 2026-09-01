<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\AuthorizableOrders;
use App\Domain\Identity\Exceptions\GuardianAuthorizationExistsException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — la puerta ÚNICA por la que nace
 * (`docs/specs/waiver-por-reserva.md` §4.9).
 *
 * Un adulto sin cuenta rellena el formulario de un enlace y esto escribe, **en una sola transacción y
 * bajo el lock de la fila del RESPONSABLE**:
 *
 *  1. la autorización (la persona + su ancla al pedido), si no existía ya;
 *  2. la firma probatoria, delegando en {@see WaiverSigner} — que es el único escritor de
 *     `waiver_signatures` y sigue siéndolo.
 *
 * ❗ **Las dos cosas van juntas por dos razones, y ninguna es estética:**
 *
 *  - **No hay carrera contra el `UNIQUE`.** El lock del responsable es el punto de serialización de
 *    todas sus cadenas, así que dos envíos simultáneos del mismo menor se ordenan: el segundo
 *    ENCUENTRA la fila en vez de estrellarse contra la clave única con un 500.
 *  - **No hay autorización huérfana.** Si la firma no llega a escribirse —texto superado, versión
 *    equivocada, lo que sea—, la autorización tampoco. PII de un menor **sin prueba detrás** es
 *    exactamente lo que la regla de `dependents` («sin firma, se borra») existe para evitar.
 *
 * ⚠️ El lock se toma aquí y `WaiverSigner` lo vuelve a tomar dentro: sobre la misma fila y la misma
 * transacción es un no-op, y así el firmador sigue siendo correcto llamándolo por su cuenta.
 *
 * ⚠️ **«Un niño, un papel»** (`[DECIDIDO owner]` §7·9): si ese menor ya tiene justificante en este
 * pedido y lo firmó **otro adulto**, esto **no escribe nada** y lanza — el segundo progenitor ve que
 * ya está firmado. Si es el MISMO adulto reenviando, se comporta como la idempotencia de siempre:
 * misma versión → la firma que hay; versión nueva → una firma más, encadenada.
 */
final class GuardianAuthorizationSigner
{
    public function __construct(
        private readonly WaiverSigner $signer,
        private readonly AuthorizableOrders $orders,
    ) {}

    /**
     * @param  User  $responsible  el titular del pedido — el RESPONSABLE, no quien firma
     * @param  array{minor_name:string, minor_surname:string, minor_born_on:string, guardian_name:string, guardian_surname:string, guardian_relationship:string, guardian_email:?string, guardian_phone:?string}  $data
     * @return array{authorization: GuardianAuthorization, signature: WaiverSignature, created: bool}
     */
    public function sign(
        User $responsible,
        int $orderId,
        LegalDocumentVersion $version,
        array $data,
        WaiverSignatureRequest $request,
    ): array {
        $key = GuardianAuthorization::keyFor($data['minor_name'], $data['minor_surname']);

        return DB::transaction(function () use ($responsible, $orderId, $version, $data, $request, $key): array {
            // El MISMO punto de serialización que usa el firmador (§4.4). Va primero, antes de leer
            // nada: si se buscara la autorización fuera del lock, dos envíos simultáneos del mismo
            // menor podrían decidir los dos que no existe.
            User::query()->whereKey($responsible->getKey())->lockForUpdate()->firstOrFail();

            // ⚠️⚠️ **Las tres puertas se comprueban AQUÍ, bajo el lock, y no al pintar el formulario**
            // (`SEC-04` aplicado al tiempo): entre que el padre abre el enlace y lo envía puede pasar
            // la visita, cancelarse el pedido o llenarse el cupo. Y el cupo, en concreto, **solo es
            // correcto dentro del lock**: dos envíos simultáneos con una plaza libre lo leerían los
            // dos como disponible.
            $order = $this->orders->find($orderId);
            if ($order === null || ! $order->isPaid) {
                throw GuardianAuthorizationRefusedException::notPaid($orderId);
            }
            if ($order->visitFinished) {
                throw GuardianAuthorizationRefusedException::closed($orderId);
            }

            $existing = GuardianAuthorization::query()
                ->where('order_id', $orderId)
                ->where('minor_key', $key)
                ->first();

            if ($existing !== null) {
                // «Un niño, un papel». Reenviar el MISMO adulto es idempotencia; que aparezca OTRO es
                // el caso de los dos progenitores, y ahí se para: dos justificantes del mismo menor
                // harían que la puerta y la hoja de sala lo enseñaran dos veces.
                if (! $this->sameGuardian($existing, $data)) {
                    throw GuardianAuthorizationExistsException::for($existing);
                }

                $signature = $this->signer->sign(
                    $responsible,
                    $version,
                    $request->forGuestMinor((int) $existing->getKey()),
                );

                return ['authorization' => $existing, 'signature' => $signature, 'created' => false];
            }

            // El TOPE. No es `SUM(quantity)`: lo cuenta el contrato sobre las líneas principales
            // VIVAS (`AuthorizableOrdersReader`). Se mira DESPUÉS de la idempotencia a propósito —
            // un padre que reenvía su propio formulario no consume plaza, así que un pedido lleno
            // sigue admitiendo su reenvío.
            $used = GuardianAuthorization::query()->where('order_id', $orderId)->count();
            if ($used >= $order->capacity) {
                throw GuardianAuthorizationRefusedException::full($orderId, $order->capacity);
            }

            $authorization = GuardianAuthorization::create([
                'order_id' => $orderId,
                'minor_name' => mb_substr(trim($data['minor_name']), 0, GuardianAuthorization::NAME_MAX),
                'minor_surname' => mb_substr(trim($data['minor_surname']), 0, GuardianAuthorization::SURNAME_MAX),
                'minor_key' => $key,
                'minor_born_on' => $data['minor_born_on'],
                'guardian_name' => mb_substr(trim($data['guardian_name']), 0, GuardianAuthorization::NAME_MAX),
                'guardian_surname' => mb_substr(trim($data['guardian_surname']), 0, GuardianAuthorization::SURNAME_MAX),
                'guardian_relationship' => $data['guardian_relationship'],
                'guardian_email' => $this->trimmedOrNull($data['guardian_email'] ?? null, GuardianAuthorization::EMAIL_MAX),
                'guardian_phone' => $this->trimmedOrNull($data['guardian_phone'] ?? null, GuardianAuthorization::PHONE_MAX),
            ]);

            $signature = $this->signer->sign(
                $responsible,
                $version,
                $request->forGuestMinor((int) $authorization->getKey()),
            );

            return ['authorization' => $authorization, 'signature' => $signature, 'created' => true];
        });
    }

    /**
     * ¿Es el mismo adulto el que vuelve? Se compara por la MISMA normalización que identifica al
     * menor: un reenvío desde el móvil puede traer «MARÍA LÓPEZ» donde antes puso «maría lopez», y
     * eso no es otro progenitor.
     *
     * @param  array{guardian_name:string, guardian_surname:string}  $data
     */
    private function sameGuardian(GuardianAuthorization $existing, array $data): bool
    {
        return GuardianAuthorization::keyFor($existing->guardian_name, $existing->guardian_surname)
            === GuardianAuthorization::keyFor($data['guardian_name'], $data['guardian_surname']);
    }

    private function trimmedOrNull(?string $value, int $max): ?string
    {
        $clean = trim((string) $value);

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }
}
