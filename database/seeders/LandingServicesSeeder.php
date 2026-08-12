<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder DEDICADO Y QUIRÚRGICO de los servicios de /servicios (editorial + tablas de tarifas).
 *
 * Toca SOLO la tabla `landing_services` (idempotente, `updateOrCreate` por slug). NO resiembra
 * FAQs, normas, ajustes ni ningún otro contenido → seguro de ejecutar en una instalación ya en
 * marcha (p. ej. PRODUCCIÓN tras el deploy, que migra pero NO siembra) sin pisar ediciones live:
 *
 *     php artisan db:seed --class='Database\Seeders\LandingServicesSeeder' --force
 *
 * Reutiliza la FUENTE ÚNICA `LandingContentSeeder::seedLandingServices()` (sin duplicar datos), que
 * también invoca el seeder de contenido completo en instalaciones nuevas.
 */
class LandingServicesSeeder extends Seeder
{
    public function run(): void
    {
        (new LandingContentSeeder)->seedLandingServices();
    }
}
