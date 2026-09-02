<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Users\Pages\ViewUser;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **El panel dice si el cliente entra con Google** (`specs/auth-con-google.md` §21.3, `#347`).
 *
 * Encargo del owner: *«quiero también en el panel de admin el cliente que tenga su correo vinculado
 * a Google o no»*. Sirve para atender a quien llama diciendo «no me deja entrar»: la primera
 * pregunta útil es **cómo entra**.
 *
 * ⚠️⚠️ **Y lo que NO se publica es tan importante como lo que sí: el `sub` NO sale.** Es la misma
 * línea que traza `#344` para la lista del cliente — el identificador solo viaja en el export del
 * art. 20, que es un acto explícito del titular. Al operador no le sirve de nada y a quien mire por
 * encima de su hombro sí.
 */
class UserGoogleIdentityInfolistTest extends TestCase
{
    use RefreshDatabase;

    private const SUB = '110000000000000000042';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_customer_without_google_says_so(): void
    {
        $customer = $this->userWithRole('customer');

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertSee(__('admin.users.col_google'))
            ->assertSee(__('admin.users.no'));
    }

    public function test_a_customer_with_google_says_since_when(): void
    {
        $customer = $this->linked($this->userWithRole('customer'), 'ana@gmail.com');

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertSee(__('admin.users.col_google'))
            // La fecha la compone `DisplayTime` con la zona de la instalación, así que se compara con
            // el mismo compositor y no con un `format()` escrito aquí.
            ->assertSee(__('admin.users.google_linked', [
                'date' => DisplayTime::format($customer->identities->first()->linked_at),
            ]));
    }

    /**
     * ⚠️⚠️ **La regla que hay que mirar con lupa**: el identificador de Google NO se pinta. Es la
     * misma línea de `#344` — el `sub` viaja solo en el export del art. 20.
     */
    public function test_the_google_subject_is_never_printed(): void
    {
        $customer = $this->linked($this->userWithRole('customer'), 'ana@gmail.com');

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertDontSee(self::SUB);
    }

    /**
     * **El correo con el que se vinculó sale SOLO si es distinto del de la cuenta**, y ahí está el
     * valor del dato: desde `#347` se puede vincular un Google con otra dirección, así que «entra con
     * Google» a secas dejaría al operador sin saber con cuál. Cuando coinciden, repetirlo es ruido.
     */
    public function test_the_linked_address_shows_only_when_it_differs(): void
    {
        $same = $this->linked($this->userWithRole('customer'), null);
        $other = $this->linked($this->userWithRole('customer'), 'otra.suya@gmail.com');

        $admin = $this->userWithRole('admin');

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $other->id])
            ->assertSee('otra.suya@gmail.com');

        // Control: con la misma dirección no se repite — y el caso solo vale si la primera mitad
        // demuestra que el escaneo SÍ sabe ver un correo cuando lo hay.
        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $same->id])
            ->assertDontSee($same->email.' · ');
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    /** @param  string|null  $emailAtLink  `null` = la misma dirección que la cuenta. */
    private function linked(User $user, ?string $emailAtLink): User
    {
        UserIdentity::create([
            'user_id' => $user->id,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_id' => self::SUB.$user->id,
            'email_at_link' => $emailAtLink ?? $user->email,
            'linked_via' => UserIdentity::VIA_ACCOUNT,
            'linked_at' => now(),
        ]);

        return $user->fresh();
    }
}
