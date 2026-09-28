<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Analytics\Changes;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use App\Filament\Widgets\Analytics\ChangesWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Lo que ha cambiado** (T3c·1 de `specs/analitica-para-decidir.md` §4.5; `DECISIONES #791`): entra lo que sale de su rango
 * normal con una escala que merezca decirse, primero el dinero en juego, cinco como mucho y «y N más». Las cifras, hechas a
 * mano; las frases, tecleadas (`#734`).
 */
class ChangesTest extends TestCase
{
    use RefreshDatabase;

    /** Una historia de 8 meses de 100 a 200 (céntimos o unidades, según la cifra). */
    private const BAND = [100, 120, 140, 150, 160, 170, 180, 200];

    /** (Ni `count()` ni `countOf()`: los dos son de PHPUnit y son finales.) */
    private static function figureCount(string $key, int $value, ?int $previous, Polarity $p = Polarity::UpIsGood): Metric
    {
        return Metric::count($key, 'Cifra '.$key, $value, $previous, $p, 'Definición.')->withHistory(self::BAND, 'month');
    }

    private static function figureMoney(string $key, int $cents, int $operations, ?int $previousOperations): Metric
    {
        return Metric::money($key, 'Dinero '.$key, $cents, null, $operations, $previousOperations, Polarity::UpIsGood, 'Definición.')->withHistory(self::BAND, 'month');
    }

    /**
     * Entra lo que sale de su rango con escala: la de un recuento es él mismo o el de antes (una BAJADA tiene su escala
     * antes); la del dinero, sus operaciones. Lo normal, lo que no tiene historia y lo diminuto, no.
     */
    public function test_what_enters_is_out_of_its_band_with_a_scale_worth_saying(): void
    {
        $picked = Changes::select([
            'a' => self::figureCount('a', 250, 190),                // alta, con escala
            'b' => self::figureCount('b', 150, 140),                // normal
            'c' => self::figureCount('c', 5, 150),                  // baja: su escala está en el periodo de antes
            'd' => Metric::count('d', 'Diminuta', 3, 1, Polarity::UpIsGood, 'Def.')->withHistory([0, 1, 1, 0, 1, 2, 1, 0], 'month'),
            'e' => self::figureMoney('e', 250, 30, 25),             // dinero alto, 30 operaciones
            'f' => self::figureMoney('f', 250, 4, 3),               // dinero alto, 4 operaciones: sin escala
            'g' => Metric::count('g', 'Sin historia', 999, 1, Polarity::UpIsGood, 'Def.')->withHistory([1, 2], 'month'),
            'h' => Metric::count('h', 'Sin comparación', 999, null, Polarity::UpIsGood, 'Def.'),
        ]);

        $this->assertSame(['e', 'c', 'a'], array_map(static fn (array $i): string => $i['metric']->key, $picked['items']));
        $this->assertSame(0, $picked['more']);
        $this->assertSame(6, $picked['judged'], 'a, b, c, d, e y f tienen veredicto; g no tiene historia y h ni se juzga');
    }

    /**
     * Primero el DINERO, por los euros fuera de su banda; después lo demás, por lo lejos que queda RELATIVO a su banda
     * (c, de 5 frente a un borde de 100, queda más lejos que a, de 250 frente a 200).
     */
    public function test_money_goes_first_by_the_euros_at_stake_then_the_rest_by_how_far(): void
    {
        $picked = Changes::select([
            'a' => self::figureCount('a', 250, 190),                // 50 fuera de un borde de 200: 0,25
            'big' => self::figureMoney('big', 900, 30, 30),        // 700 céntimos fuera
            // UN céntimo fuera: va delante de «far», que se sale 1,5 veces su borde, SOLO porque es dinero. (El arnés lo
            // cazó el 28-09: sin este caso, los céntimos ya pesaban más que cualquier distancia relativa y el orden salía igual.)
            'tiny' => self::figureMoney('tiny', 201, 30, 30),
            'far' => self::figureCount('far', 500, 190),            // 300 fuera de un borde de 200: 1,5
            // 100 fuera, MÁS que «a» en absoluto, pero de un borde de 2.000: 0,05. Por lo absoluto iría delante.
            'z' => Metric::count('z', 'Cifra z', 2100, 1900, Polarity::UpIsGood, 'Def.')->withHistory([1000, 1200, 1400, 1500, 1600, 1700, 1800, 2000], 'month'),
        ]);

        $this->assertSame(['big', 'tiny', 'far', 'a', 'z'], array_map(static fn (array $i): string => $i['metric']->key, $picked['items']));
    }

