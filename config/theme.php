<?php

use App\Domain\Content\Services\ThemeFonts;

return [

    /*
    |--------------------------------------------------------------------------
    | Tipografía de la instalación
    |--------------------------------------------------------------------------
    |
    | Las familias que se descargan, en el formato de la URL de Bunny Fonts:
    |
    |     slug-en-minusculas:pesos|otro-slug:pesos
    |
    | El SLUG es el de Bunny (minúsculas y guiones), no el nombre visible. El nombre visible vive
    | en los tokens `--font-display` / `--font-body` / `--font-mono` del CSS, y una instalación lo
    | redefine desde `public/css/client.css`. Son las dos mitades del mismo mecanismo: aquí se
    | dice QUÉ SE DESCARGA, allí QUÉ SE USA. Cambiar solo una deja la otra sin efecto.
    |
    | ⚠️ El HOST no se configura: la CSP (`SecurityHeaders`) permite un único origen de fuentes, y
    | apuntar a otro no daría error — la CSP lo bloquearía en silencio y la web se quedaría sin
    | tipografía. Vive en `Content\Services\ThemeFonts::HOST`.
    |
    | ⚠️ Un valor inválido NO rompe la web: se sirve la lista del producto (defensivo, como el
    | color de marca). Si tu fuente no aparece, revisa el slug — el fallo es silencioso por diseño.
    |
    | Ejemplo para una instalación (segundo cliente):
    |     THEME_FONTS="bungee:400|hanken-grotesk:400,500,600,700,800|jetbrains-mono:400,500,700|permanent-marker:400|lilita-one:400"
    |
    */

    'fonts' => env('THEME_FONTS', ThemeFonts::DEFAULT_FAMILIES),

];
