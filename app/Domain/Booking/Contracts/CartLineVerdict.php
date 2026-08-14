<?php

namespace App\Domain\Booking\Contracts;

/**
 * En qué queda una línea candidata frente a una cesta ({@see CartLineValidation}).
 *
 * **Devuelve un resultado; no lanza ni pinta** — mismo criterio que `AdmissionDecision`. El sidebar
 * traduce los problemas a `addError` por campo, la API a un cuerpo con códigos, y ninguna de las dos
 * tiene que capturar excepciones para hacer lo mismo.
 *
 * Lo interesante no son solo los problemas: **un veredicto aceptado también informa**. La cantidad
 * puede venir recortada al cupo y la línea puede fundirse con otra que ya estaba, y las dos cosas
 * cambian lo que el cliente va a ver en su cesta.
 */
final readonly class CartLineVerdict
{
    /**
     * @param  bool  $accepted  ¿puede entrar la línea?
     * @param  list<CartLineProblem>  $problems  vacío si `accepted`
     * @param  int  $quantity  la cantidad EFECTIVA con la que entraría, ya recortada al cupo
     * @param  bool  $quantityCapped  ¿se recortó? El servidor lo hace en silencio desde siempre
     *                                (anti-manipulación); un cliente que no lo sepa pinta la
     *                                cantidad que pidió y no la que tiene
     * @param  int  $maxQuantity  techo de esta franja con la cesta descontada — el mismo número que
     *                            acota el selector en `AvailabilityOffer`
     * @param  int|null  $mergesWithIndex  índice de la línea de la cesta que ABSORBERÍA a esta, o
     *                                     `null` si entraría como línea nueva. La fusión solo ocurre
     *                                     entre entradas del mismo producto, día y hora **sin
     *                                     complementos**: un pack y una línea con complementos son
     *                                     siempre su propio bloque, porque fundirlos cambiaría
     *                                     cantidad, señal y ocupación
     */
    private function __construct(
        public bool $accepted,
        public array $problems,
        public int $quantity,
        public bool $quantityCapped,
        public int $maxQuantity,
        public ?int $mergesWithIndex,
    ) {}

    public static function accept(int $quantity, bool $quantityCapped, int $maxQuantity, ?int $mergesWithIndex): self
    {
        return new self(true, [], $quantity, $quantityCapped, $maxQuantity, $mergesWithIndex);
    }

    /**
     * Rechazo. `quantity` viaja a 0 a propósito: una línea que no entra no tiene cantidad efectiva,
     * y devolver la pedida invitaría a usarla igualmente.
     *
     * @param  list<CartLineProblem>  $problems
     */
    public static function reject(array $problems, int $maxQuantity = 0): self
    {
        return new self(false, $problems, 0, false, $maxQuantity, null);
    }

    public function rejected(): bool
    {
        return ! $this->accepted;
    }
}
