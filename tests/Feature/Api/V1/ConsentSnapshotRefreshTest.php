<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Services\Analytics\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La foto del consentimiento de una sesión se REFRESCA lote a lote** (`specs/analitica.md` §4.3, T3a·3): se
 * toma al abrir la sesión y, si un lote posterior la trae distinta —el visitante aceptó «análisis» a mitad de
 * visita—, la sesión pasa a decir lo nuevo. Sin esto, el sello del pedido y el enlace con la cuenta mirarían la
 * foto de cuando entró, y quien consintió después contaría como «no» hasta la sesión de mañana.
 */
class ConsentSnapshotRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const VISITOR = '01HZX8K4N2P7Q9R3S5T6V8W0YA';

    /** @param array<string, bool>|null $consent */
    private function lote(?array $consent, string $name = 'page_viewed'): void
    {
        $event = [
            'event_id' => Visitor::mint(), 'name' => $name, 'route' => '/entradas', 'props' => $name === 'page_viewed' ? ['entry' => '/entradas'] : [],
            'occurred_at' => now()->getTimestampMs(),
        ];
        $meta = ['webdriver' => false] + ($consent === null ? [] : ['consent' => $consent]);

        $this->withCredentials()
            ->withUnencryptedCookie(Visitor::COOKIE, self::VISITOR)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'])
            ->postJson('/api/v1/events', ['events' => [$event], 'meta' => $meta])
            ->assertStatus(202);
    }

    public function test_a_later_batch_with_a_different_consent_refreshes_the_session_snapshot(): void
    {
        $this->lote(['analytics' => false, 'marketing' => false]);
        $session = AnalyticsSession::query()->where('visitor_id', self::VISITOR)->sole();
        $this->assertSame(['analytics' => false, 'marketing' => false], $session->consent);

        $this->lote(['analytics' => true, 'marketing' => false], 'consent_updated');

        $this->assertSame(1, AnalyticsSession::query()->count(), 'la misma sesión: consentir no abre otra');
        $this->assertSame(['analytics' => true, 'marketing' => false], $session->fresh()->consent);
    }

    public function test_a_batch_without_a_snapshot_does_not_erase_the_one_the_session_has(): void
    {
        $this->lote(['analytics' => true, 'marketing' => false]);
        $this->lote(null, 'section_viewed');
        $this->lote([], 'section_viewed');

        $this->assertSame(['analytics' => true, 'marketing' => false], AnalyticsSession::query()->sole()->consent);
    }
}
