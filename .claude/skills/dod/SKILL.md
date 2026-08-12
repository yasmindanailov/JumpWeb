---
name: dod
description: Checklist de «Hecho» (Definition of Done) de JumpWeb — usar antes de marcar ✅ cualquier tarea, feature o fix, o cuando haya duda de si algo está terminado de verdad.
---

# Definition of Done (CONVENCIONES §3.bis)

Una tarea es ✅ solo si cumple LAS TRES. Verifica cada una EMPÍRICAMENTE, no de memoria:

## 1. El código existe y está integrado
- Clases/rutas/migraciones/vistas reales en el árbol (no un diseño ni un plan).
- `grep`/`ls` confirmando que lo que dices que existe, existe donde dices.

## 2. Prueba automática que lo cubre
- Test nuevo o existente que FALLA si se revierte el cambio (si dudas, revierte mentalmente:
  ¿qué test se pondría rojo?).
- Ubicación correcta: puro sin BD → `tests/Unit` (PHPUnit\Framework\TestCase);
  resto → `tests/Feature` (Tests\TestCase). (CONVENCIONES §3.ter)
- La suite del módulo tocado en verde AHORA (no «debería pasar»):
  `docker compose exec -u sail -T laravel.test php artisan test --filter=<Modulo>`

## 3. Verificación empírica del comportamiento
- HTTP/render/BD reales: `curl localhost:8081/...`, render de la vista, fila en BD, email
  en Mailpit (`localhost:8028`) — lo que aplique al cambio.
- Si toca dinero, aforo, RGPD o seguridad: relee `docs/INVARIANTES.md` y confirma que
  ninguna invariante se relaja. En dinero/aforo considera además revisión adversarial
  (agentes independientes intentando refutar).
- Si tocaste `OrderCreator`, `RedsysReturnHandler` o `SlotGenerator`: corre el comando de
  concurrencia que aplique — `php artisan redsys:verify-concurrency --workers=16` /
  `purchase:verify-oversell --workers=16` (la suite SQLite NO ve esas carreras,
  `INVARIANTES §6`; el pre-push de `main` exigirá `VERIFY_CONC=1` como confirmación).
- Feature visible de producto → además validación del owner (Yasmin) antes del ✅ definitivo:
  mientras tanto es 🟦 «pdte. validación».

Si falla cualquiera de las tres → la tarea queda 🟦 y se anota QUÉ falta en el tracker.
