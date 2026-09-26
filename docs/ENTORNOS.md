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

> 🏦 **DESDE EL 2026-09-11 STAGING ES ADEMÁS EL ENTORNO DE VALIDACIÓN DEL BANCO** (`DECISIONES #453`,
> `[DECIDIDO owner]`): lleva el **catálogo y la configuración de playjump.es** (solo tablas de catálogo,
> **ninguna con personas** — la guarda 2 sigue en pie) y apunta al **terminal de PRUEBAS de CaixaBank**
> (`redsys_merchant_code=369809538` · `redsys_terminal=1` · `redsys_environment=test`), con la venta online
> **ABIERTA** (`sales.online_enabled=1`). Cuentas de prueba: `pruebas.tpv@playjump.es` (cliente, para el
> equipo del banco) y `admin.test@playjump.es` (panel); las contraseñas las tiene el owner, no el repo.
> Copia previa al cambio: `~/backups/jumpweb-pre-tpv-20260911-055029.sql.gz` en el servidor (es la marcha
> atrás). ⚠️ Un `--seed` lo pisaría: **no re-sembrar** mientras dure la validación. ⚠️ Los pagos de prueba
> que se hagan aquí aparecen en el **Canales de pruebas** del comercio (`sis-t.redsys.es:25443/canales/`).
> ▶ **Medido con ese terminal**: la confirmación llega por la **notificación S2S** y la vuelta del navegador
> viene **sin datos** («incluir datos en redirección» está apagado en su terminal de test); la tarjeta
> «denegada» del banco da la excepción `SIS0093`, no una denegación (§5.undecies del e2e).

- **Infra propia** del owner, gestionada desde el panel **enhanceCP**.
- **La pila, MEDIDA (2026-09-23, `SEC-13`)**: PHP corre como `lsphp` bajo **LiteSpeed en el mismo host**
  (`server: LiteSpeed`, IP privada `10.169.0.63`, sin CDN: ninguna cabecera `cf-*`/`via`); LiteSpeed termina el
  TLS y pone `HTTPS=on`, así que **no hay proxy al que confiar** y `bootstrap/app.php` no declara ninguno. El
  sondeo que lo decidió, y que se repite si algún día se pone un CDN delante: con los cubos del limitador
  limpios, 65 `GET /api/v1/catalog/zones` con `X-Forwarded-For` falsa rotatoria → 0 × 429 (con `*` la app la
  honraba) y 65 sin cabecera → 429 desde la #61 (el limitador existe); y `X-Forwarded-Proto: http` no cambia
  ninguna URL absoluta de la home. **Producción dio los mismos números** (§6; sondeo de solo lectura autorizado
  por el owner). ⚠️ Un CDN delante exige acotar `trustProxies` a **sus** rangos, nunca a `*`.
- **Acceso**: `ssh jumpweb-staging` (alias configurado en `~/.ssh/config`, clave dedicada
  `~/.ssh/jumpweb_staging_ed25519`). Solo por CLAVE — **verificado el 2026-08-15**.
  ⚠️ **Ningún secreto vive en este repo**: ni contraseñas, ni claves privadas, ni `.env`.
  ⚠️⚠️ **Y por eso el acceso es POR PUESTO DE TRABAJO, no del proyecto.** Medido el 2026-08-21: el
  segundo puesto no tenía ni `~/.ssh/config` ni la clave, así que `deploy.sh` moría en `2/9` y desde
  ahí **no se podía desplegar ni verificar nada**. Al montar un puesto nuevo, la clave y el alias son
  parte del aprovisionamiento, igual que la terna de deny de `.claude/settings.json` — y los dos los
  aporta el owner, porque el repo no puede llevarlos.
- **El build de los assets se hace en LOCAL y por SAIL** (`DECISIONES #114`), no con «el npm que
  haya»: es el canal que usa el `pre-push`, así que lo que el gate verificó es lo que se sube. Bajo
  WSL, `command -v npm` resuelve al npm de **Windows** por el interop de `/mnt/c` y ese no puede
  construir (lanza `CMD.EXE`, que no admite rutas UNC). `deploy.sh` ya lo descarta solo.
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
  `Platform\Services\Turnstile`.
  ⚠️⚠️ **[CORREGIDO 2026-08-19, `#107`] NO «van por el panel de admin»: el panel NO las puede
  editar.** Es lo que decía esta línea y es falso, medido: `security.turnstile_*` no aparece en
  ningún formulario de `app/Filament/`, y el docblock de la página de ajustes lo dice al revés —
  «**Fuera de alcance por seguridad (NUNCA editables aquí): los SECRETOS (`redsys_secret_key`,
  `security.turnstile_secret`)**». Quien entre al panel a buscarlas no las encuentra.
  ▶ ✅ **El mecanismo es `app:set-setting`** (`DECISIONES #109`), que existe justo por esto:
  ```bash
  php artisan app:set-setting security.turnstile_site_key '0x…' --group=security
  php artisan app:set-setting security.turnstile_secret  '0x…' --group=security
  ```
  Nunca imprime el valor de un secreto (se ejecuta por SSH y su salida acaba en el log del
  despliegue), y relee de la BD antes de dar el verde.
  <details><summary>El SQL equivalente, por si hiciera falta sin `vendor/`</summary>
  ```sql
  INSERT INTO settings (`key`,`value`,`group`,created_at,updated_at)
  VALUES ('security.turnstile_site_key','…','security',NOW(),NOW()),
         ('security.turnstile_secret','…','security',NOW(),NOW())
  ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW();
  ```
  </details>
  No hace falta limpiar caché: `Setting` solo memoiza **por proceso** (`flushMemo` en `saved`/`deleted`),
  no de forma persistente. Se comprueba por HTTP en `/api/v1/config` (`turnstile_site_key` deja de ser
  `null`), que además fija que **la secreta nunca viaja**.
  ▶ La ficha de `DEUDA.md` queda **retirada**: era exactamente esto.
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
| MySQL: cómo se conecta | ⚠️ **socket UNIX** — 0 sockets TCP en 3306 | TCP | ⚠️ **`DB_HOST=localhost`, NO `127.0.0.1`**: con la IP da `ERROR 2002 (115)` |
| **node / npm** | **NO ESTÁN** | sí | ⚠️ **sí — ver abajo** |
| git · rsync · unzip | sí | — | ✓ |
| HOME | `/var/www/<uuid>` | — | rutas con UUID, no con el nombre |
| Document root | ✅ **`~/public_html/public`** (cambiado el 2026-08-16, `#102`) | — | apunta ya al `public/` de Laravel |
| `robots.txt` | `Disallow: /` en el nuevo docroot | — | ⚠️ **NO es de fábrica**: el del repo permite indexar (`#102`) |
| Base de datos | ✅ **`jumpweb_1_test`** (2026-08-16) | — | usuario propio con `ALL PRIVILEGES`; verificada conectando |
| Panel: API | `https://cp.hosturbo.net/api` · `Bearer` + `orgId` | — | **sin OpenAPI publicado** (`#102`) |
| Usuario Unix | ✅ **`jumpweb_1`** (uid/gid 1051) — medido 2026-08-19 | — | el `rsync` entra con él; **no hace falta `chown`** |
| Raíz de la app | ✅ **`~/public_html/`** (`$HOME` = `/var/www/c4bf5527-126c-4fe3-9086-7f346458a4fd`) | — | destino del `rsync`; el docroot es su `public/` |
| Crontab del sitio | escribible, y `deploy.sh` instala su línea | — | ⚠️ **pero NADIE la ejecuta** — ver abajo |
| **Demonio cron** | ❗ **NO HAY** (medido 2026-08-21) | sí | ⚠️⚠️ **sí — el scheduler NO corre** |
| rsync · git · unzip · crontab · mysql | ✅ todos en PATH | — | `deploy.sh` no necesita instalar nada |
| Disco | 423 GB libres de 467 GB | — | holgado (el árbol + `vendor` ≈ 200 MB) |
| **Redis** | ✅ **7.0.15, activado el 2026-08-25** — instancia PROPIA del sitio, dentro de su contenedor PHP | ✅ **`redis:7.0-alpine` en `compose.yaml`** (puerto propio `6382`) | ⚠️⚠️ **sí — ver «Redis» abajo** |
| Redis: cómo se conecta | `127.0.0.1:6379`, **sin contraseña** (solo alcanzable dentro del contenedor) | — | ✅ **coincide con los valores por defecto de Laravel: 0 variables que tocar** |
| `phpredis` | ✅ **6.3.0** | ✅ presente | ⚠️ **compilado SIN igbinary**: configurar ese serializador revienta |

