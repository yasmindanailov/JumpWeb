<?php

namespace App\Domain\Identity\Contracts;

/**
 * **Con qué reconfirma el titular una acción sensible** (A2a de `docs/specs/acceso-con-codigo.md` §4.9, `DECISIONES #855`):
 * borrar la cuenta, cambiar el correo, desvincular Google y cerrar las demás sesiones. Con la CONTRASEÑA, como hasta ahora,
 * o con un CÓDIGO `confirm` enviado al correo de la cuenta; la contraseña del cliente se retira en la A5.
 *
 * Es uno o el otro, nunca los dos: lo exige la forma de la petición, y aquí no se puede construir de otra manera.
 */
final readonly class Reconfirmation
{
    private function __construct(
        public ?string $password,
        public ?string $code,
    ) {}

    public static function password(string $password): self
    {
        return new self($password, null);
    }

    public static function code(string $code): self
    {
        return new self(null, $code);
    }

    /**
     * Desde los datos ya validados de una petición: `code` si viene, si no `current_password`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return isset($data['code']) && is_string($data['code'])
            ? self::code($data['code'])
            : self::password((string) ($data['current_password'] ?? ''));
    }

    public function isCode(): bool
    {
        return $this->code !== null;
    }
}
