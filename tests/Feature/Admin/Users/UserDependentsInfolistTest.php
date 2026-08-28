<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Orders\Support\AssignedDependents;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Support\HolderDependents;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo, tanda 5 (D·3) — la sección «Menores a cargo» de la FICHA DEL CLIENTE
 * (`docs/specs/menores-a-cargo.md` §9.10, §4.1–§4.4).
 *
 * Lo que se sostiene aquí: que salen TODOS los declarados en orden de alta (los retirados también,
 * marcados), que la edad es la de HOY, que el estado de la exención solo existe en modo interno y
 * habla el vocabulario de la ficha del pedido, que una cuenta ANONIMIZADA no lista a nadie
 * (`RGPD-01`) y que el presupuesto de consultas no crece con el número de menores.
 *
 * ⚠️ Las claves `admin.users.dependents.*` las añade el orquestador de la tanda: mientras no existan,
 * `__()` devuelve la propia clave. Por eso NADA aquí se afirma por su literal en castellano —los
 * rótulos se afirman por CLAVE y los DATOS (edad, estado de la exención, fila retirada) por sus
 * `data-*`, que no dependen de ninguna traducción—. Un test que dijera `assertSee('9 años (hoy)')`
 * pasaría a rojo el día que el orquestador escriba el texto definitivo, que es justo cuando no debe.
 */
class UserDependentsInfolistTest extends TestCase
{
    use RefreshDatabase;