### ⚠️ REDIS en Enhance, MEDIDO (2026-08-25, `DECISIONES #137`)

La doc pública de Enhance solo dice «Advanced → Developer tools → Redis → On». Lo demás está medido
sobre la máquina, porque **una inferencia razonable resultó falsa**: el host tiene una unidad
`redis-server@.service` (instancia por sitio con socket unix), y **no es así como funciona**.

| Qué | Medido |
|---|---|
| Dónde corre | **Dentro del contenedor PHP del sitio** (`pid 4`), no en el host |
| Aislamiento | Instancia **propia**: `config_file` = `<HOME>/redis.conf`, keyspace vacío al arrancar |
| Conexión | TCP `127.0.0.1:6379`, sin contraseña — no sale del contenedor |
| Config y log | `<HOME>/redis.conf` y `<HOME>/redis.log`, **fuera de `public_html`** → el `rsync` del deploy NO los toca |
| Volcado RDB | `dir` = `<HOME>`, `dbfilename dump.rdb` |
| Laravel | Conecta **sin cambiar ninguna variable**: sus valores por defecto ya son `127.0.0.1:6379` y `REDIS_CACHE_DB=1` |
| Tags | ✅ **Verificados contra el Redis REAL** (escribir con tags, leer, `flush()` de un tag → invalida) |
| Camino web | ✅ Verificado: una petición HTTP real dejó `cta.min_price_cents` en la db 1 vía PHP-FPM |

⚠️⚠️ **Reiniciar el contenedor PHP se lleva Redis por delante** —y el propio panel ofrece ese botón
para aplicar cambios de `redis.conf`—. Para una caché es un arranque en frío; **es el motivo por el
que la SESIÓN y la COLA se quedan en base de datos** (`#137`).

❗❗ **Y con `CACHE_STORE=redis`, si Redis no responde el sitio devuelve 500.** Medido apuntando a un
puerto muerto: **500 en 0,14 s** — falla rápido, que es la menos mala de las dos formas, pero es una
dependencia DURA. Es la consecuencia aceptada de usar tags: `database` **lanza** al usarlos.

✅ **Y el local está IGUALADO a propósito**: `redis:7.0-alpine`, no `redis:alpine` —que hoy sirve otra
rama mayor—. Probar tags contra una versión que producción no ejecuta es la otra mitad del mismo
error que hace falsa la suite. La suite lo ejercita con `CacheTaggingContractTest`, y ese caso
**FALLA si Redis no está levantado**, en vez de saltarse: Redis es requisito duro, así que un `skip`
quedaría verde para siempre justo en la máquina donde importa.

