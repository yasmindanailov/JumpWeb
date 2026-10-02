<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;

/**
 * **EL LOGIN DEL PANEL, QUE RECUERDA UN DÍA** (§4.4 de `docs/specs/panel-a-salvo.md`; `DECISIONES #877`, `[DECIDIDO owner]`:
 * «un día, para todos»). Es el de Filament, igual en todo; solo «Recordarme» viene MARCADA: tras la contraseña —y el código
 * de la app, si es administrador (`#851`)— el dispositivo queda dentro un día, aunque pasen las dos horas sin uso de la
 * sesión. En un ordenador compartido se desmarca, y entonces vale la sesión de siempre.
 *
 * ⚠️ El DÍA lo pone el guard `admin` (`config/auth.php`, con {@see REMEMBER_MINUTES}), no esta página: sin él la cookie
 * «recuérdame» del framework dura 400 días y se salta el código de la app todo ese tiempo. Y no se alarga con el uso —a
 * diferencia del dispositivo recordado de la web (`RememberedDevice`)—: a las 24 h de entrar, contraseña y código otra vez.
 */
class PanelLogin extends Login
{
    /** Un día, en minutos, como lo pide `config/auth.php` (`guards.admin.remember`). */
    public const REMEMBER_MINUTES = 24 * 60;

    protected function getRememberFormComponent(): Component
    {
        $remember = parent::getRememberFormComponent();

        if ($remember instanceof Checkbox) {
            $remember->default(true);
        }

        return $remember;
    }
}
