#!/usr/bin/env python3
"""Arnés de mutación de la T1·3a: el cliente de la API de la ficha de Google
(`docs/specs/google-business-profile.md` §4.2·5–§4.2·7; `DECISIONES #524`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · el token va en la **cabecera** y **nunca en la URL**;
  · `readMask` viaja (sin él Google responde 400) y se recorren **todas** las cuentas del token;
  · el token de acceso se pide **una vez** y el 401 se reintenta **con uno nuevo**;
  · los **dos 403** se separan: uno lo arregla el parque, el otro no lo arregla nadie del parque;
  · un **429 no apaga** la conexión;
  · **ningún token llega al log** —la mutación es la línea de depuración que todos escribimos—;
  · y sin credenciales **no se llama** a Google.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t1-3.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

API = 'app/Domain/Platform/Services/GoogleBusinessApi.php'
EXC = 'app/Domain/Platform/Exceptions/GoogleBusinessApiException.php'

T = 'GoogleBusinessApiTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        # Una URL acaba en el log del servidor, en el del proxy y en el historial de cualquier
        # herramienta por medio. Una cabecera `Authorization`, no.
        'el token NO viaja en la URL',
        API,
        '        return Http::withToken($accessToken)\n',
        "        return Http::withToken($accessToken)\n            ->withQueryParameters(['access_token' => $accessToken])\n",
        php(T + 'test_las_cuentas_llegan_y_el_token_va_en_la_cabecera'),
    ),
    (
        'el token SÍ viaja en la cabecera',
        API,
        '        return Http::withToken($accessToken)\n            ->acceptJson()',
        '        return Http::acceptJson()',
        php(T + 'test_las_cuentas_llegan_y_el_token_va_en_la_cabecera'),
    ),
    (
        'el readMask viaja (sin él, 400)',
        API,
        "            'readMask' => self::LOCATION_READ_MASK,\n",
        '',
        php(T + 'test_las_fichas_se_piden_con_el_read_mask'),
    ),
    (
        # Un administrador puede tener varias cuentas, y la ficha del parque vivir en la segunda.
        'se recorren TODAS las cuentas, no solo la primera',
        API,
        '                $fichas = array_merge($fichas, $this->locations($refreshToken, $nombre));',
        '                return array_merge($fichas, $this->locations($refreshToken, $nombre));',
        php(T + 'test_se_recorren_todas_las_cuentas_del_token'),
    ),
    (
        'el token de acceso se REUTILIZA dentro de la pasada',
        API,
        '        if ($this->accessToken !== null\n'
        '            && $this->accessTokenExpiresAt !== null\n'
        '            && $this->accessTokenExpiresAt > now()->getTimestamp()\n'
        '        ) {\n'
        '            return $this->accessToken;\n'
        '        }\n\n',
        '',
        php(T + 'test_el_token_de_acceso_se_pide_una_sola_vez_y_se_reutiliza'),
    ),
    (
        # Reintentar con el MISMO token de acceso caducado no arregla nada: gasta otra llamada de
        # una cuota que comparten todos los parques y vuelve a fallar igual.
        'el reintento del 401 pide un token NUEVO',
        API,
        '            $this->accessToken = null;\n            $this->accessTokenExpiresAt = null;\n',
        '',
        php(T + 'test_un_401_se_reintenta_una_sola_vez_con_token_nuevo'),
    ),
    (
        # El 403 es dos cosas: «la cuenta perdió el rol» lo arregla el parque; «la API no está
        # aprobada» no lo arregla nadie del parque. Confundirlos le hace perder el día al admin.
        'los DOS 403 se separan por su razón',
        EXC,
        "            in_array($reason, ['SERVICE_DISABLED', 'accessNotConfigured', 'PERMISSION_DENIED_API'], true) => GoogleBusinessStatus::NoApiAccess,\n\n",
        '',
        php(T + 'test_cada_negativa_de_google_se_traduce_a_su_estado'),
    ),
    (
        'un 429 NO apaga la conexión',
        EXC,
        '            default => null,',
        '            default => GoogleBusinessStatus::Expired,',
        php(T + 'test_un_429_no_toca_el_estado_de_la_conexion'),
    ),
    (
        # ⚠️ La mutación es exactamente la línea de depuración que cualquiera escribe un martes por
        # la tarde. El canario existe para que esa línea no llegue a producción.
        'ningún token llega al log',
        API,
        '        return Http::withToken($accessToken)\n',
        "        Log::warning('google_business.call', ['token' => $accessToken]);\n\n        return Http::withToken($accessToken)\n",
        php(T + 'test_el_token_no_aparece_en_ningun_registro_pase_lo_que_pase'),
    ),
    (
        'sin credenciales NO se llama a Google',
        API,
        '        if (! $credentials->configured()) {\n'
        "            throw GoogleBusinessApiException::from(401, 'invalid_client');\n"
        '        }\n\n',
        '',
        php(T + 'test_sin_credenciales_la_llamada_es_una_conexion_caducada'),
    ),
]


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def main() -> int:
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
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
