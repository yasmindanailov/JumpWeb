<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Http\Api\AdmissionCodeMap;
use ReflectionClass;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.0b — el mapa veredicto → código público es **exhaustivo**.
 *
 * Hermano pequeño de `ReservationErrorMapTest`, y por el mismo motivo: el contrato público no puede
 * ser una constante interna, y la traducción entre ambos es donde se cuela el olvido. Aquí no es
 * teórico — `AdmissionDecision::TOO_MANY_PENDING` vale `'too_many_pending'` y el código que ven los
 * clientes es `'too_many_pending_orders'`.
 *
 * El mapa no tiene `default` a propósito: un motivo nuevo LANZA en vez de degradar a «demasiadas
 * peticiones», que es lo que hacía el `match` original y lo que convertiría un motivo sin traducir
 * en un 429 mentiroso. Este test lo descubre en la suite en vez de en producción: lee las
 * constantes del dominio por reflexión, así que **no hay lista que mantener a mano**.
 */
class AdmissionCodeMapTest extends TestCase
{
    /** @return list<string> los motivos que el dominio puede devolver, leídos de la clase */
    private function domainReasons(): array
    {
        $reasons = [];

        foreach ((new ReflectionClass(AdmissionDecision::class))->getConstants() as $name => $value) {
            if (is_string($value)) {
                $reasons[$name] = $value;
            }
        }

        return $reasons;
    }

    public function test_the_scan_actually_sees_the_domain_reasons(): void
    {
        $this->assertNotEmpty($this->domainReasons(), 'no se ha leído ningún motivo de AdmissionDecision');
    }

    /** Cada motivo del dominio tiene su código público. */
    public function test_every_domain_reason_maps_to_a_public_code(): void
    {
        foreach ($this->domainReasons() as $name => $reason) {
            $code = AdmissionCodeMap::codeFor($reason);

            $this->assertNotSame(
                '', $code->value,
                "«AdmissionDecision::{$name}» no tiene código público en AdmissionCodeMap"
            );
        }
    }

    /** Y su estado HTTP, que no es el mismo para los tres. */
    public function test_every_domain_reason_maps_to_a_status(): void
    {
        foreach ($this->domainReasons() as $name => $reason) {
            $this->assertContains(
                AdmissionCodeMap::statusFor($reason),
                [409, 429],
                "«AdmissionDecision::{$name}» devuelve un estado que no es ni 409 ni 429"
            );
        }
    }

    /**
     * El límite de FRECUENCIA es el único 429, y esa distinción es información para el cliente: es
     * lo único de los tres que se arregla esperando. La pausa invita a llamar al negocio y el tope
     * de pendientes, a pagar o dejar caducar lo que hay.
     */
    public function test_only_the_rate_limit_is_a_429(): void
    {
        $this->assertSame(429, AdmissionCodeMap::statusFor(AdmissionDecision::RATE_LIMITED));
        $this->assertSame(409, AdmissionCodeMap::statusFor(AdmissionDecision::RESERVATIONS_PAUSED));
        $this->assertSame(409, AdmissionCodeMap::statusFor(AdmissionDecision::TOO_MANY_PENDING));
    }

    /** Los dos nombres que NO coinciden, fijados: es la razón por la que existe el mapa. */
    public function test_the_public_code_is_not_the_domain_constant(): void
    {
        $this->assertSame('too_many_pending', AdmissionDecision::TOO_MANY_PENDING);
        $this->assertSame('too_many_pending_orders', AdmissionCodeMap::codeFor(AdmissionDecision::TOO_MANY_PENDING)->value);

        $this->assertSame('rate_limited', AdmissionDecision::RATE_LIMITED);
        $this->assertSame('too_many_requests', AdmissionCodeMap::codeFor(AdmissionDecision::RATE_LIMITED)->value);
    }
}