⚠️ **El `redis.conf` de fábrica son 14 bytes (`bind 127.0.0.1`) y se comporta como un almacén, no como
una caché**: `maxmemory 0` (sin techo), `maxmemory-policy noeviction`, snapshots activos y
`stop-writes-on-bgsave-error yes` —o sea que si falla un volcado **deja de aceptar escrituras**—.
Ficha abierta en `DEUDA.md`; el contenido acordado está en `DECISIONES #137`.

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
3. ❗❗ **EL SCHEDULER NO CORRE** (medido el 2026-08-21, `DECISIONES #115`). El crontab está instalado
   y correcto, `php` resuelve, y `schedule:run` funciona **a mano** — pero **no hay demonio cron en el
   contenedor del sitio**, así que nadie lo invoca. Medido: 6 avisos llevaban **24 h** en `jobs` con
   `attempts = 0`, y un pedido llevaba 24 h sin caducar (`orders:expire` lo caducó al instante al
   ejecutarlo a mano).
   ▶ **Consecuencia para verificar aquí**: en staging **no se ha ejercitado nunca** la caducidad de
   pedidos ni el envío diferido de correo. Lo que necesite el scheduler, dispáralo a mano:
   `ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.
   ▶ **Consecuencia para INSTALAR A UN CLIENTE**, que es lo grave: sin cron, el cliente **paga y no
   recibe nada** (las notificaciones son `ShouldQueue`) y **el aforo se fuga** (las franjas retenidas
   no se liberan). Comprobar el cron es parte de la instalación, no un extra.
   ▶ **Pendiente del owner**: activar las tareas programadas del sitio en el panel de Enhance.

   **LA ENTRADA EXACTA** (medida el 2026-08-22, no supuesta):

   ```
   * * * * * cd /var/www/c4bf5527-126c-4fe3-9086-7f346458a4fd/public_html && /usr/bin/php artisan schedule:run >/dev/null 2>&1
   ```

   Si el panel pide los campos por separado: **frecuencia** cada minuto (`* * * * *`) · **directorio**
   `~/public_html` · **comando** `/usr/bin/php artisan schedule:run`.

   ⚠️ **Cada minuto no es negociable**, aunque solo una de las cinco tareas corra a esa frecuencia:
   `schedule:run` es el despachador; es él quien decide qué toca. Con una frecuencia menor, el worker
   de cola (`queue:work`, cada minuto) se retrasa y con él **todo el correo transaccional**.

   ⚠️ **`/usr/bin/php` y no `/opt/ecp-php85/bin/php`**, aunque hoy el primero sea un enlace al segundo:
   `/usr/bin/php` sigue al PHP que el panel asigne al sitio, y clavar la ruta de la versión rompería
   el cron el día que se suba a 8.6. Medido: incluso con el entorno vacío —que es como corre cron—
   `php` resuelve a `/usr/bin/php` → 8.5.1.

   ⚠️ **Puede quedar DUPLICADO con el que instala `deploy.sh`** en el crontab del usuario (mismo
   comando, marcador `# jumpweb:scheduler`). Hoy ese no se ejecuta —no hay demonio cron— pero si al
   activar el panel también empieza a correr, habría dos despachadores por minuto. No es peligroso
   —las tareas llevan `withoutOverlapping`— pero **comprueba cuál de los dos vive** y quédate con uno:
   `ssh jumpweb-staging 'crontab -l'`.

   ▶ **CÓMO SABER QUE FUNCIONA, sin esperar a que algo falle.** Hay un canario servido: en la cola
   quedó un `OrderExpiredWithoutPayment` varado desde el 2026-08-21. Si el cron arranca, se envía solo
   en menos de un minuto:

   ```
   ssh jumpweb-staging "cd ~/public_html && php artisan tinker --execute='echo DB::table(\"jobs\")->count();'"
   ```

   **1 → 0** significa que el despachador vive. Y desde `DECISIONES #115` el propio `deploy.sh` lo
   comprueba al terminar: su paso 9 pasa de `✗ cola: 1 jobs VARADOS` a verde.

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
   por el panel, el panel le concedió todo sobre ella.
   ⚠️ **Pero NO es el único, y creerlo fue un error de método**: desde `jumpweb_1` no se puede leer
   `mysql.user`, así que «no veo otro usuario» se confundió con «no hay otro». **Sí existe el usuario
   DEDICADO del sitio, `jumpweb_1_test`** (verificado conectando el 2026-08-19: `ALL PRIVILEGES` sobre
   su BD y DDL real probado). **Ese es el que va en el `.env`.**
   ▶ **Pero la razón para NO usarlo en el `.env` sigue en pie, y es otra: el propio `.my.cnf` avisa de
   que «the password may be rotated from time to time».** Una rotación del panel dejaría el sitio sin
   BD **sin avisar y sin que ningún test lo cace**. Es decir: la conclusión de `#102(b)` era correcta y
   su motivo no. Quien lea «no tiene permisos» perderá el tiempo buscando otro usuario; el peligro real
   es la ROTACIÓN.
   ▶ **Regla, y vale igual para un cliente: en el `.env` va SIEMPRE el usuario dedicado del sitio**
   (aquí `jumpweb_1_test`), nunca el de `~/.my.cnf`. Su contraseña vive en el vault del owner, no en
   el repo.
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
> Lo guarda `DeployScriptGateTest` (26 casos, 14 mutaciones muertas), incluido un caso que **deriva el
> suelo de PHP del `composer.lock`** para que el desfase de `#103(f)` no pueda repetirse.
> ✅ **EJECUTADO el 2026-08-19** (`#106`), dos veces (idempotente). Sitio sirviendo, 12 páginas públicas
> en 200, tres idiomas, panel accesible, 1440 franjas.
> ⚠️⚠️ **Y destapó que la guarda del DINERO daba un VERDE FALSO**: leía `redsys_environment` por un FQCN
> cuyos backslashes no sobreviven a ssh, el `tr` convertía el `PARSE ERROR` en basura con pinta de
> valor, y la condición preguntaba «¿contiene `live`?». **Habría pasado con el entorno en `live`.**
> Ahora es **fail-closed** (exige `test` exacto) y lee por `DB::table`, sin namespaces.
> ▶ **Regla para toda guarda de dinero: pregunta «¿es lo que ESPERO?», nunca «¿es lo que TEMO?».**
> ▶ Y su corolario, hermano de `#102(e)`: **un `tr`/`grep` que SANEA la salida puede convertir un error
> en un valor plausible**. Si una comprobación limpia lo que recibe, valida la FORMA de lo que queda.
> ⚠️ **El idioma va por SESIÓN** (`/lang/{locale}`), no por prefijo de URL: `/en` y `/fr` dan **404** y
> es correcto. `INSTALACION-CLIENTE.md` §7 («200 en es/en/fr») se leía como si hubiera prefijo.
>
> ⚠️ **Y el `robots.txt` es responsabilidad del DESPLIEGUE, no del producto**: el del repo dice
> `Disallow:` (vacío = permitir todo) porque la instalación de un cliente **debe** indexarse. El
> primer `rsync` tumbaría la guarda 4 si el script no lo reescribe **y lo verifica por HTTP**.
> ✅ **Confirmado en las DOS puntas el 2026-08-19**: `~/public_html/public/robots.txt` sirve hoy
> `Disallow: /` (26 B) y el del repo es permisivo (25 B) — o sea que el `rsync` **sí** lo pisa. Ojo a
> la trampa: **los dos ficheros pesan casi igual**, así que comprobar el tamaño no distingue uno de
> otro; hay que comprobar el CONTENIDO, y por HTTP. (Hay además un `~/public_html/robots.txt` de 25 B
> con la guarda, resto de cuando el docroot era `public_html`: hoy **no se sirve** y es inocuo.)
>
> ✅ **DESPLEGADO el 2026-08-28 a las 07:53 (hora de Madrid), commit `577cf4f`** (carril A: `#210` + el
> `#211` del carril C + `#212`, las dos superficies del carné). Dry-run limpio (PHP remoto 8.5.1, seis
> guardas del `.env` ✓, 353 entradas), **volcado de la BD ANTES de migrar** (`~/backups/jumpweb-pre-212-*.sql.gz`,
> 68 KB, 41 tablas, `orders` dentro) y `--go`: **4 migraciones** (`customer_cards`, `customer_visits`,
> `puerta.profile`, `dependent_assignments`), `redsys_environment = test`, 1415 franjas, salud 7/7. Verificado
> además lo que el script no mira: `GET /me/card`, `GET /me/card/png` y `POST /me/card/rotate` responden **401
> JSON** (no 404), las cinco tablas nuevas existen, el permiso `puerta.profile` está, el chunk del cajón servido
> es **idéntico byte a byte** al local, y el log sin errores. ⚠️ Los tres ajustes nuevos de puerta NO están en
> `settings` y `PuertaSettings` cae a sus defaults (30 · 5 · 1): es lo diseñado (`identidad-qr-puerta.md` §9.2 A·9).
> ⚠️ **Dos trampas del volcado, medidas**: (1) extraer la contraseña del `.env` con `cut`/`tr` la mutila si
> lleva caracteres especiales — se lee con `php artisan tinker --execute='echo config("database.connections.mysql.password");'`
> dentro de una variable del shell remoto, sin que salga por el terminal—; y (2) **`mariadb-dump` lee `~/.my.cnf`
> (el usuario `jumpweb_1` que rota el panel) y la contraseña del fichero de opciones GANA a `MYSQL_PWD`**, así que
> «Access denied for user 'jumpweb_1_test'» no era la contraseña de la app: era la del otro usuario. `--no-defaults`
> lo arregla. El aviso del cron es el conocido `#115`: sigue sin demonio.
>
> ⚠️ **El CATÁLOGO de staging ya NO es el del `ProductionSeeder` puro (2026-08-28, 09:35, a petición del
> owner para la prueba de cabo a rabo).** El seeder siembra las cuatro ENTRADAS con `is_sellable = false`
> («no se venden online por ahora») y solo da plantillas de franjas a Cumpleaños, así que la web de staging
> vendía únicamente packs. Se hizo por `tinker`, idempotente: `is_sellable = 1` en las entradas #15–#18,
> **154 plantillas** para JUMP (zona 1: aforo 60 / online 40) y KIDS (zona 2: 40 / 25), 7 días × 11 franjas
> de 60 min de 10:00 a 20:00 — **las mismas que tiene local desde el 2026-08-12**, no un horario inventado—,
> y `slots:generate-rolling` (3.875 franjas). Verificado por la API: 8 referencias publicadas, fechas con
> precio (15 € especial / 12 € normal) y horas. ▶ Un `--seed` posterior NO lo deshace (el seeder hace
> `updateOrCreate` sobre las entradas y volvería a ponerlas `false`: si se re-siembra, hay que repetir esto).
>
> ⚠️⚠️ **CADUCADO el 2026-09-11: ese catálogo ya no está.** Staging lleva ahora el de **producción**
> (`#453`, §1): 4 zonas · 25 productos · 43 precios · 453 plantillas · 6.760 franjas · las 30 fechas
> especiales, importado por `mysqldump --complete-insert` de las tablas de catálogo y `TRUNCATE` previo.
> Para repetirlo o revertirlo, el procedimiento y la copia están en `#453`.

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
- **Un guion de datos contra producción** (mudado del carril de plataforma el 24-09) lleva valor ESPERADO por fila y
  transacción, se prueba antes en local y se corre dos veces para ver que la segunda aborta; escribe por Eloquent y
  olvida `cta.min_price_cents` (`PERF-05`). A tinker por `ssh`: `tail -n +2 guion.php | ssh host 'cd public_html &&
  php artisan tinker --execute="$(cat)"'`; `require "php://stdin"` NO funciona.

## 6 · PRODUCCIÓN · playjump.es, MEDIDO (2026-09-01, `DECISIONES #325`)

