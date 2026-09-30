<?php

namespace App\Domain\Content;

use App\Domain\Content\Services\MailTextLoader;
use App\Domain\Content\Services\MailTexts;
use App\Notifications\Support\MailTextCatalog;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;

/**
 * Módulo **Content** (Fase 2 — `docs/specs/modulos-dominio.md`): nace con los TEXTOS DE CORREO DEL PARQUE (R1·T de
 * `specs/correos-rediseno.md` §4.2.1, `#802`). Envuelve el cargador de ficheros del traductor con `MailTextLoader`, que
 * superpone a los textos de fábrica lo que el parque escribió en el panel —solo en las claves editables del catálogo de los
 * correos—, así que ninguna notificación cambia para leerlos.
 *
 * ⚠️ `extend()` y no `singleton()`: el cargador de ficheros lo registra el framework (con sus rutas: `lang/` y las de los
 * paquetes) y aquí solo se le pone encima la capa; lo demás que sepa hacer, lo sigue haciendo (`MailTextLoader::__call`).
 */
class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailTexts::class);

        $this->app->extend('translation.loader', static fn (Loader $archivos): Loader => new MailTextLoader(
            $archivos,
            static fn (string $clave): bool => MailTextCatalog::esEditable($clave),
            MailTextCatalog::grupos(),
        ));
    }
}
