<?php

namespace App\Filament\Pages;

use App\Domain\Content\Models\MailText;
use App\Domain\Content\Services\MailTextRules;
use App\Domain\Content\Services\MailTexts;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\SiteLocales;
use App\Filament\Resources\EmailSends\Tables\EmailSendTable;
use App\Notifications\Support\MailPreviews;
use App\Notifications\Support\MailSituations;
use App\Notifications\Support\MailTextCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Livewire\Attributes\Url;

/**
 * «TEXTOS DE LOS CORREOS» (R1·T de `specs/correos-rediseno.md` §4.2.1, `[DECIDIDO owner]` `#802`): lo que dice cada correo
 * al cliente, editable por el parque bloque a bloque y en sus tres idiomas, con la estructura fija del molde y su vista
 * previa sobre el último caso real. Ajustes → Sistema, junto a «Correos enviados»; permiso propio `emails.edit_texts`,
 * re-exigido en CADA petición (`SEC-04`) por Filament: `CanAuthorizeAccess::hydrateCanAuthorizeAccess()` corre
 * `canAccess()` antes de cualquier acción. ⚠️ No se repite aquí: el arnés midió que una copia nunca se ve fallar;
 * `EmailTextsPageTest::test_the_permission_is_asked_again_on_every_action` fija la propiedad, venga de donde venga.
 *
 * Dos estados en una página (`?correo=`): la LISTA por tipo, con lo que tiene cada correo (de fábrica, cuántos textos
 * propios, «sin traducir» si un idioma cambió y otro no, «desfasado» si ya no pasa las reglas de hoy —el producto cambió
 * sus datos o una regla— y por eso no sale), y el CORREO, con un bloque por texto —su nombre humano, el de fábrica y sus
 * variables debajo— en pestañas es/en/fr, y su vista previa con el asunto y el adelanto de la bandeja encima.
 *
 * ⚠️ Guardar es TODO o NADA: se valida cada bloque que cambió en los tres idiomas y, con un solo problema, no se guarda
 * ninguno (cada uno dice el suyo en su campo). Un correo a medio guardar diría una cosa en un idioma y otra en otro.
 * ⚠️ «Volver al de fábrica» pone el texto de fábrica en el campo; al guardar, un texto igual al de fábrica BORRA la fila
 * (`MailTexts::guardar`): así el siguiente arreglo del producto llega a ese correo.
 *
 * @property Schema $form el formulario de la página (propiedad mágica de Filament; sin esto, Larastan no la ve)
 */
