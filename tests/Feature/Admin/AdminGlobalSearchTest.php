<?php

namespace Tests\Feature\Admin;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\CatalogResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\PanelGlobalSearchProvider;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #224 — El buscador del panel: qué encuentra cada rol y qué NO.
 *
 * Lo que fija, en orden de importancia:
 *
 *  1. **Que un EMPLEADO no encuentre clientes.** Es la decisión del owner del 2026-08-28 y la
 *     única parte de esto que toca datos personales. Hoy se sostiene sola —Filament exige
 *     `canAccess()` del recurso y `UserResource` pide `users.manage`—, y precisamente por eso
 *     hay que aseverarla: nadie la escribió, así que nada avisaría si dejara de cumplirse.
 *  2. **Que sí encuentre sus pedidos**, que es su caso de uso real en el mostrador.
 *  3. **Que buscar en un nombre traducible ignore las mayúsculas.** MySQL extrae el JSON con
 *     colación `utf8mb4_bin`: sin la bandera de Filament, buscar «jump» NO encuentra
 *     «Jump · 1 hora» (medido: 0 filas frente a 5). Era un defecto real y VIVO en las tablas
 *     del panel antes de esta tanda.
 *  4. **Que las PANTALLAS se encuentren, y solo las que uno puede abrir** — la categoría que
 *     Filament no trae y que es la que hace barato tener 19 pantallas escondidas (`#223`).
 */
class AdminGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $u = User::factory()->create($attributes);
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    /** Deja el panel resuelto para el usuario dado (la navegación se memoiza por proceso). */
    private function actingInPanel(User $user): PanelGlobalSearchProvider
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return new PanelGlobalSearchProvider;
    }

    /** @return array<string, array<int, string>> categoría => títulos */
    private function search(User $user, string $query): array
    {
        $results = $this->actingInPanel($user)->getResults($query);

        $out = [];

        foreach ($results?->getCategories() ?? [] as $category => $items) {
            $out[$category] = collect($items)->map(fn ($item): string => (string) $item->title)->all();
        }

        return $out;
    }

    private function orderFor(User $customer, string $code): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'code' => $code,
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function jumpTicket(): TicketType
    {
        $zone = Zone::create([
            'name' => ['es' => 'JUMP'], 'slug' => 'jump', 'max_per_slot' => 20, 'is_active' => true, 'position' => 1,
        ]);

        return TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'],
            'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id,
            'duration_min' => 60,
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ]);
    }

    // ─── Lo que toca datos personales ────────────────────────────────────────

    public function test_staff_cannot_find_customers_through_the_search(): void
    {
        $customer = $this->userWithRole('customer', ['name' => 'Lior Buscable']);
        $staff = $this->userWithRole('staff');

        $found = $this->search($staff, 'Lior');

        $this->assertArrayNotHasKey(
            (string) UserResource::getPluralModelLabel(),
            $found,
            'El buscador le ha devuelto CLIENTES a un empleado. El owner decidió que busque '
            .'pedidos, no clientes: comprobar a una persona se hace en la pantalla de Puerta, '
            .'que es la que lleva límite y auditoría (SEC-05).',
        );

        // Y no es que no haya nadie que encontrar: al admin sí le sale.
        $this->assertContains(
            $customer->name,
            $this->search($this->userWithRole('admin'), 'Lior')[(string) UserResource::getPluralModelLabel()] ?? [],
        );
    }

    public function test_staff_finds_the_orders_of_the_person_at_the_counter(): void
    {
        $customer = $this->userWithRole('customer', ['name' => 'Vilma Cliente']);
        $this->orderFor($customer, 'R-BUSCA1');

        $found = $this->search($this->userWithRole('staff'), 'R-BUSCA1');

        $this->assertContains('R-BUSCA1', $found[(string) OrderResource::getPluralModelLabel()] ?? []);
    }

    /** Por el NOMBRE del titular, que es como llega quien no recuerda su código. */
    public function test_an_order_is_also_found_by_its_customer_name(): void
    {
        $customer = $this->userWithRole('customer', ['name' => 'Vilma Cliente']);
        $this->orderFor($customer, 'R-BUSCA2');

        $found = $this->search($this->userWithRole('staff'), 'Vilma');

        $this->assertContains('R-BUSCA2', $found[(string) OrderResource::getPluralModelLabel()] ?? []);
    }

    // ─── El defecto de las mayúsculas en columnas JSON ───────────────────────

    public function test_searching_a_translated_name_ignores_case(): void
    {
        $this->jumpTicket();
        $admin = $this->userWithRole('admin');

        foreach (['jump', 'JUMP', 'Jump', 'hora'] as $query) {
            $this->actingInPanel($admin);

            $this->assertCount(
                1,
                CatalogResource::getGlobalSearchResults($query),
                "Buscar «{$query}» no encontró «Jump · 1 hora». MySQL extrae el JSON con colación "
                .'utf8mb4_bin, así que el LIKE distingue mayúsculas salvo que se fuerce lower().',
            );
        }
    }

    /**
     * ⚠️⚠️ El caso de arriba **no puede demostrar esto en la suite**: corre sobre SQLite, cuyo
     * `LIKE` ya ignora mayúsculas en ASCII, así que quitar el `LOWER()` lo deja igual de verde
     * —comprobado mutándolo—. Quien distingue mayúsculas es MySQL, por la colación
     * `utf8mb4_bin` de la extracción JSON, y ahí no llega el test.
     *
     * Lo que sí se puede aseverar en cualquier motor es la CONSULTA: que la columna va envuelta
     * en `LOWER(...)` y que el término buscado viaja en minúsculas. Con eso, quitar la
     * envoltura pone la suite roja aunque el motor de test no note la diferencia.
     *
     * La conducta en MySQL se verificó a mano contra la base local: «jump» → 5 resultados,
     * «JUMP» → 5, «Jump» → 5 (antes: 0 / 5 / 5).
     */
    public function test_the_query_lowercases_both_sides_so_mysql_json_search_is_case_insensitive(): void
    {
        $query = CatalogResource::getGlobalSearchEloquentQuery();
        CatalogResource::getGloballySearchableAttributes();

        $reflection = new \ReflectionMethod(CatalogResource::class, 'applyGlobalSearchAttributeConstraints');
        $reflection->setAccessible(true);
        $reflection->invoke(null, $query, 'JuMp');

        $this->assertStringContainsStringIgnoringCase('lower(', $query->toSql());
        $this->assertContains('%jump%', $query->getBindings());
    }

    public function test_a_translated_name_is_shown_translated_not_as_an_array(): void
    {
        $ticket = $this->jumpTicket();
        $this->actingInPanel($this->userWithRole('admin'));

        $this->assertSame('Jump · 1 hora', CatalogResource::getGlobalSearchResultTitle($ticket));
    }

    // ─── Las PANTALLAS ───────────────────────────────────────────────────────

    public function test_screens_are_found_by_their_name(): void
    {
        $found = $this->search($this->userWithRole('admin'), 'horario');

        $this->assertContains('Horario semanal', $found[__('admin.search.screens')] ?? []);
    }

    /**
     * Lo que de verdad hace útil la categoría: quien busca no sabe cómo se llama la pantalla,
     * sabe qué quiere hacer. «Tarifas» no contiene «precio» en ninguna letra.
     */
    public function test_screens_are_also_found_by_what_they_do(): void
    {
        $found = $this->search($this->userWithRole('admin'), 'precio');

        $this->assertContains('Tarifas', $found[__('admin.search.screens')] ?? []);
    }

    /** «catalogo» sin tilde tiene que encontrar «Catálogo»: nadie la pone al teclear. */
    public function test_screen_search_ignores_accents(): void
    {
        $found = $this->search($this->userWithRole('admin'), 'catalogo');

        $this->assertContains('Catálogo', $found[__('admin.search.screens')] ?? []);
    }

    public function test_staff_only_finds_screens_it_can_open(): void
    {
        $staff = $this->userWithRole('staff');

        // «Puerta» sí: es una de sus cuatro.
        $this->assertContains('Puerta', $this->search($staff, 'puerta')[__('admin.search.screens')] ?? []);

        // Las de puesta en marcha, no: ni la pantalla ni «Ajustes», que es su única puerta.
        foreach (['horario', 'tarifas', 'ajustes', 'mantenimiento'] as $query) {
            $this->assertSame(
                [],
                $this->search($staff, $query)[__('admin.search.screens')] ?? [],
                "Un empleado ha encontrado la pantalla «{$query}», que no puede abrir.",
            );
        }
    }

    public function test_an_empty_query_returns_no_screens(): void
    {
        $this->assertSame([], $this->search($this->userWithRole('admin'), '   ')[__('admin.search.screens')] ?? []);
    }
}
