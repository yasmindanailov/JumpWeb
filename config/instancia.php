<?php

/*
|--------------------------------------------------------------------------
| El PAQUETE DE INSTANCIA
|--------------------------------------------------------------------------
|
| La landing de una instalación no vive en este repo: vive en su propio repo
| (`instancia-<slug>`) y el producto la renderiza desde aquí — la «vía B» de
| `docs/specs/paquete-de-instancia.md` §4.1.
|
| ⚠️⚠️ ESTO ES UNA RUTA DESDE LA QUE EL SERVIDOR EJECUTA CÓDIGO. Blade compila
| a PHP, así que un directorio de vistas es un directorio de código y quien
| pueda escribir ahí puede ejecutar aquí. Es el invariante `SEC-12`, y de él
| salen las tres reglas que aplica `InstanceViews`:
|
|   1. sale de CONFIGURACIÓN (este fichero, por `.env`) y jamás de una
|      petición — ni parámetro, ni cabecera, ni host;
|   2. vive FUERA del árbol del producto: bajo `public/` el servidor
|      entregaría el `.blade.php` en crudo, y en cualquier otro sitio del
|      árbol el `rsync --delete` del despliegue se lo llevaría en el próximo
|      despliegue, dejando la instalación sin landing.
|
| Vacío = no hay paquete. NO es un error: el producto sirve su anfitrión
| mínimo y arranca igual. Una instalación recién montada está justo así.
|
*/

return [

    /*
     * Ruta ABSOLUTA a la raíz del paquete de instancia. Las vistas se buscan
     * en su subcarpeta `web/`.
     *
     * ⚠️ Absoluta a propósito: una ruta relativa se resolvería contra el
     * directorio de trabajo del proceso, que no es el mismo en `php artisan`,
     * en php-fpm y en la cola — tres landings distintas según quién renderice.
     */
    'ruta' => env('INSTANCIA_RUTA'),

];