    protected ?LegalDocumentVersion $version = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        // Hoy FIJO: la edad de esta pantalla es la de hoy, así que un test que no fije el reloj
        // caducaría solo (y en el peor momento: el día del cumpleaños de un dato de prueba).
        $this->travelTo(Carbon::parse('2026-08-28 12:00:00', 'Europe/Madrid'));
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function mode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }

    private function publish(string $title = 'Exención'): LegalDocumentVersion
    {
        return $this->version = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => $title, 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function add(User $holder, string $name, string $bornOn): Dependent
    {
        return app(DependentRegistry::class)->add($holder, $name, $bornOn);
    }

    private function signFor(User $holder, Dependent $dependent): void
    {
        app(WaiverSigner::class)->sign($holder, $this->version ?? $this->publish(), new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: (int) $dependent->getKey(),
        ));
    }

    private function sheet(User $customer): Testable
    {
        return Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id]);
    }

    /**
     * La sección enseña a los menores del titular con su edad de HOY y el estado de su exención, y
     * los TRES estados hablan el vocabulario de la ficha del pedido (`AssignedDependents::WAIVER_*`).
     *
     * El «sin exención firmada» se construye simplemente no firmando; el «de una versión anterior»,
     * publicando una segunda versión DESPUÉS de la firma.
     */
    public function test_the_section_lists_the_dependents_with_todays_age_and_the_waiver_state(): void
    {
        $this->mode('interno');
        $customer = $this->userWithRole('customer');
        $this->publish();
        $lucas = $this->add($customer, 'Lucas', '2017-03-12');  // 9 hoy (cumple en marzo)
        $vera = $this->add($customer, 'Vera', '2019-11-02');    // 6 hoy (cumple en noviembre)
        $noa = $this->add($customer, 'Noa', '2015-08-28');      // 11 hoy: cumple HOY mismo
        $this->signFor($customer, $lucas);
        $this->signFor($customer, $noa);
        $this->publish('Exención v2');                          // deja la firma de Noa desfasada
        $this->signFor($customer, $lucas);                      // Lucas re-firma la vigente

        $html = $this->sheet($customer)->assertSee(__('admin.users.section_dependents'))->html();

        // Los tres, en orden de alta.
        $this->assertSame(
            [$lucas->id, $vera->id, $noa->id],
            $this->attributes($html, 'data-dependent-row'),
            'salen los tres declarados y en orden de id',
        );
        $this->assertStringContainsString('Lucas', $html);
        $this->assertStringContainsString('Vera', $html);
        $this->assertStringContainsString('Noa', $html);

        // La EDAD es la de hoy (28/08/2026), y el que cumple hoy ya los tiene cumplidos.
        $this->assertSame([9, 6, 11], $this->attributes($html, 'data-dependent-age'));

        // El ESTADO de la exención: firmada vigente, sin firmar, firmada de una versión anterior.
        $this->assertSame(
            [AssignedDependents::WAIVER_CURRENT, AssignedDependents::WAIVER_MISSING, AssignedDependents::WAIVER_OUTDATED],
            $this->strings($html, 'data-dependent-waiver'),
        );
        // Y sus rótulos son los MISMOS que enseña la ficha del pedido (no un vocabulario paralelo).
        $this->assertStringContainsString(__('admin.orders.dependents.waiver_current'), $html);
        $this->assertStringContainsString(__('admin.orders.dependents.waiver_missing'), $html);
        $this->assertStringContainsString(__('admin.orders.dependents.waiver_outdated'), $html);

        // Nadie está retirado → la columna de la retirada ni se pinta.
        $this->assertStringNotContainsString('data-dependent-removed', $html);
    }

    /**
     * §4.1: la fila SOBREVIVE a la mayoría de edad —nadie la borra por un cumpleaños— y deja de estar
     * cubierta por el waiver del adulto. Se crea directa porque `DependentRegistry::add()` rechaza a
     * quien ya tiene 18: la única forma real de llegar aquí es cumplirlos con la fila puesta.
     */
    public function test_a_dependent_who_turned_eighteen_is_listed_as_an_adult(): void
    {
        $this->mode('externo');
        $customer = $this->userWithRole('customer');
        Dependent::create(['user_id' => $customer->id, 'name' => 'Iris', 'born_on' => '2005-01-04']); // 21 hoy

        $html = $this->sheet($customer)->assertSee(__('admin.users.dependents.adult'))->html();

        $this->assertSame([21], $this->attributes($html, 'data-dependent-age'));
        $this->assertStringNotContainsString(__('admin.users.dependents.age_today', ['age' => 21]), $html);
    }

    /** Sin menores declarados, el empty-state — y ninguna fila. */
    public function test_empty_state_when_the_holder_has_no_dependents(): void
    {
        $html = $this->sheet($this->userWithRole('customer'))
            ->assertSee(__('admin.users.dependents.empty'))
            ->html();

        $this->assertStringNotContainsString('data-dependent-row', $html);
    }

    /**
     * `RGPD-01` (§5): una cuenta anonimizada NO lista a nadie. Las filas que sobreviven a
     * `anonymize()` lo hacen bajo el régimen restringido de su firma y su sitio es la acción
     * «Registro del waiver», con permiso propio y cada apertura auditada.
     */
    public function test_an_anonymized_account_lists_nobody_and_says_why(): void
    {
        $this->mode('interno');
        $customer = $this->userWithRole('customer');
        $this->publish();
        $lucas = $this->add($customer, 'Lucas', '2017-03-12');
        $this->signFor($customer, $lucas);
        $customer->anonymize();

        // La fila SOBREVIVE desvinculada (es lo que hace este caso interesante): lo que no sobrevive
        // es su presencia en esta pantalla.
        $this->assertTrue($customer->dependents()->first()?->isRemoved());

        $html = $this->sheet($customer->fresh())
            ->assertSee(__('admin.users.dependents.anonymized'))
            ->html();

        $this->assertStringNotContainsString('Lucas', $html);
        $this->assertStringNotContainsString('data-dependent-row', $html);
        $this->assertStringNotContainsString(__('admin.users.dependents.empty'), $html, 'no es que no tenga: es que no se listan');

        // Guarda de la guarda: aunque alguien pinte la lista desde otro sitio, el read-model tampoco
        // devuelve nada — la regla no vive solo en la vista.
        $this->assertSame([], HolderDependents::forHolder($customer->fresh()));
    }

    /**
     * Un menor RETIRADO de la cuenta sigue saliendo, marcado: tiene un waiver firmado detrás y el
     * operador necesita verlo para entender la cuenta (§4.4). Mismo criterio que la ficha del pedido.
     */
    public function test_a_removed_dependent_is_still_listed_and_marked(): void
    {
        $this->mode('interno');
        $customer = $this->userWithRole('customer');
        $this->publish();
        $lucas = $this->add($customer, 'Lucas', '2017-03-12');
        $vera = $this->add($customer, 'Vera', '2019-11-02');
        $this->signFor($customer, $vera);
        $vera->unlink();

        $html = $this->sheet($customer)->html();

        $this->assertSame([$lucas->id, $vera->id], $this->attributes($html, 'data-dependent-row'));
        $this->assertSame([$vera->id], $this->attributes($html, 'data-dependent-removed'), 'solo la retirada lleva la marca');
        $this->assertStringContainsString(__('admin.users.dependents.col_removed'), $html, 'con una retirada, la columna aparece');
        $this->assertStringContainsString('Vera', $html);
    }

    /**
     * Fuera de modo interno no hay pregunta que hacer sobre la exención (`waiver-probatorio.md`
     * §4.1): ni columna ni celdas. Los menores siguen saliendo — existen igual.
     */
    public function test_outside_internal_mode_there_is_no_waiver_column(): void
    {
        $customer = $this->userWithRole('customer');
        $this->add($customer, 'Lucas', '2017-03-12');

        foreach (['externo', 'desactivado'] as $mode) {
            $this->mode($mode);
            $html = $this->sheet($customer)->html();

            $this->assertStringContainsString('Lucas', $html, "en modo {$mode} el menor sigue saliendo");
            $this->assertStringNotContainsString('data-dependent-waiver', $html, "en modo {$mode} no hay estado de exención");
            $this->assertStringNotContainsString(__('admin.users.dependents.col_waiver'), $html);
            $this->assertFalse(HolderDependents::showsWaiver());
        }
    }

    /**
     * Presupuesto: la composición NO crece con el número de menores — 1 consulta fuera de interno
     * (los menores) y ≤4 en interno (los menores + las firmas + sus versiones + la versión vigente).
     * Los ajustes no cuentan: `Setting::value()` memoiza todos en una sola lectura, y por eso se
     * calienta el memo antes de encender el registro.
     */
    public function test_the_read_model_runs_a_constant_number_of_queries(): void
    {
        $customer = $this->userWithRole('customer');
        $this->publish();
        $one = $this->add($customer, 'Lucas', '2017-03-12');
        $this->mode('interno');
        $this->signFor($customer, $one);

        $many = $this->userWithRole('customer');
        foreach ([['Ana', '2016-01-05'], ['Bea', '2017-02-06'], ['Cid', '2018-03-07'], ['Dan', '2019-04-08']] as [$name, $born]) {
            $this->signFor($many, $this->add($many, $name, $born));
        }

        $count = function (User $holder): int {
            WaiverSettings::mode(); // calienta el memo de ajustes: no es parte del presupuesto
            DB::enableQueryLog();
            DB::flushQueryLog();
            HolderDependents::forHolder($holder);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $forOne = $count($customer);
        $forMany = $count($many);
        $this->assertSame($forOne, $forMany, 'el presupuesto no crece con el número de menores');
        $this->assertLessThanOrEqual(4, $forOne, 'menores + firmas + versiones + versión vigente');

        // Fuera de interno no se pregunta por ninguna firma: solo los menores.
        $this->mode('externo');
        $this->assertSame(1, $count($many), 'fuera de interno, una sola consulta');
    }

    /** Los ids de un `data-*` numérico, en el orden en que aparecen en el HTML. @return list<int> */
    private function attributes(string $html, string $attribute): array
    {
        preg_match_all('/'.preg_quote($attribute, '/').'="(\d+)"/', $html, $m);

        return array_map('intval', $m[1]);
    }

    /** Los valores de un `data-*` de texto, en orden. @return list<string> */
    private function strings(string $html, string $attribute): array
    {
        preg_match_all('/'.preg_quote($attribute, '/').'="([^"]*)"/', $html, $m);

        return $m[1];
    }
}