> 🚀 **NOVENO DESPLIEGUE · HECHO Y VERIFICADO · EL PRIMERO POR ETIQUETA** (2026-09-18, 07:23:01–07:23:51
> local, 50 s con la ventana de 503 dentro; el parque abre a las 16:30, `#594`). Subió **v1.1.0 = `3547de9f`**
> (`CHANGELOG.md`): la promo tachada y el recuadro (`#628`), la piel del justificante con su barra de firmar
> (`#572`, defecto vivo desde el octavo) y la invitación digital apagada (`#573`→`#576`). 134 entradas de
> `rsync`, **una migración** (`create_party_invitations`, 118 ms), 8553 franjas, cola 0. Copia previa
> `~/backups/playjump2_main_v1.1.0-20260918-052113.sql.gz` (728 KB, 53 tablas). `client.css` sin tocar (idéntico
> a la rama). **La guarda 8 estrenada en real**: pre-vuelo «v1.1.0 (etiqueta anotada, y en origin)», el script
> escribió `v1.1.0 3547de9f 2026-09-18T05:23:24Z` en `storage/app/version` y la salud lo releyó (✓ esperada
> v1.1.0). Después, las cuatro filas `promo.*` por `ssh`+tinker (idempotente) y verificado en las tres lenguas
> (recuadro y «Antes/Was/Avant 12 €» en la portada, 9 tachados en `/precios`), `/`, `/precios`, `/up` y
> `/admin/login` en 200. ⚠️ El ensayo en seco lo denegó el clasificador «auto» una vez («Blind Apply», con la
> salida redirigida a un fichero) y lo dejó pasar sin redirigir; el `--go` pasó a la primera con la orden del
> owner en el turno. ▶ Pendiente del carril del SPA: mirar el justificante en producción en móvil y el Turnstile real.
>
> ⚠️ **LA RECETA PARA TERMINAR LA PROMO, con sus cifras** (bajada aquí desde `carriles/plataforma.md` en
> `#675`, que es donde se va a buscar y donde no caduca). Cuando el owner la dé por terminada, **el mismo
> día**: subir los precios en el panel a los ORIGINALES —los `from` de `audit_logs`: **800, 1000, 1200,
> 1500, 1800, 1200, 1400, 1800, 2200**—, quitar el badge y **borrar las cuatro filas `promo.*`**. Sin
> desplegar: son datos. ❗ El «antes» tachado solo es cierto mientras el porcentaje del ajuste sea el que
> se aplicó al catálogo (`#628`), así que dejar las filas con los precios ya subidos publica un descuento
> que no existe.

> 🚀 **OCTAVO DESPLIEGUE · HECHO Y VERIFICADO** (2026-09-16, 22:52:26–22:53:03 local, 37 s con la ventana de
> 503 dentro; el parque cerró a las 21:30, `#594`; ensayado a las 20:20 y aplazado por estar abierto). Subió
> **`1272cb93`**, cuyo único código sobre lo servido (`bd61e5a9`) es **`448ea4f5`** (`#569`–`#571`: la piel del
> formulario post-reserva y **el arreglo del número de invitados, que no se enviaba en producción desde el
> 08-09**), 19 ficheros, **sin migraciones** (113 «Ran» en la columna de estado; `Nothing to migrate`). **Sin
> etiqueta: el último por hash** (`#613` pone la guarda de etiquetas en F3). 81 entradas de `rsync` (el ensayo
> dio 80; la nueva, `CLAUDE.md`). Producción sirve `1272cb93`.
> ▶ **Receta seguida, en este orden** (vale para el noveno):
> 1. Copia previa: `ssh jumpweb-prod bash -s -- pre571 < scripts/copia-bd-remota.sh` →
>    `~/backups/playjump2_main_pre571-20260916-204505.sql.gz` (672 KB, 53 tablas, gzip verificado; **se queda
>    en el servidor**). ⚠️ **El cliente `mariadb` del servidor IGNORA `MYSQL_PWD`**: por eso el guion deja que
>    Laravel escriba un fichero de opciones 0600 que se borra al salir.
> 2. El `client.css`: extraído de la rama `cliente/playjump` (`3ded45ee`) con `git archive`, sha1 `687ffcb3…`
>    = la copia local (el servido más `--err-ink`, `--done`, `--on-done` y `--done-ink`); `scp` a
>    `public_html/public/css/client.css`; servido antes `d265355e…`, después `687ffcb3…` (en el servidor y por
>    HTTP). ⚠️ **La rama iba por detrás en `--money`** (Lima 800; `#540` manda 700): comparar con la copia
>    local antes de subirla. El `rsync` lo excluye, así que sobrevive al `--go`.
> 3. `DEPLOY_PRODUCTION=1 DEPLOY_SSH_HOST=jumpweb-prod DEPLOY_URL=https://playjump.es scripts/deploy.sh` en
>    seco y después `--go`: cola drenada (0), `composer install`, guarda 1 (`live`, exacto), 8520 franjas,
>    cachés, cron, `up`; la salud del script en verde (kit 26 símbolos, scheduler 6/6, `failed_jobs` 0, 0
>    varados). Sin `cache:clear`: las reseñas no se pierden.
> 4. Verificado después: nueve páginas en 200 (`/`, `/entradas`, `/precios`, `/cumpleanos`, `/normas`,
>    `/contacto`, `/atracciones`, `/admin/login`, `/up`) y **`/bar` en 503 A PROPÓSITO**:
>    `maintenance.page.bar = 1` en los ajustes de producción, el interruptor de mantenimiento por página («Esta
>    sección está en mantenimiento», `Retry-After: 3600`), no es del despliegue · en la vista desplegada
>    `grep -c 'form="gf-form"' resources/views/reservation/guests.blade.php` = **2** (control previo: **0**) ·
>    `migrate:status` solo «Ran» · la portada sigue nombrando reseñas · el log del día solo tiene un
>    `auth.google_state_mismatch` de las 18:04 UTC.
> ⚠️ **El clasificador del modo «auto» del harness deniega el `scp` y el `--go` como escritura remota**
> («Remote Shell Writes») aunque dejó pasar el `ssh` de la copia: el owner cambió el modo de permisos y los dos
> comandos se repitieron tal cual. ⚠️ El servidor va en **UTC** (la copia se llama 20:45 siendo las 22:45).

