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
- **Acceso**: `ssh -p 22 jumpweb_1@51.68.7.199` (alias local `jumpweb-staging`). Solo por CLAVE.
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
4. **No indexable.** `noindex` y, mejor, autenticación básica delante. Un staging indexado compite en
   Google con el sitio del cliente que se instale mañana.
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

## 4 · El procedimiento de despliegue

> ⚠️ **[PENDIENTE DE MEDIR EN LA MÁQUINA]** — este hueco cierra el `[DECISION-PENDIENTE]` de
> `INSTALACION-CLIENTE.md` §1, y **no se escribe a ojo**: se ejecuta una vez, se anota lo que de verdad
> pasó y se deja reproducible. Falta conocer PHP, MySQL, document root, si hay composer/node y qué
> permisos tiene el usuario.

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
