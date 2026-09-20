#!/usr/bin/env python3
"""Arnés de mutación de la T1·5: `business-profile:verify` y la cuenta de la ficha
(`docs/specs/google-business-profile.md` §4.2·10; `DECISIONES #524`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · las reseñas se piden con **la CUENTA delante** —son dos APIs que nombran la ficha distinto—;
  · una conexión **sin cuenta** se dice, en vez de salir con un 404 que despista;
  · el comando **no llama** a Google en los estados que no llaman;
  · **revalida** la ficha en vivo, que es la pregunta que de verdad importa;
  · **no imprime** el cuerpo de la respuesta de Google;
  · y **no escribe nada**: un diagnóstico que cambia lo que mide deja de servir.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t1-5.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

CMD = 'app/Console/Commands/VerifyGoogleBusiness.php'
VO = 'app/Domain/Platform/Services/GoogleBusinessLocation.php'
LOCS = 'app/Domain/Platform/Services/GoogleBusinessLocations.php'
CONN = 'app/Domain/Platform/Services/GoogleBusinessConnector.php'
API = 'app/Domain/Platform/Services/GoogleBusinessApi.php'

T = 'GoogleBusinessVerifyTest::'
L = 'GoogleBusinessLocationTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        # ⚠️ La trampa medida contra la doc oficial el 20-09: `locations.list` devuelve
        # `locations/9` y `reviews.list` (v4) exige `accounts/1/locations/9`. Pedirlo con el `name`
        # a secas devuelve un 404 que se lee como «ficha perdida» y manda a reconectar para nada.
        'las reseñas se piden con la CUENTA delante',
        VO,
        "        return ($account === '' || $name === '') ? null : $account.'/'.$name;",
        "        return $name === '' ? null : $name;",
        php(T + 'test_las_resenas_se_piden_con_la_cuenta_delante'),
    ),
    (
        'una conexión SIN cuenta se dice, no se intenta',
        CMD,
        '        if ($parent === null) {',
        '        if (false) {',
        php(T + 'test_una_conexion_sin_cuenta_lo_dice_en_vez_de_dar_un_404'),
    ),
    (
        # Un 401 garantizado y un mensaje que no dice lo que pasa.
        'los estados que no llaman NO llaman',
        CMD,
        '        if ($estado !== GoogleBusinessStatus::Connected) {',
        '        if (false) {',
        php(T + 'test_sin_conexion_no_llama_a_google_y_dice_que_hacer'),
    ),
    (
        # «¿Hay fichas?» no es la pregunta: la pregunta es si sigue estando la NUESTRA.
        'la ficha conectada se REVALIDA en vivo',
        CMD,
        '        $sigue = $elegida !== null && $locations->revalidate($token, $elegida) !== null;',
        '        $sigue = $elegida !== null;',
        php(T + 'test_si_la_ficha_ya_no_esta_en_el_listado_lo_dice'),
    ),
    (
        # ⚠️ La mutación va en `failure()` y NO en el comando, y el arnés enseñó por qué: la
        # excepción **nunca lleva el cuerpo** —solo `error.status`—, así que el comando no puede
        # filtrarlo aunque quiera. El único sitio donde el cuerpo podría colarse es donde nace la
        # excepción. La salida acaba en el log de un despliegue y en capturas que se pegan en un chat.
        'el CUERPO de Google no entra en la excepción',
        API,
        "        $reason = $response->json('error.status')\n            ?? $response->json('error')\n            ?? '';",
        '        $reason = $response->body();',
        php(T + 'test_no_imprime_ningun_secreto_cuando_google_falla'),
    ),
    (
        # Un diagnóstico que cambia lo que mide deja de servir para medirlo.
        'el diagnóstico NO escribe nada',
        CMD,
        "            $this->warn('Eso deja la conexión en «'.$e->status->value.'». '.$this->queHacer($e->status));",
        "            $this->warn('Eso deja la conexión en «'.$e->status->value.'».');\n"
        '            \\App\\Domain\\Platform\\Models\\GoogleBusinessConnection::current()?->update([\n'
        "                'status' => $e->status,\n"
        '            ]);',
        php(T + 'test_el_comando_no_escribe_nada'),
    ),
    (
        'el resumen de reseñas trae la CIFRA de Google',
        API,
        "            'totalReviewCount' => is_numeric($total) ? (int) $total : 0,",
        "            'totalReviewCount' => 0,",
        php(T + 'test_con_todo_en_orden_dice_lo_que_contesta_google'),
    ),
    (
        # Un administrador puede tener varias cuentas y la ficha del parque vivir en la segunda.
        'se recorren TODAS las cuentas, no solo la primera',
        LOCS,
        '            foreach ($this->api->locations($refreshToken, $nombre) as $fila) {',
        '            foreach (($fichas === [] ? $this->api->locations($refreshToken, $nombre) : []) as $fila) {',
        php(L + 'test_se_recorren_todas_las_cuentas_y_cada_ficha_guarda_la_suya'),
    ),
    (
        'cada ficha recuerda DE QUÉ cuenta salió',
        LOCS,
        '                $ficha = GoogleBusinessLocation::fromApi($fila, $nombre);',
        '                $ficha = GoogleBusinessLocation::fromApi($fila);',
        php(L + 'test_se_recorren_todas_las_cuentas_y_cada_ficha_guarda_la_suya'),
    ),
    (
        'elegir ficha GUARDA la cuenta',
        CONN,
        "                'account_name' => $ficha->account,\n",
        '',
        php(L + 'test_elegir_una_ficha_la_guarda_entera'),
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
