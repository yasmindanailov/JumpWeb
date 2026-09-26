<?php

namespace Tests\Feature\Isla;

use App\Http\Cuenta\AvisoDeSesion;
use Tests\TestCase;

/**
 * **El aviso que deja el servidor al volver a una página nueva** (T5e·2, `#779`): el `status` de la sesión, con el texto
 * del aviso de siempre y su tono. Que se vea en la página lo prueba `InstancePagesTest`; aquí, que ningún `status` con
 * texto se quede sin tono y que el tono de cada uno sea el que dice.
 */
class AvisoDeSesionTest extends TestCase
{
    /**
     * Un `status` nuevo en `account.status` sin clasificar saldría como `info` sin que nadie lo hubiera decidido: un «no
     * se pudo» pintado como un aviso neutro. Aquí se para, en los tres idiomas.
     */
    public function test_every_account_status_is_classified_once_and_every_classified_one_has_its_text(): void
    {
        $clasificados = [...AvisoDeSesion::BIEN, ...AvisoDeSesion::MAL, ...AvisoDeSesion::INFO];

        $this->assertSame(count($clasificados), count(array_unique($clasificados)), 'Un `status` en dos listas.');

        foreach (['es', 'en', 'fr'] as $idioma) {
            $textos = (array) __('account.status', [], $idioma);

            $this->assertEqualsCanonicalizing(array_keys($textos), $clasificados, "Sin clasificar o sin texto en «{$idioma}».");
        }
    }

    public function test_it_gives_the_classic_text_with_its_tone(): void
    {
        $aviso = new AvisoDeSesion;

        $this->assertSame(['texto' => __('account.status.google-linked'), 'tono' => 'success'], $aviso->paraLaIsla('google-linked'));
        $this->assertSame(['texto' => __('account.status.google-provider-taken'), 'tono' => 'danger'], $aviso->paraLaIsla('google-provider-taken'));
        $this->assertSame(['texto' => __('account.status.google-cancelled'), 'tono' => 'info'], $aviso->paraLaIsla('google-cancelled'));
    }

    public function test_without_a_status_with_an_account_text_there_is_nothing_to_say(): void
    {
        $aviso = new AvisoDeSesion;

        foreach ([null, '', 'invitation-saved', 'no-existe', ['google-linked']] as $status) {
            $this->assertNull($aviso->paraLaIsla($status), json_encode($status));
        }
    }
}
