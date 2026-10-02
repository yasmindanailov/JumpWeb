<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * **La contraseña del cliente, retirada** (A5 de `docs/specs/acceso-con-codigo.md` §4.12, `DECISIONES #869`): el cliente
 * entra con un código al correo o con Google, y ninguna superficie le pide, le cambia ni le recupera una contraseña. El
 * personal conserva la suya, la del PANEL (`#870`, `SendPanelPasswordActionTest`).
 *
 * Lo que se vigila aquí es que la retirada SE SOSTENGA: que ninguna de sus superficies vuelva —una ruta repuesta sin
 * pensar sería otra vez una puerta con contraseña— y que las dos direcciones que pudieran estar en un marcador o en un
 * correo viejo lleven a la puerta del código y no a un 404.
 */
class CustomerPasswordRetiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_has_no_password_door_left(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ana@example.test'])->assertNotFound();
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'ana@example.test', 'token' => 't'])->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->putJson('/api/v1/me/password', ['current_password' => 'x', 'password' => 'una-nueva-larga'])
            ->assertNotFound();
    }

    public function test_the_old_web_addresses_lead_to_the_code_door(): void
    {
        $this->get('/recuperar-contrasena')->assertStatus(301)->assertRedirect('/login');
        // Sin arrastrar el correo de la query a la puerta: la dirección vieja no decide nada.
        $this->get('/restablecer-contrasena/un-token-viejo?email=ana@example.test')->assertStatus(301)->assertRedirect('/login');
    }

    public function test_the_signed_link_of_the_new_email_is_gone(): void
    {
        $this->get('/mi-cuenta/email/confirmar/1/'.sha1('nueva@example.test'))->assertNotFound();
    }

    /** Nada puede volver a ENLAZARLAS por su nombre: un `route('password.reset')` olvidado fallaría al pintarse. */
    public function test_their_route_names_are_gone(): void
    {
        // ⚠️ Las de la API, con su prefijo de nombre (`api.v1.`): sin él, la aserción no podría fallar nunca.
        $this->assertTrue(Route::has('api.v1.auth.login'), 'control: el prefijo de nombre de la API es éste');

        foreach (['password.request', 'password.reset', 'api.v1.auth.password.forgot', 'api.v1.auth.password.reset', 'api.v1.me.password.update', 'account.email.confirm'] as $nombre) {
            $this->assertFalse(Route::has($nombre), "la ruta «{$nombre}» ha vuelto");
        }
    }
}