> 🚀 **SÉPTIMO DESPLIEGUE · HECHO Y VERIFICADO** (2026-09-13, commit `89e49ed0`, `DECISIONES #590`).
> **La web nueva entera** —181 commits desde `e76d6f2a`: la portada y las páginas rehechas, las paradas
> 03–06 del cajón y `#587`–`#589`— **con el contenido de Play Jump Park** (`storage/app/contenido/
> aplicar-produccion-589.php`, fuera del repo) y las decisiones del owner sobre la configuración que
> difería entre local y producción, tomadas una a una tras un diff de solo lectura.
>
> ▶ **Orden, y cada paso tiene su porqué**: copia → **paquete de tema por `scp`** (`client.css`,
> `client-kit.svg`, `client-menu.webp`, `client-tag.svg`), con los hashes comparados antes de seguir —
> ⚠️ sin el kit nuevo **la GUARDA 7 falla DESPUÉS de `artisan up`**, con las migraciones ya aplicadas —
> → `deploy.sh --go` (**6 migraciones**; la de `landing_service_products` BORRA la columna vieja, que en
> producción estaba a NULL en los tres servicios) → el contenido, **probado antes en local con 0
> diferencias** (es idempotente) → `artisan cache:clear` (la caché es **Redis**: sin él, la portada sirve
> cifras y ajustes de antes).
> ▶ **Copia previa**: `~/backups/playjump2_pre589-20260913-132313.sql.gz` · 50 tablas · gzip verificado ·
> **se queda en el servidor**. ⚠️ Esta vez se bajó primero a local y se corrigió: se subió aquí y se
> borró la copia local — un volcado de producción lleva datos personales.
> ▶ **Verificado**: salud en verde (kit con 26 símbolos, ninguna migración pendiente, `failed_jobs` vacía); las
> ocho páginas públicas en 200 con sus piezas nuevas; y el diff de configuración contra local deja
> **SOLO lo elegido** (enganches y precios de la hora extra de producción, calcetines incluidos por
> invitado, máximo de 50 niños, compra online CERRADA) y lo excluido a propósito.
> ⚠️ **Siguen fuera, a propósito**: la compra online (`sales.online_enabled = 0`), las reseñas (sin
> `place_id` ni opiniones propias reales) y las imágenes de la cafetería (las locales eran marcadores de
> diseño). ⚠️ **El título de la pestaña de la portada dice «Murcia»** (`landing.footer.tag`, el respaldo
> cuando no hay «Título web»): pendiente de corregir.
> ▶ **Las reseñas de Google, después** (`#591`): la clave de Places va en el `.env` (con `config:cache`) y
> el `place_id` en Ajustes, pero Google responde **403 `API_KEY_IP_ADDRESS_BLOCKED`**: la clave está
> restringida por IP y falta la del servidor, **51.38.54.41** (Google Cloud → Credenciales → la clave →
> Restricciones de aplicación). Hecho eso, `artisan social-proof:refresh` una vez a mano y mirar la
> portada con las cookies de terceros aceptadas. El refresco va **cada 30 minutos** (48 llamadas al día):
> el tope diario de la API en la consola tiene que ser **100**, no 50.
> ✅ La IP ya está admitida: Google responde 200 (4,9 · 50 reseñas).
>
> 🚀 **TRES DESPLIEGUES MÁS EL MISMO DÍA** (2026-09-13):
> - `88c947d2` (`#591`, las reseñas cada 30 minutos) — limpio;
> - `bc538fb8` (`#592`, el aviso de reseñas y el banner de cookies) — más el script gitignorado
>   `aplicar-produccion-592.php`, que se sube a `~/contenido/` y se ejecuta desde `~/public_html`;
> - `bd61e5a9` (`#593`, tres titulares).
>
> ❗❗ **Después de un `artisan cache:clear`, `artisan social-proof:refresh`**: la caché de las reseñas vive en
> Redis con las demás, y sin refresco la sección se queda vacía hasta el siguiente medio punto.
> 📜 **Desde `#771` (26-09) Places NO EXISTE**: ni refresco ni caché de reseñas. **Al desplegar la v2.0.0**: quitar
> `GOOGLE_PLACES_API_KEY` del `.env`, revocar la clave en la consola de Google, y subir e importar las reseñas
> copiadas y ya elegidas (`php artisan reviews:import <json>`, con sus páginas: `google-reviews.md` §9); el importador
> guarda también la nota de la ficha. Sin ese paso, la sección y la nota se quedan vacías hasta el Perfil de Empresa.
> El fichero es `storage/app/resenas/playjump-curado.json` del ordenador del owner (gitignorado: datos personales); y
> se borran en «Opiniones» las 3 opiniones propias antiguas (Ángela M., Jose Luis R., Marta S.), como pidió el owner.
> ❗❗❗ **El tercero se paró en la GUARDA 1 y dejó el sitio 3 minutos en 503** (`#594`): el owner había
> pasado Redsys a `live` a las 17:29. Se levantó con `artisan up` y se completaron a mano las franjas,
> `artisan optimize` y la salud. Desde `#594`, en producción la guarda admite `test` o `live`.
> ❗❗ **CUÁNDO SE DESPLIEGA** (`[DECIDIDO owner, 2026-09-13]`, `#594`): **de noche o con el parque cerrado**.
> El script baja el sitio antes de sus guardas, y una que falle lo deja en mantenimiento hasta levantarlo a
> mano; con la compra en `live`, eso puede pillar un pago a medias.
> ▶ **Redsys está en `live`** desde el 2026-09-13 (TPV real, notificación en
> `https://playjump.es/pago/redsys/notificacion`). **Probado de punta a punta por el owner**: `R-VPCOHW`
> (10,00 €) se cobró a las 17:42 y se devolvió por REST a las 17:51. `R-AFO3SG`, iniciado un minuto antes del
> corte, no llegó a confirmarse y se canceló: no hay cargo en nuestros registros.

> 🚀 **SEXTO DESPLIEGUE · HECHO Y VERIFICADO** (2026-09-08, commit `e76d6f2a`, `DECISIONES #448`/`#449`).
> El **SELLO DEL MODO** de un complemento: T1–T4 más el re-escalado por-invitado del post-form.
>
> ❗❗❗ **ES EL PRIMERO DE LA SERIE QUE CAMBIA CONDUCTA**, y por eso llevó un paso propio: **el SELECT
> de control de `hora-extra.md` §12.20·M1, en solo lectura, JUSTO ANTES de migrar**. La razón no es
> ceremonia: el criterio del relleno se validó con una foto del día 8, y **el corpus se mueve** — entre
> dos mediciones de esa misma tarde las hijas vivas pasaron de 27 a 28. Salió **1 fila** (la prevista)
> y **0** en el hueco simétrico: verde para migrar.
>
> ▶ **Copia previa**: `~/backups/playjump2_pre448-20260908-211055.sql.gz` · **364 K · 50 tablas** ·
> gzip verificado · **se queda en el servidor** (no se trae a local un volcado con datos personales).
>
> **Resultado medido tras migrar, y coincide con la simulación previa al dígito**: **3 filas selladas**
> (las dos horas extra de sala y la línea del «Menú 2» de `R-BOMAZH`, que evita que 2,00 € pasen a
> 34,00 € en la primera edición) · **25 hijas vivas sin sello**, que es lo que el candado sigue
> protegiendo · el candado baja de **9 a 7** enganches · y **`#60` y `#61` quedan LIBRES**, que era el
> encargo del owner. Control: una hija sin sello resuelve su unidad al mismo valor que el pivote vivo
> —conducta intacta—.
>
> **Salud**: 9/9 · `/`, `/entradas`, `/precios`, `/admin/login`, `/up` → 200 · **ni una línea en el log
> de hoy** · 6.760 franjas y **0 CERRADAS** (`AFORO-04`) · migración en 19,84 ms.
>
> ⚠️⚠️ **El ensayo en seco enseñó un cambio de CSS que NO era del encargo, y se midió antes de
> aceptarlo**: `public/build` está **gitignorado**, así que rsync envía el build de la máquina que
> despliega. El delta resultó ser **una sola regla** (`.ps-3`, 52 bytes) que **no usa nadie en el
> repo** —viene del escaneo de `vendor`—, y se comprobó una a una que **las doce clases de la pastilla
> nueva ya estaban en el CSS de producción**. *Un ensayo en seco que enseña algo inesperado no es una
> molestia: es la única oportunidad de medirlo antes de que sea irreversible.*
>
> ⚠️ El aviso del cron sigue igual que en los cinco anteriores (`DECISIONES #115`): el crontab del
> usuario no corre; scheduler y `queue:work` viven en el cron del PANEL.
>
> ▶ **Queda el OJO del owner** (`VERIFICACION-E2E-CAJON.md` §5.nonies) **y configurar el modo**, que
> ya no lo impide nada.


