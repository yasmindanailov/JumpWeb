<?php

namespace App\Filament\Pages;

use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Content\Models\GoogleBusinessReviewSuppression;
use App\Domain\Content\Services\GoogleReviewSuppressions;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Services\GoogleBusinessConnectionState;
use App\Domain\Platform\Services\GoogleBusinessLocation;
use App\Domain\Platform\Services\GoogleBusinessLocations;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * **Ficha de Google** — el estado de la conexión y el botón que la abre
 * (`docs/specs/google-business-profile.md` §4.2·1).
 *
 * ⚠️⚠️ **Esta pantalla NO hace el viaje de OAuth**, y esa es la frontera que importa: el reto, el
 * `code_verifier` y el canje viven en el controlador de §4.2·2. Una página de Livewire que los
 * custodiara los llevaría en su estado, **que viaja al navegador en el snapshot** — justo lo que PKCE
 * existe para impedir. Lo que sí hace es **listar** las fichas (§4.2·4), que es una lectura sin
 * secretos y para la que se entra aquí.
 *
 * ⚠️ De los siete estados, **el que se pinta es el EFECTIVO**
 * ({@see GoogleBusinessConnectionState}), no la columna: «sin configurar» y «lista para conectar» no
 * están guardadas en ningún sitio.
 *
 * ▶ **Lo que falta aquí es la T1·4**: quién conectó y la última pasada (§4.2·1), y desconectar
 * (§4.2·8). Hoy la pantalla dice el estado, deja conectar y deja elegir la ficha.
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

    /**
     * Las fichas que el permiso alcanza (§4.2·4), o lista vacía si no hay conexión o Google no
     * contesta.
     *
     * ⚠️ **Sí, esto llama a Google al pintar la pantalla, y es deliberado.** `PERF-02` protege la
     * PORTADA, no el panel: aquí no hay tráfico y la llamada es justo para lo que se entra. La
     * alternativa —listar por un botón y arrastrar el resultado por la sesión— añade estado que se
     * queda obsoleto solo, para ahorrar una llamada que hace un admin una vez.
     *
     * ⚠️⚠️ **Y no escribe el estado aunque Google falle.** Pintar es un GET: marcar «sin permiso»
     * desde aquí haría que abrir una pantalla apagase la conexión de un parque, y sin el
     * comparar-y-escribir del §4.2·7. Eso lo hace la pasada, que sí sabe con qué token llamó.
     *
     * @return list<GoogleBusinessLocation>
     */
    public function fichas(): array
    {
        $this->resolverFichas();

        return $this->fichas ?? [];
    }

    /** El motivo por el que no hay lista, para decírselo en vez de enseñar un hueco. */
    public function fichasError(): ?string
    {
        $this->resolverFichas();

        return $this->fichasError;
    }

    /** ¿Ya hay ficha elegida? */
    public function fichaElegida(): ?string
    {
        return $this->conexion()?->location_name;
    }

    /**
     * **Quién conectó** (§4.2·1), por su nombre.
     *
     * ⚠️ Se resuelve AQUÍ y no con una relación de Eloquent: la conexión vive en Platform, que no
     * puede mirar a Identity (`ModuleBoundariesTest`), así que guarda la FK sin relación (`#720`). La
     * capa de entrega sí ve a los dos, y esto es la capa de entrega.
     */
    public function conectadaPor(): ?string
    {
        $id = $this->conexion()?->connected_by_user_id;

        return $id === null ? null : User::query()->whereKey($id)->value('name');
    }

    /** ¿Hay algo que desconectar? Sin token no hay permiso que retirar. */
    public function puedeDesconectar(): bool
    {
        return $this->conexion()?->hasStoredToken() === true;
    }

    /**
     * **Las reseñas que hay publicadas ahora mismo** (T2·5, §4.3·7, `#731`), para poder ocultarlas.
     *
     * ⚠️⚠️ **Se leen con el MISMO filtro de plazo que la portada** (`withinRetention()`): si aquí
     * saliera una que la web ya no enseña, el admin la ocultaría creyendo que estaba a la vista — y
     * gastaría una entrada de una lista que no caduca en algo que ya no se veía.
     *
     * @return Collection<int, GoogleBusinessReview>
     */
    public function resenas(): Collection
    {
        return GoogleBusinessReview::query()
            ->withinRetention()
            ->orderByDesc('review_created_at')
            ->get();
    }

    /**
     * **Lo que está oculto**, para poder dejar de ocultarlo.
     *
     * ⚠️ De aquí solo sale un hash y una fecha: es todo lo que se guarda (§4.3·7). El panel no puede
     * enseñar qué reseña era **porque no lo sabemos**, y eso es lo correcto — enseñarlo obligaría a
     * conservar su texto en la única tabla que no caduca.
     *
     * @return Collection<int, GoogleBusinessReviewSuppression>
     */
    public function ocultas(): Collection
    {
        return app(GoogleReviewSuppressions::class)->all();
    }

    /**
     * Los motivos tasados del desplegable.
     *
     * @return list<GoogleReviewSuppressionReason>
     */
    public function motivos(): array
    {
        return GoogleReviewSuppressionReason::cases();
    }

    /** La media y el total, con su fecha. ⚠️ «Ocultar» NO los toca (§4.3·7). */
    public function resumen(): ?GoogleBusinessReviewSummary
    {
        return GoogleBusinessReviewSummary::current();
    }

    /** @var list<GoogleBusinessLocation>|null */
    private ?array $fichas = null;

    private ?string $fichasError = null;

    private bool $fichasResueltas = false;

    /**
     * Memo por render: la vista pregunta por la lista y por el error, y sin esto serían **dos**
     * viajes a Google para pintar una pantalla.
     */
    private function resolverFichas(): void
    {
        if ($this->fichasResueltas) {
            return;
        }

        $this->fichasResueltas = true;
        $this->fichas = [];

        $token = $this->estado() === GoogleBusinessStatus::Connected
            ? $this->conexion()?->readToken()
            : null;

        if ($token === null) {
            return;
        }

        try {
            $this->fichas = app(GoogleBusinessLocations::class)->available($token);
        } catch (GoogleBusinessApiException $e) {
            $this->fichasError = $e->reason;
        }
    }
}
