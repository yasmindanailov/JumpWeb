<?php

namespace Tests\Feature\Admin\Offers;

use App\Domain\Content\Models\Offer;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ofertas (#270) — `OfferResource`: gating por `content.manage`, CRUD con imagen subida (FileUpload)
 * + título i18n + audit, imagen requerida, y limpieza de huérfanos (borrar/reemplazar borra el fichero).
 */
class OfferResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake(Offer::IMAGE_DISK);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    public function test_gating_admin_only(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(OfferResource::canViewAny());
        $this->get('/admin/offers')->assertOk();

        $this->actingAs($this->staff())->get('/admin/offers')->assertForbidden();
    }

    public function test_create_persists_with_image_i18n_and_audit(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateOffer::class)
            ->fillForm([
                'position' => 2,
                'is_active' => true,
                'title' => ['es' => 'Oferta 2x1', 'en' => 'Offer 2for1', 'fr' => ''],
                'image' => UploadedFile::fake()->image('promo.png'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::firstOrFail();
        $this->assertSame(['es' => 'Oferta 2x1', 'en' => 'Offer 2for1'], $offer->title, 'fr vacío descartado');
        $this->assertNotNull($offer->image, 'la imagen subida se persiste');
        $this->assertStringStartsWith('ofertas/', $offer->image);
        Storage::disk(Offer::IMAGE_DISK)->assertExists($offer->image);
        $this->assertSame(2, $offer->position);
        $this->assertTrue($offer->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.offer_created', 'target_id' => $offer->id]);
    }

    public function test_create_requires_spanish_title_and_image(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateOffer::class)
            ->fillForm(['title' => ['es' => ''], 'position' => 0, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['title.es', 'image']);
    }

    public function test_edit_toggle_and_audit(): void
    {
        // El fichero debe existir en el disco para que el FileUpload lo dé por «relleno» al editar
        // sin tocarlo (en producción existe de verdad; aquí, disco fake).
        Storage::disk(Offer::IMAGE_DISK)->put('ofertas/x.webp', 'x');
        $offer = Offer::create(['title' => ['es' => 'X'], 'image' => 'ofertas/x.webp', 'is_active' => true, 'position' => 0]);

        Livewire::actingAs($this->admin())
            ->test(EditOffer::class, ['record' => $offer->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($offer->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.offer_updated', 'target_id' => $offer->id]);
    }

    public function test_delete_removes_record_image_and_audits(): void
    {
        Storage::disk(Offer::IMAGE_DISK)->put('ofertas/del.webp', 'x');
        $offer = Offer::create(['title' => ['es' => 'Del'], 'image' => 'ofertas/del.webp', 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(EditOffer::class, ['record' => $offer->id])
            ->callAction('deleteOffer');

        $this->assertNull($offer->fresh());
        Storage::disk(Offer::IMAGE_DISK)->assertMissing('ofertas/del.webp'); // huérfano borrado
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.offer_deleted', 'target_id' => $offer->id]);
    }

    public function test_replacing_image_deletes_the_old_file(): void
    {
        Storage::disk(Offer::IMAGE_DISK)->put('ofertas/old.webp', 'x');
        $offer = Offer::create(['title' => ['es' => 'R'], 'image' => 'ofertas/old.webp', 'is_active' => true]);

        $offer->update(['image' => 'ofertas/new.webp']);

        Storage::disk(Offer::IMAGE_DISK)->assertMissing('ofertas/old.webp');
    }
}
