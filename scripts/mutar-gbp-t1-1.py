#!/usr/bin/env python3
"""Arnés de mutación de la T1·1: la conexión con la ficha de Google
(`docs/specs/google-business-profile.md` §4.2; `DECISIONES #524`, `#719`).

Cada mutación rompe UNA propiedad del cimiento y comprueba que su guarda se pone en rojo:

  · **sin credenciales de JumpSystem no hay estado que valga**, ni aunque haya token guardado;
  · hacen falta **las DOS** credenciales (la lección de `PublicConfigResource`);
  · se leen con **consulta fresca**, o un worker vivo nunca ve el aprovisionamiento;
  · un token **ilegible** es «caducada» y no «lista para conectar» —ni una excepción—;
  · la lectura mira el atributo **vigente**, no el que se leyó de la base;
  · el token va **cifrado**, **oculto** al serializar, y el secreto no sale en un **volcado**;
  · `syncs()` es **lista blanca de un caso**, no «lo que no está roto»;
  · la **huella** distingue el token viejo de una reconexión;
  · y la conexión es **una sola fila**, por índice único.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t1-1.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

MODEL = 'app/Domain/Platform/Models/GoogleBusinessConnection.php'
STATE = 'app/Domain/Platform/Services/GoogleBusinessConnectionState.php'
CREDS = 'app/Domain/Platform/Services/GoogleBusinessCredentials.php'
ENUM = 'app/Domain/Platform/Enums/GoogleBusinessStatus.php'
MIGRATION = 'database/migrations/2026_09_20_140000_create_google_business_connections.php'

T = 'GoogleBusinessConnectionTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'sin credenciales manda, aunque haya token',
        STATE,
        "        if (! GoogleBusinessCredentials::fresh()->configured()) {\n"
        '            return GoogleBusinessStatus::Unconfigured;\n'
        '        }\n\n',
        '',
        php(T + 'test_sin_credenciales_esta_sin_configurar_aunque_haya_token'),
    ),
    (
        'hacen falta LAS DOS credenciales',
        CREDS,
        "        return $this->clientId !== '' && $this->clientSecret !== '';",
        "        return $this->clientId !== '' || $this->clientSecret !== '';",
        php(T + 'test_media_credencial_no_basta'),
    ),
    (
        'las credenciales se leen FRESCAS, no del memo',
        CREDS,
        '        /** @var array<string,mixed> $rows */\n'
        '        $rows = Setting::query()\n'
        '            ->whereIn(\'key\', [self::CLIENT_ID_KEY, self::CLIENT_SECRET_KEY])\n'
        "            ->pluck('value', 'key')\n"
        '            ->all();',
        '        $rows = [\n'
        '            self::CLIENT_ID_KEY => Setting::value(self::CLIENT_ID_KEY),\n'
        '            self::CLIENT_SECRET_KEY => Setting::value(self::CLIENT_SECRET_KEY),\n'
        '        ];',
        php(T + 'test_las_credenciales_se_leen_frescas_y_no_del_memo'),
    ),
    (
        'un token ilegible es CADUCADA, no «lista para conectar»',
        STATE,
        '        if ($row->readToken() === null) {\n'
        '            return GoogleBusinessStatus::Expired;\n'
        '        }\n\n',
        '',
        php(T + 'test_un_token_ilegible_es_una_conexion_caducada'),
    ),
    (
        # ⚠️ Lo contrario: si «hay token» se preguntara por la PROPIEDAD, un token ilegible no
        # devolvería un estado — reventaría con `DecryptException` dentro de la pantalla del panel.
        'saber si HAY token no puede descifrar',
        MODEL,
        "        $raw = $this->getAttributes()['refresh_token'] ?? null;",
        '        $raw = $this->refresh_token;',
        php(T + 'test_un_token_ilegible_es_una_conexion_caducada'),
    ),
    (
        # El desfase medido el 20-09: `getRawOriginal()` entrega el token que se LEYÓ de la base.
        'la lectura mira el atributo VIGENTE, no el original',
        MODEL,
        "        $raw = $this->getAttributes()['refresh_token'] ?? null;",
        "        $raw = $this->getRawOriginal('refresh_token');",
        php(T + 'test_el_token_recien_puesto_se_lee_antes_de_guardar'),
    ),
    (
        'el token va CIFRADO en la base',
        MODEL,
        "            'refresh_token' => 'encrypted',\n",
        '',
        php(T + 'test_el_token_va_cifrado_en_la_base'),
    ),
    (
        'el token no sale al serializar',
        MODEL,
        "    protected $hidden = ['refresh_token', 'token_fingerprint'];",
        "    protected $hidden = ['token_fingerprint'];",
        php(T + 'test_ni_el_token_ni_su_huella_salen_al_serializar'),
    ),
    (
        'el secreto del cliente no sale en un volcado',
        CREDS,
        "            'clientSecret' => $this->clientSecret === '' ? '(vacío)' : '(oculto)',",
        "            'clientSecret' => $this->clientSecret,",
        php(T + 'test_el_secreto_del_cliente_no_aparece_en_un_volcado'),
    ),
    (
        # La trampa que el propio docblock de `syncs()` anuncia: escrito como «lo que no está roto»,
        # un estado nuevo entraría LLAMANDO a Google por omisión.
        'syncs() es lista blanca de un caso, no «lo que no está roto»',
        ENUM,
        '        return $this === self::Connected;',
        '        return ! $this->isFailure();',
        php(T + 'test_solo_la_conexion_llama_a_google'),
    ),
    (
        'la huella distingue el token viejo de una reconexión',
        MODEL,
        '        return is_string($stored) && hash_equals($stored, self::fingerprint($token));',
        '        return is_string($stored);',
        php(T + 'test_la_huella_distingue_el_token_viejo_de_una_reconexion'),
    ),
    (
        # La suite migra en SQLite en memoria, así que `RefreshDatabase` sí ejecuta esta migración
        # mutada: el índice único es comprobable desde la suite, al contrario que un `lockForUpdate`.
        'la conexión es UNA sola fila, por índice único',
        MIGRATION,
        "            $table->unique('singleton');",
        '',
        php(T + 'test_solo_puede_haber_una_conexion'),
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
