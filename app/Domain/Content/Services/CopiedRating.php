<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Platform\Models\Setting;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * **La NOTA de la ficha de Google, copiada** (`DECISIONES #771`): «4,9 · 191 reseñas», con el día en que se copió.
 *
 * ▶ Sustituye a la que traía Places (retirado en `#771`) mientras Google no aprueba el Perfil de Empresa, que la
 * traerá sola y va delante en la cascada. La escriben el importador (`reviews:import`, desde la copia de la ficha) o el
 * panel («Opiniones» → «Nota de Google»), y la sirven las opiniones del panel ({@see CmsSocialProof::rating()}).
 * ⚠️ **No es una media compuesta con opiniones propias** (`#491` lo prohíbe y sigue en pie): es la cifra DE GOOGLE,
 * tal cual la enseña la ficha, con su fecha (`asOf`) —una cifra copiada envejece, y la fecha lo dice—.
 * ⚠️ Vive en `settings` (cuatro claves `reviews.rating_*`): es un dato de la instalación, sin pantalla propia.
 */
final class CopiedRating
{
    public const VALUE = 'reviews.rating_value';

    public const COUNT = 'reviews.rating_count';

    public const URL = 'reviews.rating_url';

    public const AS_OF = 'reviews.rating_as_of';

    /** La nota copiada, o `null` si no hay una publicable (valor entre 1 y 5 y al menos una reseña). */
    public function get(): ?Rating
    {
        $valor = (float) str_replace(',', '.', (string) Setting::value(self::VALUE, ''));
        $total = (int) Setting::value(self::COUNT, 0);

        if ($valor < 1 || $valor > Rating::MAX || $total < 1) {
            return null;
        }

        $url = trim((string) Setting::value(self::URL, ''));
        $dia = trim((string) Setting::value(self::AS_OF, ''));

        return new Rating(
            value: round($valor, 1),
            count: $total,
            url: str_starts_with($url, 'https://') ? $url : null,
            source: TestimonialData::SOURCE_GOOGLE,
            asOf: $dia === '' ? null : Carbon::parse($dia),
            // La PALABRA «Google», como el Perfil de Empresa: el logotipo de Maps es la licencia de Places.
            attribution: TestimonialData::ATTRIBUTION_GOOGLE_WORD,
        );
    }

    /** Guarda la nota que enseña la ficha, con el día en que se copió. */
    public function put(float $valor, int $total, ?string $url, DateTimeInterface $dia): void
    {
        foreach ([
            self::VALUE => (string) round($valor, 1),
            self::COUNT => (string) $total,
            self::URL => $url !== null && str_starts_with($url, 'https://') ? $url : '',
            self::AS_OF => Carbon::instance($dia)->toDateString(),
        ] as $clave => $valorAjuste) {
            Setting::query()->updateOrCreate(['key' => $clave], ['value' => $valorAjuste, 'group' => 'reviews']);
        }

        Setting::flushMemo();
    }
}
