# Entornos — dónde corre esto y con qué reglas

> Estado: vivo · Creado el 2026-08-15 (`DECISIONES #76`) ·
> Se invalida si: cambia el proveedor de hosting, el dominio de pruebas o el canal de despliegue.

Hay **dos** entornos y ninguno es producción. El de trabajo es LOCAL; el remoto existe solo para lo
que **necesita una URL pública** y no se puede ver en local.

| Entorno | Dónde | Para qué | Datos |
|---|---|---|---|
| **Local** | Docker (Sail) en WSL2, `localhost:8081` | **El bucle de trabajo entero**: código, suite, gate | Semilla de dev |
| **Staging** | `https://jumpweb.sites.aelium.app` | Solo lo que exige URL pública o navegador real | Semilla NEUTRA |

## 1 · Staging: qué es y qué NO es

⚠️ **0 LIVE · 0 PRODUCCIÓN.** No hay clientes, no hay dinero real, no hay datos de personas reales.
Es un banco de pruebas del PRODUCTO, no la instalación de nadie. Si algún día existe una instalación
real de un cliente, será otra cosa distinta y con otras reglas (`INSTALACION-CLIENTE.md`).

- **Infra propia** del owner, gestionada desde el panel **enhanceCP**.
- **Acceso**: `ssh jumpweb-staging` (alias configurado en `~/.ssh/config`, clave dedicada
  `~/.ssh/jumpweb_staging_ed25519`). Solo por CLAVE — **verificado el 2026-08-15**.
  ⚠️ **Ningún secreto vive en este repo**: ni contraseñas, ni claves privadas, ni `.env`.
- **El repo NO despliega solo**: no hay `.github`, no hay webhook. El despliegue es explícito (§4).

## 2 · Las SEIS guardas, y por qué cada una

Ninguna es teórica: todas salen de algo que este código hace hoy.

1. ⚠️ **Redsys se queda en `test`.** El entorno lo decide un `Setting` (`redsys_environment`, cuyo
   default es `test`). Un staging con `live` **cobra de verdad, con tarjetas de verdad**. Es el único
   fallo de esta lista que cuesta dinero.
2. ⚠️ **Nunca un volcado del cliente ORIGEN.** Sembrar con `ProductionSeeder` —la semilla neutra de
   negocio ficticio, que además **aborta si ya hay pedidos**— y, si hace falta contenido,
   `LandingContentSeeder`. Un dump de producción traería **nombres, edades y alergias de menores** a un
   servidor de pruebas: es el riesgo RGPD más caro que tiene este producto (`INVARIANTES` §3).
3. ⚠️ **El correo no sale.** `MAIL_MAILER=log` o un buzón trampa. Los seeds llevan direcciones con
   pinta de reales y los avisos de pedido son `ShouldQueue`: con SMTP real, se envían.
4. **No indexable.** Un staging indexado compite en Google con el sitio del cliente que se instale
   mañana. La vía es el **`robots.txt` con `Disallow: /`**, que es responsabilidad del DESPLIEGUE, no
   del producto (§4, `#102(d)`): el del repo dice `Disallow:` (vacío = permitir todo) a propósito,
   así que **cada `rsync` la tumba si el script no la repone y la verifica por HTTP**.
   ⚠️⚠️ **[DECIDIDO 2026-08-19] La autenticación básica GLOBAL queda PROHIBIDA**, y antes esta guarda
   la recomendaba («mejor, autenticación básica delante»). Medido: `/pago/redsys/notificacion` **no
   lleva auth** —no puede llevarla, es una S2S máquina-a-máquina— así que un basic-auth sobre `/`
   devuelve **401 a Redsys** y el pedido caduca **con la tarjeta ya cobrada** (`PAY-02`). Es decir:
   rompería exactamente la verificación por la que este servidor existe (bloque B del e2e). Si algún
   día se pone, **debe excluir `pago/redsys/*` y `up`**.
5. **`APP_URL` = el dominio real, con HTTPS.** Si no coincide se rompen A LA VEZ los enlaces absolutos
   de los correos, las URLs firmadas, la derivación de CORS y los dominios *stateful* de Sanctum. Ya
   mordió en local (`ESTADO.md` § entorno).
6. **`QUEUE_CONNECTION=database` y worker vivo.** Con `sync`, los correos y avisos se procesan en la
   petición y el comportamiento deja de parecerse al real.

## 3 · Lo que staging DESBLOQUEA (y lo que no cambia)

