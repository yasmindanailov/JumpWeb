<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\MaintenanceSettings;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.3·3, re-apuntado en 4.7·2b·2 — **el aviso de pausa dice y enlaza lo que dicen los
 * AJUSTES DEL PANEL y el diccionario** (`DECISIONES #81`).
 *
 * La referencia era el Blade del motor viejo, y no era una fuente: `Purchase::pausedTitle()` es
 * literalmente `MaintenanceSettings::reservationTitle()`, y los `href` los componía con
 * `contact.phone` y `contact.whatsapp`. Todo eso sobrevive; el Blade no. La composición esperada se
 * **verificó contra el motor vivo en los cuatro estados de canales antes de sustituirlo**, que es el
 * mismo criterio con el que `#60` congeló el manifiesto de DOM.
 *
 * ⚠️ **Aquí está casi todo lo que el diff de árbol NO puede ver de este bloque**, y es mucho:
 *  - **`href`, `target` y `rel` no son atributos de contrato**, así que el enlace de WhatsApp y el de
 *    `/contacto` producen árboles **byte a byte idénticos** (`<a class=btn.btn--lg>`). Un motor que
 *    mandara a la página de contacto donde el servidor manda al WhatsApp —o que apuntara el `tel:` al
 *    número equivocado— pasaría el gate en verde;
 *  - el normalizador descarta los nodos de TEXTO, así que el título, el mensaje y los rótulos de los
 *    tres botones tampoco entran;
 *  - **en qué pasos se enseña el aviso no lo publica ningún endpoint**: es una regla de interfaz, y
 *    los pasos de resultado (6, 7, 9, 10, 11) tienen que quedar FUERA porque son acciones ya
 *    iniciadas que deben poder completarse.
 */
class SidebarPausedParityTest extends TestCase
{
    use RefreshDatabase;

