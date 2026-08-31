<?php

namespace Tests\Feature\Admin\Settings;

use App\Domain\Booking\Services\AgeFamilySeal;
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los TRES textos por instalación de «una edad sin producto» (`docs/specs/cumple-mixto.md` §22.4,
 * `DECISIONES #284` D6): se editan por idioma en Ajustes, mandan sobre el texto por defecto cuando
 * están escritos, caen al respaldo cuando no, y `:phone` se resuelve con el teléfono del parque.
 */
class MixedPartyTextsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        // Baseline mínimo para los campos obligatorios del formulario de Settings.
        foreach ([
            ['business.name', 'SaltoPark', 'business'],
            ['contact.email', 'hola@saltopark.example', 'contact'],
            ['contact.phone', '968 22 22 22', 'contact'],
            ['sales.hold_minutes', '15', 'payment'],
            ['sales.purchase_horizon_months', '6', 'payment'],
            ['puerta.validate_rate_limit_per_minute', '100', 'puerta'],
            ['redsys_environment', 'test', 'payment'],
            ['redsys_currency', '978', 'payment'],
        ] as [$key, $value, $group]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
        Setting::flushMemo();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    public function test_the_three_texts_are_saved_per_language_from_the_settings_page(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'mixed_party.no_product.below.es' => 'Para menores de 3 no hay cumpleaños: llámanos al :phone.',
                'mixed_party.no_product.gap.en' => 'That age falls between two bands: call us on :phone.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Para menores de 3 no hay cumpleaños: llámanos al :phone.', Setting::value('mixed_party.no_product.below.es'));
        $this->assertSame('That age falls between two bands: call us on :phone.', Setting::value('mixed_party.no_product.gap.en'));
        $this->assertSame('mixed_party', Setting::where('key', 'mixed_party.no_product.below.es')->value('group'));
    }

    public function test_the_park_text_wins_the_fallback_follows_and_the_phone_is_filled_in(): void
    {
        // Sin texto del parque: el por defecto, con el teléfono puesto. Mutación: leer siempre el
        // respaldo, o no sustituir `:phone` → rojo.
        $this->assertSame(
            __('guestform.no_product_above', ['phone' => '968 22 22 22']),
            MixedPartySettings::noProductText(AgeFamilySeal::NO_PRODUCT_ABOVE, 'es'),
        );

        // Con texto del parque en el idioma pedido: el suyo.
        Setting::updateOrCreate(['key' => 'mixed_party.no_product.above.es'], ['value' => 'Mayores de 12: llama al :phone.', 'group' => 'mixed_party']);
        Setting::flushMemo();
        $this->assertSame('Mayores de 12: llama al 968 22 22 22.', MixedPartySettings::noProductText(AgeFamilySeal::NO_PRODUCT_ABOVE, 'es'));

        // En otro idioma sin texto propio cae al que el parque SÍ escribió (pedido → respaldo de la
        // app → es → en → fr), no al por defecto: mejor su frase en otro idioma que una que no dice
        // lo que él decidió. Mutación: leer solo el idioma pedido → rojo.
        $this->assertSame('Mayores de 12: llama al 968 22 22 22.', MixedPartySettings::noProductText(AgeFamilySeal::NO_PRODUCT_ABOVE, 'fr'));

        // Un texto EN BLANCO no es un texto: respaldo.
        Setting::updateOrCreate(['key' => 'mixed_party.no_product.above.es'], ['value' => '   ', 'group' => 'mixed_party']);
        Setting::flushMemo();
        $this->assertStringContainsString('968 22 22 22', MixedPartySettings::noProductText(AgeFamilySeal::NO_PRODUCT_ABOVE, 'es'));
        $this->assertStringNotContainsString(':phone', MixedPartySettings::noProductText(AgeFamilySeal::NO_PRODUCT_ABOVE, 'es'));
    }

    public function test_without_a_park_phone_the_sentence_still_has_someone_to_call(): void
    {
        Setting::where('key', 'contact.phone')->delete();
        Setting::flushMemo();

        $text = MixedPartySettings::noProductText(AgeFamilySeal::NO_PRODUCT_GAP, 'es');

        $this->assertStringNotContainsString(':phone', $text);
        $this->assertStringContainsString(__('guestform.no_product_phone_fallback'), $text);
    }
}