**Desbloquea**, porque necesitan URL pública o navegador real:
- **4.4b·2 · el widget de Turnstile**: Cloudflare emite las claves contra un **hostname**.
  ⚠️ **Las claves de Turnstile se leen SOLO de `settings` (BD), no de `.env`** — verificado en
  `Platform\Services\Turnstile`. Van por el panel de admin. Es una contradicción ya declarada en
  `INSTALACION-CLIENTE.md` §3, y aquí es la forma práctica de configurarlo.
- **La notificación S2S de Redsys** (`redsys_merchant_url`): es el «bloque B» de
  `VERIFICACION-E2E-CAJON.md`, que hasta hoy exigía un túnel. Sin ella, un terminal *data-less* deja el
  pedido caducando con la tarjeta cobrada (`PAY-02`).
- **3DS con challenge** y **móvil real**, los otros dos caminos que el e2e dejó declarados.

⚠️ **Lo que NO cambia: `DECISIONES #62` no se reabre.** Retiró la condición «dejar el flag en `spa` en
uso real unos días» **por vacía**, y sigue siéndolo: un staging **no tiene tráfico**. Lo que ordena la
retirada de `Purchase.php` sigue siendo el CONTADOR de `PurchaseRetirementTest`, no el calendario.
Quien lea «ya hay servidor» y deduzca «esperemos a que ruede» está reintroduciendo un bloqueo que ya se
midió como imposible de cumplir.

## 4 · La máquina, MEDIDA (2026-08-15, acceso por clave)

Inventario en solo lectura, para no volver a suponerlo:

| | Staging | Local | ¿Importa? |
|---|---|---|---|
| SO | Ubuntu 24.04.4 LTS | contenedor Sail | no |
| PHP (CLI por defecto) | ✅ **8.5.1** (subido el 2026-08-19; era 8.3.29) | **8.5.9** | ✓ el lock exige `>=8.4.1` — ver abajo |
| PHP disponibles en panel | 8.0 … **8.5.1** (`/opt/ecp-php85/bin/php`) | — | **se puede igualar a local** |
| Base de datos | **MariaDB 11.4.10** | **MySQL 8.4.11** | ⚠️ **sí — ver abajo** |
| Composer | 2.9.5 | — | ✓ |
| **node / npm** | **NO ESTÁN** | sí | ⚠️ **sí — ver abajo** |
| git · rsync · unzip | sí | — | ✓ |
| HOME | `/var/www/<uuid>` | — | rutas con UUID, no con el nombre |
| Document root | ✅ **`~/public_html/public`** (cambiado el 2026-08-16, `#102`) | — | apunta ya al `public/` de Laravel |
| `robots.txt` | `Disallow: /` en el nuevo docroot | — | ⚠️ **NO es de fábrica**: el del repo permite indexar (`#102`) |
| Base de datos | ✅ **`jumpweb_1_test`** (2026-08-16) | — | usuario propio con `ALL PRIVILEGES`; verificada conectando |
| Panel: API | `https://cp.hosturbo.net/api` · `Bearer` + `orgId` | — | **sin OpenAPI publicado** (`#102`) |
| Usuario Unix | ✅ **`jumpweb_1`** (uid/gid 1051) — medido 2026-08-19 | — | el `rsync` entra con él; **no hace falta `chown`** |
| Raíz de la app | ✅ **`~/public_html/`** (`$HOME` = `/var/www/c4bf5527-126c-4fe3-9086-7f346458a4fd`) | — | destino del `rsync`; el docroot es su `public/` |
| Crontab del sitio | ✅ **VACÍO** (medido 2026-08-19) | — | el scheduler se instala de cero, sin riesgo de pisar nada |
| rsync · git · unzip · crontab · mysql | ✅ todos en PATH | — | `deploy.sh` no necesita instalar nada |
| Disco | 423 GB libres de 467 GB | — | holgado (el árbol + `vendor` ≈ 200 MB) |

⚠️ **TRES diferencias con local que condicionan el procedimiento, y ninguna es cosmética:**

