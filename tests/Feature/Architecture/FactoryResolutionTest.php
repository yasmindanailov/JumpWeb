<?php

namespace Tests\Feature\Architecture;

use App\Domain\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2 — las factories resuelven por NOMBRE CORTO del modelo, no por su namespace completo.
 *
 * Por defecto Laravel adivina `Database\Factories\{namespace-tras-App}\{Modelo}Factory`, así que
 * en cuanto un modelo se muda a `app/Domain/<Módulo>/Models` buscaría
 * `Database\Factories\Domain\<Módulo>\Models\{Modelo}Factory` y `Modelo::factory()` reventaría.
 * `UserFactory` es la única factory del repo y la usa media suite: sin el resolver de
 * `AppServiceProvider`, el paso 4 (Identity) tumbaría cientos de tests de golpe.
 *
 * La alternativa —espejar el árbol de módulos dentro de `database/factories/`— se descartó: no
 * aporta nada y obligaría a mover ficheros en cada mudanza.
 */
class FactoryResolutionTest extends TestCase
{
    use RefreshDatabase;

    /** El caso que de verdad importa: el modelo YA mudado sigue teniendo factory. */
    public function test_a_model_inside_a_domain_module_still_resolves_its_flat_factory(): void
    {
        $this->assertSame(
            'Database\\Factories\\UserFactory',
            Factory::resolveFactoryName('App\\Domain\\Identity\\Models\\User'),
            'un modelo bajo app/Domain debe resolver a la factory PLANA de database/factories'
        );
        $this->assertSame(
            'Database\\Factories\\FaqFactory',
            Factory::resolveFactoryName('App\\Domain\\Content\\Models\\Faq')
        );
    }

    /** Y el modelo aún sin mudar sigue resolviendo igual que siempre. */
    public function test_a_model_still_in_app_models_resolves_as_before(): void
    {
        $this->assertSame('Database\\Factories\\UserFactory', Factory::resolveFactoryName(User::class));
    }

    /**
     * El sentido CONTRARIO (factory → modelo), que también se rompe y por otro sitio: Laravel
     * adivina `App\Models\{Basename}` a partir del nombre de la factory. Con `User` fuera de
     * `App\Models` eso apuntaba a una clase inexistente — y con el classmap de composer sin
     * regenerar ni siquiera fallaba limpio: `class_exists()` intentaba incluir el fichero
     * borrado. Se arregla declarando `$model` en la factory.
     */
    public function test_the_factory_points_at_the_moved_model(): void
    {
        $this->assertSame(
            'App\\Domain\\Identity\\Models\\User',
            (new UserFactory)->modelName(),
            'la factory debe declarar `$model` explícito: adivinarlo apunta a App\\Models\\User'
        );
    }

    /** No basta con los nombres: la factory tiene que construir de verdad. */
    public function test_the_factory_actually_builds(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->assertTrue($user->exists);
        $this->assertSame('Ada Lovelace', $user->name);
    }
}
