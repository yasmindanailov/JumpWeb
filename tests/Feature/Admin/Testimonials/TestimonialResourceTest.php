<?php

namespace Tests\Feature\Admin\Testimonials;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Opiniones» del panel con las reseñas copiadas de la ficha (`DECISIONES #771`): las PÁGINAS en que sale cada una se
 * guardan limpias, el enlace a Google solo lo lleva una copiada de Google, y el listado dice de dónde es cada una.
 */
class TestimonialResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    public function test_the_pages_are_stored_clean_and_an_own_one_carries_no_google_link(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateTestimonial::class)
            ->fillForm([
                'author' => 'Marta Ruiz', 'origin' => Testimonial::ORIGIN_OWN, 'source_url' => 'https://www.google.com/maps/x',
                'text' => ['es' => 'Genial.'], 'tags' => [' Kids ', 'kids', 'JUMP', ''], 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $o = Testimonial::firstOrFail();
        $this->assertSame(['kids', 'jump'], $o->tags, 'en minúscula, sin espacios, sin repetir y sin vacías');
        $this->assertNull($o->source_url, 'una escrita en el panel no lleva enlace a Google');
    }

    public function test_a_google_copy_keeps_its_link_and_the_list_says_where_it_comes_from(): void
    {
        $copia = Testimonial::create([
            'origin' => Testimonial::ORIGIN_GOOGLE, 'source_ref' => 'Abc', 'source_url' => 'https://www.google.com/maps/place/Play+Jump+Park',
            'author' => 'Lucía P.', 'text' => ['es' => 'Muy bien.'], 'is_active' => false,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditTestimonial::class, ['record' => $copia->id])
            ->fillForm(['tags' => ['kids'], 'is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $copia->refresh();
        $this->assertSame([['kids'], true, 'https://www.google.com/maps/place/Play+Jump+Park'], [$copia->tags, $copia->is_active, $copia->source_url]);

        Livewire::actingAs($this->admin())
            ->test(ListTestimonials::class)
            ->assertCanSeeTableRecords([$copia])
            ->assertSee(__('admin.testimonials.origins.google'));
    }
}
