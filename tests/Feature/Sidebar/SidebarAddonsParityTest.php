<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.7·2b·2 — **lo que publica el endpoint de complementos es el modelo de vista del
 * dominio, campo a campo** (`DECISIONES #77`).
 *
 * ### Qué preguntaba antes y qué pregunta ahora
 *
 * Nació en 4.2 comparando el endpoint con lo que componía el componente Livewire, porque el diff de
 * árbol alimentaba a Vue con el view-model del servidor **traducido por el propio test**: un
 * componente que leyera `id`/`qty`/`can_inc` en vez de `product_id`/`quantity`/`can_increase` pasaba
 * en verde con el cajón pintando filas vacías. Ese hueco lo cerró (B): desde `DECISIONES #69` el
 * paso 3 del diff se alimenta de las respuestas REALES, y esa mitad de la pregunta ya no es suya.
 *
 * Lo que queda **no es una comparación entre motores**, y por eso sobrevive a la retirada de
 * `Purchase`: el componente solo era un **intermediario** de `AddonResolver::viewModel()`, que es
 * dominio y conserva dos consumidores —este endpoint, vía `AddonOfferReader`, y el alta manual del
 * panel (`CreateManualOrderPage`)—. Así que se compara contra esa fuente directamente.
 *
 * ### Lo que protege, MEDIDO por mutación contra los 2715 casos (2026-08-15)
 *
 * `AddonOfferReader::toDto()` + `ResolvedAddonsResource` son **21 traducciones de clave a mano**, y
 * cruzar dos o vaciar una es silencioso: el contrato (`required` + `additionalProperties: false`)
 * fija los NOMBRES publicados, nunca que el valor de cada uno venga del campo que le toca. Mutando
 * campo a campo y corriendo la suite entera:
 *
 * · `note`, `min_quantity`, `max_quantity` → **este es el ÚNICO caso de la suite que los caza**.
 * · `features`, `can_toggle`, `can_increase` → aquí y en el diff de árbol (llegan al DOM).
 * · `price_cents`, `charged_cents` → aquí y en `CatalogAddonsTest`.
 * · `free_quantity` → solo en `CatalogAddonsTest`; el fixture de aquí no lo distinguía.
 *
 * ⚠️ **Y el fixture viejo pinchaba `min` y `max` en valores TRIVIALES** —0 y `null` en los cinco
 * complementos—, así que cruzar `min` ← `max` salía **VERDE**: `(int) null` es `0`. Es el mecanismo
 * de `#68` otra vez —el caso frontera se elige por el MECANISMO del fallo, no por el síntoma—. Por
 * eso ahora hay un complemento OBLIGATORIO (`min` = 2) y otro con TOPE (`max` = 3): con eso, cruzar
 * las dos claves en cualquier dirección deja el caso rojo.
 *
 * ⚠️ **Lo que este test NO es**: no verifica `viewModel()` —las dos mitades salen de él, así que una
 * regla mal calculada saldría igual en las dos—. Eso es `AddonDependencyTest` y las reglas del
 * endpoint son de `CatalogAddonsTest`. Aquí el sujeto es **la capa de publicación**, y solo ella.
 */
class SidebarAddonsParityTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $rateId;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        // El reloj, parado: los dos lados resuelven sobre `Carbon::today()` y el fixture siembra
        // franjas relativas a hoy. Una foto que incluye el tiempo se toma con el reloj quieto
        // (`DECISIONES #64`).
        $this->freezeTime();

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true,
            'max_guests_per_slot' => 60, 'max_per_slot' => 0, 'prep_blocks_cupo' => false,
        ]);
    }

    public function test_the_published_addons_are_the_domain_view_model_field_by_field(): void
    {
        $pack = $this->packWithAddons();

        // Un estado ELEGIDO, no el de partida: el elegido del grupo no es el primero por posición,
        // el incluido va por encima de sus unidades gratis y el del tope va justo en él. Las dos
        // entradas se pasan explícitas a los dos lados, así que este test no repite el completado
        // por defecto de `AddonOfferReader::resolve()` — de eso responde `CatalogAddonsTest`.
        $guests = 8;
        $quantities = [$this->ids['tarta'] => 2, $this->ids['globos'] => 3];
        $choices = ['menu' => $this->ids['pizza']];

        $fromDomain = $this->flatten($this->asApi(app(AddonResolver::class)->viewModel(
            $pack->addons,
            $quantities,
            $choices,
            $guests,
            $pack->isPack(),
            Carbon::today(),
        )));

        $response = $this->postJson('/api/v1/catalog/products/'.$pack->id.'/addons', [
            'quantity' => $guests,
            'addons' => array_map(
                static fn (int $id, int $qty): array => ['product_id' => $id, 'quantity' => $qty],
                array_keys($quantities),
                array_values($quantities),
            ),
            'choices' => [['group' => 'menu', 'product_id' => $this->ids['pizza']]],
        ]);

        $response->assertOk();

        $fromApi = $this->flatten($response->json());

        $this->assertNotEmpty($fromApi, 'sin complementos el test compararía dos listas vacías');

        // Las fronteras que el fixture existe para poner, comprobadas sobre lo PUBLICADO: si algún
        // día dejan de darse, la comparación de abajo seguiría verde sin probar lo que dice probar.
        $this->assertSame(2, $fromApi['suelto '.$this->ids['seguro'].' · min_quantity'],
            'el obligatorio tiene que publicar un mínimo > 0, o cruzar `min` con `max` no se ve');
        $this->assertSame(3, $fromApi['suelto '.$this->ids['globos'].' · max_quantity'],
            'el del tope tiene que publicar un máximo no nulo, y distinto de su mínimo');
        $this->assertNotSame([], $fromApi['suelto '.$this->ids['tarta'].' · features'],
            'las ventajas tienen que viajar no vacías, o vaciarlas sería un mutante equivalente');

        $this->assertSame(
            $fromDomain, $fromApi,
            "Lo que publica el endpoint NO es el modelo de vista del dominio.\n".
            '`AddonOfferReader::toDto()` y `ResolvedAddonsResource` solo pueden RENOMBRAR claves: si '.
            'aquí sale una diferencia, la publicación ha perdido un campo, ha cruzado dos o ha '.
            'decidido algo por su cuenta — y eso es la segunda fuente de verdad que el contrato '.
            'existe para no tener.'
        );
    }

    /**
     * Un pack con las formas de complemento que llenan campos DISTINTOS, que es el único criterio
     * para que esté en el fixture: un grupo excluyente, un incluido con extras y ventajas, un
     * dependiente, un por-invitado, un obligatorio (el único que da `min` > 0) y uno con tope (el
     * único que da `max` no nulo).
     */
    private function packWithAddons(): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 6, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $pack->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 5000]);

        $this->ids['hamburguesa'] = $this->addon($pack, 'Hamburguesa', 800, ['choice_group' => 'menu'])->id;
        $this->ids['pizza'] = $this->addon($pack, 'Pizza', 900, ['choice_group' => 'menu'])->id;

        $tarta = $this->addon($pack, 'Tarta', 1000,
            ['is_included' => true, 'included_quantity' => 1, 'allow_extra' => true],
            ['es' => ['Bizcocho de chocolate', 'Velas incluidas']],
        );
        $this->ids['tarta'] = $tarta->id;

        $this->ids['velas'] = $this->addon($pack, 'Velas', 200, ['requires_addon_id' => $tarta->id])->id;
        $this->ids['comida'] = $this->addon($pack, 'Comida', 700, ['quantity_mode' => 'per_guest'])->id;

        // `min` = max(1, included_quantity) solo si es obligatorio y no es por-invitado.
        $this->ids['seguro'] = $this->addon($pack, 'Seguro', 400, ['is_mandatory' => true, 'included_quantity' => 2])->id;
        // `max` sale del pivote y de ningún otro sitio.
        $this->ids['globos'] = $this->addon($pack, 'Globos', 300, ['max_qty' => 3])->id;

        for ($i = 1; $i <= 3; $i++) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => now()->addDays($i)->toDateString(),
                'start_time' => '10:00:00', 'end_time' => '12:00:00',
                'capacity' => 60, 'online_capacity' => 60,
            ]);
        }

        return $pack;
    }

    /**
     * @param  array<string, mixed>  $pivot
     * @param  array<string, list<string>>|null  $features
     */
    private function addon(TicketType $product, string $name, int $priceCents, array $pivot = [], ?array $features = null): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 0, 'is_sellable' => true, 'is_active' => true,
            'features' => $features,
            'position' => (int) TicketType::max('position') + 1,
        ]);
        $addon->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);

        $product->configurableAddons()->attach($addon->id, array_merge([
            'quantity_mode' => 'fixed',
            'position' => (int) $product->configurableAddons()->count() + 1,
        ], $pivot));

        return $addon;
    }

    /**
     * El modelo de vista del dominio → la forma que publica el endpoint.
     *
     * ⚠️ **Es una segunda escritura DELIBERADA de `AddonOfferReader::toDto()`**, y ahí está todo el
     * valor del test: la de producción y esta se escribieron por separado, así que un cruce de
     * claves en una no se repite en la otra. Copiarla de allí la volvería tautológica.
     *
     * @param  array<string, mixed>  $model
     * @return array<string, mixed>
     */
    private function asApi(array $model): array
    {
        $row = fn (array $opt): array => [
            'product_id' => $opt['id'],
            'product_name' => $opt['name'],
            'price_cents' => $opt['price'],
            'note' => $opt['note'],
            'is_included' => $opt['is_included'],
            'is_mandatory' => $opt['is_mandatory'],
            'per_guest' => $opt['per_guest'],
            'allow_extra' => $opt['allow_extra'],
            'badge' => $opt['badge'],
            'features' => $opt['features'],
            'gifts' => $opt['gifts'],
            'selected' => $opt['selected'],
            'available' => $opt['available'],
            'requires_name' => $opt['requires_name'],
            'quantity' => $opt['qty'],
            'free_quantity' => $opt['free'],
            'charged_cents' => $opt['charged'],
            'min_quantity' => $opt['min'],
            'max_quantity' => $opt['max'],
            'can_toggle' => $opt['can_toggle'],
            'can_increase' => $opt['can_inc'],
            'can_decrease' => $opt['can_dec'],
        ];

        return [
            'groups' => array_map(fn (array $g): array => [
                'key' => $g['key'], 'label' => $g['label'], 'options' => array_map($row, $g['options']),
            ], $model['groups']),
            'singles' => array_map($row, $model['singles']),
        ];
    }

    /**
     * Aplana a `clave => valor` por complemento, para que el fallo señale QUÉ campo difiere en vez de
     * volcar dos árboles enteros. De paso, un campo que sobre o falte en un lado cambia el juego de
     * claves, así que también se ve.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload): array
    {
        $flat = [];

        foreach ($payload['groups'] ?? [] as $group) {
            foreach ($group['options'] ?? [] as $opt) {
                foreach ($opt as $key => $value) {
                    $flat["grupo {$group['key']} · {$opt['product_id']} · {$key}"] = $value;
                }
            }
        }

        foreach ($payload['singles'] ?? [] as $opt) {
            foreach ($opt as $key => $value) {
                $flat["suelto {$opt['product_id']} · {$key}"] = $value;
            }
        }

        return $flat;
    }
}
