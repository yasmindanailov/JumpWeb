<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;

/**
 * **LO QUE UNA CUENTA DEBE ANTES DE PODER CONTRATAR** (`specs/auth-con-google.md` §21.4.2, `#349`).
 *
 * Son dos cosas y no se parecen en nada salvo en cuándo se piden:
 *  · **las condiciones**, en su versión vigente — el momento del contrato es donde el TRLGDCU
 *    (art. 97) y la LCGC (art. 5) las sitúan, no la creación de la cuenta;
 *  · **el teléfono**, si la cuenta no lo tiene. `[owner]`: *«imprescindible para las reservas»*.
 *
 * ⚠️⚠️ **Existe porque la pregunta se estaba respondiendo en DOS sitios.** La primera versión la
 * resolvía el contexto de cuenta por un lado y el controlador de pedidos por otro, cada uno con su
 * `trim($user->phone) === ''`. Dos copias de «qué le falta a esta cuenta» divergen el día que alguien
 * arregle una — y aquí divergir significa **pintar un campo que el servidor no pide, o al revés**.
 *
 * ⚠️ **Y existe además porque `ApiBoundariesTest` lo exigió**, que es la mejor versión de esta
 * historia: escribir el teléfono desde el controlador es una escritura de dominio en la capa HTTP, y
 * la guarda lo puso en rojo antes de que llegara a ninguna parte. *Lo que la capa de entrega puede
 * hacer es preguntar y pasar lo que el cliente mandó; decidir y escribir es del módulo.*
 */
final class CheckoutDuties
{
    public function __construct(private readonly TermsAcceptance $terms) {}

    /**
     * Qué le falta a esta cuenta, y —para las condiciones— si es porque han CAMBIADO.
     *
     * ⚠️ `updated` solo puede ser cierto si `terms` lo es: decide qué se le DICE, no si se le pide.
     *
     * @return array{terms: bool, updated: bool, phone: bool}
     */
    public function pendingFor(User $user): array
    {
        $terms = $this->terms->statusFor($user);

        return [
            'terms' => $terms['pending'],
            'updated' => $terms['updated'],
            // ⚠️ `trim()`: un teléfono de espacios no es un teléfono, y ésta es la ÚNICA copia de esa
            // frase en el producto desde que este servicio existe.
            'phone' => trim((string) $user->phone) === '',
        ];
    }

    /**
     * Salda lo que se haya mandado. **Solo escribe lo que de verdad faltaba**: quien mande un teléfono
     * teniendo uno no se lo pisa, y quien mande la casilla teniendo las condiciones aceptadas no deja
     * una segunda prueba del mismo consentimiento.
     *
     * ⚠️ **Se llama ANTES de crear el pedido**, y es lo correcto: la ley pide que la aceptación sea
     * previa a quedar vinculado. Si después el pedido se cae por aforo, lo escrito aquí se queda —
     * porque el cliente lo dio de verdad.
     */
    public function settle(User $user, ?string $phone, bool $acceptedTerms, string $ip): void
    {
        $pending = $this->pendingFor($user);

        if ($pending['phone'] && is_string($phone) && trim($phone) !== '') {
            $user->forceFill(['phone' => trim($phone)])->save();
        }

        if ($pending['terms'] && $acceptedTerms) {
            $this->terms->accept($user, $ip);
        }
    }
}
