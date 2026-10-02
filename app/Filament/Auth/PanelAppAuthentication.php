<?php

namespace App\Filament\Auth;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Forms\Components\OneTimeCodeInput;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * **El AUTHENTICATOR del panel que continúa con la sexta cifra** (P3 de `docs/specs/panel-a-salvo.md`, `#851`; el owner,
 * `#867`: «continuar al escribir el último dígito, también en el authenticator del panel»). Es el de Filament, igual en
 * todo —secreto, ventana, recuperación, su id `app`—; solo su campo del RETO, en el login, envía el formulario en cuanto
 * tiene todas las cifras, como las casillas del código de la isla y del cajón. Configurarlo o regenerar los códigos (sus
 * acciones, en modales) siguen con su botón: se hacen una vez.
 *
 * ⚠️ Filament para la propagación del `input` en cada casilla (`one-time-code.js`), así que se escucha en CAPTURA en su
 * contenedor y se lee `state` en el siguiente tic, tras su `commit()`. Envía solo por un gesto (teclear, pegar o el
 * autocompletado del móvil), nunca al pintarse: un re-pintado con un código malo dentro no puede reenviarlo en bucle. Un
 * código que no vale deja el error y las cifras; corregir una vuelve a enviar.
 */
class PanelAppAuthentication extends AppAuthentication
{
    /** El envío con la última cifra, sobre el ámbito Alpine del campo de Filament (`state`, `inputs`). */
    public const SUBMIT_ON_LAST_DIGIT = "\$nextTick(() => { if ((state ?? '').length === inputs.length) \$el.closest('form')?.requestSubmit?.() })";

    public function getChallengeFormComponents(Authenticatable $user): array
    {
        $components = parent::getChallengeFormComponents($user);

        foreach ($components as $component) {
            if ($component instanceof OneTimeCodeInput) {
                $component->extraAlpineAttributes(['x-on:input.capture' => self::SUBMIT_ON_LAST_DIGIT], merge: true);
            }
        }

        return $components;
    }
}