0. ✅ **[RESUELTO 2026-08-19] El PHP del sitio ya es 8.5.1** —el owner lo subió por el panel, y está
   **verificado por SSH**: `php -v` → `PHP 8.5.1 (cli)`, y `which php` → `/usr/bin/php`, o sea que el
   binario por defecto del sitio (el que usarán `composer`, `artisan` y el cron) es ya el bueno.
   ⚠️ **Se deja escrito el porqué, que no era obvio y volverá a serlo al instalar un cliente**: hasta
   hoy esta tabla decía «cumple `composer.json` (`^8.3`)»: cierto sobre `composer.json`, **falso sobre
   lo que se instala**. El requisito efectivo lo fija el LOCK, no el `.json`, y está medido:
   `composer why-not php 8.3.29` → **17 paquetes de producción** (todo `symfony/*` 8.1) exigen
   `php >=8.4.1`. No hay `config.platform` en `composer.json` que lo amortigüe, y
   `--ignore-platform-reqs` **no es opción** (Symfony 8.1 usa sintaxis de 8.4). Es decir:
   **`composer install --no-dev` aborta en el sitio tal y como se aprovisionó** (`#102(a)`, PHP 8.3).
   ▶ **Consecuencia con orden obligatorio, del mismo tipo que `#102(c)`: subir el sitio a PHP 8.5 por
   la API del panel ANTES del primer despliegue** — hecho. La alternativa —fijar
   `config.platform.php=8.3` y re-resolver el lock— se **descartó**: degradaría el lock del PRODUCTO
   para acomodar un servidor de pruebas.
   ▶ **Requisito para `INSTALACION-CLIENTE.md`: el hosting de un cliente necesita PHP ≥ 8.4.1**, no el
   `^8.3` de `composer.json`. Comprobarlo ANTES de vender/instalar, con `composer check-platform-reqs`.
1. **La BD es MariaDB, no MySQL.** Los invariantes de dinero y aforo de este proyecto —`AFORO-01`, el
   lock de franjas— y **los dos verificadores de concurrencia** se han validado sobre **MySQL 8.4**.
   MariaDB no es un drop-in para razonar sobre locks. **Consecuencia que hay que escribir donde nadie
   la pierda: «verificado en staging» NO equivale a «verificado en MySQL».** Para Turnstile, S2S, 3DS y
   móvil da igual —no dependen del motor—, pero ninguna conclusión sobre concurrencia sale de ahí.
2. **No hay node ni npm.** `npm run build` y `build:ssr` **no se pueden ejecutar en el servidor**, así
   que los assets se construyen fuera y se suben ya compilados. Eso convierte el despliegue en
   «construir + sincronizar», no en «clonar y compilar» — y hay que decidirlo así en el procedimiento,
   no descubrirlo a mitad.

### El APROVISIONAMIENTO, ya medido (2026-08-16, `DECISIONES #102`)

✅ **Hecho**: BD `jumpweb_1_test` + su usuario · sitio en **PHP 8.3** · `documentRoot` →
`public_html/public` · `robots.txt` con `Disallow: /` sirviéndose y verificado por HTTP.

**Lo que enseñó, y no está en la documentación de enhance:**

1. ⚠️ **Desde el sitio NO se puede aprovisionar, y es correcto.** `appinit` es PID 1: cada sitio es un
   contenedor. Su usuario de BD tiene `USAGE` **a nivel global**, así que `CREATE DATABASE` da
   `ERROR 1044`. El plano de control vive fuera: automatizar el aprovisionamiento exige la **API del
   panel**, no la shell del sitio.
   ⚠️ **[CORRECCIÓN MEDIDA 2026-08-19] `#102(b)` decía que `jumpweb_1` «no es el usuario de BD del
   sitio». Eso era cierto ANTES de crear la base, y hoy es FALSO** — `SHOW GRANTS` lo dice sin
   ambigüedad: `GRANT USAGE ON *.*` **+ `GRANT ALL PRIVILEGES ON jumpweb_1_test.*`**. Al crear la BD
   por el panel, el panel le concedió todo sobre ella; es el ÚNICO usuario con acceso (`SHOW DATABASES`
   → solo `information_schema` y `jumpweb_1_test`).
   ▶ **Pero la razón para NO usarlo en el `.env` sigue en pie, y es otra: el propio `.my.cnf` avisa de
   que «the password may be rotated from time to time».** Una rotación del panel dejaría el sitio sin
   BD **sin avisar y sin que ningún test lo cace**. Es decir: la conclusión de `#102(b)` era correcta y
   su motivo no. Quien lea «no tiene permisos» perderá el tiempo buscando otro usuario; el peligro real
   es la ROTACIÓN.
   ▶ Para una instalación de CLIENTE: usuario de BD dedicado, creado por el panel, con su contraseña en
   el vault. Para staging es admisible usar `jumpweb_1` a sabiendas de que una rotación se arregla
   releyendo `.my.cnf` y reescribiendo el `.env`.
