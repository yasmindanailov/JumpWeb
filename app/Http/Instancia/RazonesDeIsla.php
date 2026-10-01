<?php

namespace App\Http\Instancia;

use App\Domain\Content\Services\Lucide;

/**
 * **Las razones de la isla, con su icono DIBUJADO** (Z6b de `isla-y-landing-nueva.md` §4.27; opción C «Da la razón»).
 *
 * Una página declara la razón de cada pieza (`razones`, en lo que da a la isla) con el NOMBRE de su icono, que es dato de
 * cada instalación (`wallet`, `umbrella`, `send`…). La isla mete en su paquete cada dibujo que pinta (`ui/iconos.js`): con
 * estos, cada página nueva le pediría al producto un dibujo más, y las calculadoras —que comparten ese registro— pagarían
 * los de todas. Así que el servidor manda el icono YA DIBUJADO (`svg`), con el mismo `Lucide::svg()` que pinta los de las
 * páginas: cualquier icono del set, cero bytes en el paquete. Un nombre que no existe no dibuja nada (el hueco, como el
 * `Icon` del diseño).
 */
class RazonesDeIsla
{
    /**
     * @param  array<string, mixed>  $isla  lo que la página da a su isla
     * @return array<string, mixed> lo mismo, con el `svg` de cada razón que nombra un icono
     */
    public static function conDibujos(array $isla): array
    {
        if (! isset($isla['razones']) || ! is_array($isla['razones'])) {
            return $isla;
        }

        foreach ($isla['razones'] as $zona => $razon) {
            if (is_array($razon) && is_string($razon['icon'] ?? null) && ($svg = Lucide::svg($razon['icon'])) !== '') {
                $isla['razones'][$zona]['svg'] = $svg;
            }
        }

        return $isla;
    }
}
