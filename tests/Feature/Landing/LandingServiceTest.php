<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\LandingService;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 (#256, modelo A): la entidad `LandingService` es la ÚNICA fuente de clasificación de
 * superficie. La existencia de un LandingService que referencie un pack lo saca de Cumpleaños.
 */
class LandingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixture: 2 packs vendibles (Jump/Kids) en la zona cumpleaños operativa.
        $this->seed(LandingContentSeeder::class);
    }

    public function test_birthday_surface_lists_packs_without_a_landing_service(): void
    {
        $this->assertSame(2, TicketType::birthdaySurfacePacks()->count());
    }

    public function test_a_pack_with_a_landing_service_leaves_the_birthday_surface(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();

        LandingService::create([
            'slug' => 'eventos-empresa',
            'title' => ['es' => 'Eventos de empresa'],
            'ticket_type_id' => $pack->id,
            'position' => 1,
        ]);

        $ids = TicketType::birthdaySurfacePacks()->pluck('id');
        $this->assertSame(1, $ids->count());
        $this->assertFalse($ids->contains($pack->id), 'El pack con LandingService sale de Cumpleaños.');
    }

    public function test_is_purchasable_reflects_the_linked_pack(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();

        $withPack = LandingService::create(['slug' => 'con-pack', 'title' => ['es' => 'Con pack'], 'ticket_type_id' => $pack->id]);
        $contactOnly = LandingService::create(['slug' => 'solo-contacto', 'title' => ['es' => 'Solo contacto'], 'ticket_type_id' => null]);

        $this->assertTrue($withPack->isPurchasable());
        $this->assertFalse($contactOnly->isPurchasable(), 'Sin pack vinculado = solo-contacto.');

        // Si el pack deja de ser vendible, la sección degrada a contacto (coherencia #226).
        $pack->update(['is_sellable' => false]);
        $this->assertFalse($withPack->fresh()->isPurchasable());

        // Si la ZONA del pack se desactiva, tampoco es comprable: los packs venden por aforo de zona
        // y el sidebar no podría venderlo (coherencia CTA⟺catálogo, #210/#226).
        $pack->update(['is_sellable' => true]);
        $pack->zone->update(['is_active' => false]);
        $this->assertFalse($withPack->fresh()->isPurchasable(), 'zona desactivada = no comprable');
    }

    public function test_deleting_the_pack_nulls_the_link_and_degrades_to_contact(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();
        $svc = LandingService::create(['slug' => 'con-pack', 'title' => ['es' => 'X'], 'ticket_type_id' => $pack->id]);
        $this->assertTrue($svc->isPurchasable());

        $pack->delete();

        $svc->refresh();
        $this->assertNull($svc->ticket_type_id, 'nullOnDelete: la FK queda en NULL al borrar el pack');
        $this->assertFalse($svc->isPurchasable(), 'sin pack vinculado = solo-contacto');
    }

    public function test_scopes_active_in_nav_and_ordered(): void
    {
        // Aísla los scopes de los servicios sembrados por el fixture (3 secciones reales).
        LandingService::query()->delete();

        LandingService::create(['slug' => 'b', 'title' => ['es' => 'B'], 'position' => 2, 'is_active' => true, 'show_in_nav' => false]);
        LandingService::create(['slug' => 'a', 'title' => ['es' => 'A'], 'position' => 1, 'is_active' => false, 'show_in_nav' => true]);
        LandingService::create(['slug' => 'c', 'title' => ['es' => 'C'], 'position' => 3, 'is_active' => true, 'show_in_nav' => true]);

        $this->assertSame(['a', 'c'], LandingService::inNav()->ordered()->pluck('slug')->all());
        $this->assertSame(['b', 'c'], LandingService::active()->ordered()->pluck('slug')->all());
    }
}
