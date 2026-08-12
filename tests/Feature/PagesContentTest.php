<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_legal_pages_are_seeded(): void
    {
        foreach (['privacidad', 'condiciones', 'waiver', 'cookies', 'aviso-legal'] as $slug) {
            $this->assertDatabaseHas('pages', ['slug' => $slug]);
        }

        $this->assertSame(5, Page::count());
    }

    public function test_page_translation_returns_active_locale(): void
    {
        $privacy = Page::where('slug', 'privacidad')->firstOrFail();

        app()->setLocale('es');
        $this->assertSame('Política de privacidad', $privacy->tr('title'));

        app()->setLocale('en');
        $this->assertSame('Privacy policy', $privacy->tr('title'));
    }
}
