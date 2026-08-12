<?php

use App\Domain\Identity\Models\CookieConsentLog;
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
 * #219 — Poda del log de consentimiento de cookies. `CookieConsentLog` es Prunable (borra las filas
 * > 24 meses, la vida del consentimiento). Diario es de sobra: el plazo es de meses. Acota el
 * crecimiento de la tabla y cumple la minimización / limitación del plazo de conservación del RGPD
 * para un dato de acreditación con IP/User-Agent. Requiere el mismo cron `schedule:run` (Fase 9).
 */
Schedule::command('model:prune', ['--model' => [CookieConsentLog::class]])
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
 * En PRODUCCIÓN usa el MISMO cron `schedule:run` (ya configurado en Enhance). Si ese cron no corre,
 * los emails se acumulan en `jobs` sin enviarse — vigilar `failed_jobs` (`docs/10-DESPLIEGUE.md §6`).
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