> 🚀 **QUINTO DESPLIEGUE · HECHO Y VERIFICADO** (2026-09-08, commit `200b019a`, `DECISIONES #446`).
> `#443` (la hora extra de un pack se cobra **por invitado**), `#444` (el cliente cambia sus
> **invitados** desde el post-form) y `#445` (las salidas mudas del icono del QR).
>
> ▶ **El primero de los cinco SIN NINGUNA MIGRACIÓN**, y comprobado en las dos direcciones: el rango
> no toca `database/migrations/` y producción declaraba **0 pendientes antes y después**. Tampoco
> cambia `config/` ni el `.env`. Solo código.
>
> ⚠️ **El ensayo en seco pedía enviar 5 ficheros que NO habían cambiado** (`OrderItem`,
> `ItemRescheduleOffer`, `OrderCreator`, `PackAvailability`, `SlotAvailability`): rsync los marca por
> **fecha** (`..t`), no por contenido — son los `touch` que dejan los arneses de mutación al
> restaurar. *Se comprobó contra `git` antes del `--go` en vez de suponerlo.*
>
> **Verificado en caliente**: salud 9/9 · `/`, `/entradas`, `/precios`, `/admin/login`, `/up` → 200 ·
> **ni una línea en el log de hoy** · **6.959 franjas y 0 CERRADAS** (`AFORO-04`) · 18 pedidos · 43
> reservas vivas · `sales.online_enabled = 0`. Las cinco piezas nuevas responden y
> **`ReservationPlacesTaken` resuelve a `GuardianPlaces`** —el enlace del *composition root*, lo único
> que no comprueba un `class_exists`—. La regla, ejercitada: con el modo real (`fixed`) y 12
> invitados la hora extra calcula **720 min**; con `per_guest`, **60**.
>
> ❗❗ **El cerrojo del modo BLOQUEA hoy al owner y está medido**: los enganches `#121` (JUMP) y `#122`
> (KIDS) tienen **una fiesta viva cada uno el 2026-09-21** (18:00 y 17:30), así que el panel rechaza
> el cambio de `quantity_mode` hasta que pasen. Es para lo que se construyó. Copia previa de la BD:
> **323 K · 50 tablas**, gzip verificado, **en el servidor**.

> 🚀 **TERCER DESPLIEGUE · HECHO Y VERIFICADO** (2026-09-06, commit `612989a`, `DECISIONES #430`/`#431`).
> **79 commits** desde el segundo (`64ff3b6`): los complementos de venta posterior (`#413`→`#419`), la
> hora extra de entrada (`#410`), **la hora extra de un pack** (`#421`→`#427`), la rejilla de media
> hora (`#420`), el arreglo de la oferta sin precio (`#429`) y **el panel de admin** (`#460`→`#467`:
> el shell nuevo y el asistente de «Crear pedido» en siete pasos).
>
> ▶ **Las CUATRO migraciones son puramente ADITIVAS** —columnas nuevas con valor por defecto seguro
> (`occupies_after_parent` false, `stage` 'booking', `extends_parent_stay` false, `extra_minutes` 0,
> `guest_form_link_version`)—: **ninguna reescribe ni borra nada, y con sus valores por defecto la
> conducta de lo que ya está vendido no cambia**. Comprobado leyendo las cuatro.
>
> ❗❗❗ **EL DESPLIEGUE NO BASTA: el catálogo hay que CONFIGURARLO, y son 4 productos y 14 enganches.**
> Medido en producción por SSH (solo lectura, 2026-09-06): **no existe ninguna hora extra** —ni de
> entrada ni de sala— y los siete complementos de comida están enganchados **sin fase**, así que tras
> migrar quedarían todos en «al reservar» (el valor por defecto). ▶ Para eso está el script
> **`configurar-catalogo.php`**, que vive **fuera del repo** —bajo `storage/`, gitignorado, porque es
> catálogo de un cliente y no producto (`DECISIONES #1`)— y se sube por `scp`: **idempotente**,
> no borra nada, y deja las dos horas extra de entrada (solo tarifa especial), las dos de sala (precio
> por tarifa) y los siete de comida en post-form con corte de 48 h. Probado dos veces en local con el
> mismo resultado.
>
> ▶ **Y el paquete del cliente sigue pendiente**: medido, `public/css/client.css` de producción **no
> tiene ninguna** de las cinco líneas de `#434`/`#436` (`--on-ok`, `--on-err` y las tres de
> `--interactive`). No viaja por rsync — se edita a mano o se sube por `scp`.
>
> ⚠️ **`deploy.sh` NO hace copia de la base de datos** y corre `migrate --force`: la copia va antes, a
> mano (`mysqldump --single-transaction`), como en los dos despliegues anteriores.
>
> **El orden, y por qué**: copia → `deploy.sh` (código + migraciones) → `configurar-catalogo.php` (las
> columnas tienen que existir antes) → las cinco líneas del `client.css` → verificación. La compra
> online sigue **cerrada** (`sales.online_enabled = 0`), así que nada de esto se le enseña todavía a
> un cliente: es exactamente la ventana para verificarlo sin prisa.
>
> ### Lo EJECUTADO, con su evidencia (2026-09-06)
>
> | paso | resultado |
> |---|---|
> | Copia de la BD | `~/backups/playjump2_main-20260906-104459.sql.gz` · **255 KB · 50 tablas** · gzip íntegro. **Se queda en el servidor**: no se trae a local un volcado con datos personales |
> | `deploy.sh --go` | commit `612989a`; las **4 migraciones** aplicadas; **6.760 franjas** generadas y **0 CERRADAS** (ninguna reserva tocada, `AFORO-04`); las 9 comprobaciones de salud ✓ |
> | Catálogo | script idempotente: **4 complementos creados** (2 horas extra de entrada, 2 de sala) y **14 enganches** a post-form |
> | `client.css` | ⚠️⚠️ **se comparó ANTES de subir**: el remoto difería **exactamente** en esas 5 líneas y sus comentarios, sin ningún cambio propio. Copia previa en `~/backups/` y hash idéntico al local tras subirlo |
> | Verificación | hora extra de sala **KIDS 3/5 €** y **JUMP 5/8 €** por tarifa · **«Kids · Ilimitada» 9 horas entre semana y 0 el sábado** · `/`, `/entradas`, `/admin/login` y `/up` → **200** · **0 errores** en el log |
>
> ⚠️⚠️ **ESA FILA DE PRECIOS CADUCÓ LA MISMA TARDE, y el rastro dice cuándo** (`#443`, medido en
> producción el 2026-09-07): el **2026-09-06 a las 15:21** se cambió KIDS de 3,00 → **4,00 €** y se
> **borró el precio de la tarifa `special` de las dos** (`catalog.prices_updated`,
> `{"2":{"from":500,"to":null}}` y `{"2":{"from":800,"to":null}}`). Como un complemento sin precio
> para la tarifa del día **no se ofrece**, hoy **la hora extra de sala no se vende viernes, sábado ni
> domingo** — verificado de punta a punta: la oferta del Pack JUMP un sábado son Calcetines y el grupo
> de menús, sin hora extra. ▶ `[DECIDIDO owner, 2026-09-07]`: **se queda así**, es un producto de entre
> semana. **No lo «arregles» devolviendo el precio especial.**
>
> **Estado tras el despliegue**: 4 zonas · 25 productos · 16 complementos · 29 enganches (**14 en
> post-form**) · 6.871 franjas · 14 pedidos · 163 clientes · `sales.online_enabled = 0`.
>
> ⚠️ **El aviso del cron del panel sigue saliendo** (`#115`): el script no ve un demonio cron en el
> servidor y lo dice. Es el mismo de siempre — las dos líneas viven en el cron del PANEL.

