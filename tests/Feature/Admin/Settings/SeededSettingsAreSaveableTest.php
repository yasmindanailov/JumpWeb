<?php

namespace Tests\Feature\Admin\Settings;

use App\Domain\Identity\Models\User;
use App\Filament\Pages\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Lo que siembra `db:seed` tiene que poder GUARDARSE tal cual desde Ajustes.**
 *
 * ⚠️ Medido con el owner delante el 2026-08-26 (`DEUDA.md` · Baja): `LandingContentSeeder` sembraba
 * `address.maps_url = '#'`, el formulario exige una URL, y «Guardar» —pulsado al final de la página
 * para cambiar el modo del waiver— rechazaba el formulario ENTERO sin nada visible junto al botón.
 * Una instalación recién sembrada no podía guardar NINGÚN ajuste hasta dar con ese campo, media
 * página más arriba. La landing ya pinta `#` cuando el ajuste está vacío (`AppServiceProvider`), así
 * que el placeholder era redundante además de bloqueante.
 *
 * Esta guarda cierra la raíz: si un seeder vuelve a sembrar un valor que el formulario no acepta, cae
 * aquí y no en el navegador del operador. Siembra el `DatabaseSeeder` ENTERO —el mismo que corre
 * `db:seed`— y guarda con el admin de prueba que ese seeder crea, sin tocar ningún campo.
 */
class SeededSettingsAreSaveableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freshly_seeded_installation_can_save_its_settings_untouched(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@jumpweb.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