class EmailTexts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $slug = 'email-texts';

    protected string $view = 'filament.pages.email-texts';

    /** El correo abierto (`order_confirmation`…), o `null` para la lista. */
    #[Url]
    public ?string $correo = null;

    /** @var array<string, mixed>|null idioma → clave de texto (con sus puntos, anidada) → texto del parque */
    public ?array $textos = [];

    /** La vista previa: idioma, claro u oscuro, y lo pintado (o por qué no). */
    public string $vistaIdioma = 'es';

    public bool $vistaOscuro = false;

    /**
     * La SITUACIÓN de la vista previa (R1·T2, `#809`): una de `MailSituations::de($correo)`; `null`, la primera. Llega del
     * navegador, así que se valida contra las del correo cada vez (`MailSituations::elegida`): una ajena no se pinta.
     */
    public ?string $vistaSituacion = null;

    public ?string $vistaHtml = null;

    /** Lo que se lee en la bandeja antes de abrirlo: el cuerpo no lo enseña y son dos bloques que se editan. */
    public ?string $vistaAsunto = null;

    public ?string $vistaAdelanto = null;

    public ?string $vistaMotivo = null;

    /** El id del último aviso de «No se ha guardado nada», para cerrarlo cuando el siguiente guardar le quita la razón. */
    public ?string $avisoFallido = null;

    /** @var array<string, array<string, string>>|null clave → idioma → texto guardado de ESTE correo (una lectura por petición) */
    private ?array $filas = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('emails.edit_texts') ?? false;
    }

    /** Fuera del menú: se entra por «Ajustes» (`AdminSettingsHub`), como el resto de la puesta en marcha (`#223`). */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.mail_texts.nav_label');
    }

    public function getTitle(): string
    {
        return $this->correo === null
            ? __('admin.mail_texts.title')
            : __('admin.email_sends.mails.'.$this->correo);
    }

    public function mount(): void
    {
        if ($this->correo !== null && ! MailTextCatalog::existe($this->correo)) {
            $this->correo = null;
        }
        if ($this->correo !== null) {
            $this->form->fill($this->cargar());
        }
    }

    public function form(Schema $schema): Schema
    {
        if ($this->correo === null) {
            return $schema->components([]);
        }

        return $schema
            ->statePath('textos')
            ->components([
                Tabs::make('idiomas')->tabs(array_map(
                    fn (string $locale): Tab => Tab::make(__('admin.settings.lang_'.$locale))
                        ->badge(fn (): ?string => $this->pendientesDe($locale) > 0 ? (string) $this->pendientesDe($locale) : null)
                        ->schema($this->bloques($locale)),
                    SiteLocales::SUPPORTED,
                )),
            ]);
    }

    /**
     * La LISTA: los correos del catálogo por tipo, con su estado.
     *
     * @return list<array{label: string, items: list<array{correo: string, label: string, description: string, url: string, propios: int, sinTraducir: list<string>, desfasados: int}>}>
     */
    public function tipos(): array
    {
        $filas = MailText::query()->get(['key', 'locale', 'text']);
        $porClave = [];
        foreach ($filas as $fila) {
            $porClave[$fila->key][$fila->locale] = $fila->text;
        }

        $tipos = [];
        foreach (MailTextCatalog::TIPOS as $tipo) {
            $items = [];
            foreach (MailTextCatalog::CORREOS as $correo => [, $suTipo]) {
                if ($suTipo !== $tipo) {
                    continue;
                }
                $propios = 0;
                $idiomas = [];
                $desfasados = 0;
                foreach (MailTextCatalog::claves($correo) as $clave) {
                    foreach ($porClave[$clave] ?? [] as $locale => $texto) {
                        $propios++;
                        $idiomas[$locale] = true;
                        if (! $this->alDia($clave, (string) $locale, (string) $texto)) {
                            $desfasados++;
                        }
                    }
                }
                $items[] = [
                    'correo' => $correo,
                    // El nombre de «Correos enviados»: una sola fuente por correo.
                    'label' => __('admin.email_sends.mails.'.$correo),
                    'description' => __('admin.mail_texts.descripciones.'.$correo),
                    'url' => static::getUrl(['correo' => $correo]),
                    'propios' => $propios,
                    // Un idioma con textos propios y otro sin ninguno: el cliente en ese otro idioma lee el de fábrica.
                    'sinTraducir' => $idiomas === [] ? [] : array_values(array_diff(SiteLocales::SUPPORTED, array_keys($idiomas))),
                    'desfasados' => $desfasados,
                ];
            }
            $tipos[] = ['label' => __('admin.mail_texts.tipos.'.$tipo), 'items' => $items];
        }

        return $tipos;
    }

    /** Guarda los bloques que cambiaron en los tres idiomas: todos o ninguno (ver la cabecera). */
    public function guardar(): void
    {
        if ($this->correo === null) {
            return;
        }

        $estado = $this->form->getState();
        $guardados = $this->cargar();
        $cambios = [];
        $problemas = 0;
        foreach (SiteLocales::SUPPORTED as $locale) {
            foreach (MailTextCatalog::claves($this->correo) as $clave) {
                $texto = trim(str_replace("\r\n", "\n", (string) Arr::get($estado, $locale.'.'.$clave, '')));
                if ($texto === trim((string) Arr::get($guardados, $locale.'.'.$clave, ''))) {
                    continue;
                }
                $fabrica = (string) MailTextCatalog::fabrica($clave, $locale);
                $problema = MailTextRules::problema($texto, $fabrica, $clave);
                if ($problema !== null) {
                    $problemas++;
                    $this->addError('textos.'.$locale.'.'.$clave, $this->motivo($problema));

                    continue;
                }
                $cambios[] = [$clave, $locale, $texto, $fabrica];
            }
        }

        // ⚠️ Un aviso de error dura sus segundos en pantalla: quien arregla y guarda enseguida veía «No se ha guardado nada»
        // encima de «Guardado» (visto en la sonda a 390). El siguiente resultado cierra el anterior.
        $this->cerrarAvisoFallido();
        if ($problemas > 0) {
            $aviso = Notification::make()->title(trans_choice('admin.mail_texts.no_guardado', $problemas, ['count' => $problemas]))->danger();
            $aviso->send();
            $this->avisoFallido = $aviso->getId();

            return;
        }
        if ($cambios === []) {
            Notification::make()->title(__('admin.mail_texts.sin_cambios'))->info()->send();

            return;
        }

        $almacen = app(MailTexts::class);
        $quien = $this->quien();
        DB::transaction(static function () use ($almacen, $cambios, $quien): void {
            foreach ($cambios as [$clave, $locale, $texto, $fabrica]) {
                $almacen->guardar($clave, $locale, $texto, $fabrica, $quien);
            }
        });

        $this->filas = null;
        $this->form->fill($this->cargar());
        $this->vistaHtml = $this->vistaAsunto = $this->vistaAdelanto = null;
        Notification::make()->title(trans_choice('admin.mail_texts.guardado', count($cambios), ['count' => count($cambios)]))->success()->send();
    }

    /**
     * Las situaciones de la vista previa del correo abierto, clave → su nombre (vacío: el correo no tiene textos que dependan
     * de la situación y la página no enseña el desplegable).
     *
     * @return array<string, string>
     */
    public function situaciones(): array
    {
        $suyas = MailSituations::de((string) $this->correo);

        return array_combine($suyas, array_map(static fn (string $s): string => (string) __('admin.mail_texts.situaciones.'.$s), $suyas));
    }

    /** Pinta la vista previa con lo que hay en la pantalla (guardado o no) en el idioma, el tono y la situación elegidos. */
    public function verVista(?string $locale = null, ?bool $oscuro = null, ?string $situacion = null): void
    {
        if ($this->correo === null) {
            return;
        }
        if ($locale !== null && in_array($locale, SiteLocales::SUPPORTED, true)) {
            $this->vistaIdioma = $locale;
        }
        if ($oscuro !== null) {
            $this->vistaOscuro = $oscuro;
        }
        if ($situacion !== null) {
            $this->vistaSituacion = $situacion;
        }
        $this->vistaSituacion = MailSituations::elegida($this->correo, $this->vistaSituacion);

        $estado = $this->textos ?? [];
        $borrador = [];
        foreach (MailTextCatalog::claves($this->correo) as $clave) {
            $texto = trim((string) Arr::get($estado, $this->vistaIdioma.'.'.$clave, ''));
            // Un bloque que no pasa sus reglas no se pinta con el borrador: sale el de fábrica y el campo dice por qué al guardar.
            if ($texto !== '' && MailTextRules::problema($texto, (string) MailTextCatalog::fabrica($clave, $this->vistaIdioma), $clave) === null) {
                $borrador[$clave] = $texto;
            }
        }

        $resultado = MailPreviews::pintar($this->correo, $this->vistaIdioma, $borrador, $this->quien(), $this->vistaOscuro, $this->vistaSituacion);
        AuditLogger::log('emails.text_previewed', null, ['mail' => $this->correo, 'locale' => $this->vistaIdioma, 'situation' => $this->vistaSituacion]);

        $this->vistaHtml = isset($resultado['html']) ? EmailSendTable::inert($resultado['html'], '') : null;
        $this->vistaAsunto = $resultado['asunto'] ?? null;
        $this->vistaAdelanto = $resultado['adelanto'] ?? null;
        $this->vistaMotivo = $resultado['motivo'] ?? null;
    }

    /**
     * Lo que hay GUARDADO para este correo, idioma → clave → texto (el del parque o, sin él, el de fábrica con sus llaves).
     *
     * @return array<string, mixed>
     */
    private function cargar(): array
    {
        $filas = $this->filas();
        $valores = [];
        foreach (SiteLocales::SUPPORTED as $locale) {
            foreach (MailTextCatalog::claves((string) $this->correo) as $clave) {
                Arr::set($valores, $locale.'.'.$clave, $filas[$clave][$locale]
                    ?? MailTextRules::aParque((string) MailTextCatalog::fabrica($clave, $locale)));
            }
        }

        return $valores;
    }

    /** @return array<string, array<string, string>> */
    private function filas(): array
    {
        if ($this->filas !== null) {
            return $this->filas;
        }
        $filas = [];
        foreach (MailText::query()->whereIn('key', MailTextCatalog::claves((string) $this->correo))->get(['key', 'locale', 'text']) as $f) {
            $filas[$f->key][$f->locale] = $f->text;
        }

        return $this->filas = $filas;
    }

    /** @return list<Textarea> un bloque por texto editable del correo, en un idioma */
    private function bloques(string $locale): array
    {
        return array_map(function (string $clave) use ($locale): Textarea {
            $fabrica = (string) MailTextCatalog::fabrica($clave, $locale);
            $corto = MailTextRules::tope($clave) < MailTextRules::TOPE;

            return Textarea::make($locale.'.'.$clave)
                ->label(MailTextCatalog::etiqueta($clave))
                ->rows($corto ? 1 : 3)
                ->autosize()
                ->maxLength(MailTextRules::tope($clave))
                // Al salir del campo, la página se entera: aparece «Volver al de fábrica» y la chapa de su pestaña cuenta el cambio.
                ->live(onBlur: true)
                ->helperText($this->ayuda($fabrica, $clave))
                ->hint(fn (): ?string => $this->estadoDe($clave, $locale))
                ->hintColor(fn (): string => $this->estadoDe($clave, $locale) === __('admin.mail_texts.estado.desfasado') ? 'danger' : 'primary')
                ->hintAction(
                    Action::make('fabrica')
                        ->label(__('admin.mail_texts.volver'))
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        // Solo donde hace algo: en un bloque que ya dice lo de fábrica, el botón era ruido.
                        ->visible(static fn (Textarea $component): bool => trim((string) $component->getState()) !== trim(MailTextRules::aParque($fabrica)))
                        ->action(static fn (Textarea $component) => $component->state(MailTextRules::aParque($fabrica))),
                );
        }, MailTextCatalog::claves((string) $this->correo));
    }

    /**
     * Debajo de cada bloque: CUÁNDO sale, si depende de la situación (R1·T2, `#809`: «Solo sale si…», lo primero, porque es
     * lo que la vista previa puede no enseñar), el de fábrica y sus variables, con lo que significa cada una.
     */
    private function ayuda(string $fabrica, string $clave): string
    {
        $variables = MailTextRules::variablesDeFabrica($fabrica);
        $condicion = MailSituations::condicion($clave);
        $partes = $condicion === null ? [] : [__('admin.mail_texts.solo_si.'.$condicion)];
        $partes[] = __('admin.mail_texts.de_fabrica', ['texto' => MailTextRules::aParque($fabrica)]);
        if ($variables !== []) {
            $partes[] = __('admin.mail_texts.variables_intro').' '.implode(' · ', array_map(
                static fn (string $v): string => '{'.$v.'}'.(Lang::has('admin.mail_texts.variables.'.$v) ? ' '.__('admin.mail_texts.variables.'.$v) : ''),
                $variables,
            ));
        }

        return implode(' — ', $partes);
    }

    /** «Personalizado», «Desfasado» o nada (de fábrica), por lo GUARDADO. */
    private function estadoDe(string $clave, string $locale): ?string
    {
        $fila = $this->filas()[$clave][$locale] ?? null;
        if ($fila === null) {
            return null;
        }

        return $this->alDia($clave, $locale, $fila)
            ? __('admin.mail_texts.estado.propio')
            : __('admin.mail_texts.estado.desfasado');
    }

    /**
     * ¿El texto del parque pasa HOY las reglas contra su texto de fábrica de hoy? Si no, sale el de fábrica: es el mismo filtro
     * que el cargador (`MailTextLoader`), así que lo que la lista llama «desfasado» es exactamente lo que no llega al correo.
     */
    private function alDia(string $clave, string $locale, string $texto): bool
    {
        $fabrica = MailTextCatalog::fabrica($clave, $locale);

        return $fabrica !== null && MailTextRules::problema($texto, $fabrica, $clave) === null;
    }

    /** Cuántos bloques de un idioma difieren de lo guardado (la chapa de su pestaña). */
    private function pendientesDe(string $locale): int
    {
        $guardados = $this->cargar();
        $n = 0;
        foreach (MailTextCatalog::claves((string) $this->correo) as $clave) {
            if (trim((string) Arr::get($this->textos ?? [], $locale.'.'.$clave, '')) !== trim((string) Arr::get($guardados, $locale.'.'.$clave, ''))) {
                $n++;
            }
        }

        return $n;
    }

    /** @param  array{motivo: string, tope?: int, variables?: list<string>}  $problema */
    private function motivo(array $problema): string
    {
        return __('admin.mail_texts.errores.'.$problema['motivo'], [
            'tope' => $problema['tope'] ?? 0,
            // Los nombres de ENLACE (la R2) se escriben tal cual, sin llaves: van entre paréntesis, `[escríbenos](whatsapp)`.
            'variables' => implode(', ', array_map(
                static fn (string $v): string => $problema['motivo'] === 'enlaces' ? $v : '{'.$v.'}',
                $problema['variables'] ?? [],
            )),
        ]);
    }

    /** Cierra en el navegador el último «No se ha guardado nada», si sigue abierto (`x-on:close-notification.window`). */
    private function cerrarAvisoFallido(): void
    {
        if ($this->avisoFallido !== null) {
            $this->dispatch('close-notification', id: $this->avisoFallido);
            $this->avisoFallido = null;
        }
    }

    private function quien(): User
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
