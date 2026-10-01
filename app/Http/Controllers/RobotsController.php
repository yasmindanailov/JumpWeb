<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * **El `robots.txt` de la instalación** (`docs/specs/seo.md` §4, S4): deja rastrear todo menos la API y dice DÓNDE está el
 * mapa del sitio, con la URL ENTERA que pide el protocolo (`Sitemap: <url absoluta>`). Era un fichero estático en `public/`,
 * y un fichero no sabe el dominio de cada instalación: por eso lo escribe el producto.
 *
 * ⚠️ **Staging sigue cerrado**: el despliegue escribe ahí su propio `public/robots.txt` con `Disallow: /` (la guarda 4 de
 * `scripts/deploy.sh`, verificada por HTTP), y el servidor sirve un fichero que existe ANTES de llegar a Laravel. Esta
 * ruta solo responde donde no hay fichero: en producción y en local.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $texto = implode("\n", [
            'User-agent: *',
            'Disallow: /api/',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($texto, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
