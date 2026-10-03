<?php

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **A QUIÉN SE LE PUEDE ESCRIBIR UN COMERCIAL** (la C1 de `specs/correos-rediseno.md` §4.4, `[DECIDIDO owner]` `#920`): SOLO
 * con «novedades», con correo y sin anonimizar. Es UN predicado escrito dos veces —la consulta que elige el público
 * (`User::scopeMarketable()`) y la cuenta que el envío relee al salir (`User::canReceiveMarketing()`)—, y aquí se mide que
 * dicen lo mismo, cuenta a cuenta: si una de las dos se relaja, la otra la tapa en las pruebas del correo y no se vería.
 */
class MarketableAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_query_and_the_account_agree_only_news_an_address_and_not_anonymized(): void
    {
        $buena = User::factory()->create(['marketing_opt_in' => true]);
        $fuera = [
            'sin «novedades»' => User::factory()->create(['marketing_opt_in' => false]),
            // La casilla encendida a propósito sobre una dirección suprimida: el predicado no se fía de la casilla sola.
            'con la dirección de una cuenta suprimida' => User::factory()->create([
                'marketing_opt_in' => true, 'email' => 'deleted_9@'.User::ANONYMIZED_EMAIL_DOMAIN,
            ]),
            'sin correo' => User::factory()->withoutEmail()->create(['marketing_opt_in' => true]),
            'con el correo vacío' => User::factory()->create(['marketing_opt_in' => true, 'email' => '']),
        ];
        // Y una suprimida DE VERDAD, por su camino (`RGPD-01`: apaga la casilla y cambia la dirección).
        $suprimida = User::factory()->create(['marketing_opt_in' => true]);
        $this->assertTrue($suprimida->anonymize());
        $fuera['suprimida por su camino'] = $suprimida;

        $this->assertSame([$buena->getKey()], User::query()->marketable()->orderBy('id')->pluck('id')->all(), 'el público');
        $this->assertTrue($buena->fresh()?->canReceiveMarketing(), 'CONTROL: la que sí');
        foreach ($fuera as $porque => $cuenta) {
            $this->assertFalse((bool) $cuenta->fresh()?->canReceiveMarketing(), "al salir, tampoco: {$porque}");
        }
    }
}