2. ⚠️⚠️ **Crear el directorio destino ANTES de cambiar el `documentRoot`.** Enhance **no valida que
   exista**: si no está, regenera el vhost apuntando a la nada, el sitio cae al vhost por defecto de
   LiteSpeed —con su certificado, que parece un fallo de TLS y no lo es— y **no avisa**. Medido con un
   A/B: volver al docroot viejo lo revive; repetir el cambio con el directorio ya creado entra limpio.
3. **El certificado NO se pierde** en el proceso: era el vhost el que no cargaba.
4. **La API del panel no publica OpenAPI.** `/swagger/v1/swagger.json` devuelve `200` **con HTML**
   —es una SPA y responde 200 a cualquier ruta—, así que ese verde no significa nada. Los endpoints
   se descubren y se verifican uno a uno.

**Endpoints verificados** (`Authorization: Bearer <token>`, base `https://cp.hosturbo.net/api`):
`GET orgs/{org}` · `GET orgs/{org}/websites` · `GET orgs/{org}/websites/{ws}` ·
`GET|PATCH orgs/{org}/websites/{ws}/domains[/{domainId}]` → el `PATCH` de `documentRoot` responde `204`.

> ✅ **[HECHO 2026-08-19, `DECISIONES #105`] El despliegue es `scripts/deploy.sh`**, y con él se cierra
> el `[DECISION-PENDIENTE]` de `INSTALACION-CLIENTE.md` §1. **DRY-RUN por defecto**: sin `--go` no toca
> el servidor. Hace, en este orden: `down` → drenar cola → `rsync` (con sus exclusiones) → reponer
> `robots.txt` → `composer install --no-dev` → `migrate --force` → [`--seed`] → `slots:generate-rolling`
> → [`--admin-email`] → `optimize` → cron → `up` → **salud**.
> ⚠️ **`storage:link` NO va** (lo prohíbe `INSTALACION-CLIENTE.md` §1 y el código lo confirma: 0 usos
> del disco `public`), y **`public/uploads` se excluye del `--delete`** o el segundo despliegue borra
> las subidas del panel.
> Lo guarda `DeployScriptGateTest` (23 casos, 10 mutaciones muertas), incluido un caso que **deriva el
> suelo de PHP del `composer.lock`** para que el desfase de `#103(f)` no pueda repetirse.
>
> ⚠️ **Y el `robots.txt` es responsabilidad del DESPLIEGUE, no del producto**: el del repo dice
> `Disallow:` (vacío = permitir todo) porque la instalación de un cliente **debe** indexarse. El
> primer `rsync` tumbaría la guarda 4 si el script no lo reescribe **y lo verifica por HTTP**.
> ✅ **Confirmado en las DOS puntas el 2026-08-19**: `~/public_html/public/robots.txt` sirve hoy
> `Disallow: /` (26 B) y el del repo es permisivo (25 B) — o sea que el `rsync` **sí** lo pisa. Ojo a
> la trampa: **los dos ficheros pesan casi igual**, así que comprobar el tamaño no distingue uno de
> otro; hay que comprobar el CONTENIDO, y por HTTP. (Hay además un `~/public_html/robots.txt` de 25 B
> con la guarda, resto de cuando el docroot era `public_html`: hoy **no se sirve** y es inocuo.)

**El principio que sí está decidido**: staging se levanta con el MISMO procedimiento que levantaría la
instalación de un cliente. Si se configura a mano deja de ser una prueba del producto y pasa a ser un
*snowflake*: lo que funcione ahí no demuestra nada sobre lo que instalará el siguiente.

## 5 · Reglas de trabajo con los dos entornos

- **El bucle es LOCAL.** Staging no entra en el ciclo de desarrollo: desplegar en cada cambio lo
  ralentiza sin comprar nada.
- **Staging se toca EN BLOQUE**, en sesiones de verificación con guion escrito —Turnstile, S2S, 3DS y
  móvil juntos—, no a goteo.
- ⚠️ **Staging NO es CI.** El gate sigue siendo el `pre-push` local, con sus seis pasos. «Funciona en
  staging» **no sustituye a la suite**: `#59` demostró justo lo contrario, con la fase entera en verde
  y el motor sin vender.
- **Todo hallazgo vuelve al repo**: como TEST si se puede testear; si no se puede (navegador, pasarela,
  móvil), como **receta escrita** con sus trampas medidas — el patrón de `VERIFICACION-E2E-CAJON.md`,
  que ya demostró que sirve.
- **Lo que el owner tiene que hacer se documenta igual**, aunque lo ejecute un agente: este repo es
  agent-first y el siguiente agente no puede adivinar qué se tocó en un panel.
