<?php

namespace Tests\Feature\Admin\Promotions;

use App\Domain\Booking\Models\Promotion;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\PromotionResource;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Promociones» del panel (`docs/specs/promociones.md` §4.2, `#770`): quién entra, que se llega desde Ajustes, que el
 * OBJETIVO se guarda en la clave que toca y limpia la otra, que una oferta exige su fin, el estado del listado y la
 * auditoría.
 */
class PromotionResourceTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00', 'Europe/Madrid'));
        $this->zona = Zone::create(['name' => ['es' => 'Kids'], 'slug' => 'kids', 'accent' => 'kids', 'color' => '#C6FF3A', 'is_active' => true, 'position' => 1]);
    }

    private function usuario(string $rol): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $rol)->value('id')]);

        return $u;
    }

    private function producto(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Pack Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zona->id,
            'duration_min' => 120, 'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    public function test_only_who_manages_the_catalog_gets_in_and_it_is_reachable_from_settings(): void
    {
        $this->actingAs($this->usuario('admin'))->get('/admin/promotions')->assertOk();
        $this->actingAs($this->usuario('staff'))->get('/admin/promotions')->assertForbidden();

        $this->actingAs($this->usuario('admin'));
        $enlaces = collect((new AdminSettingsHub)->visibleAreas())->flatMap(fn (array $a): array => array_column($a['items'], 'url'));
        $this->assertContains(PromotionResource::getUrl('index'), $enlaces->all(), 'Promociones tiene que colgar de Ajustes: fuera del menú, es la única puerta');
    }

    public function test_an_offer_for_a_zone_is_created_with_its_end_and_audited(): void
    {
        Livewire::actingAs($this->usuario('admin'))
            ->test(CreatePromotion::class)
            ->fillForm([
                'kind' => Promotion::KIND_OFFER,
                'target' => Promotion::TARGET_ZONE,
                'zone_id' => $this->zona->id,
                'ends_on' => '2026-09-30',
                'text' => ['es' => ' −20 % si reservas online ', 'en' => '', 'fr' => ''],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Promotion::firstOrFail();
        $this->assertSame(['es' => '−20 % si reservas online'], $p->text, 'el texto se guarda recortado y sin idiomas vacíos');
        $this->assertSame(Promotion::TARGET_ZONE, $p->target());
        $this->assertNull($p->ticket_type_id);
        $this->assertSame('2026-09-30', $p->ends_on?->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.promotion_created', 'target_id' => $p->id]);
    }

    public function test_an_offer_without_an_end_is_refused_and_a_gift_does_not_need_one(): void
    {
        Livewire::actingAs($this->usuario('admin'))
            ->test(CreatePromotion::class)
            ->fillForm(['kind' => Promotion::KIND_OFFER, 'target' => Promotion::TARGET_INSTALLATION, 'text' => ['es' => 'Oferta']])
            ->call('create')
            ->assertHasFormErrors(['ends_on' => 'required']);
        $this->assertSame(0, Promotion::count());

        Livewire::actingAs($this->usuario('admin'))
            ->test(CreatePromotion::class)
            ->fillForm(['kind' => Promotion::KIND_GIFT, 'target' => Promotion::TARGET_INSTALLATION, 'text' => ['es' => 'Calcetines para todos']])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(Promotion::TARGET_INSTALLATION, Promotion::firstOrFail()->target());
    }

    public function test_changing_the_target_clears_the_key_that_no_longer_applies(): void
    {
        $producto = $this->producto();
        $p = Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Cono'], 'zone_id' => $this->zona->id]);

        Livewire::actingAs($this->usuario('admin'))
            ->test(EditPromotion::class, ['record' => $p->id])
            ->assertFormSet(['target' => Promotion::TARGET_ZONE, 'zone_id' => $this->zona->id])
            ->fillForm(['target' => Promotion::TARGET_PRODUCT, 'ticket_type_id' => $producto->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $p->refresh();
        $this->assertSame([null, $producto->id], [$p->zone_id, $p->ticket_type_id], 'la zona de antes no se queda escondida en la fila');
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.promotion_updated', 'target_id' => $p->id]);
    }

    public function test_the_model_refuses_what_no_form_may_let_through(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Promotion::create(['kind' => Promotion::KIND_OFFER, 'text' => ['es' => 'Sin fin']]);
    }

    public function test_the_model_refuses_two_targets(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Dos'], 'zone_id' => $this->zona->id, 'ticket_type_id' => $this->producto()->id]);
    }

    public function test_the_list_says_the_state_of_each_one_today(): void
    {
        $vigente = Promotion::create(['kind' => Promotion::KIND_OFFER, 'text' => ['es' => 'Vigente', 'en' => 'Live', 'fr' => 'En cours'], 'ends_on' => '2026-09-30']);
        $programada = Promotion::create(['kind' => Promotion::KIND_OFFER, 'text' => ['es' => 'Programada'], 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);
        $acabada = Promotion::create(['kind' => Promotion::KIND_OFFER, 'text' => ['es' => 'Acabada'], 'ends_on' => '2026-09-24']);
        $apagada = Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Apagada'], 'is_active' => false]);

        $this->assertSame(['current', 'scheduled', 'ended', 'inactive'], [$vigente->state(), $programada->state(), $acabada->state(), $apagada->state()]);

        Livewire::actingAs($this->usuario('admin'))
            ->test(ListPromotions::class)
            ->assertCanSeeTableRecords([$vigente, $programada, $acabada, $apagada])
            ->assertSee(__('admin.promotions.states.scheduled'))
            ->assertSee(__('admin.promotions.languages_missing', ['langs' => 'EN, FR']));
    }

    public function test_deleting_audits_and_removes_it(): void
    {
        $p = Promotion::create(['kind' => Promotion::KIND_GIFT, 'text' => ['es' => 'Cono']]);

        Livewire::actingAs($this->usuario('admin'))
            ->test(EditPromotion::class, ['record' => $p->id])
            ->callAction('deletePromotion');

        $this->assertSame(0, Promotion::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.promotion_deleted', 'target_id' => $p->id]);
    }
}
