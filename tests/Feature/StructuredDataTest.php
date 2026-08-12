<?php

namespace Tests\Feature;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Content\Services\StructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_graph_includes_real_fields(): void
    {
        $graph = StructuredData::businessGraph([
            'name' => 'SaltoPark',
            'phone' => '968 22 22 22',
            'phone_tel' => '+34968222222',
            'email' => 'hola@saltopark.example',
            'address1' => 'Avenida de los Saltos, 22',
            'address2' => '',
            'city' => 'Villaparque',
            'instagram' => 'https://instagram.com/saltopark',
            'tiktok' => '#',
            'og_image' => 'https://cdn.example.com/og.jpg',
        ]);

        $this->assertSame('https://schema.org', $graph['@context']);
        [$org, $biz] = $graph['@graph'];

        $this->assertSame('Organization', $org['@type']);
        $this->assertSame('SaltoPark', $org['name']);
        // tiktok '#' (sentinela "sin configurar") se excluye; solo queda instagram.
        $this->assertSame(['https://instagram.com/saltopark'], $org['sameAs']);

        $this->assertSame('AmusementPark', $biz['@type']);
        $this->assertSame('+34968222222', $biz['telephone']);
        $this->assertSame('hola@saltopark.example', $biz['email']);
        $this->assertSame('PostalAddress', $biz['address']['@type']);
        $this->assertSame('Avenida de los Saltos, 22', $biz['address']['streetAddress']);
        $this->assertSame('Villaparque', $biz['address']['addressLocality']);
        $this->assertSame('https://cdn.example.com/og.jpg', $biz['image']);
        $this->assertSame(['@id' => url('/').'#organization'], $biz['parentOrganization']);
    }

    public function test_business_graph_omits_placeholders_and_empty_values(): void
    {
        $graph = StructuredData::businessGraph([
            'name' => 'SaltoPark',
            'phone' => '',
            'phone_tel' => '',
            'email' => '[PENDIENTE]',
            'address1' => '[PENDIENTE]',
            'address2' => '',
            'city' => '',
            'instagram' => '#',
            'tiktok' => '#',
            'og_image' => null,
        ]);

        [$org, $biz] = $graph['@graph'];

        // No se emite ningún campo vacío o con placeholder (Google desconfía del ruido).
        $this->assertArrayNotHasKey('telephone', $biz);
        $this->assertArrayNotHasKey('email', $biz);
        $this->assertArrayNotHasKey('address', $biz);
        $this->assertArrayNotHasKey('sameAs', $biz);
        $this->assertArrayNotHasKey('sameAs', $org);

        // Pero SIEMPRE emite un grafo válido mínimo (nombre + url + imagen por defecto).
        $this->assertSame('SaltoPark', $biz['name']);
        $this->assertArrayHasKey('url', $biz);
        $this->assertStringContainsString('og-image.jpg', $biz['image']);
    }

    public function test_to_json_escapes_script_breakout(): void
    {
        $payload = 'Hack</script><script>alert(1)</script>';
        $json = StructuredData::toJson(StructuredData::businessGraph(['name' => $payload]));

        // Anti-XSS: un valor editable no puede romper el <script type="application/ld+json">.
        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringNotContainsString('<script>', $json);

        // Sigue siendo JSON válido y conserva el valor original al decodificar.
        $decoded = json_decode($json, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertSame($payload, $decoded['@graph'][0]['name']);
    }

    public function test_opening_hours_groups_days_with_same_window(): void
    {
        // L-V 16:00-22:00 (weekday 1..5) y finde 11:00-22:00 (sábado=6, domingo=0).
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            OpeningHour::create(['weekday' => $weekday, 'open_time' => '16:00:00', 'close_time' => '22:00:00', 'is_closed' => false]);
        }
        OpeningHour::create(['weekday' => 6, 'open_time' => '11:00:00', 'close_time' => '22:00:00', 'is_closed' => false]);
        OpeningHour::create(['weekday' => 0, 'open_time' => '11:00:00', 'close_time' => '22:00:00', 'is_closed' => false]);

        $specs = StructuredData::openingHours();

        $this->assertCount(2, $specs); // dos ventanas distintas, no 7 entradas
        // Orden determinista por hora de apertura: 11:00 antes que 16:00.
        $this->assertSame('11:00', $specs[0]['opens']);
        $this->assertSame('22:00', $specs[0]['closes']);
        $this->assertCount(2, $specs[0]['dayOfWeek']); // domingo + sábado
        $this->assertSame('16:00', $specs[1]['opens']);
        $this->assertCount(5, $specs[1]['dayOfWeek']); // lunes-viernes
        $this->assertContains('https://schema.org/Monday', $specs[1]['dayOfWeek']);
        $this->assertSame('OpeningHoursSpecification', $specs[0]['@type']);
    }

    public function test_opening_hours_skips_closed_and_unset_days(): void
    {
        OpeningHour::create(['weekday' => 1, 'open_time' => '16:00:00', 'close_time' => '22:00:00', 'is_closed' => false]);
        OpeningHour::create(['weekday' => 2, 'open_time' => null, 'close_time' => null, 'is_closed' => true]);

        $specs = StructuredData::openingHours();

        $this->assertCount(1, $specs);
        $this->assertCount(1, $specs[0]['dayOfWeek']);
    }

    public function test_faq_page_builds_questions(): void
    {
        // Stubs con `tr()` (desacopla el test de los casts/locale del modelo Faq real).
        $faqs = [
            $this->fakeFaq('¿Edad mínima?', 'Desde 1 año, con un adulto.'),
            $this->fakeFaq('¿Calcetines?', 'Antideslizantes obligatorios.'),
        ];

        $schema = StructuredData::faqPage($faqs);

        $this->assertSame('FAQPage', $schema['@type']);
        $this->assertCount(2, $schema['mainEntity']);
        $this->assertSame('Question', $schema['mainEntity'][0]['@type']);
        $this->assertSame('¿Edad mínima?', $schema['mainEntity'][0]['name']);
        $this->assertSame('Desde 1 año, con un adulto.', $schema['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_faq_page_null_when_empty(): void
    {
        $this->assertNull(StructuredData::faqPage([]));
    }

    private function fakeFaq(string $question, string $answer): object
    {
        return new class($question, $answer)
        {
            public function __construct(private string $question, private string $answer) {}

            public function tr(string $field): string
            {
                return $field === 'question' ? $this->question : $this->answer;
            }
        };
    }
}