    /** Cinco como mucho, y las que no caben se cuentan: nunca un tope callado. */
    public function test_at_most_five_and_the_rest_are_counted(): void
    {
        $metrics = [];
        foreach (range(1, 7) as $i) {
            $metrics["k{$i}"] = self::figureCount("k{$i}", 200 + $i * 10, 190);
        }

        $picked = Changes::select($metrics);

        $this->assertCount(5, $picked['items']);
        $this->assertSame(2, $picked['more']);
        $this->assertSame('k7', $picked['items'][0]['metric']->key, 'la más lejos, primero');
    }

    /** Sin historia en ninguna cifra, lo dice; y con historia pero nada fuera, dice cuántas se miraron. */
    public function test_the_widget_says_why_there_is_nothing(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        app()->setLocale('es');
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $this->actingAs($admin);

        Livewire::test(ChangesWidget::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Lo que ha cambiado')
            ->assertSee('Aún sin historia para decir qué ha cambiado: hacen falta 8 meses con datos.');

        // Con historia pero sin escala (un pedido más cada mes: junio, 10, es el más alto, pero de 10): nada que decir, y lo
        // dice con cuántas cifras miró.
        $month = Carbon::parse('2025-09-05 08:00:00');
        for ($n = 1; $n <= 10; $n++) {
            for ($i = 0; $i < $n; $i++) {
                Order::create([
                    'user_id' => User::factory()->create()->id, 'code' => 'R-'.Str::upper(Str::random(8)), 'status' => Order::STATUS_PAID,
                    'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => $month->copy()->addMinutes($i),
                ]);
            }
            $month->addMonthNoOverflow();
        }
        Cache::flush();

        Livewire::test(ChangesWidget::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSeeHtml('data-analytics-changes-empty')
            ->assertSee('Nada fuera de lo normal: las ')
            ->assertSee(' cifras con historia están dentro de su rango.')
            ->assertDontSee('data-change=', escape: false);
    }

    /**
     * Con historia: pedidos cobrados el día 5 de cada mes desde septiembre de 2025, TRES más cada mes (3… 30 en junio): lo
     * vendido y los pedidos de junio son los más altos de sus 8 meses, con escala. Sale primero el dinero (lo vendido), con su
     * frase, su tono y el enlace a su pestaña.
     */
    public function test_the_widget_says_the_changes_with_their_tab(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        app()->setLocale('es');
        $month = Carbon::parse('2025-09-05 08:00:00');
        for ($n = 1; $n <= 10; $n++) {
            for ($i = 0; $i < 3 * $n; $i++) {
                Order::create([
                    'user_id' => User::factory()->create()->id, 'code' => 'R-'.Str::upper(Str::random(8)), 'status' => Order::STATUS_PAID,
                    'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => $month->copy()->addMinutes($i),
                ]);
            }
            $month->addMonthNoOverflow();
        }
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $this->actingAs($admin);

        $html = Livewire::test(ChangesWidget::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Vendido: 300 €. Bien: la más alta de tus últimos 8 meses (iba de 60,00 € a 270 €).')
            ->assertSee('Pedidos cobrados: 30. Bien: la más alta de tus últimos 8 meses (iba de 6 a 27).')
            ->assertSee('Ver en Dinero')
            ->html();

        $this->assertMatchesRegularExpression('/data-change="money\.sold" data-change-tone="good".*data-change="money\.orders"/s', $html, 'el dinero, primero');
    }
}
