<?php

use App\Http\Controllers\Prototipos\PortadaController;
use Illuminate\Support\Facades\Route;

/*
 * Prototipos de la fase 2c del diseño (`docs/specs/guion-de-la-portada.md` §6.5). Solo se cargan en
 * local (ver `routes/web.php`). Tres formas de portada + una página de piezas:
 *
 *   /_diseno/portada/a   Conversational FAQ — la portada son las cuatro preguntas
 *   /_diseno/portada/b   Split Studio       — díptico alternado
 *   /_diseno/portada/c   Map / Diagram      — el esquema del parque organiza «qué hay dentro»
 *   /_diseno/piezas      las variantes del selector de zona (D-G4) y del precio por día (D-G5)
 *
 * Parámetros opcionales en las tres portadas: `?zona=altura|tarjetas|pestanas` y
 * `?precio=semana|dos|desde` para ver cada pieza dentro de cada forma.
 */
Route::get('/_diseno/portada/{forma}', [PortadaController::class, 'portada'])
    ->where('forma', '[abc]')->name('prototipo.portada');
Route::get('/_diseno/piezas', [PortadaController::class, 'piezas'])->name('prototipo.piezas');
