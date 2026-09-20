#!/usr/bin/env python3
"""Arnés de mutación de la T1·4: desconectar, quién conectó y el aviso del cambio de ficha
(`docs/specs/google-business-profile.md` §4.2·1, §4.2·4 y §4.2·8; `DECISIONES #524`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · desconectar borra **la fila entera**, no solo el token, y deja **rastro**;
  · se dice si Google **NO** confirmó la retirada, que es lo único accionable que queda;
  · desconectar es **solo POST** y exige **`settings.manage`**;
  · el aviso sale **solo si cambió** la ficha, lleva **desde cuál**, alcanza a quien tiene el
    permiso **sin ser admin**, **no** llega a una cuenta anonimizada, y **si falla no tumba** la
    elección;
  · y la pantalla dice **quién conectó**.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t1-4.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

CONN = 'app/Domain/Platform/Services/GoogleBusinessConnector.php'
CTRL = 'app/Http/Controllers/Admin/GoogleBusinessConnectController.php'
PAGE = 'app/Filament/Pages/GoogleBusinessProfilePage.php'
ROUTES = 'routes/web.php'

T = 'GoogleBusinessDisconnectTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        # Media conexión —sin llave pero con la ficha y el `placeId` dentro— es un estado que nadie
        # sabe leer y que la próxima pasada intentaría usar.
        'desconectar borra la FILA ENTERA',
        CONN,
        '            $row->delete();',
        "            $row->update(['refresh_token' => null]);",
        php(T + 'test_desconectar_borra_la_fila_entera'),
    ),
    (
        'desconectar deja RASTRO',
        CONN,
        "            AuditLogger::log('google_business.disconnected', $row, ['by' => $userId]);\n\n",
        '',
        php(T + 'test_desconectar_deja_rastro_antes_de_borrar'),
    ),
    (
        # Si Google no confirma, lo único accionable que le queda al admin es retirarlo a mano; decir
        # «desconectada» a secas le deja un permiso vivo sin saberlo.
        'se distingue «no revocado» de «revocado»',
        CTRL,
        "        return $this->back($connector->revoke($token)\n"
        "            ? 'google-business-disconnected'\n"
        "            : 'google-business-disconnected-not-revoked');",
        "        $connector->revoke($token);\n\n        return $this->back('google-business-disconnected');",
        php(T + 'test_fuera_de_produccion_desconectar_no_revoca_y_lo_dice'),
    ),
    (
        'desconectar es SOLO POST',
        ROUTES,
        "Route::post('/admin/ficha-google/desconectar', [GoogleBusinessConnectController::class, 'disconnect'])",
        "Route::match(['get', 'post'], '/admin/ficha-google/desconectar', [GoogleBusinessConnectController::class, 'disconnect'])",
        php(T + 'test_desconectar_solo_por_post'),
    ),
    (
        # `panel_role` deja pasar a `staff`: la ruta sola no basta tampoco aquí.
        'desconectar exige settings.manage',
        CTRL,
        "        abort_unless(Auth::user()?->hasPermission('settings.manage') === true, 403);",
        '        abort_unless(Auth::check(), 403);',
        php(T + 'test_un_staff_no_puede_desconectar'),
    ),
    (
        # La primera elección no es un cambio. Un aviso ahí enseña a ignorarlos.
        'solo se avisa si CAMBIÓ la ficha',
        CTRL,
        '        if ($eleccion->changed) {',
        '        if (true) {',
        php(T + 'test_la_primera_eleccion_no_avisa_a_nadie'),
    ),
    (
        # Sin el «desde qué», el correo no deja saber si fue un error, que es para lo que se manda.
        'el aviso lleva la ficha ANTERIOR',
        CTRL,
        '                $eleccion->previousTitle,',
        '                null,',
        php(T + 'test_el_aviso_dice_quien_y_desde_que_ficha'),
    ),
    (
        'el aviso alcanza a quien tiene el PERMISO, no solo al rol admin',
        CTRL,
        "                ->where('name', 'admin')\n"
        "                    ->orWhereHas('permissions', fn (Builder $permisos) => $permisos->where('name', 'settings.manage')))",
        "                ->where('name', 'admin'))",
        php(T + 'test_el_aviso_alcanza_a_quien_tiene_el_permiso_sin_ser_admin'),
    ),
    (
        # Su correo es sintético y rebota (`RGPD-01`).
        'el aviso no se manda a una cuenta anonimizada',
        CTRL,
        "                ->where('email', 'not like', '%@'.User::ANONYMIZED_EMAIL_DOMAIN)\n",
        '',
        php(T + 'test_el_aviso_no_llega_a_una_cuenta_anonimizada'),
    ),
    (
        # El cambio ya está guardado y auditado: perderlo por un SMTP sería el peor desenlace.
        'un fallo al avisar NO tumba la elección',
        CTRL,
        '        } catch (\\Throwable $e) {\n'
        "            Log::warning('google_business.location_warning_failed', ['error' => $e::class]);\n"
        '        }',
        '        } catch (\\Throwable $e) {\n            throw $e;\n        }',
        php(T + 'test_si_el_aviso_falla_la_eleccion_se_guarda_igual'),
    ),
    (
        'la pantalla dice QUIÉN conectó',
        PAGE,
        "        return $id === null ? null : User::query()->whereKey($id)->value('name');",
        '        return null;',
        php(T + 'test_la_pantalla_dice_quien_conecto_y_cuando'),
    ),
]


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def clear_routes() -> None:
    subprocess.run(EXEC + ['php', 'artisan', 'route:clear'], cwd=ROOT, capture_output=True, text=True)


def main() -> int:
    clear_routes()
    for name, _path, _old, _new, cmd in MUTATIONS:
        if not green(cmd):
            print(f'CONTROL en ROJO antes de mutar («{name}»): el arnés no puede medir nada')
            return 2

    bitten = 0
    for name, path, old, new, cmd in MUTATIONS:
        file = ROOT / path
        original = file.read_text(encoding='utf-8')
        if original.count(old) != 1:
            print(f'la mutación «{name}» no encuentra su sitio EXACTO en {path}: el arnés ha caducado')
            return 2
        file.write_text(original.replace(old, new, 1), encoding='utf-8')
        clear_routes()
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
            clear_routes()
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
