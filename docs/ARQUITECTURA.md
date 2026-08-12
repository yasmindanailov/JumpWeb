# Arquitectura técnica — JumpWeb

> Adaptado del proyecto origen (2026-08-12). Describe la BASE HEREDADA: el refactor
> (`00-REFACTOR.md`) puede haberla cambiado. Verifica contra el código antes de construir
> encima (CONVENCIONES §7).

> Nota de fiabilidad: el doc origen era un v1 previo al grueso del código (decía «roles aún no
> instalado», «pagos Fase 5 futura»). Esta versión se **reescribió verificando contra el código
> real** el 2026-08-12; aun así, aplica la cabecera de arriba.

---

## 1. Stack real (verificado en `composer.json` / código)

| Pieza | Versión / detalle |
|---|---|
| PHP | `^8.3` (runtime Sail 8.5) |
| **Laravel** | `^13.8` |
| **Livewire** | `^4.3` (+ **Alpine.js** en la web pública) |
| **Filament** | `^5.0` — panel admin completo (`app/Filament/`) |
| **MySQL** | 8.4 (imagen Sail) |
| **Vite** | build de JS (`resources/js/app.js`, `resources/js/admin/`) y CSS del panel |
| PDFs | `barryvdh/laravel-dompdf` (`^3.1`) |
| QR | `chillerlan/php-qrcode` v5 (ya venía como dependencia de Filament) vía `app/Domain/Platform/Services/QrCode.php` — SVG inline server-side, sin GD/imagick, color por CSS |
| Pagos | **Redsys** con **conector propio**: `app/Support/Redsys.php` (único punto que conoce la criptografía) + clases vendorizadas `app/Support/Redsys/Vendor/{Signature,Utils}.php`. Sin paquete externo |
| Anti-bot | Cloudflare Turnstile (`app/Domain/Platform/Services/Turnstile.php`) |

**CSS — dos mundos separados:**
- **Web pública:** CSS estático (`public/css/landing.css` del mockup + `public/css/site.css` propio + `public/css/spinner.css`). NO Tailwind.
- **Panel admin:** Tailwind (`resources/css/app.css`) + tema Filament (`resources/css/filament/`, `public/css/filament/`).

## 2. Enfoques propios (no paquetes)

- **Traducciones de contenido:** trait propio `HasTranslations`
  (`app/Domain/Platform/Concerns/HasTranslations.php`): campos ES/EN/FR como JSON, se leen con
  `$model->tr('campo')`. NO `spatie/laravel-translatable` (DECISIONES).
- **Auth:** a medida sobre Livewire con modales (`app/Livewire/Auth/`). NO Breeze/Fortify.
  Reglas en `SEGURIDAD.md`.
- **Roles/permisos:** tablas propias `roles`/`permissions` (modelos `Role`, `Permission`;
  catálogo en `app/Support/PermissionCatalog.php`) + Gates/Policies. En
  `AppServiceProvider::boot()` hay un `Gate::before` que hace del rol `admin` un super-admin
  (pasa cualquier Gate; devolver `null` deja seguir la cadena para el resto).
- **i18n de interfaz:** `lang/{es,en,fr}/*.php` + middleware `SetLocale` (público) y
  `SetAdminLocale` (panel).

## 3. Composer global de vistas (AppServiceProvider) — CRÍTICO para rendimiento

`View::composer('*', …)` comparte con TODAS las vistas: `$site` (tabla `settings` completa,
una lectura), `$cookieConsent`, `$navServices` (servicios con `show_in_nav`), `$offers`
(ofertas activas del widget), y el ancla de precio del CTA.

- El composer corre una vez por **cada** subvista renderizada (la home monta ~79). El payload
  se **memoiza por petición** en `request()->attributes` (clave `app.shared_view_data`). Sin
  esta memoización un GET anónimo de la home dispara ~1.900 queries (~4 s de BD) — no la rompas.
- Guardas: si la tabla `settings` no existe (CI/instalación limpia) devuelve payload vacío.
- También en `AppServiceProvider`: singleton `CustomerAccountContext` (memoiza por petición la
  consulta que comparten nav y sidebar de cuenta; desde Fase 2 paso 1 pide los datos al contrato
  `App\Domain\Booking\Contracts\CustomerReservations` en vez de consultar Booking a mano);
  `Model::preventSilentlyDiscardingAttributes()`
  activo FUERA de producción (caza `$fillable` incompletos en tests; en prod se descarta en
  silencio para no romper flujos de pago); `URL::forceScheme('https')` solo en producción.

## 4. Estructura real del proyecto

> ⚠️ **En migración (Fase 2, `docs/specs/modulos-dominio.md`)**: el destino es
> `app/Domain/<Contexto>/{Contracts,Models,Services,…}` con 5 módulos (Booking · Content ·
> Identity · Payments · Platform). Al 2026-08-12 están hechos el paso 1 (contratos) y el
> paso 2 (**Platform mudado entero**); Content, Identity, Payments y Booking siguen en
> `app/Support`/`app/Models` y mudan en los pasos 3–6. La frontera la impone
> `tests/Feature/Architecture/ModuleBoundariesTest.php`. Este árbol se reescribe en el paso 7.

