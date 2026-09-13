<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL PIE DE LA SECCIÓN DE CUMPLEAÑOS** (`DECISIONES #498`).
 *
 * `[DECIDIDO owner, 2026-09-10]`: bajo las tarjetas de cumpleaños había **tres bloques de texto
 * seguidos** —los días de la especial, las edades mezcladas y la cabecera del carril de
 * complementos— sin aire y con ruido: *«algo debe irse y dejarlo para la página de cumpleaños»*.
 *
 * ▶ Estos dos casos vivían en `AddonsRailSailsTest`, junto a las velas del carril de complementos.
 * El carril se retiró de la web en `#583` (los complementos solo se ofrecen al reservar) y con él sus
 * guardas; lo que no era del carril se queda aquí, con su nombre.
 */
class PartyFootNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /**
     * ❗❗❗ **LA NOTA DE EDADES MEZCLADAS SE MOVIÓ A `/cumpleanos`; NO SE BORRÓ.**
     *
     * ⚠️⚠️ Medido antes de tocarla: esa frase **solo existía en la portada**, así que quitarla sin
     * más la habría hecho desaparecer del sitio entero — y el suplemento mixto **cobra dinero**
     * (`specs/cumple-mixto.md`). Sale solo cuando dos packs o más comparten familia de edades; el
     * seeder no la siembra, así que el caso la pone — sin ella miraría el vacío.
     */
    public function test_the_mixed_age_note_moved_to_the_page_instead_of_vanishing(): void
    {
        TicketType::birthdaySurfacePacks()->update(['guest_age_family' => 'cumple']);
        $texto = __('landing.birthday.mixed_text');

        $this->assertStringNotContainsString($texto, $this->home(),
            'La nota de edades mezcladas ha vuelto a la portada, donde era el tercero de tres bloques '.
            'de texto seguidos bajo las tarjetas.');

        $this->assertStringContainsString($texto,
            (string) $this->get('/cumpleanos')->assertOk()->getContent(),
            "La nota de edades mezcladas NO está en `/cumpleanos`.\n".
            '▶ Se movió, no se borró: era el único sitio del sitio que lo decía.');
    }

    /**
     * **La nota de los días SÍ se queda en la portada**, y con aire.
     *
     * ⚠️ Sin ella «16,95 € en tarifa especial» no significa nada: el término hay que definirlo una
     * vez y en el sitio donde se usa (`#479`). El margen lo pone el CONTEXTO —`.rates__note` nace
     * con 4 px porque en tarifas va pegada a su carril—.
     */
    public function test_the_special_days_note_stays_on_the_home_with_room(): void
    {
        $this->assertStringContainsString('rates__note', $this->home(),
            'la nota de los días de la tarifa especial ha desaparecido de la portada');

        $this->assertMatchesRegularExpression('/#events\s+\.rates__note\s*\{[^}]*margin-top:/',
            (string) file_get_contents(base_path('public/css/landing.css')),
            "La nota bajo las tarjetas de cumpleaños ha vuelto a quedarse sin aire propio.\n".
            '▶ `.rates__note` nace con 4 px, que es lo correcto en tarifas y la deja colgando aquí.');
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }
}
