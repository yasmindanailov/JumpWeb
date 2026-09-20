<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Subidas del panel (ofertas #270, `docs/PLAN-OFERTAS-WIDGET.md` §4). Van DIRECTO bajo
        // public/ (servidas de forma nativa por el web server) para NO depender del symlink
        // `public/storage` (que está roto en prod). El deploy excluye `/public/uploads` del
        // `rsync --delete` (deploy-prod.sh) para no borrar lo que suba la clienta; y `.gitignore`
        // ignora `/public/uploads` para no versionar las subidas de prueba en local.
        'uploads' => [
            'driver' => 'local',
            'root' => public_path('uploads'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/uploads',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Las imágenes de las reseñas de Google (T2·4 de `specs/google-business-profile.md` §4.3·6,
        // `DECISIONES #730`): la foto del autor y las fotos que adjuntó a su reseña.
        //
        // ⚠️⚠️ **FUERA de `public/` a propósito, y por dos motivos distintos.** (1) El fichero se
        // borra **en la misma operación que su fila** —purga, reemplazo, «Ocultar», desconectar— y
        // eso solo se puede prometer si nadie más lo sirve por detrás; bajo `public/` lo serviría el
        // servidor web aunque la fila ya no exista. (2) Lo que se sirve son bytes que nos dio un
        // TERCERO: van por una ruta nuestra que les pone su propia CSP (`default-src 'none';
        // sandbox`) y `nosniff`, y eso bajo `public/` no se puede poner.
        //
        // ⚠️ **Sin `url` y sin `serve`**: no se enlaza nunca directamente. La única puerta es
        // `ReviewPhotoController`, que valida el nombre contra el hash y no deja pasar una ruta.
        // ⚠️ **Sin `visibility: public`**: no es una preferencia, es lo que hace que `ServeFile` de
        // Laravel —si algún día alguien pusiera `serve`— siguiera exigiendo firma.
        'google-reviews' => [
            'driver' => 'local',
            'root' => storage_path('app/private/google-reviews'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
