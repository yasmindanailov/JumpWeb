<?php

namespace Tests\Feature\Catalog;

use App\Domain\Booking\Models\TicketType;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **LOS REGALOS DE UN PRODUCTO** (`DECISIONES #589`, `[DECIDIDO owner]`): lo que el parque da sin
 * cobrar va en su propio campo (`ticket_types.gifts`) y se pinta cada uno en su etiqueta, en la web y
 * en el cajón.
 *
 * ▶ Lo que se vigila es lo que se rompería EN SILENCIO, con la página cargando:
 *  · la normalización es UNA (`TicketType::giftLines()`): sin vacíos y en el idioma activo;
 *  · cada superficie pinta UNA etiqueta por regalo, y sin regalos no deja contenedor;
 *  · en la comparativa de `/cumpleanos` los regalos comunes NO bajan a «Igual»;
 *  · la API los publica APARTE de las ventajas — mezclarlos es el defecto que motivó el campo.
 */
class ProductGiftsTest extends TestCase
{
    use RefreshDatabase;

    private const GIFTS = ['es' => ['Cono de chuches', '  ', ' Calcetines para todos '], 'en' => ['Sweet cone']];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
        app()->setLocale('es');
    }

    private function pack(): TicketType
    {
        return TicketType::birthdaySurfacePacks()->orderBy('position')->firstOrFail();
    }

    /** El recorte de la sección de cumpleaños de la portada. */
    private function homeSection(): string
    {
        preg_match('#<section id="events".*?</section>#s', (string) $this->get('/')->assertOk()->getContent(), $m);

        return $m[0] ?? '';
    }

    public function test_the_gift_list_is_normalised_once_in_the_active_locale(): void
    {
        $pack = $this->pack();
        $pack->update(['gifts' => self::GIFTS]);

        $this->assertSame(['Cono de chuches', 'Calcetines para todos'], $pack->fresh()->giftLines());

        app()->setLocale('en');
        $this->assertSame(['Sweet cone'], $pack->fresh()->giftLines());

        $pack->update(['gifts' => null]);
        $this->assertSame([], $pack->fresh()->giftLines());
    }

    public function test_the_home_party_card_paints_one_label_per_gift_and_nothing_without_them(): void
    {
        TicketType::query()->update(['gifts' => null]);
        $sin = $this->homeSection();
        $this->assertStringContainsString('party-card', $sin, 'el recorte no enmarca la sección de cumpleaños');
        $this->assertStringNotContainsString('gifts', $sin, 'sin regalos no se pinta ni el contenedor');

        $this->pack()->update(['gifts' => self::GIFTS]);
        $seccion = $this->homeSection();

        $this->assertSame(2, substr_count($seccion, '<span class="gift">'), 'una etiqueta por regalo, sin los vacíos');
        $this->assertStringContainsString('class="gifts party-card__gifts"', $seccion);
        // El lector de pantalla oye qué es; a la vista lo dice la caja de regalo.
        $this->assertStringContainsString('<span class="sr-only">De regalo: </span>Cono de chuches', $seccion);
    }

    public function test_the_birthday_comparison_keeps_shared_gifts_in_their_own_row(): void
    {
        TicketType::query()->update(['gifts' => null]);
        $this->assertStringNotContainsString('party-compare__row--gifts', (string) $this->get('/cumpleanos')->getContent());

        $packs = TicketType::birthdaySurfacePacks()->get();
        foreach ($packs as $pack) {
            $pack->update(['gifts' => self::GIFTS]);
        }

        $html = (string) $this->get('/cumpleanos')->assertOk()->getContent();
        preg_match('#<table class="party-compare__table">.*?</table>#s', $html, $tabla);
        preg_match('#<ul class="party-shared__list".*?</ul>#s', $html, $igual);

        $this->assertStringContainsString('party-compare__row--gifts', $tabla[0] ?? '', 'los regalos comunes han bajado de la tabla');
        $this->assertSame(2 * $packs->count(), substr_count($tabla[0] ?? '', '<li class="gift">'), 'una etiqueta por regalo en cada columna');
        $this->assertStringNotContainsString('Cono de chuches', $igual[0] ?? '', 'un regalo no se repite en «Igual»');
    }

    public function test_the_catalog_api_publishes_gifts_apart_from_features(): void
    {
        $pack = $this->pack();
        $pack->update(['gifts' => self::GIFTS, 'features' => ['es' => ['Merienda']]]);

        $item = collect($this->getJson('/api/v1/catalog/products')->assertOk()->json('data'))->firstWhere('id', $pack->id);

        $this->assertNotNull($item, 'el pack no sale en el catálogo de la API');
        $this->assertSame(['Cono de chuches', 'Calcetines para todos'], $item['gifts']);
        $this->assertSame(['Merienda'], $item['features'], 'los regalos se han mezclado con las ventajas');
    }
}
