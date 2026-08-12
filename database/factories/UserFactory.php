<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Modelo EXPLÍCITO (Fase 2, paso 4). La resolución factory→modelo de Laravel adivina
     * `App\Models\{Basename}`, y `User` vive ahora en `App\Domain\Identity\Models`. Declararlo
     * aquí es más barato y más legible que enseñarle a adivinar módulos.
     * (El sentido contrario —modelo→factory— lo resuelve `AppServiceProvider`.)
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('6########'),
            'locale' => 'es',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Cliente dado de alta a mano SIN email (reserva de agenda, solo teléfono — #263). El email
     * queda `NULL` (no `''`) y la cuenta no tiene email verificado: no puede iniciar sesión ni recibe
     * correos — vive solo en el panel. Ver `CustomerRegistrar` y la migración de email nullable.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'email_verified_at' => null,
        ]);
    }
}
