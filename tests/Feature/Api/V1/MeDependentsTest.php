<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\DependentSettings;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Api\ApiTestCase;

/**
 * `/api/v1/me/dependents` — mis menores a cargo: listar, declarar y quitar
 * (`specs/menores-a-cargo.md` §4.2, §4.4, §4.5, §4.9), contra el contrato.
 *
 * La regla que vale todo el endpoint es la de §4.9: **un id ajeno no existe**. Y la de §4.5: el
 * tope lo pone el servidor, y sin él este fichero cae.
 */
class MeDependentsTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/me/dependents';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-27 12:00:00', 'Europe/Madrid'));
    }

    /**
     * ⚠️ Los apellidos y la relación llegan por DEFECTO vacíos (`#236`): el escritor sirve también
     * a las fichas de antes de esa tanda, que no los tienen. Quien quiera probar el alta COMPLETA
     * los pasa, y quien pruebe la ficha vieja no. La obligatoriedad vive en la validación de
     * `POST /me/dependents`, no aquí, y así se puede seguir midiendo las dos formas.
     */
    private function add(User $holder, string $name, string $bornOn, string $surname = '', ?string $relationship = null): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn, $surname, $relationship);
    }

    private function cap(int $max): void
    {
        Setting::updateOrCreate(
            ['key' => DependentSettings::KEY_MAX_PER_ACCOUNT],
            ['value' => (string) $max, 'group' => DependentSettings::GROUP],
        );
    }

    // ─── GET ─────────────────────────────────────────────────────────────────

    public function test_it_lists_only_the_holders_active_dependents(): void
    {
        $user = User::factory()->create();
        $lucas = $this->add($user, 'Lucas', '2017-03-12', 'Pérez Gil', 'mother');
        $this->add($user, 'Lior', '2019-11-02')->unlink();
        $this->add(User::factory()->create(), 'Ajeno', '2018-01-01');

        $response = $this->actingAs($user)->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame([
            'id' => $lucas->id,
            'name' => 'Lucas',
            'surname' => 'Pérez Gil',
            'full_name' => 'Lucas Pérez Gil',
            'relationship' => 'mother',
            'born_on' => '2017-03-12',
            'age' => 9,
            'is_minor' => true,
            'adult_from' => '2035-03-12',
            // Su waiver (tanda 2): fuera del modo interno no hay pregunta; nunca el sello del titular.
            'waiver' => [
                'mode' => 'externo', 'signed' => false, 'outdated' => false, 'accepted_at' => null,
                'accepted_label' => null, 'version' => null, 'signature_id' => null, 'pdf_url' => null,
            ],
        ], $response->json('data.0'));
    }

    public function test_the_list_keeps_the_declaration_order_and_marks_who_is_no_longer_covered(): void
    {
        $user = User::factory()->create();
        $this->add($user, 'Mayor', '2008-08-28'); // 17: mañana cumple 18
        $this->add($user, 'Peque', '2020-05-05');

        $this->travelTo(Carbon::parse('2026-08-28 09:00:00', 'Europe/Madrid'));
        $response = $this->actingAs($user)->getJson(self::PATH)->assertOk()->assertValidResponse(200);

        $this->assertSame(['Mayor', 'Peque'], $response->json('data.*.name'));
        $this->assertSame([18, 6], $response->json('data.*.age'));
        $this->assertSame([false, true], $response->json('data.*.is_minor'), 'la fila sobrevive a la mayoría de edad: se marca, no se borra');
    }

    // ─── POST ────────────────────────────────────────────────────────────────

    public function test_declaring_a_dependent_answers_201_with_the_resource(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::PATH, [
                'name' => 'Lucas', 'surname' => 'Pérez Gil',
                'relationship' => 'mother', 'born_on' => '2017-03-12',
            ])
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201);

        $this->assertSame('Lucas', $response->json('name'));
        $this->assertSame('Pérez Gil', $response->json('surname'));
        $this->assertSame('Lucas Pérez Gil', $response->json('full_name'));
        $this->assertSame('mother', $response->json('relationship'));
        $this->assertSame(9, $response->json('age'));
        $this->assertTrue($response->json('is_minor'));
        $stored = Dependent::where('user_id', $user->id)->sole();
        $this->assertSame('Lucas', $stored->name);
        $this->assertSame('Pérez Gil', $stored->surname);
        $this->assertSame('mother', $stored->relationship);
        $this->assertSame('2017-03-12', $stored->born_on->toDateString());
        $this->assertNull($stored->removed_at);
    }

    /**
     * `#773`·a (`[DECIDIDO owner]`, revoca esa parte de `#236`): los apellidos son OPCIONALES. Ausentes o vacíos, la ficha
     * los guarda como `null` —nunca un texto vacío— y el nombre completo es el que se declaró.
     */
    public function test_the_surname_is_optional_and_is_stored_as_null_when_absent_or_blank(): void
    {
        $user = User::factory()->create();

        $sin = $this->actingAs($user)->postJson(self::PATH, ['name' => 'Vera', 'relationship' => 'mother', 'born_on' => '2019-03-07'])
            ->assertCreated()->assertValidRequest()->assertValidResponse(201);
        $this->assertNull($sin->json('surname'));
        $this->assertSame('Vera', $sin->json('full_name'));

        $vacio = $this->actingAs($user)->postJson(self::PATH, ['name' => 'Pol', 'surname' => '  ', 'relationship' => 'father', 'born_on' => '2021-05-01'])
            ->assertCreated()->assertValidResponse(201);
        $this->assertNull($vacio->json('surname'));

        $this->assertSame([null, null], Dependent::where('user_id', $user->id)->orderBy('id')->pluck('surname')->all());

        // La relación SIGUE siendo obligatoria: es lo que sostiene que este adulto firme por el menor.
        $this->actingAs($user)->postJson(self::PATH, ['name' => 'Leo', 'born_on' => '2020-01-01'])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed')->assertJsonStructure(['error' => ['fields' => ['relationship']]]);
    }

    public function test_it_validates_the_body_and_a_future_date_is_a_field_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(self::PATH, [])->assertStatus(422)->assertValidResponse(422);
        $this->assertSame('validation_failed', $response->json('error.code'));
        $this->assertArrayHasKey('name', $response->json('error.fields'));
        $this->assertArrayHasKey('born_on', $response->json('error.fields'));

        foreach (['2026-08-28', '12/03/2017', '2026-02-30'] as $bad) {
            $response = $this->actingAs($user)->postJson(self::PATH, ['name' => 'Ana', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => $bad])->assertStatus(422);
            $this->assertSame('validation_failed', $response->json('error.code'), "«{$bad}»");
            $this->assertArrayHasKey('born_on', $response->json('error.fields'), "«{$bad}»");
        }

        $this->assertSame(0, Dependent::count());
    }

    public function test_an_adult_is_rejected_with_its_own_code(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(self::PATH, ['name' => 'Ana', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2008-08-27'])
            ->assertStatus(422)
            ->assertValidResponse(422);

        $this->assertSame('dependent_not_minor', $response->json('error.code'));
        $this->assertSame(__('api.errors.dependent_not_minor'), $response->json('error.message'));
        $this->assertSame(0, Dependent::count());
    }

    /** §4.5 — MUTACIÓN OBLIGATORIA: sin el tope en el servidor, este test cae. */
    public function test_the_server_cap_answers_422_with_the_max(): void
    {
        $user = User::factory()->create();
        $this->cap(1);

        $this->actingAs($user)->postJson(self::PATH, ['name' => 'Uno', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2017-03-12'])->assertCreated();

        $response = $this->actingAs($user)
            ->postJson(self::PATH, ['name' => 'Dos', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2018-03-12'])
            ->assertStatus(422)
            ->assertValidResponse(422);

        $this->assertSame('dependents_limit_reached', $response->json('error.code'));
        $this->assertSame(1, $response->json('error.params.max'));
        $this->assertStringContainsString('1', $response->json('error.message'));
        $this->assertSame(1, Dependent::count());
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    /** §4.9 — la guarda más importante de la spec: un id ajeno NO EXISTE. */
    public function test_removing_answers_204_and_a_foreign_id_is_a_404(): void
    {
        $ana = User::factory()->create();
        $bea = User::factory()->create();
        $ofBea = $this->add($bea, 'De Bea', '2017-03-12');

        $response = $this->actingAs($ana)->deleteJson(self::PATH.'/'.$ofBea->id)->assertNotFound()->assertValidResponse(404);
        $this->assertSame('not_found', $response->json('error.code'));
        $this->assertDatabaseHas('dependents', ['id' => $ofBea->id, 'removed_at' => null]);

        $own = $this->add($ana, 'De Ana', '2017-03-12');
        $this->actingAs($ana)->deleteJson(self::PATH.'/'.$own->id)->assertNoContent();
        $this->assertDatabaseMissing('dependents', ['id' => $own->id]);
    }

    public function test_a_removed_or_unknown_dependent_is_a_404(): void
    {
        $user = User::factory()->create();
        $own = $this->add($user, 'Lucas', '2017-03-12');

        $this->actingAs($user)->deleteJson(self::PATH.'/'.$own->id)->assertNoContent();
        $this->actingAs($user)->deleteJson(self::PATH.'/'.$own->id)->assertNotFound()->assertValidResponse(404);
        $this->actingAs($user)->deleteJson(self::PATH.'/999999')->assertNotFound()->assertValidResponse(404);
        $this->actingAs($user)->deleteJson(self::PATH.'/abc')->assertNotFound();
    }

    // ─── Transversales ───────────────────────────────────────────────────────

    public function test_it_requires_authentication(): void
    {
        $this->getJson(self::PATH)->assertUnauthorized()->assertValidResponse(401);
        $this->postJson(self::PATH, ['name' => 'Ana', 'surname' => 'Gil', 'relationship' => 'father', 'born_on' => '2017-03-12'])->assertUnauthorized()->assertValidResponse(401);
        $this->deleteJson(self::PATH.'/1')->assertUnauthorized()->assertValidResponse(401);
        $this->assertSame(0, Dependent::count());
    }

    /** `RGPD-04` / spec §5 — el export del art. 20 lleva las personas a cargo activas. */
    public function test_the_export_carries_the_active_dependents(): void
    {
        $user = User::factory()->create();
        $this->add($user, 'Lucas', '2017-03-12');
        $this->add($user, 'Vera', '2019-11-02')->unlink();

        $response = $this->actingAs($user)->getJson(self::ROOT.'/me/export')->assertOk()->assertValidResponse(200);

        $this->assertSame(['Lucas'], $response->json('dependents.*.name'));
        $this->assertSame('2017-03-12', $response->json('dependents.0.born_on'));
        $this->assertNotNull($response->json('dependents.0.added_at'));
    }

    /** Revisión `#169` §10.5: un `throttle` sin prefijo comparte cubo con el reintento del pago. */
    public function test_the_write_routes_have_a_named_throttle(): void
    {
        foreach (['api.v1.me.dependents.store', 'api.v1.me.dependents.destroy'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('throttle:30,1,dependents-write', $route->gatherMiddleware(), $name);
        }

        $index = Route::getRoutes()->getByName('api.v1.me.dependents.index');
        $this->assertSame([], array_values(array_filter($index->gatherMiddleware(), fn (string $m): bool => str_starts_with($m, 'throttle:30'))), 'leer no consume el cubo de escritura');
    }
}
