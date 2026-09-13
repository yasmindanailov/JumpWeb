<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\LandingService;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 (#256, modelo A): la entidad `LandingService` es la ÚNICA fuente de clasificación de
 * superficie. Un pack vinculado a un servicio sale de Cumpleaños.
 * ▶ Desde `#588` un servicio vende VARIOS productos (una excursión son dos: 2 y 3 horas), y un
 * producto sigue estando en UN servicio como mucho.
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
            'position' => 1,
        ])->products()->attach($pack->id);

        $ids = TicketType::birthdaySurfacePacks()->pluck('id');
        $this->assertSame(1, $ids->count());
        $this->assertFalse($ids->contains($pack->id), 'El pack con LandingService sale de Cumpleaños.');
    }

    /**
     * **Los DOS productos de un servicio salen de Cumpleaños** (`#588`). Era el síntoma: con el 1:1 la
     * excursión de 3 horas no tenía servicio y se anunciaba en la página de cumpleaños.
     */
    public function test_a_service_sells_several_products_and_all_of_them_leave_the_birthday_surface(): void
    {
        $packs = TicketType::ofType(TicketType::TYPE_PACK)->pluck('id');
        $this->assertCount(2, $packs);

        $service = LandingService::create(['slug' => 'excursiones', 'title' => ['es' => 'Excursiones']]);
        $service->products()->attach($packs->all());

        $this->assertSame(0, TicketType::birthdaySurfacePacks()->count());
        $this->assertEqualsCanonicalizing($packs->all(), $service->fresh()->products->pluck('id')->all());
    }

    /** Un producto se vende desde UN servicio como mucho: en dos se duplicaría su tabla. */
    public function test_a_product_cannot_be_sold_from_two_services(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();
        LandingService::create(['slug' => 'uno', 'title' => ['es' => 'Uno']])->products()->attach($pack->id);

        $this->expectException(QueryException::class);
        LandingService::create(['slug' => 'dos', 'title' => ['es' => 'Dos']])->products()->attach($pack->id);
    }

    public function test_is_purchasable_reflects_the_linked_packs(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();

        $withPack = LandingService::create(['slug' => 'con-pack', 'title' => ['es' => 'Con pack']]);
        $withPack->products()->attach($pack->id);
        $contactOnly = LandingService::create(['slug' => 'solo-contacto', 'title' => ['es' => 'Solo contacto']]);

        $this->assertTrue($withPack->fresh()->isPurchasable());
        $this->assertFalse($contactOnly->isPurchasable(), 'Sin packs vinculados = solo-contacto.');

        // Si el pack deja de ser vendible, la sección degrada a contacto (coherencia #226).
        $pack->update(['is_sellable' => false]);
        $this->assertFalse($withPack->fresh()->isPurchasable());
        $this->assertCount(0, $withPack->fresh()->purchasableProducts());

        // Si la ZONA del pack se desactiva, tampoco es comprable: los packs venden por aforo de zona
        // y el sidebar no podría venderlo (coherencia CTA⟺catálogo, #210/#226).
        $pack->update(['is_sellable' => true]);
        $pack->zone->update(['is_active' => false]);
        $this->assertFalse($withPack->fresh()->isPurchasable(), 'zona desactivada = no comprable');
    }

    public function test_deleting_the_pack_removes_the_link_and_degrades_to_contact(): void
    {
        $pack = TicketType::ofType(TicketType::TYPE_PACK)->first();
        $svc = LandingService::create(['slug' => 'con-pack', 'title' => ['es' => 'X']]);
        $svc->products()->attach($pack->id);
        $this->assertTrue($svc->fresh()->isPurchasable());

        $pack->delete();

        $this->assertTrue($svc->products()->doesntExist(), 'borrar el pack borra su enlace (cascada)');
        $this->assertFalse($svc->fresh()->isPurchasable(), 'sin packs vinculados = solo-contacto');
    }

    /**
     * ⚠️ El scope `inNav()` se retiró con su único consumidor, el menú viejo (`#521`): los destinos
     * del menú son el inventario de páginas. Aquí quedan los dos scopes que sí se usan.
     */
    public function test_scopes_active_and_ordered(): void
    {
        // Aísla los scopes de los servicios sembrados por el fixture (3 secciones reales).
        LandingService::query()->delete();

        LandingService::create(['slug' => 'b', 'title' => ['es' => 'B'], 'position' => 2, 'is_active' => true]);
        LandingService::create(['slug' => 'a', 'title' => ['es' => 'A'], 'position' => 1, 'is_active' => false]);
        LandingService::create(['slug' => 'c', 'title' => ['es' => 'C'], 'position' => 3, 'is_active' => true]);

        $this->assertSame(['b', 'c'], LandingService::active()->ordered()->pluck('slug')->all());
        $this->assertSame(['a', 'b', 'c'], LandingService::ordered()->pluck('slug')->all());
    }
}