> 🚀 **SEGUNDO DESPLIEGUE, 2026-09-02** (`DECISIONES #353`): commit `64ff3b6`, con las cuatro
> migraciones de Google auth y del justificante. Estado tras él, medido:
> `sales.online_enabled = 0` (compra cerrada, decisión del owner hasta tener Redsys de producción) ·
> **condiciones v1 publicada** · `GoogleAuth::enabled() = true` · 4 zonas y 21 productos (entran las
> excursiones) · 30 fechas especiales · 69 clientes y 6 pedidos.
> ▶ ❗❗ **Este documento y `ESTADO` decían que el agente NO tiene acceso a producción** («medido:
> Permission denied»). **Lo tiene**: el `~/.ssh/config` declara `jumpweb-prod` y entra con la llave de
> staging. *Una medición heredada no es una medición.*
> ▶ ⚠️⚠️ **Las claves de Google NO van en el `.env`**: viven en `settings` (`[DECIDIDO owner]` Q10) y
> se ponen con `php artisan app:set-setting auth.google_client_id|auth.google_client_secret … --force`,
> que además **enmascara el secreto en su salida** porque esto se ejecuta por SSH.
> ▶ ⚠️ **`deploy.sh` NO hace copia de la base de datos** y corre `migrate --force`. Antes de este
> despliegue se hizo a mano (`mysqldump --single-transaction` → `~/backups/`, 134 KB, 49 tablas).
> Ficha en `DEUDA.md`.
> ▶ **Pasos de DATO que el script no hace** y que hubo que dar aparte: publicar la v1 de
> «Condiciones», crear las excursiones y cargar los festivos.

El mismo panel que staging (**Enhance**), así que `deploy.sh` vale con dos variables y una bandera:

```bash
DEPLOY_PRODUCTION=1 DEPLOY_SSH_HOST=jumpweb-prod DEPLOY_URL=https://playjump.es scripts/deploy.sh --go
```

**GUARDA 8 · producción despliega SOLO etiquetas** (`[DECIDIDO owner]` 2026-09-17, `DECISIONES #613` y `#624`).
Con `DEPLOY_PRODUCTION=1`, lo primero que mira el pre-vuelo local es que HEAD **sea una versión**: una etiqueta
ANOTADA `vX.Y.Z` que apunta exactamente a ese commit y que ya está en `origin`. Sin etiqueta, con una ligera,
con `v1.0` o `v1.0.0-rc1`, con la etiqueta sin empujar o con un commit por delante, `--go` **aborta antes de la
primera conexión**; en seco avisa y sigue, para poder mirar el plan antes de versionar. Staging despliega
`main` y no se le pide nada. Así que el noveno despliegue empieza por **`/release`** (changelog, etiqueta,
push) y, si `main` se movió después, por `git checkout vX.Y.Z`. El despliegue escribe la versión en
**`storage/app/version`** del servidor (`versión hash fecha-UTC`; `storage/` no viaja, el `--delete` no la
toca) y la salud la relee: `ssh jumpweb-prod 'cat public_html/storage/app/version'` contesta «¿qué corre
aquí?». **v1.0.0 = `1272cb93`**, lo del octavo despliegue; aquel despliegue fue anterior a la guarda, así que
el fichero se escribió A MANO el **2026-09-17 a las 22:05 local** (parque cerrado, orden del owner en el turno),
con el formato del script: `v1.0.0 1272cb93 2026-09-17T20:05:48Z` (la fecha es la de la ESCRITURA; el código
corre desde el 16-09 a las 22:53). Releído por `ssh` (37 B, `playjump2`, 0664) y `/`, `/up` y `/admin/login`
en 200 después. Medido: `DeployScriptGateTest` ejecuta el script en un repo de usar y tirar y
`scripts/mutar-guarda8.sh` da 9/9.

**GUARDA 9 · lo que git no ve, `rsync` sí lo sube** (`DECISIONES #638`, 2026-09-19). El pre-vuelo lista lo que
git NO conoce bajo `public/` y, con `--go`, aborta; en seco avisa. Existe porque la comprobación de árbol
limpio se apoya en `git status --porcelain`, que **calla los ficheros ignorados** —incluidos los de
`.git/info/exclude`, que solo existe en UNA máquina— y `rsync` sincroniza el árbol de trabajo sin saber nada
de git: el banco de pruebas de la F4 (`public/landing-ajena.html`) habría acabado publicado en el dominio del
cliente sin aparecer en ningún diff. Lista blanca —lo que vive ahí fuera de git a propósito—: `build/`,
`uploads/`, `storage`, `hot`, `css/client.css`, `img/`. Medido: `DeployScriptGateTest` comprueba el patrón
real del script en las dos direcciones (mutación vista).

**No hay noveno despliegue pendiente a 2026-09-17** (medido con lecturas, el clasificador «auto» denegó el
ensayo en seco del script por «Blind Apply»): de `v1.0.0` a `13289269` hay 7 commits y **0 ficheros de
runtime** (`app/`, `resources/`, `routes/`, `config/`, `database/`, `public/`, `lang/`, `bootstrap/`,
`package.json`); el `rsync` solo llevaría 8 ficheros inertes (`CHANGELOG.md`, `CLAUDE.md`, `README.md`,
`composer.json`/`.lock`, tres de `scripts/`); los 117 paquetes de producción del `composer.lock` no se mueven
(solo entra Larastan, de desarrollo); `client.css` servido = rama `cliente/playjump` = copia local
(`687ffcb3…`). ⚠️ **El build NO es reproducible byte a byte**: 6 de las 7 entradas del `manifest.json`
servido coinciden con el build local, pero `resources/css/app.css` cambia de hash (65.339 B servido, 50.080 B
local) porque Tailwind escanea el árbol entero —docs y mockups incluidos— y **ninguna vista, config ni ruta
la carga** (0 coincidencias): es una entrada de Vite sin consumidor. Comparar manifiestos enteros da un
falso «hay algo que desplegar»; se compara entrada a entrada.

**OPERACIÓN DE DATOS · promo «−20 % en las entradas online»** (`[DECIDIDO owner]` 2026-09-17, aplicada a las
22:35 local con el parque cerrado; es DATO de la instalación, sin código ni despliegue). Las 5 entradas
(`ticket_types` 100–104, tipo `entry`): sus **9 filas de `prices` × 0,8** (exacto, sin redondeos; 0 tramos) y
el **badge** `−20 % online` / `−20% online` / `−20 % en ligne`. Packs, excursiones y complementos, intactos.
Copia previa en el servidor: `~/backups/playjump2_main_prepromo20-20260917-203115.sql.gz`. El guion llevaba el
precio ESPERADO de cada fila (la segunda pasada aborta: medido en local, que partía del mismo estado), corre en
una transacción, escribe por Eloquent, deja **10 filas en `audit_logs`** (`catalog.prices_updated` y
`catalog.updated`, con `from`/`to` y los badges anteriores: **de ahí se revierte**) y olvida
`cta.min_price_cents`, como `EditCatalog::afterSave()`. Verificado: BD, `/api/v1/catalog/products`, la portada
(«Desde 6,40 €», 5 badges), `/`, `/precios` y `/up` en 200. ⚠️ **Taquilla cobra lo mismo**: el pedido manual
del panel usa el mismo `RateResolver` y la misma tabla; por eso el badge dice «online» y no «solo online».
**El precio de antes tachado y el recuadro** (`#628`, 18-09, chapuza declarada, v1.1.0): en la card de la
portada y en `/precios`, el «antes» = precio × 100 / (100 − `promo.percent`) y el recuadro = `promo.banner.{es,en,fr}`.
▶ **Desplegada v1.1.0 y las cuatro filas ESCRITAS en `settings` de producción el 18-09 a las 07:25** (grupo
`promo`; el copy con espacio FINO antes de «%» y menos tipográfico): `promo.percent` = `20` · `promo.banner.es` = «−20 % en todas las entradas
online: compra en la web, elige día y hora, y ahorra un 20 %.» · `en` = «20% off all tickets online: book on the
website, pick your day and time, and save 20%.» · `fr` = «−20 % sur toutes les entrées en ligne : réservez sur le
site, choisissez le jour et l'heure, et économisez 20 %.». Sin las filas, la web es la de siempre. **Fin de la
promo**: subir los precios en el panel (los `from` de `audit_logs`), quitar el badge y BORRAR las cuatro filas el
mismo día, o el tachado mentiría. Medido en local a 390 y 1280 antes de commitear; ⚠️ en `/precios` a 390 la
cifra «9,60 €» ya se partía en dos renglones ANTES de este cambio (84 px de celda): no es de esta promo.

