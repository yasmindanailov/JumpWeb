<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.2 — **los complementos que ve el cajón SPA son los mismos que ve la web**.
 *
 * `SidebarDomContractTest` compara el ÁRBOL, pero alimenta al componente Vue con el view-model del
 * SERVIDOR traducido a la forma de la API. Eso deja un hueco que ya mordió una vez durante este
 * mismo paso: el componente usaba los nombres de Livewire (`id`, `qty`, `can_inc`) y el endpoint
 * publica otros (`product_id`, `quantity`, `can_increase`), así que **el diff seguía verde mientras
 * el cajón real habría pintado filas vacías**.
 *
 * Esto lo cierra por el otro lado: se compara lo que el ENDPOINT devuelve con lo que el componente
 * Livewire compone, campo a campo, para la misma selección. Si las dos fuentes se separan —un nombre
 * que cambia, un campo que se añade en una sola— salta aquí.
 *
 * ⚠️ **Las dos salen del mismo `AddonResolver`**, así que no es una comparación de dos aritméticas:
 * es una comparación de dos PROYECCIONES de la misma. Justamente por eso divergir es fácil y
 * silencioso.
 */
class SidebarAddonsParityTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $rateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true,
            'max_guests_per_slot' => 60, 'max_per_slot' => 0, 'prep_blocks_cupo' => false,
        ]);
    }

    public function test_the_endpoint_and_the_web_view_model_describe_the_same_addons(): void
    {
        $pack = $this->packWithAddons();
        $date = now()->addDay()->toDateString();

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $pack->id)
            ->call('selectDate', $date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00');

        $quantity = (int) $component->get('qty');

        // La API, pidiendo lo mismo que la web tiene elegido: la selección por defecto.
        $response = $this->postJson('/api/v1/catalog/products/'.$pack->id.'/addons', [
            'quantity' => $quantity,
            'date' => $date,
            'time' => '10:00:00',
        ]);

        $response->assertOk();

        $fromApi = $this->flatten($response->json());
        $fromWeb = $this->flatten($this->asApi($component->viewData('addonModel')));

        $this->assertNotEmpty($fromApi, 'sin complementos el test compararía dos listas vacías');

        $this->assertSame(
            $fromWeb, $fromApi,
            "El endpoint de complementos y el view-model de la web NO describen lo mismo.\n".
            'Los dos salen del mismo `AddonResolver`, así que una diferencia aquí es una PROYECCIÓN '.
            'que se ha separado: un nombre distinto, un campo que solo tiene una de las dos. El diff '.
            'de árbol no lo ve, porque a Vue se le pasa el view-model del servidor.'
        );
    }

    /**
     * Un pack con las tres formas de complemento que existen, porque cada una llena campos
     * distintos: un grupo excluyente, un incluido con extras y un dependiente.
     */
    private function packWithAddons(): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 6, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $pack->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 5000]);

        $this->addon($pack, 'Hamburguesa', 800, ['choice_group' => 'menu']);
        $this->addon($pack, 'Pizza', 900, ['choice_group' => 'menu']);
        $tarta = $this->addon($pack, 'Tarta', 1000, ['is_included' => true, 'included_quantity' => 1, 'allow_extra' => true]);
        $this->addon($pack, 'Velas', 200, ['requires_addon_id' => $tarta->id]);
        $this->addon($pack, 'Comida', 700, ['quantity_mode' => 'per_guest']);

        for ($i = 1; $i <= 3; $i++) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => now()->addDays($i)->toDateString(),
                'start_time' => '10:00:00', 'end_time' => '12:00:00',
                'capacity' => 60, 'online_capacity' => 60,
            ]);
        }

        return $pack;
    }

    /** @param array<string, mixed> $pivot */
    private function addon(TicketType $product, string $name, int $priceCents, array $pivot = []): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 0, 'is_sellable' => true, 'is_active' => true,
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
     * El view-model de la web → la forma que publica el endpoint. Es la MISMA traducción que hace el
     * test de árbol, y está aquí a propósito: si un día deja de valer, los dos caen a la vez.
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
     * volcar dos árboles enteros.
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
