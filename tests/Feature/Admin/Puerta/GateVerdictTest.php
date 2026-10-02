<?php

namespace Tests\Feature\Admin\Puerta;

use App\Livewire\Admin\Puerta\GateVerdict;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use ReflectionClass;
use Tests\TestCase;

/**
 * **El veredicto de la Puerta nueva** (`docs/specs/puerta-nueva.md` §4.4, la P1): total sobre los estados, el rojo solo
 * para «No encontrado», los textos del mockup ESCRITOS A MANO (una aserción con `__()` pasa aunque se vacíe la clave,
 * `#734`) y, con ficha, el descargo del titular y el de TODOS sus menores a cargo.
 */
class GateVerdictTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    /** @return array<string, mixed> */
    private function profile(bool $enabled = true, bool $signed = true, array $minors = []): array
    {
        return [
            'waiver' => ['enabled' => $enabled, 'signed' => $signed, 'accepted_on' => null, 'outdated' => false, 'pending_acceptance' => false],
            'dependents' => array_map(fn (?string $w): array => ['name' => 'Vera', 'age' => 6, 'waiver' => $w], $minors),
        ];
    }

    public function test_every_status_of_the_screen_has_a_verdict_and_nothing_else_does(): void
    {
        $statuses = array_values(array_filter(
            (new ReflectionClass(ValidarRegistro::class))->getConstants(),
            fn (mixed $v, string $k): bool => str_starts_with($k, 'STATUS_'),
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->assertEqualsCanonicalizing($statuses, GateVerdict::states(), 'un estado sin veredicto deja al empleado sin nada que leer');
    }

    public function test_without_a_profile_each_state_says_the_mockups_words_and_only_not_found_is_red(): void
    {
        $esperado = [
            ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER => ['verde', 'Listos para saltar', 'Descargo aceptado el 05/09/2026.', false, 'verde'],
            ValidarRegistro::STATUS_REGISTERED => ['verde', 'Listos para saltar', 'Tiene cuenta en el sistema.', false, 'verde'],
            ValidarRegistro::STATUS_REGISTERED_NO_WAIVER => ['ambar', 'Falta firmar el descargo', 'Pásale la tablet al cliente para que firme el descargo antes de saltar.', false, 'ambar'],
            ValidarRegistro::STATUS_NOT_REGISTERED => ['rojo', 'No encontrado', 'Si no tiene cuenta, que escanee el cartel de registro', false, 'rojo'],
            ValidarRegistro::STATUS_CARD_REVOKED => ['ambar', 'QR caducado', 'Se renovó y este ya no vale. Búscalo por su correo o su teléfono', false, 'ambar'],
            ValidarRegistro::STATUS_CARD_UNKNOWN => ['gris', 'QR no reconocido', 'No es de ningún cliente. Búscalo por su correo o su teléfono', false, null],
            ValidarRegistro::STATUS_INVALID_INPUT => ['gris', 'Escribe un correo o un teléfono válido', null, true, null],
            ValidarRegistro::STATUS_RATE_LIMITED => ['gris', 'Demasiadas búsquedas seguidas: espera un minuto', null, true, null],
            ValidarRegistro::STATUS_LOOKUP_LIMITED => ['gris', 'Demasiadas búsquedas tecleadas en una hora. El escaneo sigue funcionando; para teclear, espera o avisa a un responsable.', null, true, null],
        ];

        foreach ($esperado as $status => [$tone, $text, $sub, $notice, $sound]) {
            $v = GateVerdict::for(['status' => $status, 'date' => '05/09/2026'], null);
            $this->assertSame(['tone' => $tone, 'text' => $text, 'sub' => $sub, 'notice' => $notice, 'sound' => $sound], $v, $status);
        }

        $antiguo = GateVerdict::for(['status' => ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER, 'date' => '05/09/2026', 'outdated' => true], null);
        $this->assertSame('Descargo aceptado el 05/09/2026. Su descargo es de una versión anterior del texto: puede pasar. Se le pedirá la firma nueva en su próxima compra o inicio de sesión, no en el mostrador.', $antiguo['sub'], 'sin ficha, el semáforo sigue diciendo la versión anterior');
        $this->assertSame('verde', $antiguo['tone'], 'y deja pasar');
    }

    public function test_with_a_profile_the_minors_decide_too(): void
    {
        $verde = fn (array $profile): string => GateVerdict::for(['status' => ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER], $profile)['tone'];

        $this->assertSame('verde', $verde($this->profile(minors: ['current', 'current'])));
        $this->assertSame('ambar', $verde($this->profile(minors: ['current', 'missing'])), 'un menor sin descargo: falta firmar, aunque el titular lo tenga');
        $this->assertSame('verde', $verde($this->profile(minors: ['outdated'])), 'una versión anterior deja pasar: lo dice su tarea');
        $this->assertSame('verde', $verde($this->profile(minors: [null])), 'fuera del modo interno no se mide el menor');
        $this->assertSame('ambar', $verde($this->profile(signed: false)), 'el titular sin descargo');
        $this->assertSame('verde', $verde($this->profile(enabled: false, signed: false, minors: ['missing'])), 'con el descargo apagado no hay nada que firmar (`#216`)');

        $ambar = GateVerdict::for(['status' => ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER], $this->profile(minors: ['missing']));
        $this->assertSame(['tone' => 'ambar', 'text' => 'Falta firmar el descargo', 'sub' => null, 'notice' => false, 'sound' => 'ambar'], $ambar, 'la ficha manda sobre el estado del semáforo');
    }
}