```
app/
  Domain/
    Platform/       ✅ MUDADO (paso 2). Base compartida: todos pueden depender de ella y ella
                    de nadie. Models/ (Setting, AuditLog) · Services/ (AuditLogger, DisplayTime,
                    Money, Duration, PhoneNormalizer, MaintenanceSettings, QrCode, Turnstile) ·
                    Concerns/ (HasTranslations) · Enums/ (DashboardPeriod).
    Booking/        Contracts/ (CustomerReservations, PublishableCatalog + DTOs) + ServiceProvider.
    Payments/       Contracts/ (RefundGateway, RefundResult) + ServiceProvider.
                    Regla: desde fuera de app/Domain solo se tocan los `Contracts` de un
                    módulo — salvo Platform, que es la base común y se usa entera.
  Models/           modelos aún sin mudar (Order, OrderItem, Ticket, TicketType, Slot,
                    SlotTemplate, Season, SpecialDate, Zone, Attraction, Room,
                    LandingService, Offer, Payment, PaymentRefund, Role, Permission, User…)
                    + Models/Concerns/ (traits de Booking/Payments)
  Support/          ⚠️ AQUÍ viven los SERVICIOS DE DOMINIO aún sin mudar (NO existe
                    app/Services): directorio plano — OrderCreator, TicketIssuer,
                    SlotGenerator, SlotAvailability, PackAvailability, RateResolver,
                    AddonResolver, Cart, Redsys*, ParkSchedule, CookieConsent,
                    *Settings (ThemeSettings, PaymentSettings, PuertaSettings, …), etc.
  Livewire/         Site/ · Auth/ · Account/ · Admin/ · Tickets/ (Purchase) · Concerns/
  Filament/         Resources/ (Orders, Catalog, Slots, SlotTemplates, Seasons,
                    SpecialDates, Zones, Attractions, LandingServices, Offers, Pages,
                    Faqs, ParkRules, RateTypes, Users, Roles, AuditLogs, …)
                    + Pages/ · Widgets/ · Support/ · Concerns/
  Http/Middleware/  SetLocale, SetAdminLocale, SecurityHeaders, NoStore,
                    EnsureSiteAvailable, RequiresStaffOrAdmin
  Mail/ · Notifications/ · Console/ · Exceptions/ · Providers/
resources/
  views/            home, pages/, livewire/, components/, layouts/, account/, auth/,
                    emails/, pdf/, payments/, reservation/, filament/, errors/
  css/ · js/        Tailwind del panel · Alpine público + js/admin/
routes/             web.php (~37 rutas, único fichero de rutas HTTP) · console.php
database/           migrations/ · seeders/ · factories/
lang/{es,en,fr}/    textos de interfaz
tests/              Feature/ · Unit/ (ver TESTING.md)
public/css/         landing.css · site.css · spinner.css · filament/
```

Vocabulario del dominio en el código: zonas, atracciones, cumpleaños, **puerta** (validar/
canjear entradas), waiver (vocabulario del sector origen; su generalización se decide en
`00-REFACTOR.md` Fase 1/2). P. ej. `PuertaSettings`, `ParkSchedule`, `ParkRule`.

## 5. White-label (3 capas)

1. **Componentes** (Blade/Livewire): estructura y lógica, una sola vez.
2. **Tokens de diseño** (variables CSS): colores, fuentes → aspecto por instalación.
3. **Configuración y contenido en BD** (vía panel): textos, precios, imágenes, tema.

Para otra instalación: nueva instancia + su tema + su contenido. **El código no se toca.**
(Es el principio; hasta dónde lo cumple hoy la base heredada lo audita `00-REFACTOR.md`.)

## 6. Entornos

- **Local:** Docker (Laravel Sail) en WSL2 — web `http://localhost:8081`, MySQL en `3308`,
  Mailpit en `8028`. Repo: `~/proyectos/jumpweb`. Comandos y gotcha de permisos (`-u sail`)
  en `CLAUDE.md`/`README.md`.
- **Producción:** no aplica todavía (JumpWeb es el producto; cada cliente tendrá su despliegue).
- **Git:** repo propio de JumpWeb; el repo origen es solo lectura (linaje).

## 7. Calidad

- Suite de tests Feature/Unit sobre lo crítico (compra, aforo, registro, Redsys) → `TESTING.md`.
- Redsys siempre en **sandbox** hasta que un despliegue real lo requiera.
- Seguridad (hash de contraseñas, CSRF, cabeceras, control por rol) → `SEGURIDAD.md`.

## 8. Arquitectura objetivo del refactor

Este doc describe la base **heredada**. La arquitectura a la que se quiere llegar (qué se
generaliza, qué se renombra, qué se extrae a configuración, fases y criterios de cierre) vive
en **`00-REFACTOR.md`** — no se duplica aquí. Ante conflicto entre ambos: manda `00-REFACTOR.md`
más lo que digas verificar en el código.
