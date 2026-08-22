<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * **El eslabón del ANTI-BOT entre el servidor y el cajón** (Fase 4 · paso 4.4b·2, `DECISIONES #108`).
 *
 * ⚠️⚠️ **Nace el 2026-08-23 mudando un caso de `SidebarRegisterParityTest`**
 * (`specs/auth-en-cajon.md` §4.7.bis). Aquel fichero se retira cuando la auth entre en el cajón,
 * porque compara el cajón con un modal que desaparece; **este caso no comparaba dos motores** —no
 * monta Livewire por ningún lado— sino los dos extremos de un contrato que sobrevive: `GET /config`
 * y el módulo de alta del cajón. Clasificado por SUJETO, no por el fichero en que estaba
 * (`CONVENCIONES §3.quater`).
 *
 * ⚠️ Fichero propio, y no una carpeta de sobras: el nombre dice qué vigila, que es la condición para
 * que dentro de seis meses alguien sepa si puede tocarlo.
 */
class SidebarAntiBotTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ **Los dos extremos, comprobados de punta a punta.**
     *
     * Desde 4.4b·2 el cajón MONTA su propio widget, así que este bit no decide «si delegar en el
     * modal de Livewire» sino **con qué clave montar el widget**. Lo que fija no ha cambiado, y es lo
     * que importa: que `GET /config` publique la clave **solo cuando el anti-bot está activo de
     * verdad** —las DOS claves, no una— y que el módulo del cajón lo lea igual. Se comprueban los dos
     * extremos con la respuesta REAL del endpoint pasada por el módulo REAL.
     *
     * ⚠️ Con la clave a medias, el cajón pintaría un widget que no verifica nada y el servidor
     * rechazaría **todas** las altas con «no eres un robot», sin correo y **sin una sola línea de
     * log**: `Turnstile::verify('')` corta antes del POST a Cloudflare y antes de su `Log::warning`.
     * Ese fallo es invisible en los dos extremos, y por eso la equivalencia se comprueba aquí.
     */
    public function test_the_cajon_knows_when_the_signup_needs_a_captcha(): void
    {
        $states = [
            'sin anti-bot' => [[], false],
            'solo la clave pública' => [['security.turnstile_site_key' => 'site'], false],
            'anti-bot ACTIVO' => [['security.turnstile_site_key' => 'site', 'security.turnstile_secret' => 'secreto'], true],
        ];

        foreach ($states as $label => [$settings, $expected]) {
            Setting::query()
                ->whereIn('key', ['security.turnstile_site_key', 'security.turnstile_secret'])->delete();

            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'security']);
            }

            Setting::flushMemo();
            Turnstile::flushCache();

            $config = $this->getJson('/api/v1/config')->assertOk()->json();

            $this->assertSame(
                Turnstile::enabled(), $expected,
                "con «{$label}» el estado del anti-bot no es el que este caso supone"
            );

            $this->assertSame(
                $expected,
                $this->requiresCaptchaInNode($config),
                "Con «{$label}» el cajón NO decide bien si tiene que montar el widget del anti-bot.\n".
                '⚠️ Montarlo con la configuración a medias, o NO montarlo con el anti-bot activo, '.
                'rechazaría todas las altas con «no eres un robot», sin correo y sin log: un registro '.
                'que no funciona para nadie y que nada delata.'
            );
        }
    }

    /** @param array<string, mixed> $config */
    private function requiresCaptchaInNode(array $config): bool
    {
        $script = <<<'JS'
            import { signupRequiresCaptcha } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ needs: signupRequiresCaptcha(JSON.parse(raw)) }));
            });
            JS;

        $path = base_path('storage/framework/testing/signup-captcha.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/register.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo de alta falló:\n".$process->getErrorOutput());

        return (bool) json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR)['needs'];
    }
}
