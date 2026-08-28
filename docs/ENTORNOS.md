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
