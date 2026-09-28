<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * **La fecha de nacimiento del TITULAR, en UN solo sitio** (`DECISIONES #792`, `specs/analitica-para-decidir.md` §4.14, TP·1).
 *
 * La piden cuatro puertas —el alta con correo, la pantalla tras Google, el mostrador y Mi cuenta— y en las cuatro es
 * **opcional**: sin ella la cuenta nace igual. Lo que no se admite es una fecha que no pueda ser la de un titular:
 *  - **futura** — una errata;
 *  - **de menos de 18 años** — la cuenta es de un adulto («no vendemos a menores», el owner). El hueco de que un menor se
 *    registre SIN dar la fecha queda abierto a propósito (`#792`): esto no comprueba la mayoría de edad, solo no la niega;
 *  - **de más de {@see self::MAX_AGE} años** — `0198` por `1998` envenenaría las cifras del público (TP·2) sin que nadie lo viera.
 *
 * ⚠️ La edad se cuenta **fecha con fecha el día del PARQUE** (`DisplayTime::today()` y {@see Dependent::ageBetween()}), la
 * misma cuenta que la de un hijo: el día del 18.º cumpleaños ya vale, también entre la medianoche de Madrid y la de UTC.
 *
 * Lo vigila `HolderBirthDateTest`.
 */
final class BirthDatePolicy
{
    /** Más años que esto es una errata, no una persona. */
    public const MAX_AGE = 120;

    public const FUTURE = 'future';

    public const MINOR = 'minor';

    public const IMPLAUSIBLE = 'implausible';

    /**
     * Las reglas del campo, opcional. `$messages` es el grupo de traducción de los tres avisos: `api.born_on` para el
     * cliente (es/en/fr) y uno de `admin.php` para el mostrador (es/zh_CN).
     *
     * ⚠️ `bail`: si el formato falla, la comprobación de la edad no llega a leer una fecha que no lo es.
     *
     * @return list<string|Closure>
     */
    public static function rules(string $messages = 'api.born_on'): array
    {
        return ['bail', 'nullable', 'date_format:Y-m-d', function (string $attribute, mixed $value, Closure $fail) use ($messages): void {
            $verdict = self::verdict((string) $value);

            if ($verdict !== null) {
                $fail(__($messages.'.'.$verdict, ['age' => Dependent::ADULT_AGE, 'max' => self::MAX_AGE]));
            }
        }];
    }

    /** Por qué no vale esta fecha (`FUTURE`, `MINOR`, `IMPLAUSIBLE`), o `null` si vale. */
    public static function verdict(string $bornOn, ?CarbonInterface $today = null): ?string
    {
        $today ??= DisplayTime::today();

        try {
            $born = CarbonImmutable::createFromFormat('!Y-m-d', $bornOn, 'UTC');
        } catch (Throwable) {
            $born = null;
        }

        if (! $born instanceof CarbonImmutable) {
            return self::IMPLAUSIBLE;
        }

        if ($born->greaterThan(CarbonImmutable::createFromFormat('!Y-m-d', $today->toDateString(), 'UTC'))) {
            return self::FUTURE;
        }

        $age = Dependent::ageBetween($bornOn, $today);

        if ($age < Dependent::ADULT_AGE) {
            return self::MINOR;
        }

        return $age > self::MAX_AGE ? self::IMPLAUSIBLE : null;
    }

    /**
     * El primer aviso de las mismas reglas, o `null` si la fecha vale (o no hay). Para quien recibe la fecha SIN pasar por un
     * formulario que la valide: un método público de Livewire se puede llamar con cualquier dato.
     */
    public static function firstError(mixed $value, string $messages = 'api.born_on'): ?string
    {
        $validator = Validator::make(['born_on' => $value], ['born_on' => self::rules($messages)]);

        return $validator->fails() ? $validator->errors()->first('born_on') : null;
    }

    /** La fecha que se guarda: la tecleada, recortada, o `null` si no hay ninguna. */
    public static function normalize(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
