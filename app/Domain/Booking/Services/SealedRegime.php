<?php

namespace App\Domain\Booking\Services;

use App\Domain\Platform\Services\Translated;

/**
 * Un pack de la familia por edad TAL COMO SE SELLÓ en una reserva (`docs/specs/cumple-mixto.md`
 * §21.3): su tramo y su precio para el día de la fiesta, copiados en el momento de la venta.
 *
 * Es un VALOR: no sabe nada del catálogo vivo, y por eso ni un renombrado, ni un cambio de tramo, ni
 * un borrado del producto cambian lo que ya se le comunicó al cliente (`DECISIONES #284` D2).
 */
final class SealedRegime
{
    /**
     * @param  array<string, mixed>  $name  el nombre traducible ENTERO, no resuelto: se lee en el
     *                                      idioma de quien mira, y es el nombre que se le dijo
     */
    public function __construct(
        public readonly int $typeId,
        public readonly array $name,
        public readonly ?int $ageMin,
        public readonly ?int $ageMax,
        public readonly ?int $priceCents,
    ) {}

    /** @param  array<string, mixed>  $raw */
    public static function fromArray(array $raw): ?self
    {
        $typeId = $raw['type_id'] ?? null;
        if (! is_numeric($typeId)) {
            return null; // un miembro sin producto no describe nada: se descarta, no se inventa.
        }
        $name = $raw['name'] ?? null;

        return new self(
            (int) $typeId,
            is_array($name) ? $name : [],
            self::nullableInt($raw['age_min'] ?? null),
            self::nullableInt($raw['age_max'] ?? null),
            self::nullableInt($raw['price_cents'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type_id' => $this->typeId,
            'name' => $this->name,
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'price_cents' => $this->priceCents,
        ];
    }

    /**
     * ¿Este tramo cubre esa edad? El MISMO criterio que `TicketType::coversGuestAge()`: los dos
     * extremos INCLUIDOS («de 1 a 6» es 1–6: el de 6 entra y el de 7 no) y un extremo nulo es «sin
     * tope por ese lado», no «cero». Si los dos divergieran, el sello diría una cosa y el catálogo
     * otra sobre el mismo niño.
     */
    public function covers(int $age): bool
    {
        if ($this->ageMin !== null && $age < $this->ageMin) {
            return false;
        }

        return ! ($this->ageMax !== null && $age > $this->ageMax);
    }

    public function displayName(?string $locale = null): string
    {
        $name = Translated::pick($this->name, $locale);

        return is_string($name) && $name !== '' ? $name : '—';
    }

    /** El mismo régimen con OTRO precio: lo que hace un cambio de fecha (§21.4, `PAY-18`). */
    public function withPrice(?int $priceCents): self
    {
        return new self($this->typeId, $this->name, $this->ageMin, $this->ageMax, $priceCents);
    }

    private static function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
