<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Platform\Models\Setting;
use App\Http\Sidebar\PurchaseResume;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La compra que salió a Google y vuelve nace ABIERTA** (T3e·4 de `specs/isla-y-landing-nueva.md` §4.10,
 * `DECISIONES #695`): la isla manda a Google con `next` = la misma página + `?compra=reanudar`, y con ese parámetro el
 * layout la sirve con la compra abierta —el mecanismo de `/entradas`— y marcada como vuelta, para que la apertura
 * se cuente como `resume`. Sin el parámetro, nada cambia; con la compra online cerrada, no se abre nada.
 */
class PurchaseResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_the_return_from_google_is_served_with_the_purchase_open_and_marked(): void
    {
        $this->get('/?compra=reanudar')
            ->assertOk()
            ->assertSee('data-purchase-open="1"', false)
            ->assertSee('data-purchase-resume="1"', false);
    }

    public function test_without_the_parameter_or_with_another_value_nothing_changes(): void
    {
        foreach (['/', '/?compra=otra', '/?reanudar=compra'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-purchase-open=""', false)
                ->assertSee('data-purchase-resume=""', false);
        }
    }

    public function test_with_online_sales_closed_the_return_opens_nothing(): void
    {
        Setting::query()->updateOrCreate(['key' => 'sales.online_enabled'], ['value' => '0', 'group' => 'sales']);
        Setting::flushMemo();

        $this->get('/?compra=reanudar')
            ->assertOk()
            ->assertSee('data-purchase-open=""', false)
            ->assertSee('data-purchase-resume=""', false);
    }

    /**
     * ⚠️ El parámetro lo ESCRIBE el navegador (`resources/js/sidebar/reanudar.js`) y lo LEE el servidor: una errata en
     * uno de los dos no rompería nada visible —la página volvería cerrada y la compra se quedaría a medias— y nadie lo
     * notaría. Es la familia de `DECISIONES #117`: algo que «no falla y no hace nada».
     */
    public function test_the_browser_writes_the_parameter_the_server_reads(): void
    {
        $js = (string) file_get_contents(resource_path('js/sidebar/reanudar.js'));

        $this->assertStringContainsString("export const PARAMETRO = '".PurchaseResume::PARAM."';", $js);
        $this->assertStringContainsString("export const VALOR = '".PurchaseResume::VALUE."';", $js);
    }
}
