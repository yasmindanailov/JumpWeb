<?php

namespace Tests\Support;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Platform\Models\Setting;

/**
 * `#441` — **cómo se construye hoy un menor SIN firma**, que es el sujeto de una parte grande de la
 * suite (`specs/firma-al-declarar-menor.md` §6.3).
 *
 * Desde que declarar y aceptar son un solo gesto, `DependentRegistry::add()` **exige la aceptación**
 * donde la exención se gestiona dentro y hay texto publicado. Los casos que necesitan un menor sin
 * firma —el estado de la exención en la puerta, la ficha del titular, el asignador, la privacidad—
 * dejarían de tener sujeto si se limitaran a llamar al escritor.
 *
 * ⚠️⚠️ **Y no es un truco del arnés: es la ficha HEREDADA.** Un menor sin firma sigue existiendo en
 * el producto por dos caminos —el declarado antes de esta tanda, y el que queda `outdated` cuando se
 * publica una versión nueva—, y son exactamente los que la tarjeta del cajón sigue atendiendo. Lo que
 * este helper reproduce es el primero: se declara con el modo apagado y se devuelve como estaba.
 *
 * ▶ **Lo que NO hace es saltarse el escritor.** Sigue pasando por `DependentRegistry`, así que el
 * tope, el lock, la minoría y la validación de la relación se ejercen igual. Un fixture que creara la
 * fila con `Dependent::create()` mediría un mundo en el que esas reglas no existen.
 */
trait DeclaresDependents
{
    /** Un menor declarado como los de antes de `#441`: sin aceptación y, por tanto, sin firma. */
    protected function declareLegacyDependent(
        User $holder,
        string $name = 'Lior',
        string $bornOn = '2017-03-12',
        string $surname = '',
        ?string $relationship = null,
    ): Dependent {
        $antes = Setting::query()->where('key', 'waiver.mode')->value('value');

        $this->setWaiverMode('externo');

        try {
            return app(DependentRegistry::class)->add($holder, $name, $bornOn, $surname, $relationship);
        } finally {
            $this->setWaiverMode(is_string($antes) && $antes !== '' ? $antes : 'externo');
        }
    }

    /**
     * ⚠️ `flushMemo()` **siempre**: `Setting::value()` memoiza la tabla entera, así que sin él el
     * escritor seguiría leyendo el modo de antes y el helper no haría nada — en verde.
     */
    private function setWaiverMode(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => $mode, 'group' => 'waiver']);
        Setting::flushMemo();
    }
}
