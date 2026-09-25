<?php

namespace App\Console\Commands;

use App\Domain\Content\Services\CopiedReviewImport;
use Illuminate\Console\Command;

/**
 * **Importa las reseñas COPIADAS de la ficha de Google** (`DECISIONES #771`) desde el JSON de
 * `scripts/resenas-google.mjs`: cada una, como opinión del panel con la marca de Google, sus imágenes en casa y
 * APAGADA hasta que el parque la publique en una página (Ajustes → Web → Opiniones).
 *
 *     php artisan reviews:import storage/app/resenas/ficha.json
 *
 * Reimportar actualiza lo que viene de Google y conserva lo que decidió el parque ({@see CopiedReviewImport}).
 */
class ImportCopiedReviews extends Command
{
    protected $signature = 'reviews:import {fichero : el JSON de la herramienta de copia}';

    protected $description = 'Importa las reseñas copiadas de la ficha de Google como opiniones del panel (apagadas, sin páginas).';

    public function handle(CopiedReviewImport $importador): int
    {
        $fichero = (string) $this->argument('fichero');
        $json = is_file($fichero) ? json_decode((string) file_get_contents($fichero), true) : null;

        if (! is_array($json)) {
            $this->error("No se puede leer «{$fichero}» como JSON.");

            return self::FAILURE;
        }

        $cuenta = $importador->import($json);

        $this->line(sprintf(
            'Nuevas %d · actualizadas %d · sin texto (no se importan) %d · imágenes traídas %d.',
            $cuenta['nuevas'], $cuenta['actualizadas'], $cuenta['sin_texto'], $cuenta['imagenes'],
        ));

        return self::SUCCESS;
    }
}