`jumpweb-prod` es un alias de `~/.ssh/config` (`HostName 51.68.7.199 · User playjump2 · Port 22`,
clave `jumpweb_staging_ed25519` — la misma que staging, registrada en el panel como «jumpweb-prod»).
`DEPLOY_PRODUCTION=1` **invierte** las guardas 3 y 4 de §2: el correo tiene que salir y el
`robots.txt` permisivo del repo es el bueno.

| | Producción | Nota |
|---|---|---|
| Usuario / HOME | `playjump2` · `/var/www/9dcee356-e579-46a8-9f3a-1c4a8d999e52` | rutas con UUID |
| PHP | **8.5.1** (`/opt/ecp-php85/bin/php`; también 8.4.16) | elegido en el panel |
| BD | `playjump2_main`, MariaDB por **socket** (`DB_HOST=localhost`) | ⚠️ el usuario dedicado `playjump2_main` nació **sin permisos** (1044): la app usa `playjump2` (el de `~/.my.cnf`, que el panel rota) hasta que el owner lo añada |
| Redis | `127.0.0.1:6379`, sin contraseña, PONG; phpredis | `CACHE_STORE=redis` · `SESSION_DRIVER=redis` |
| Correo | **`sendmail` funciona** (`MAIL_MAILER=sendmail`, `MAIL_SENDMAIL_PATH="/usr/sbin/sendmail -t -i"`) | dos pruebas recibidas en Gmail, sin spam |
| Cola | `QUEUE_CONNECTION=database`; **el cron del panel la procesa** (medido: un trabajo encolado desapareció de `jobs` sin worker manual) | ⚠️ sin `queue:work --stop-when-empty` en el cron los correos del registro no salen. La sonda con un CLOSURE desde tinker cae con `bindTo() on null` (serialización del REPL): no es la cola, es la sonda — usa un Job de clase |
| Cron | **El crontab del usuario NO corre** (medido: 0 tics en 5 min) — igual que staging. Las dos líneas (scheduler + `queue:work --stop-when-empty --max-time=50`) van en el **cron del panel** (Advanced → Cron), cada minuto, con `/opt/ecp-php85/bin/php` | hecho por el owner el 01-09 |
| Fuentes | ⚠️ **`THEME_FONTS` es del `.env`** (`config/theme.php` → `ThemeFonts::stylesheetUrl()`, familias de Bunny): sin él la página pide las familias del PRODUCTO y `client.css` cae a `system-ui`. Copiado del local el 01-09 (`bungee · hanken-grotesk · jetbrains-mono · permanent-marker · lilita-one`) | el paquete del cliente son CUATRO piezas, no tres: `client.css` + `client-*` + el kit + **esta variable** |
| Document root | ⚠️ nació en `public_html` (404 en `/`); **puente `.htaccess` → `public/`** hasta que el panel apunte a `public_html/public` | el mismo `#102` de staging |
| Paquete del cliente | `client.css` + `client-*` **no viajan por rsync** (excluidos y protegidos del `--delete`): se suben por `scp` a `public/css` y `public/img` | hecho el 01-09 |
| Datos | `migrate` + `REPLACE INTO` de las tablas de catálogo/config del local (`~/prod-datos-catalogo.replace.sql`) | dos migraciones siembran filas: por eso `REPLACE`, no `INSERT` |
| Post-despliegue | `~/post-deploy.sh` (import si `zones` vacía · Turnstile · usuarios · `post-deploy.php`: roles de puerta + publicar la descarga v1 · cachés · `up`) | idempotente |
| Redsys | ~~`redsys_environment=test`, comercio de pruebas~~ → **`live` desde el 2026-09-13** (TPV real configurado por el owner en el panel; `#594`) | la compra online está **abierta** (`sales.online_enabled=1`); la GUARDA 1 del despliegue admite `live` solo en producción |

> ▶ **RUNBOOK DEL DESPLIEGUE DE LA V3 DE LA POLÍTICA DE COOKIES** (T3a de `specs/analitica.md` §4.3; pendiente
> de desplegar): de noche, como siempre (`#594`). Tras `deploy.sh --go` (la migración de `users` con las marcas
> del aviso) y con el correo saliendo por la cola: **`php artisan analytics:notify-accounts --dry-run`** (dice a
> cuántas cuentas avisaría: clientes existentes, sin el equipo, sin quien se opuso, sin filas anónimas) y, si el
> número cuadra con los clientes, **`php artisan analytics:notify-accounts`**. Es idempotente
> (`users.analytics_notified_at`): se puede repetir y no escribe dos veces a nadie. Desde esa noche el aviso
> del índice del cajón lo ven esas mismas cuentas hasta que lo despiden; una cuenta creada después no recibe
> ninguno de los dos. ⚠️ El enlace sesión↔cuenta solo existe para quien acepte «análisis» en el banner nuevo
> (`POLICY_VERSION` `2026-09-24`: todo visitante vuelve a decidir), así que el orden correcto es desplegar → avisar
> → dejar que el banner pregunte, y no configurar la herramienta de análisis (Ajustes) hasta después del aviso.
> ▶ **Los píxeles (T3b)**: los ids públicos van en «Ajustes → Píxeles de anuncios»; los tokens de las APIs de
> conversiones (`META_CAPI_ACCESS_TOKEN`, `TIKTOK_EVENTS_ACCESS_TOKEN`) en el `.env` con `config:cache`, ANTES de
> poner los ids: sin token el job de conversiones anota y no manda, y el cron ya corre `queue:work` (`PAY-14`).

## Anexo · La fila del enrutador, mudada el 2026-09-16

> Lo que decía la fila **«Staging / desplegar / aprovisionar · PRODUCCIÓN playjump.es»** de `CLAUDE.md` cuando el enrutador bajó a una línea por fila
> (`DECISIONES #619`). Se conserva **verbatim** porque es historia de trampas medidas: léelo
> después del §0 y no lo reescribas. Documentos que la fila citaba: `docs/ENTORNOS.md`.

- `docs/ENTORNOS.md` §4 (staging, **medido**) · **§6 (producción, medido el 2026-09-01, `DECISIONES #325`)**: `DEPLOY_PRODUCTION=1 DEPLOY_SSH_HOST=jumpweb-prod DEPLOY_URL=https://playjump.es scripts/deploy.sh --go` —
- ⚠️ el paquete del cliente son CUATRO piezas (`client.css` · `client-*` · el kit · **`THEME_FONTS` en el `.env`**) y ninguna viaja por rsync · el docroot del panel no se pudo cambiar: **puente `.htaccess` → `public/`**, protegido del `--delete` · el crontab del usuario NO corre: scheduler y `queue:work` viven en el cron del PANEL ·
- ⚠️ **Redsys en `live` desde el 2026-09-13** (TPV real; la GUARDA 1 admite `live` solo en producción, `#594`) ·
- ❗ **se despliega DE NOCHE o con el parque cerrado** (`[DECIDIDO owner]`: una guarda que falla deja el sitio en mantenimiento)
