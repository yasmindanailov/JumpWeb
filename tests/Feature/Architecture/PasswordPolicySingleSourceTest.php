<?php

namespace Tests\Feature\Architecture;

use App\Domain\Identity\Services\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * **La política de contraseñas tiene UNA fuente** (`Identity\Services\PasswordPolicy`).
 *
 * ⚠️⚠️ **Nace de un hallazgo medido el 2026-08-22**: `Password::min(8)->uncompromised()` estaba
 * escrito a mano en **seis** superficies —registro web y API, restablecimiento web y API, cambio de
 * contraseña de «Mi cuenta» y la contraseña explícita de `app:create-admin`—. Es la misma familia que
 * la fórmula del rótulo de día (`DayLabelSingleSourceTest`), con un agravante: **esto es seguridad**.
 *
 * ▶ El fallo que esta guarda impide no es un error de programa, es una **erosión**: el día que la
 * política suba, alguien tocará cinco sitios de seis y el que quede se convertirá en la puerta más
 * floja del producto — sin romper nada, sin ponerse rojo y sin que nadie lo note.
 */
class PasswordPolicySingleSourceTest extends TestCase
{
    /**
     * Quién puede nombrar la regla a mano. Hoy, solo su dueña.
     *
     * ⚠️ La contraseña **generada** de `app:create-admin` no aparece aquí porque **no usa la regla**:
     * son 24 caracteres aleatorios que entran en la política por construcción, y comprobarlos contra
     * Have I Been Pwned metería una llamada de red en el camino feliz del despliegue. Su contraseña
     * **explícita** —la que escribe un humano— sí pasa por la fuente única.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'app/Domain/Identity/Services/PasswordPolicy.php',
    ];

    public function test_nobody_else_declares_the_password_policy(): void
    {
        $offenders = [];

        foreach ($this->sources() as $relative => $path) {
            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            if (preg_match('/(Password|PasswordRule)::min\s*\(/', $source) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [], $offenders,
            "Estos ficheros declaran la política de contraseñas por su cuenta:\n  ".implode("\n  ", $offenders)."\n\n".
            "⚠️ Usa `PasswordPolicy::rules()`. Llegó a estar copiada en SEIS superficies; el día que la\n".
            "política suba, cinco se actualizan y la sexta se queda como la puerta más floja del\n".
            'producto, sin romper nada. Si de verdad es una excepción, DECLÁRALA con su motivo.'
        );
    }

    /**
     * **La guarda de la guarda**: el escáner lee el árbol de verdad.
     *
     * Sin esto, un escaneo vacío dejaría el caso de arriba pasando solo y para siempre — el fallo del
     * contador de `PurchaseRetirementTest` en `DECISIONES #63`.
     */
    public function test_the_scan_reads_the_tree_and_sees_the_owner(): void
    {
        $sources = $this->sources();

        $this->assertGreaterThan(300, count($sources), 'el escaneo no está leyendo `app/`');
        $this->assertArrayHasKey(self::ALLOWED[0], $sources, 'el escaneo no ve ni a la propia dueña');

        $this->assertMatchesRegularExpression(
            '/Password::min\s*\(/', (string) file_get_contents($sources[self::ALLOWED[0]]),
            'la dueña ha dejado de declarar la regla: entonces la excepción sobra'
        );
    }

    /**
     * **Qué rechaza la política, y qué NO — las dos mitades, a propósito.**
     *
     * ⚠️⚠️ **La segunda mitad cambió de signo el 2026-09-02** (`#351`): antes exigía que
     * `uncompromised()` estuviera PUESTO y ahora exige que NO lo esté. No es que la guarda se haya
     * relajado: es que **vigila una decisión**, y las decisiones se vigilan en la dirección en que se
     * pueden deshacer sin querer. `[DECIDIDO owner]`: se retira el corpus de filtraciones porque
     * genera demasiada fricción en el alta, con el coste asumido de que `12345678` pase.
     * ▶ Es la doctrina de `#301`: una guarda que mira **lo contrario** para que nadie «termine el
     * trabajo» reponiendo lo que se quitó a sabiendas.
     */
    public function test_the_policy_rejects_what_it_must_and_no_longer_checks_breaches(): void
    {
        $this->assertSame(8, PasswordPolicy::MIN_LENGTH);

        // Corta por longitud.
        $this->assertTrue(Validator::make(['p' => 'Abc123!'], ['p' => PasswordPolicy::rules()])->fails());

        // Y exige que exista: una contraseña vacía no pasa por descuido de `required`.
        $this->assertTrue(Validator::make(['p' => ''], ['p' => PasswordPolicy::rules()])->fails());

        $rules = PasswordPolicy::rules();
        $password = array_values(array_filter($rules, static fn ($rule): bool => $rule instanceof Password));

        $this->assertCount(1, $password, 'la regla de contraseña ha desaparecido de la política');

        // ⚠️⚠️ **Se lee la propiedad por REFLEXIÓN, y la primera versión de este caso no medía nada.**
        // Comprobaba `json_encode((array) $rule)` buscando la cadena «uncompromised»: la propiedad es
        // `protected` y no sale ahí, así que el caso pasaba **igual con `uncompromised()` y sin él** —
        // se descubrió mutándolo. Es la lección de `#65` en su forma más pura: una aserción que no se
        // ha visto fallar no prueba nada.
        //
        // ⚠️ Y NO se prueba llamando a Have I Been Pwned con una contraseña filtrada: un test que
        // dependiera de una API externa daría rojo sin red, y ese rojo no diría nada del código.
        $flag = new \ReflectionProperty($password[0], 'uncompromised');

        $this->assertFalse(
            (bool) $flag->getValue($password[0]),
            "La política ha vuelto a comprobar el corpus de filtraciones (`uncompromised()`).\n".
            "▶ Se RETIRÓ a propósito el 2026-09-02 (`[DECIDIDO owner]`, `#351`): en el alta rechazaba\n".
            "  contraseñas por un motivo que el cliente no sabe arreglar, y eso costaba altas.\n".
            "▶ Si hay que reponerla, se reabre la decisión con el owner — que ya descartó también la\n".
            '  vía intermedia (`uncompromised(500)`, solo las muy comunes). No se repone por costumbre.'
        );
    }

    /** @return array<string, string> ruta relativa → absoluta */
    private function sources(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[str_replace(base_path().'/', '', $file->getPathname())] = $file->getPathname();
            }
        }

        return $files;
    }
}