    private function pause(string $phone = '+34 968 12 34 56', string $whatsapp = '+34 600-11-22-33'): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => $phone]);
        Setting::updateOrCreate(['key' => 'contact.whatsapp'], ['value' => $whatsapp]);
        Setting::flushMemo();
    }

    /**
     * ⚠️ **El mapa ENTERO, no una muestra**, y escrito paso a paso: en qué pasos se tapa el flujo no
     * lo publica ningún endpoint —es una regla de interfaz—, así que la lista ES la decisión. Se
     * recorre igual que el del «modo» en `SidebarProgressParityTest`, y por la misma razón: así fue
     * como apareció que el paso de pago publicaba un modo distinto en cada motor.
     *
     * Los pasos 6, 7, 9, 10 y 11 son el caso que importa. Un `v-if="paused"` colgado de la raíz del
     * cajón —lo primero que uno escribe— taparía la pantalla de «pago confirmado» a quien acaba de
     * pagar, y ninguna prueba de árbol podría decirlo todavía: esos pasos no están transcritos.
     */
    public function test_the_notice_covers_exactly_the_steps_it_must_cover(): void
    {
        $this->pause();

        $this->assertSame(
            [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => false,
                7 => false, 8 => true, 9 => false, 10 => false, 11 => false],
            $this->showsNoticeInNode(range(1, 11), true),
            "El cajón NO tapa los pasos que debe.\n".
            'Los pasos de RESULTADO (6, 7, 9, 10 y 11) son acciones YA iniciadas: taparlas dejaría a '.
            'quien vuelve de la pasarela mirando un cartel de mantenimiento en vez de su reserva.'
        );
    }

    /** Sin pausa no se tapa nada, en ningún paso. Es la mitad que hace significativa a la otra. */
    public function test_nothing_is_covered_when_reservations_are_open(): void
    {
        $covered = $this->showsNoticeInNode(range(1, 11), false);

        $this->assertSame(array_fill_keys(range(1, 11), false), $covered);
    }

    /**
     * ⚠️ **Los tres canales y sus enlaces, que es lo que el árbol da por bueno.**
     *
     * Se comparan contra los `href` y los textos que salen de los AJUSTES y del diccionario (ver
     * `expectedChannels()`). Los cuatro estados de canales son necesarios: con solo WhatsApp y sin
     * ningún canal el árbol normalizado es el MISMO, así que si esta comparación no existiera nadie
     * notaría que el cajón manda a la página de contacto donde la instalación ofrece WhatsApp.
     */
    public function test_the_contact_links_come_from_the_panel_settings(): void
    {
        $states = [
            'teléfono y WhatsApp' => ['+34 968 12 34 56', '+34 600-11-22-33'],
            'solo teléfono' => ['+34 968 12 34 56', ''],
            'solo WhatsApp' => ['', '+34 600-11-22-33'],
            'ningún canal' => ['', ''],
        ];

        foreach ($states as $label => [$phone, $whatsapp]) {
            $this->pause($phone, $whatsapp);

            $expected = $this->expectedChannels($phone, $whatsapp);
            $client = $this->buildNoticeInNode([
                'status' => $this->getJson('/api/v1/booking/status')->assertOk()->json(),
                'step' => 1,
                'messages' => __('tickets'),
            ]);

            $this->assertNotNull($client, "con «{$label}» el cliente tendría que componer el aviso");

            $this->assertSame(
                $expected,
                array_map(fn (array $cta): array => [
                    'href' => $cta['href'],
                    'label' => $cta['label'],
                    // ⚠️ `primary` y `external` no son cosmética: el primero pinta el botón con el color
                    // de la zona y el segundo abre en pestaña nueva CON `rel="noopener"`. Ninguno de
                    // los dos lo compara el diff de árbol —`target` y `rel` no son atributos de
                    // contrato, y `btn--zone` solo se compararía si el test fabricara el aviso él
                    // mismo, que es justo lo que ya no hace—.
                    'zone' => $cta['primary'],
                    'external' => $cta['external'],
                ], $client['ctas']),
                "Los canales de «{$label}» NO son los que sale de los ajustes y el diccionario.\n".
                '⚠️ El diff de árbol da esto por bueno: `href`, `target` y `rel` no son atributos de '.
                'contrato, y el enlace de WhatsApp y el de contacto son el MISMO nodo para el normalizador.'
            );
        }
    }

    /** El botón de llamar enseña el teléfono CON espacios y enlaza el número SIN ellos. */
    public function test_the_call_button_shows_one_form_of_the_phone_and_links_the_other(): void
    {
        $this->pause('+34 968 12 34 56', '');

        $client = $this->buildNoticeInNode([
            'status' => $this->getJson('/api/v1/booking/status')->assertOk()->json(),
            'step' => 1,
            'messages' => __('tickets'),
        ]);

        $call = $client['ctas'][0];

        $this->assertSame('tel:+34968123456', $call['href'], 'el enlace va sin espacios');
        $this->assertStringContainsString('+34 968 12 34 56', $call['label'], 'el texto va tal cual lo escribió la dueña');
    }

    /**
     * ⚠️ **El título y el mensaje son ajustes del PANEL, no literales de i18n.**
     *
     * Es la trampa invisible de este paso: sin override, `MaintenanceSettings` cae exactamente al
     * mismo literal, así que pintarlos desde el diccionario inyectado sale idéntico en desarrollo y
     * enseña el texto genérico en toda instalación que haya escrito el suyo — que es el motivo de
     * existir de la función.
     */
    public function test_the_title_and_message_come_from_the_panel_not_from_the_dictionary(): void
    {
        $this->pause();
        Setting::updateOrCreate(['key' => 'reservations.title.es'], ['value' => 'Volvemos el lunes']);
        Setting::updateOrCreate(['key' => 'reservations.message.es'], ['value' => 'Estamos de obras.']);
        Setting::flushMemo();

        $this->app->setLocale('es');

        $client = $this->buildNoticeInNode([
            'status' => $this->getJson('/api/v1/booking/status')->assertOk()->json(),
            'step' => 1,
            'messages' => __('tickets'),
        ]);

        $this->assertSame('Volvemos el lunes', MaintenanceSettings::reservationTitle());
        $this->assertNotSame(
            __('tickets.paused.title'), $client['title'],
            'si el título del cliente coincide con el literal, es que NO está leyendo el del panel'
        );
        $this->assertSame(MaintenanceSettings::reservationTitle(), $client['title']);
        $this->assertSame(MaintenanceSettings::reservationMessage(), $client['message']);
    }

    /** Y en los tres idiomas del sitio público, porque el override del panel es POR IDIOMA. */
    public function test_the_notice_is_the_panels_text_in_every_locale(): void
    {
        $this->pause();

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $client = $this->buildNoticeInNode([
                'status' => $this->getJson('/api/v1/booking/status')->assertOk()->json(),
                'step' => 1,
                'messages' => __('tickets'),
            ]);

            $this->assertSame(
                [MaintenanceSettings::reservationTitle(), MaintenanceSettings::reservationMessage()],
                [$client['title'], $client['message']],
                "El aviso en «{$locale}» NO dice lo mismo en los dos motores."
            );
        }
    }

    /**
     * Los canales que el aviso tiene que ofrecer, compuestos desde las fuentes que SOBREVIVEN: los
     * ajustes del panel (`contact.phone`, `contact.whatsapp`) y el diccionario.
     *
     * ⚠️ **Esta composición se verificó contra el Blade ANTES de sustituirlo** (`DECISIONES #81`),
     * en los cuatro estados de canales: mismos `href`, mismos rótulos, mismo canal principal y mismo
     * `external`. No es una expectativa escrita a ojo, es la del motor vivo con su fuente detrás — el
     * mismo criterio con el que `#60` congeló el manifiesto de DOM.
     *
     * · **El orden importa**: llamar primero (es el canal principal, con el color de la zona) y
     *   WhatsApp después. Y **el enlace a contacto solo aparece cuando no hay ningún canal directo**:
     *   es el respaldo, no un tercer botón.
     * · `tel:` va sin espacios y `wa.me` solo con dígitos; el ROTULO, en cambio, lleva el teléfono tal
     *   y como lo escribió la dueña. Son tres formas del mismo número y ninguna es intercambiable.
     *
     * @return array<int, array{href: string, label: string, zone: bool, external: bool}>
     */
    private function expectedChannels(string $phone, string $whatsapp): array
    {
        $digits = static fn (string $number): string => preg_replace('/\D+/', '', $number) ?? '';

        $ctas = [];

        if ($phone !== '') {
            $ctas[] = [
                'href' => 'tel:+'.$digits($phone),
                'label' => __('tickets.paused.call', ['phone' => $phone]),
                'zone' => true,
                'external' => false,
            ];
        }

        if ($whatsapp !== '') {
            $ctas[] = [
                'href' => 'https://wa.me/'.$digits($whatsapp),
                'label' => __('tickets.paused.whatsapp'),
                'zone' => false,
                'external' => true,
            ];
        }

        return $ctas !== [] ? $ctas : [[
            'href' => url('/contacto'),
            'label' => __('tickets.paused.contact'),
            'zone' => false,
            'external' => false,
        ]];
    }

    /**
     * @param  list<int>  $steps
     * @return array<int, bool>
     */
    private function showsNoticeInNode(array $steps, bool $paused): array
    {
        return $this->runInNode(<<<'JS'
            import { showsNotice } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { steps, paused } = JSON.parse(raw);
                const out = {};
                for (const step of steps) out[step] = showsNotice(paused, step);
                process.stdout.write(JSON.stringify(out));
            });
            JS, ['steps' => $steps, 'paused' => $paused], 'shows-notice.mjs');
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    private function buildNoticeInNode(array $state): ?array
    {
        return $this->runInNode(<<<'JS'
            import { buildNotice } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ notice: buildNotice(JSON.parse(raw)) }));
            });
            JS, $state, 'build-notice.mjs')['notice'];
    }

    /**
     * Mismo patrón que las demás paridades: script efímero que importa el módulo REAL por ruta
     * ABSOLUTA y habla por stdin/stdout con JSON.
     *
     * @param  array<mixed>  $input
     * @return array<mixed>
     */
    private function runInNode(string $script, array $input, string $filename): array
    {
        $path = base_path('storage/framework/testing/'.$filename);

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/paused.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo del aviso falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
