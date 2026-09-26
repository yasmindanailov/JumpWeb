<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use App\Http\Fiesta\Sitio;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LOS AJUSTES DE LA FIESTA en el panel (`specs/fiesta-sistema-nuevo.md` F1c, `#743` §7·7): «Ver el parque» toma el
 * vídeo de portada y su foto de dos ajustes que el parque escribe en Ajustes, como los sirve su web (una ruta bajo
 * `public/`) o como URL entera. Lo que los lee es `Sitio::datos()`, con las mismas reglas que el resto de enlaces
 * externos del producto (`safeExternalUrl`).
 */
class AjustesFiestaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::where('email', 'admin@jumpweb.test')->firstOrFail();
    }

    public function test_the_park_video_and_its_poster_are_written_from_the_panel_and_served_by_the_installation(): void
    {
        Livewire::actingAs($this->admin())->test(Settings::class)
            ->assertFormFieldExists('party.park_video')
            ->fillForm(['party.park_video' => 'videos/header_hero.mp4', 'party.park_video_poster' => '/videos/header_poster.jpg'])
            ->call('save')
            ->assertHasNoFormErrors();
        Setting::flushMemo();

        $site = Sitio::datos();
        $this->assertSame(asset('videos/header_hero.mp4'), $site['park_video'], 'una ruta bajo public/, servida por la instalación');
        $this->assertSame(asset('videos/header_poster.jpg'), $site['park_video_poster'], 'la barra inicial no duplica el asset');
    }

    public function test_an_external_url_passes_the_same_door_as_every_external_link_and_empty_means_no_video(): void
    {
        Setting::query()->updateOrCreate(['key' => 'party.park_video'], ['value' => 'https://cdn.example.com/portada.mp4', 'group' => 'party']);
        Setting::query()->updateOrCreate(['key' => 'party.park_video_poster'], ['value' => 'javascript:alert(1)', 'group' => 'party']);
        Setting::flushMemo();

        $site = Sitio::datos();
        $this->assertSame('https://cdn.example.com/portada.mp4', $site['park_video']);
        $this->assertSame('', $site['park_video_poster'], 'un esquema que no es http(s) no pasa (`SEC-07`)');

        Setting::query()->where('key', 'party.park_video')->delete();
        Setting::flushMemo();
        $this->assertSame('', Sitio::datos()['park_video'], 'sin ajuste, sin vídeo');
    }
}
