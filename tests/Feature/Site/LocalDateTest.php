<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Services\LocalDate;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **Cómo se escribe una fecha, en UN solo sitio** (F5 · T2b, `DECISIONES #656`).
 *
 * ❗❗ Afirma sobre el DATO, no sobre el marcado (`#649`): la regla vivía en línea en `pages/rules.blade.php`
 * y nadie la vigilaba, y por eso el pie de `/normas` llevaba diciendo «September de 2026» en inglés sin
 * que ningún test se pusiera rojo.
 */
class LocalDateTest extends TestCase
{
    public function test_spanish_names_the_month_with_its_particle(): void
    {
        app()->setLocale('es');

        $this->assertSame('marzo de 2026', LocalDate::monthYear(Carbon::parse('2026-03-04')));
    }

    /** ⚠️ Inglés y francés NO llevan el «de»: es lo que la vista escribía mal en los dos. */
    public function test_english_and_french_name_the_month_and_the_year_without_a_particle(): void
    {
        app()->setLocale('en');
        $this->assertSame('March 2026', LocalDate::monthYear(Carbon::parse('2026-03-04')));

        app()->setLocale('fr');
        $this->assertSame('mars 2026', LocalDate::monthYear(Carbon::parse('2026-03-04')));
    }
}
