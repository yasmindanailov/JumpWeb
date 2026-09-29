<?php

/*
 * EL PANEL (`docs/specs/panel-a-salvo.md` §4.2, `DECISIONES #850`): su DIRECCIÓN.
 *
 * `admin` por defecto —la del producto, la de las pruebas y la de toda instalación que no diga otra—; en producción, una
 * SECRETA en el `.env` (`PANEL_PATH`), y la guarda 10 de `scripts/deploy.sh` no deja salir con `admin`. Todo lo del
 * personal cuelga de aquí: Filament, sus trece rutas de `routes/web.php` y las exclusiones por ruta de los middleware,
 * siempre a través de `App\Http\PanelPath`. ⚠️ La dirección de producción no vive en el repo.
 */
return [
    'path' => trim((string) env('PANEL_PATH', 'admin'), '/') ?: 'admin',

    /*
     * El authenticator OBLIGATORIO para los administradores (P3, `#851`; lo aplica `RequiresAdminAppAuthentication`).
     * ⚠️ NO sale del `.env` a propósito: apagarlo en producción no puede ser una línea de configuración. La suite lo apaga
     * en `Tests\TestCase` (las pruebas del panel entran como administrador sin app) y `PanelAppAuthenticationTest` lo
     * enciende: es la que lo prueba.
     */
    'admin_mfa' => true,
];
