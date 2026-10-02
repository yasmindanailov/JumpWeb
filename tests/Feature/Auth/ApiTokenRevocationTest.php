<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\IssuesCodes;
use Tests\TestCase;

/**
 * Fase 3 · paso 3a — **un token de API no sobrevive a nada que eche al usuario**.
 *
 * Hasta entonces toda la invalidación del proyecto era de SESIÓN: `User::anonymize()`, el cambio de
 * contraseña, «cerrar otras sesiones» y el reset borraban filas de `sessions`, y ninguna tocaba
 * `personal_access_tokens` — sencillamente porque cuando se escribieron no existían. Con un emisor
 * de Bearer (Fase 6) eso serían cuatro puertas abiertas, incluida la supresión del art. 17.
 *
 * Se cerró ANTES de que hubiera emisor, porque el momento de acordarse era ése y no el día que se
 * añadiera `POST auth/tokens`. Cada vía tiene aquí su testigo, y `AccessRevocationTest` impide que
 * aparezca otra que se olvide de hacerlo. ▶ Desde la A5 (`#869`) quedan la supresión y «cerrar las
 * demás»: el cambio y el reset de la contraseña del cliente se fueron con ella.
 */
class ApiTokenRevocationTest extends TestCase
{
    use IssuesCodes;
    use RefreshDatabase;

    private function userWithTokens(int $count = 2): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, $count) as $i) {
            $user->createToken('dispositivo-'.$i);
        }

        return $user;
    }

    private function tokenCountFor(User $user): int
    {
        return $user->tokens()->count();
    }

    /** RGPD art. 17: la supresión cierra TODOS los canales, incluido el de la app. */
    public function test_anonymizing_the_account_revokes_every_api_token(): void
    {
        $user = $this->userWithTokens();
        $this->assertSame(2, $this->tokenCountFor($user));

        $user->anonymize();

        $this->assertSame(0, $this->tokenCountFor($user->fresh()));
    }

    /**
     * Y por la vía del titular es la misma llamada, así que tampoco puede olvidarse.
     *
     * ⚠️ **Re-apuntado en la tanda 3** (`DECISIONES #120(u)`): estos tres casos conducían los
     * componentes Livewire de «Mi cuenta», que se retiraron con la página. El SUJETO no cambia —cada
     * vía que echa al titular tiene que llevarse sus tokens (`RGPD-06`)— y hoy la superficie que
     * queda es la que usa el cajón. Es la tercera categoría de `CONVENCIONES §3.quater`: la vieja era
     * un intermediario de una regla que sobrevive, y re-apuntar además **mejora el test**, porque
     * ejercita la puerta que de verdad se sirve.
     */
    public function test_the_self_service_deletion_revokes_every_api_token(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $user = $this->userWithTokens();
        $user->roles()->attach(Role::where('name', 'customer')->value('id'));

        $this->actingAs($user)
            ->deleteJson('/api/v1/me', ['code' => $this->confirmCodeFor($user)])
            ->assertNoContent();

        $this->assertSame(0, $this->tokenCountFor($user->fresh()));
    }

    // ▶ Dos vías se fueron con la contraseña del cliente (A5 de `specs/acceso-con-codigo.md`, `#869`): CAMBIARLA (`PUT
    //   /me/password`) y RESTABLECERLA (la página de la web). Sus casos se retiraron con ellas; las que quedan, aquí.

    /** «Cerrar sesión en los demás dispositivos» alcanza a la app: para el titular es otro más. */
    public function test_logging_out_other_devices_revokes_every_api_token(): void
    {
        $user = $this->userWithTokens();

        $this->actingAs($user)
            ->postJson('/api/v1/me/sessions/revoke-others', ['code' => $this->confirmCodeFor($user)])
            ->assertNoContent();

        $this->assertSame(0, $this->tokenCountFor($user->fresh()));
    }

    /**
     * La otra mitad de la regla: quien se defiende NO se autoexpulsa. Autenticado POR TOKEN, el
     * token en curso sobrevive a «cerrar otras sesiones» y los demás caen — igual que la sesión
     * actual sobrevive cuando la petición viene del navegador.
     */
    public function test_the_token_making_the_request_survives_revoke_other_access(): void
    {
        $user = $this->userWithTokens(3);
        $survivor = $user->tokens()->latest('id')->first();

        Sanctum::actingAs($user->fresh(), ['*']);
        // `Sanctum::actingAs` monta un token de mentira; se ata el real para ejercer la rama que
        // importa (la que compara identificadores), no la del `TransientToken` de sesión.
        $user->withAccessToken($survivor);

        $user->revokeOtherAccess();

        $this->assertSame([$survivor->id], $user->tokens()->pluck('id')->all());
    }

    /**
     * Los `personal_access_tokens` son una tabla MORPH **sin clave foránea** (verificado: 0 FKs),
     * así que borrar la fila de `users` los dejaría huérfanos apuntando a un id que ya no existe.
     * El comando de limpieza de go-live promete una «pizarra limpia»: tiene que borrarlos él.
     */
    public function test_the_go_live_purge_leaves_no_orphan_tokens(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $kept = User::factory()->create(['email' => 'duena@jumpweb.test']);
        $kept->roles()->attach(Role::where('name', 'admin')->value('id'));
        $kept->createToken('el-de-la-duena');

        $purged = $this->userWithTokens(2);

        $this->artisan('app:purge-customers', ['--keep' => ['duena@jumpweb.test'], '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, DB::table('personal_access_tokens')
            ->where('tokenable_id', $purged->id)->count(), 'quedaron tokens huérfanos del usuario borrado');
        $this->assertSame(1, DB::table('personal_access_tokens')
            ->where('tokenable_id', $kept->id)->count(), 'el token de una cuenta conservada no se toca');
    }
}
