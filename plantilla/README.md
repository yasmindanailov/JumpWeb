# Plantilla de INSTANCIA

Este directorio es **del producto** y es un molde: de aquí sale `instancia-<slug>`, el repo de un cliente
(`docs/specs/paquete-de-instancia.md` §4.3, `DECISIONES #647`). Lo que salga de él ya **no** es del producto.

> **La regla que ordena todo** (`#610`): lo que sería distinto para un segundo cliente es de la INSTANCIA;
> lo que es igual para todos, del PRODUCTO. Nada de un cliente en `main`; nada de código de producto aquí.

## Qué hay dentro

    web/            la LANDING, a mano. El producto la renderiza desde aquí (el namespace `instancia::`)
    tema/           el paquete de tema: `client.css`, logotipo, icono, kit de símbolos
    config/         la configuración de la instalación que no es secreta
    datos/          semillas del catálogo del cliente (nunca usuarios, pedidos, pagos ni secretos)
    docs/           la doc de ESTA instalación, con su propio prefijo de decisiones
    instancia.json  el contrato: qué versión del producto espera este paquete
    instalar.sh     lo que hay que correr en la instalación

## Cómo se estrena

1. Se copia este directorio a `instancia-<slug>/` **fuera** del árbol del producto y se hace su primer commit.
2. Se rellena `instancia.json` (el `slug`, y el `contrato` ya viene puesto).
3. En el `.env` de la instalación: `INSTANCIA_RUTA=/ruta/absoluta/a/instancia-<slug>`.
4. `php artisan optimize:clear` y listo: lo que haya en `web/` manda sobre el respaldo del producto.

## Las dos reglas que no se rompen

⚠️⚠️ **`INSTANCIA_RUTA` apunta FUERA del árbol del producto** (`SEC-12`). No es manía de orden:

- Blade **compila a PHP y lo ejecuta**, así que `web/` es un directorio de código. Dentro de `public/`, el
  servidor web entregaría el `.blade.php` **en crudo**, con lo que lleve dentro.
- En cualquier otro sitio del árbol del producto, el `rsync --delete` del despliegue **se lo lleva**: la
  instalación se queda sin landing en el siguiente despliegue y nadie sabe por qué.

⚠️ **Aquí no se copia código del producto.** Las vistas de `web/` pueden usar los componentes que el producto
publica (`<x-layout>`, `<x-site.nav>`…), pero no se traen copias de ellos: copiarlos convierte cada
actualización del producto en una divergencia silenciosa.

## Si no hay paquete

No pasa nada: el producto no registra el namespace y sirve su respaldo. Una instalación recién montada está
justo así, y por eso no hay ningún paso obligatorio que hacer antes de levantarla.
