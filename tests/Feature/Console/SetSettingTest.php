<?php

namespace Tests\Feature\Console;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `app:set-setting` — la puerta CLI a los ajustes que el panel no expone (`DECISIONES #109`).
 *
 * ⚠️ **Lo que de verdad hay que fijar aquí es lo que el comando NO deja hacer.** Escribir una fila en
 * `settings` es trivial; lo valioso es que las tres claves que cuestan dinero o corrompen la
 * numeración de pedidos exijan `--force` escrito a mano, y que un secreto no acabe impreso en el log
 * del despliegue —este comando se ejecuta por SSH—.
 *
 * El segundo caso que importa es el del anti-bot de punta a punta: no basta con que la fila exista,
 * tiene que hacer que `Turnstile::enabled()` pase a `true`. Es la diferencia entre «escribí algo» y
 * «la instalación quedó configurada».
 */
class SetSettingTest extends TestCase
{
    use RefreshDatabase;

    // ── Lo que sí hace ────────────────────────────────────────────────────────────────────────────

    public function test_it_creates_a_setting_that_did_not_exist(): void
    {
        $this->artisan('app:set-setting', ['key' => 'sidebar.engine', 'value' => 'spa'])
            ->assertExitCode(0);

        $this->assertSame('spa', Setting::value('sidebar.engine'));
    }

    public function test_it_updates_one_that_already_existed_and_keeps_its_group(): void
    {
        Setting::create(['key' => 'sidebar.engine', 'value' => 'livewire', 'group' => 'ui']);

        $this->artisan('app:set-setting', ['key' => 'sidebar.engine', 'value' => 'spa'])
            ->assertExitCode(0);

        $row = Setting::query()->where('key', 'sidebar.engine')->firstOrFail();

        $this->assertSame('spa', $row->value);
        $this->assertSame('ui', $row->group, 'el grupo de una fila existente no se pisa');
    }

    public function test_the_group_option_only_applies_when_creating(): void
    {
        $this->artisan('app:set-setting', ['key' => 'security.turnstile_site_key', 'value' => 'k', '--group' => 'security'])
            ->assertExitCode(0);

        $this->assertSame('security', Setting::query()->where('key', 'security.turnstile_site_key')->firstOrFail()->group);
    }

    public function test_rerunning_with_the_same_value_is_a_no_op(): void
    {
        $this->artisan('app:set-setting', ['key' => 'sidebar.engine', 'value' => 'spa'])->assertExitCode(0);
        $this->artisan('app:set-setting', ['key' => 'sidebar.engine', 'value' => 'spa'])
            ->expectsOutputToContain('Sin cambios')
            ->assertExitCode(0);

        $this->assertSame(1, Setting::query()->where('key', 'sidebar.engine')->count());
    }

    public function test_it_can_store_an_empty_value_without_deleting_the_row(): void
    {
        // Vaciar y borrar NO son lo mismo: `Turnstile::enabled()` distingue «clave vacía» de «sin fila»
        // solo por el valor, y una fila borrada cambiaría el grupo al recrearla.
        Setting::create(['key' => 'security.turnstile_secret', 'value' => 'algo', 'group' => 'security']);

        $this->artisan('app:set-setting', ['key' => 'security.turnstile_secret', 'value' => ''])
            ->assertExitCode(0);

        $this->assertSame(1, Setting::query()->where('key', 'security.turnstile_secret')->count());
        $this->assertSame('', Setting::query()->where('key', 'security.turnstile_secret')->firstOrFail()->value);
    }

    public function test_it_actually_switches_the_anti_bot_on(): void
    {
        // La prueba de que sirve para lo que se escribió: no «hay una fila», sino «el anti-bot está».
        $this->assertFalse(Turnstile::enabled());

        $this->artisan('app:set-setting', ['key' => 'security.turnstile_site_key', 'value' => 'site', '--group' => 'security'])->assertExitCode(0);
        $this->artisan('app:set-setting', ['key' => 'security.turnstile_secret', 'value' => 'secreto', '--group' => 'security'])->assertExitCode(0);

        Setting::flushMemo();
        Turnstile::flushCache();

        $this->assertTrue(Turnstile::enabled());
    }

    // ── Lo que NO deja hacer, que es lo valioso ───────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedKeyProvider(): array
    {
        return [
            'el entorno de Redsys' => ['redsys_environment', 'live'],
            'la clave del comercio' => ['redsys_secret_key', 'una-clave-de-32-caracteres-abcdef'],
            'el contador de pedidos' => ['redsys_next_gateway_order', '1'],
        ];
    }

    #[DataProvider('protectedKeyProvider')]
    public function test_it_refuses_a_protected_key_without_force(string $key, string $value): void
    {
        $this->artisan('app:set-setting', ['key' => $key, 'value' => $value])
            ->assertExitCode(1);

        $this->assertNull(Setting::value($key), "«{$key}» se escribió sin --force");
    }

    public function test_it_allows_a_protected_key_when_forced(): void
    {
        // La guarda es una PAUSA deliberada, no una prohibición: con --force se escribe.
        $this->artisan('app:set-setting', ['key' => 'redsys_environment', 'value' => 'test', '--force' => true])
            ->assertExitCode(0);

        $this->assertSame('test', Setting::value('redsys_environment'));
    }

    public function test_it_refuses_an_empty_key(): void
    {
        $this->artisan('app:set-setting', ['key' => '  ', 'value' => 'x'])->assertExitCode(1);

        $this->assertSame(0, Setting::query()->count());
    }

    // ── Y que un secreto no acabe en el log del despliegue ────────────────────────────────────────

    public function test_it_never_prints_a_secret_value(): void
    {
        // Este comando se ejecuta por SSH desde `deploy.sh`: su salida acaba en el log del despliegue.
        $this->artisan('app:set-setting', ['key' => 'security.turnstile_secret', 'value' => 'valor-secretisimo-de-cloudflare'])
            ->doesntExpectOutputToContain('valor-secretisimo-de-cloudflare')
            ->assertExitCode(0);
    }

    public function test_it_does_print_a_value_that_is_not_a_secret(): void
    {
        // El espejo del anterior: enmascararlo TODO haría el comando inútil para verificar a ojo.
        $this->artisan('app:set-setting', ['key' => 'sidebar.engine', 'value' => 'spa'])
            ->expectsOutputToContain('spa')
            ->assertExitCode(0);
    }
}
