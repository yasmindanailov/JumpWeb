<?php

namespace Tests\Feature\Admin\Puerta;

use App\Livewire\Admin\Puerta\QueryMask;
use Tests\TestCase;

/**
 * **«Resultado para», enmascarado** (`docs/specs/puerta-nueva.md` §4.2·D5): la cola ve la pantalla. Lo justo para que el
 * empleado reconozca su búsqueda, nunca el correo ni el teléfono enteros.
 */
class QueryMaskTest extends TestCase
{
    public function test_an_email_keeps_two_letters_and_the_domain(): void
    {
        $this->assertSame('an•••@correo.es', QueryMask::email('ana@correo.es'));
        $this->assertSame('a•••@x.com', QueryMask::email('a@x.com'), 'un usuario de una letra no se inventa la segunda');
        $this->assertSame('jo•••@mail.example.org', QueryMask::email('jose.luis+puerta@mail.example.org'));
        $this->assertSame('ñu•••@correo.es', QueryMask::email('ñuño@correo.es'), 'por caracteres, no por bytes');
    }

    public function test_a_phone_keeps_only_its_last_three_digits(): void
    {
        $this->assertSame('••• ••• 678', QueryMask::phone('612345678'));
        $this->assertSame('••• ••• 678', QueryMask::phone('+34 612 34 56 78'), 'los espacios y el prefijo no cuentan');
    }
}
