<?php

use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\WaiverSignature;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Audit hardening #113 (A1, 2026-05-28) — Scheduler de `orders:expire`.
 *
 * El comando marca como `expired` los Order `pending` cuyo `expires_at` cruzó (#105). Sin
 * scheduler, el cálculo de aforo seguía siendo correcto (lazy filter en `SlotAvailability`),
 * PERO los pending caducados se acumulaban en BD indefinidamente y el log "Order expired"
 * no se emitía → operativa ciega.
 *
 * Cada 5 minutos cubre el caso real:
 *  - `sales.hold_minutes` por defecto 20 min (#113 M2).
 *  - Granularidad de 5 min asegura que una Order caducada se procesa antes del siguiente
 *    cliente que intente comprar la misma franja.
 *
 * En PRODUCCIÓN (Fase 9): para que esto se ejecute, el servidor debe tener un cron que
 * llame a `php artisan schedule:run` cada minuto. En Enhance, configurar:
 *   `* * * * * cd /var/www/<instalacion> && php artisan schedule:run >> /dev/null 2>&1`
 * Documentado en `docs/PLAN-REDSYS.md §14` y en `docs/ESTADO.md`.
 *
 * `withoutOverlapping()`: si una ejecución tarda más de 5 min (caso patológico, BD muy
 * lenta), evita que arranque otra en paralelo y duplique el trabajo.
 */
Schedule::command('orders:expire')->everyFiveMinutes()->withoutOverlapping();

/*
 * `#491` — Las reseñas de Google a la caché corta. **La landing nunca llama a Google**: éste es el
 * único sitio que lo hace (`specs/google-reviews.md` §4.2, y `PERF-02` es la razón).
 *
 * ❗❗❗ **Cada MEDIA HORA, con una caché de 35 minutos** (`[DECIDIDO owner, 2026-09-13]`, `#591`): así
 * las reseñas están siempre puestas. Hasta `#591` iba cada tres horas con una caché de media hora y
 * la sección enseñaba Google media hora de cada tres — sin que fallara nada. **La caché tiene que
 * durar más que el hueco entre dos refrescos**, y `SocialProofNeverHitsTheRenderPathTest` lee esta
 * línea para comprobarlo.
 * ⚠️ **Es UNA llamada por pasada** (el idioma de la instalación; las otras versiones leen la misma
 * caché): **48 al día**. El tope diario de la consola de Google tiene que quedar por ENCIMA (100).
 * ⚠️ `withoutOverlapping` porque la llamada puede tardar (timeout de 8 s) y dos a la vez serían dos
 * peticiones facturadas para el mismo dato.
 * ❗ **En staging el scheduler no corre** (`#115`): allí se dispara a mano.
 */
Schedule::command('social-proof:refresh')->everyThirtyMinutes()->withoutOverlapping();

/*
 * #219 — Poda del log de consentimiento de cookies. `CookieConsentLog` es Prunable (borra las filas
 * > 24 meses, la vida del consentimiento). Diario es de sobra: el plazo es de meses. Acota el
 * crecimiento de la tabla y cumple la minimización / limitación del plazo de conservación del RGPD
 * para un dato de acreditación con IP/User-Agent. Requiere el mismo cron `schedule:run` (Fase 9).
 *
 * Fase 6 · waiver (`specs/waiver-probatorio.md` §4.6) — en la MISMA tarea, la poda del registro de
 * firmas al vencer su plazo. `WaiverSignature` es Prunable: sin `waiver.retention_months` fijado NO
 * poda nada (la conservación sin plazo se decide, no se improvisa); con plazo, borra las firmas del
 * TITULAR más antiguas que él. Es la única vía de borrado que su guarda de inmutabilidad autoriza.
 * Una sola entrada para las podas: la salud del despliegue cuenta tareas registradas.
 *
 * Fase 6 · menores a cargo (`specs/menores-a-cargo.md` §4.4, §5) — y en la misma tarea, DESPUÉS de las
 * firmas, las personas a cargo DESVINCULADAS que ya no tienen ninguna firma detrás: cuando la poda por
 * plazo se lleva la última firma de un menor, su nombre y su fecha de nacimiento se quedan sin nada
 * que los justifique. Las activas no se podan nunca (son del titular); las desvinculadas con firma,
 * tampoco (`Dependent::prunable()` las excluye antes de que la guarda de `deleting` lance).
 */
Schedule::command('model:prune', ['--model' => [CookieConsentLog::class, WaiverSignature::class, GuardianAuthorization::class, Dependent::class]])
    ->daily()
    ->withoutOverlapping();

/*
 * Auditoría Fase 1 (2026-06-12) — Regeneración RODANTE de franjas (`slots`).
 *
 * Causa de fondo del bug del calendario: nadie regeneraba franjas, así que `slots` solo cubría
 * unos días (del último seed/generación manual) mientras la venta abre `purchaseHorizonMonths`
 * (6 meses). La web se quedaba corta de fechas y el panel ofrecía días sin franjas. Este job
 * mantiene a diario las franjas alineadas con el horizonte [hoy, hoy+horizonte] y poda lo obsoleto
 * (sin borrar franjas con ventas; ver `SlotGenerator`). "Hoy" en la zona operativa del parque.
 *
 * El rango se calcula EN LA EJECUCIÓN (closure con DI), no al registrar el schedule. Requiere el
 * mismo cron del sistema `schedule:run` que el resto (Fase 9, ver `docs/10-DESPLIEGUE.md`).
 */
Schedule::command('slots:generate-rolling')
    ->dailyAt('03:00')
    ->withoutOverlapping();

/*
 * Cola de emails (D, 2026-06-15, `DECISIONES #243`) — worker SIN servicio nuevo, sobre el cron.
 *
 * Las notificaciones transaccionales son `ShouldQueue`: ya NO se manda el SMTP de forma síncrona
 * dentro de la petición del cliente (confirmación, verificación, post-form, reembolso, contacto,
 * aviso de incidencia…). El driver es `database` (tabla `jobs`, ya migrada). En vez de un
 * `queue:work` PERSISTENTE bajo supervisor (= servicio nuevo, contra la restricción «usa el cron
 * que ya tienes»), aprovechamos el `schedule:run` que el cron del sistema ya invoca cada minuto:
 * arrancamos un worker que procesa la cola y se AUTOAPAGA al vaciarla (`--stop-when-empty`) o a los
 * 55 s (`--max-time`, antes del siguiente tick), con reintentos (`--tries=3`; tras agotarlos el job
 * cae a `failed_jobs`). `withoutOverlapping(10)` evita dos workers a la vez (lock con caducidad de
 * 10 min: se auto-cura si un worker muere sin liberar). Latencia media de entrega ≈ ½ minuto:
 * aceptable para email transaccional y muy preferible a bloquear al cliente con la latencia SMTP.
 *
 * Registrado el ÚLTIMO a propósito: los comandos de arriba (orders:expire…) corren primero en cada
 * `schedule:run`, así el worker (que puede ocupar hasta 55 s) no los retrasa.
 *
 * En PRODUCCIÓN usa el MISMO cron `schedule:run`. Si ese cron no corre, los emails se acumulan en
 * `jobs` sin enviarse.
 *
 * ⚠️⚠️ **Y NO se vigila con `failed_jobs`, que es a lo que invitaba esta nota.** Medido el
 * 2026-08-21 en staging (`DECISIONES #115`): con el cron muerto, 6 avisos llevaban 24 h en `jobs`
 * con `attempts = 0` y `failed_jobs` estaba en **CERO** — porque **un job que nunca se INTENTA
 * nunca falla**. La señal correcta es la EDAD del trabajo más viejo de `jobs`: con el worker vivo la
 * cola se drena cada minuto, así que algo disponible desde hace más de 5 minutos significa que nadie
 * lo está sacando. `scripts/deploy.sh` lo comprueba así al terminar.
 */
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(10);

/*
 * Fase 3 · paso 0 (`docs/specs/api-v1.md` §4.2) — poda de tokens de API caducados.
 *
 * `config('sanctum.expiration')` hace que un token deje de AUTENTICAR al cumplirse el plazo, pero
 * la fila sigue en `personal_access_tokens` con su hash. Esto la borra 24 h después de caducar (el
 * margen deja rastro para diagnosticar un «me ha echado la app» reciente sin conservar un
 * credencial muerto indefinidamente). Semanal es de sobra para una tabla que crece por
 * dispositivo, no por petición.
 *
 * Hoy no hay emisor —`POST auth/tokens` llega en el paso 3— y el comando es un no-op sobre una
 * tabla vacía. Se registra ya porque forma parte de instalar Sanctum del derecho: la limpieza de
 * credenciales caducadas no es algo que deba recordar el paso que los empieza a emitir.
 */
Schedule::command('sanctum:prune-expired --hours=24')
    ->weekly()
    ->withoutOverlapping();
