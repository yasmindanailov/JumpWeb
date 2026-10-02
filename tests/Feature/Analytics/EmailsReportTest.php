<?php

namespace Tests\Feature\Analytics;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\EmailClick;
use App\Domain\Platform\Models\EmailOpen;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\EmailTiming;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\CsvExport;
use App\Filament\Analytics\EmailsReport;
use App\Filament\Widgets\Analytics\EmailsHeatmapWidget;
use App\Filament\Widgets\Analytics\EmailsWidget;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **Los correos, en conjunto** (`specs/correos-salientes.md` §4.14, `#796`, la C4): por correo, qué parte se pulsó y se abrió
 * entre los que se medían; y CUÁNDO, día × hora del PARQUE, solo con los correos que al cliente le LLEGAN. Sin persona: ninguna
 * cifra de 1 a 4 (`RGPD-07`).
 */
class EmailsReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => self::TZ, 'group' => 'general']);
        Setting::flushMemo();
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00', self::TZ));
        app()->setLocale('es');
    }

    /** ⚠️⚠️ El censo: cada correo al cliente está en UNA lista. Uno nuevo sin clasificar pone esto en rojo. */
    public function test_every_customer_mail_is_either_provoked_or_received_and_never_both(): void
    {
        $customer = array_values(array_filter(EmailUtm::keys(), static fn (string $k): bool => EmailUtm::isCustomerKey($k)));

        // 28 desde el código para entrar (`#853`, `LoginCode`, provocado); 29 con el de confirmar (`#855`, `ConfirmationCode`);
        // 28 otra vez sin el de restablecer la contraseña del cliente (A5, `#869`, `PasswordReset`). El de la contraseña del
        // panel (`#870`) va al personal y no cuenta aquí.
        $this->assertCount(28, $customer);
        $this->assertEqualsCanonicalizing($customer, [...EmailTiming::PROVOKED, ...EmailTiming::RECEIVED]);
        $this->assertSame([], array_values(array_intersect(EmailTiming::PROVOKED, EmailTiming::RECEIVED)));
        $this->assertFalse(EmailTiming::isReceived('order_confirmation'), 'la confirmación la provoca él al pagar');
        $this->assertTrue(EmailTiming::isReceived('visit_eve_notice'), 'la víspera le llega');
    }

    /** Por correo: los ENVÍOS del periodo, lo pulsado y lo abierto entre los que se medían; lo de una máquina no cuenta. */
    public function test_each_mail_counts_its_sends_of_the_period_among_the_measured(): void
    {
        $june = CarbonImmutable::parse('2026-06-10 08:00:00', 'UTC');
        $sends = [];
        foreach (range(1, 6) as $i) {
            $sends[] = $this->send('order_cancelled', $june, clicks: true, opens: $i <= 5);
        }
        $this->click($sends[0], $june->addMinutes(5), null);
        $this->click($sends[1], $june->addMinutes(5), null);
        $this->click($sends[1], $june->addMinutes(9), null);
        $this->click($sends[2], CarbonImmutable::parse('2026-07-02 10:00:00', 'UTC'), null);
        $this->click($sends[3], $june->addSeconds(5), EmailClick::VERDICT_SWEEP);
        $this->open($sends[0], $june->addMinutes(2), null);
        $this->open($sends[4], $june->addMinutes(3), null);
        $this->open($sends[3], $june->addSeconds(2), EmailOpen::VERDICT_APPLE);
        $this->send('order_cancelled', CarbonImmutable::parse('2026-07-01 08:00:00', 'UTC'), clicks: true, opens: true);

        $fila = collect((new EmailsReport)->compute($this->june())['by_mail'])->firstWhere('key', 'order_cancelled');

        $this->assertSame(6, $fila['sent'], 'el de julio no es del periodo');
        $this->assertSame([6, 3], [$fila['clicks_measured'], $fila['clicked']], 'el clic de julio de un envío de junio cuenta; la ráfaga no');
        $this->assertSame([5, 2], [$fila['opens_measured'], $fila['opened']], 'la de Apple no cuenta');
    }

    /** Lo que se tarda en pulsar: la MEDIANA hasta el PRIMER clic que cuenta de cada envío. */
    public function test_the_median_time_to_the_first_click(): void
    {
        $june = CarbonImmutable::parse('2026-06-10 08:00:00', 'UTC');
        foreach ([10, 20, 30, 40, 50] as $minutes) {
            $send = $this->send('order_refunded', $june, clicks: true);
            $this->click($send, $june->addMinutes($minutes), null);
            $this->click($send, $june->addMinutes($minutes + 500), null);
        }

        $fila = collect((new EmailsReport)->compute($this->june())['by_mail'])->firstWhere('key', 'order_refunded');

        $this->assertSame([30, 5], [$fila['minutes_to_click'], $fila['clicks_timed']]);
    }

    /**
     * ⚠️⚠️ El mapa: cada clic y cada apertura que CUENTAN, en su día y su hora del PARQUE, y SOLO de los correos que le llegan
     * (los que provoca él se abren al instante y darían una hora falsa).
     */
    public function test_the_map_puts_each_counted_click_and_open_on_its_park_hour_only_for_received_mails(): void
    {
        $june = CarbonImmutable::parse('2026-06-02 06:00:00', 'UTC');
        $recibido = $this->send('visit_eve_notice', $june, clicks: true, opens: true);
        $provocado = $this->send('order_confirmation', $june, clicks: true, opens: true);

        $this->click($recibido, CarbonImmutable::parse('2026-06-02 08:15:00', 'UTC'), null);
        $this->click($recibido, CarbonImmutable::parse('2026-06-02 08:16:00', 'UTC'), EmailClick::VERDICT_SWEEP);
        $this->click($provocado, CarbonImmutable::parse('2026-06-02 08:15:00', 'UTC'), null);
        $this->open($recibido, CarbonImmutable::parse('2026-06-06 19:30:00', 'UTC'), null);
        $this->open($recibido, CarbonImmutable::parse('2026-06-06 19:31:00', 'UTC'), EmailOpen::VERDICT_APPLE);

        $r = (new EmailsReport)->compute($this->june());

        $this->assertSame([2 => [10 => 1]], $r['clicks_heat'], 'martes a las 10 del parque (08:15 UTC en verano)');
        $this->assertSame([6 => [21 => 1]], $r['opens_heat'], 'sábado a las 21 del parque');
        $this->assertSame([1, 1], [$r['clicks_total'], $r['opens_total']]);
    }

    /** La tabla, sin persona: un recuento de 1 a 4 es «menos de 5»; un % sobre menos de cinco medidos, «—»; sin medir, lo dice. */
    public function test_the_table_keeps_the_minimums(): void
    {
        $june = CarbonImmutable::parse('2026-06-10 08:00:00', 'UTC');
        foreach (range(1, 5) as $i) {
            $send = $this->send('order_cancelled', $june, clicks: true);
            if ($i <= 3) {
                $this->click($send, $june->addMinutes(30), null);
            }
        }
        foreach (range(1, 3) as $i) {
            $this->send('order_item_modified', $june, clicks: true, opens: true);
        }
        $this->send('order_refunded', $june);

        $tabla = (new EmailsWidget)->tablesFor($this->june())[0];
        $filas = collect($tabla['rows'])->keyBy(0);

        $this->assertSame('Por correo', $tabla['heading']);
        $this->assertSame(['Pedido cancelado', 'Reserva cambiada', 'Pedido reembolsado'], $filas->keys()->all(), 'de más a menos enviados');
        $this->assertSame(['5', '60 % de 5', 'No se mide', '—'], array_slice($filas['Pedido cancelado'], 1), 'tres clics: la mediana no se enseña con menos de cinco');
        $this->assertSame(['menos de 5', '—', '—', '—'], array_slice($filas['Reserva cambiada'], 1));
        $this->assertSame(['menos de 5', 'No se mide', 'No se mide', '—'], array_slice($filas['Pedido reembolsado'], 1));
    }

    /** El mapa, sin persona: una casilla de 1 a 4 va con «—»; si ninguna llega a cinco, no se reparte. */
    public function test_the_map_keeps_the_minimums(): void
    {
        $june = CarbonImmutable::parse('2026-06-02 06:00:00', 'UTC');
        $send = $this->send('visit_eve_notice', $june, clicks: true, opens: true);
        foreach (range(1, 5) as $i) {
            $this->click($send, CarbonImmutable::parse('2026-06-02 08:0'.$i.':00', 'UTC'), null);
        }
        foreach (range(1, 2) as $i) {
            $this->click($send, CarbonImmutable::parse('2026-06-03 17:0'.$i.':00', 'UTC'), null);
        }
        $this->open($send, CarbonImmutable::parse('2026-06-02 08:00:00', 'UTC'), null);

        [$clics, $aperturas] = (new EmailsHeatmapWidget)->tablesFor($this->june());

        $this->assertSame(['Día', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', 'Todo el día'], $clics['columns']);
        $this->assertSame('5', $clics['rows'][1][1], 'el martes a las 10: cinco');
        $this->assertSame('—', $clics['rows'][2][10], 'el miércoles a las 19: dos, «—»');
        // Las SUMAS: el martes entero (5), el miércoles entero (2, «—»), y la semana por hora y en total.
        $this->assertSame(['5', '—'], [$clics['rows'][1][11], $clics['rows'][2][11]]);
        $this->assertSame('Toda la semana', $clics['rows'][7][0]);
        $this->assertSame(['5', '—', '7'], [$clics['rows'][7][1], $clics['rows'][7][10], $clics['rows'][7][11]]);
        $this->assertSame([], $aperturas['rows'], 'una apertura sola: el mapa no se reparte');
    }

    /** El CSV de «Marketing» lleva las MISMAS tablas. */
    public function test_the_marketing_csv_carries_the_emails_tables(): void
    {
        $csv = (new CsvExport)->build(CsvExport::REPORT_FUNNEL, $this->june());
        $titulos = array_map(static fn (array $row): string => (string) ($row[0] ?? ''), $csv['rows']);

        foreach (['Por correo', 'Clics por día y hora', 'Aperturas por día y hora'] as $titulo) {
            $this->assertContains($titulo, $titulos);
        }
    }

    // ─── Andamios ────────────────────────────────────────────────────────────────────────────────

    private function june(): Window
    {
        return ReportPeriod::Custom->window('2026-06-01', '2026-06-30');
    }

    private function send(string $key, CarbonImmutable $at, bool $clicks = false, bool $opens = false): int
    {
        return (int) DB::table('email_sends')->insertGetId([
            'send_key' => (string) Str::uuid(), 'user_id' => null, 'recipient' => null, 'mail_key' => $key,
            'tracks_clicks' => $clicks, 'tracks_opens' => $opens, 'sent_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function click(int $send, CarbonImmutable $at, ?string $verdict): void
    {
        DB::table('email_clicks')->insert(['email_send_id' => $send, 'route' => '/', 'verdict' => $verdict, 'clicked_at' => $at]);
    }

    private function open(int $send, CarbonImmutable $at, ?string $verdict): void
    {
        DB::table('email_opens')->insert(['email_send_id' => $send, 'source' => EmailOpen::SOURCE_DIRECT, 'verdict' => $verdict, 'opened_at' => $at]);
    }
}
