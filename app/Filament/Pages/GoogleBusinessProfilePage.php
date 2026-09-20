<?php

namespace App\Filament\Pages;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Services\GoogleBusinessConnectionState;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * **Ficha de Google** — el estado de la conexión y el botón que la abre
 * (`docs/specs/google-business-profile.md` §4.2·1).
 *
 * ⚠️ **Esta pantalla no habla con Google.** Lee el estado de casa y manda al admin al controlador de
 * §4.2·2, que es quien tiene la sesión, el reto y el canje. Una página de Livewire que hiciera el
 * viaje de OAuth tendría que custodiar el `code_verifier` en su propio estado —que **viaja al
 * navegador en el snapshot**— y eso es exactamente lo que PKCE existe para impedir.
 *
 * ⚠️ De los siete estados, **el que se pinta es el EFECTIVO**
 * ({@see GoogleBusinessConnectionState}), no la columna: «sin configurar» y «lista para conectar» no
 * están guardadas en ningún sitio.
 *
 * ▶ **Lo que falta aquí es la T1·3**: elegir y revalidar la ficha (§4.2·4), quién conectó y la última
 * pasada (§4.2·1) y desconectar (§4.2·8). Hoy la pantalla dice el estado y deja conectar.
 */
class GoogleBusinessProfilePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $slug = 'ficha-google';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.google-business-profile';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('settings.manage') ?? false;
    }

    /**
     * Fuera del menú lateral, como `Maintenance` y `Settings` (`#223`): es puesta en marcha, no día a
     * día, y se entra por «Ajustes». **Ocultar no es autorizar**: quien decide sigue siendo
     * {@see self::canAccess()}.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __('admin.google_business.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.google_business.subheading');
    }

    /** El estado efectivo, con su explicación y si desde él se puede conectar. */
    public function estado(): GoogleBusinessStatus
    {
        return GoogleBusinessConnectionState::of($this->conexion());
    }

    public function conexion(): ?GoogleBusinessConnection
    {
        return GoogleBusinessConnection::current();
    }

    /**
     * ¿Tiene sentido enseñar el botón de conectar?
     *
     * ⚠️ **Se enseña también en los estados ROTOS**, no solo en «lista para conectar»: caducada, sin
     * permiso y ficha perdida se arreglan precisamente **reconectando**. El único donde el botón
     * sobra es «sin configurar», porque no hay credenciales que usar y pulsarlo no haría nada.
     */
    public function puedeConectar(): bool
    {
        return $this->estado() !== GoogleBusinessStatus::Unconfigured;
    }
}
