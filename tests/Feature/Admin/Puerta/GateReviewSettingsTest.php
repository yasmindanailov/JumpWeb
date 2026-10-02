<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\ReviewKeywords;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Las palabras de la reseña del día, en el panel** (`docs/specs/puerta-nueva.md` §4.4, la P3: D17 y D21): se guardan
 * limpias, el formulario para lo que no cabe y, debajo, la ayuda dice lo que la Puerta haría con ellas —de la MISMA
 * consulta que la Puerta—.
 */
class GateReviewSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-02 10:00:00', 'Europe/Madrid'));
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app()->setLocale('es');
        $this->admin = User::factory()->create();
        $this->admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        // Lo que la página de ajustes exige para guardar (el molde de `WaiverSettingsTest`).
        foreach ([
            ['business.name', 'SaltoPark', 'business'], ['contact.email', 'hola@saltopark.example', 'contact'],
            ['sales.hold_minutes', '15', 'payment'], ['sales.purchase_horizon_months', '6', 'payment'],
            ['puerta.validate_rate_limit_per_minute', '100', 'puerta'], ['redsys_environment', 'test', 'payment'],
            ['redsys_currency', '978', 'payment'],
        ] as [$key, $value, $group]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
    }

    public function test_the_words_are_saved_clean(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->fillForm([ReviewKeywords::KEY => "Monitor\n  muy   atentos \n\nIrene\nMONITOR\nmonitór"])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame("Monitor\nmuy atentos\nIrene", Setting::value(ReviewKeywords::KEY));
    }

    public function test_the_form_stops_too_many_words_and_a_too_long_one_without_saving(): void
    {
        Setting::updateOrCreate(['key' => ReviewKeywords::KEY], ['value' => 'monitor', 'group' => 'puerta']);
        $muchas = implode("\n", array_map(static fn (int $i): string => 'palabra'.$i, range(1, ReviewKeywords::MAX_ENTRIES + 1)));

        foreach ([$muchas, "monitor\n".str_repeat('a', ReviewKeywords::MAX_LENGTH + 1)] as $mal) {
            Livewire::actingAs($this->admin)
                ->test(Settings::class)
                ->fillForm([ReviewKeywords::KEY => $mal])
                ->call('save')
                ->assertHasFormErrors([ReviewKeywords::KEY]);
        }

        Setting::flushMemo();
        $this->assertSame('monitor', Setting::value(ReviewKeywords::KEY));
    }

    /** La ayuda dice, en cada caso, lo que la Puerta haría: sin palabras, sin reseñas, sin ninguna que case y la de hoy. */
    public function test_the_help_says_what_the_gate_would_do(): void
    {
        $pagina = fn () => Livewire::actingAs($this->admin)->test(Settings::class);

        $pagina()->assertSee('Sin palabras, la Puerta no enseña ninguna reseña.');

        Setting::updateOrCreate(['key' => ReviewKeywords::KEY], ['value' => "monitor\nIrene", 'group' => 'puerta']);
        $pagina()->assertSee('Ahora no hay reseñas de Google de los últimos 30 días');

        $copiada = Testimonial::create([
            'origin' => Testimonial::ORIGIN_GOOGLE, 'source_ref' => 'copia-1', 'author' => 'Laura M.', 'rating' => 5,
            'text' => ['es' => 'Todo perfecto, repetiremos.'], 'published_at' => '2026-09-29', 'is_active' => true, 'position' => 0,
        ]);
        $pagina()->assertSee('Ahora ninguna reseña de Google de los últimos 30 días dice estas palabras');

        $copiada->update(['text' => ['es' => 'Los monitores, un diez: Irene estuvo pendiente de los peques toda la tarde.']]);
        $pagina()->assertSee('Ahora las dice 1 reseña, la de Laura M.: «Los monitores, un diez: Irene estuvo pendiente de los peques…».');

        // Con varias, salen por turno y la ayuda nombra la MÁS NUEVA (la primera del turno de la Puerta).
        Testimonial::create([
            'origin' => Testimonial::ORIGIN_GOOGLE, 'source_ref' => 'copia-2', 'author' => 'Marcos P.', 'rating' => 5,
            'text' => ['es' => 'Un equipo de diez.'], 'published_at' => '2026-09-30', 'is_active' => true, 'position' => 0,
        ]);
        Setting::updateOrCreate(['key' => ReviewKeywords::KEY], ['value' => "monitor\nIrene\nequipo", 'group' => 'puerta']);
        $pagina()->assertSee('Ahora las dicen 2 reseñas, que salen por turno; la más nueva, la de Marcos P.: «Un equipo de diez.».');
    }

    /** La ayuda sigue a lo ESCRITO al salir del campo, sin guardar: así el parque prueba sus palabras antes de guardarlas. */
    public function test_the_help_follows_what_is_typed_before_saving(): void
    {
        Testimonial::create([
            'origin' => Testimonial::ORIGIN_GOOGLE, 'source_ref' => 'copia-1', 'author' => 'Laura M.', 'rating' => 5,
            'text' => ['es' => 'Irene estuvo pendiente de los peques.'], 'published_at' => '2026-09-29', 'is_active' => true, 'position' => 0,
        ]);

        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->assertSee('Sin palabras, la Puerta no enseña ninguna reseña.')
            ->fillForm([ReviewKeywords::KEY => 'irene'])
            ->assertSee('la de Laura M.: «Irene estuvo pendiente de los peques.»');

        $this->assertNull(Setting::value(ReviewKeywords::KEY));
    }
}
