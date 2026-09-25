<?php

namespace App\Http\Fiesta;

/**
 * LOS TEMAS de la invitación (`invitados/InviteCard.jsx` → `INVITE_THEMES`), con ROLES `--fiesta-*` en vez de los
 * primitivos del parque (`specs/fiesta-sistema-nuevo.md` §4.3). Una sola tabla para la tarjeta (`x-fiesta.invitacion`)
 * y para la página de la invitación, cuyo fondo toma el tinte del tema. Solo colores del parque: el naranja es de la
 * acción y no entra en una invitación. Confeti es el defecto; con el kit de ilustración, hasta seis: cada tema nuevo es
 * una entrada más aquí, con su `art`. Cada tema trae su entrada, una vez: confeti cae; fiesta cuelga banderines y el
 * confeti estalla desde la chapa; sereno sube unas burbujas despacio.
 */
final class Temas
{
    /** @var array<string, array{band: string, chip: string, chipFg: string, accent: string, tint: string, bits: list<string>, decor: string, entrance: string}> */
    public const MAPA = [
        'confeti' => ['band' => 'var(--fiesta-agua-500)', 'chip' => 'var(--fiesta-sol-500)', 'chipFg' => 'var(--fiesta-tinta-900)', 'accent' => 'var(--fiesta-agua-700)', 'tint' => 'var(--fiesta-agua-100)', 'bits' => ['var(--fiesta-sol-500)', 'var(--fiesta-baya-500)', 'var(--fiesta-lima-500)', 'var(--fiesta-nieve)'], 'decor' => 'confeti', 'entrance' => 'fall'],
        'fiesta' => ['band' => 'var(--fiesta-baya-500)', 'chip' => 'var(--fiesta-lima-500)', 'chipFg' => 'var(--fiesta-tinta-900)', 'accent' => 'var(--fiesta-baya-700)', 'tint' => 'var(--fiesta-baya-100)', 'bits' => ['var(--fiesta-sol-500)', 'var(--fiesta-lima-500)', 'var(--fiesta-agua-400)', 'var(--fiesta-nieve)'], 'decor' => 'fiesta', 'entrance' => 'pop'],
        'sereno' => ['band' => 'var(--fiesta-agua-100)', 'chip' => 'var(--fiesta-tinta-900)', 'chipFg' => 'var(--fiesta-nieve)', 'accent' => 'var(--fiesta-agua-700)', 'tint' => 'var(--fiesta-tinta-050)', 'bits' => ['var(--fiesta-agua-600)', 'var(--fiesta-agua-400)', 'var(--fiesta-tinta-300)'], 'decor' => 'burbujas', 'entrance' => 'rise'],
    ];

    /**
     * El tema pedido, o confeti si no existe (un tema retirado de la lista no deja la tarjeta sin vestir).
     *
     * @return array{band: string, chip: string, chipFg: string, accent: string, tint: string, bits: list<string>, decor: string, entrance: string}
     */
    public static function de(?string $tema): array
    {
        return self::MAPA[$tema ?? ''] ?? self::MAPA['confeti'];
    }
}
