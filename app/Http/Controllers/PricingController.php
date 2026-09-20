<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\RateTable;
use App\Domain\Content\Services\ScheduleDisplay;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\QrCode;
use App\Http\Instancia\InstanceViews;

class PricingController extends Controller
{
    public function __construct(private readonly InstanceViews $instancia) {}

    /**
     * ⚠️ Desde `#658` (F5 · T2b) la vista vive en la INSTANCIA (`web/precios.blade.php`) y el producto sirve
     * `anfitrion/precios` sin paquete. Lo que se pasa es el CONTRATO de la vista
     * (`InstanceViews::CONTRATO_DE_VISTAS`): todo llega COMPUESTO, y la landing solo lo pinta.
     */
    public function __invoke(ScheduleDisplay $schedule)
    {
        /*
         * Solo ENTRADAS en la tabla (igual que la sección 02 de la portada): los packs tienen su
         * página —a la que esta lleva con una línea al final— y los complementos no son tarifas.
         * `inOperationalZone()`: NO anunciar entradas de una zona desactivada (espejo de packs y del
         * cajón; Sistema 6 · W4).
         *
         * ⚠️⚠️ **`priceTiers` viaja cargado y no es una precaución vacía**: la tabla pregunta el
         * precio de CADA tarifa por separado (`displayPriceCentsForRate`), que mira primero los
         * tramos — sin el `with`, una consulta por producto y por complemento.
         */
        $entradas = TicketType::with([
            'prices.rateType', 'priceTiers',
            'addons.prices.rateType', 'addons.priceTiers',
        ])
            ->ofType(TicketType::TYPE_ENTRY)
            ->where('is_active', true)->inOperationalZone()->orderBy('position')->get();

        $zonas = Zone::where('show_in_landing', true)->orderBy('position')->get();
        $tabla = new RateTable;

        /*
         * La tarjeta del QR solo existe con registro EXTERNO (`registration.url`), que es el modelo
         * de otra instalación: aquí no se pinta y el SVG ni se genera. El canvas no la dibuja porque
         * este parque registra dentro, y retirarla dejaría **sin ninguna pantalla** a un mecanismo
         * del producto que sigue vivo (`#531`).
         */
        $registro = (string) (Setting::value('registration.url') ?? '');

        /*
         * ❗❗ **`tickets`, `zones` y `registrationUrl` se pasaban y la vista NO leía ninguna** (medido al
         * mudarla, `#658`). Un dato que viaja sin consumidor no se nota —nada falla— hasta que se convierte
         * en CONTRATO: declararlo en `CONTRATO_DE_VISTAS` sería prometerle a cada instancia algo que nadie
         * pidió, y retirarlo después subiría el MAYOR del contrato sin motivo. Se retiran ANTES de
         * prometerlos. Las dos colecciones siguen aquí porque son la ENTRADA de `RateTable::compose()`.
         */
        return view($this->instancia->pick('precios', 'anfitrion.precios'), [
            // La rebaja YA aplicada al catálogo, para el «antes» tachado (chapuza declarada,
            // `Setting::promoPercent()`); 0 = la tabla de siempre.
            'rateTable' => $tabla->compose($zonas, $entradas, Setting::promoPercent()),
            'week' => $tabla->week(),
            'colNormal' => $tabla->normalColumnLabel(),
            'colSpecial' => $tabla->specialColumnLabel(),
            'specialLabel' => $tabla->specialLabel(),
            'plainDays' => $tabla->plainDays(),
            /*
             * Las fechas especiales, ENTERAS. Viven aquí y no en la 07 porque son de PRECIO: la
             * portada anuncia la que viene y esta página publica el calendario (`doc/paginas.md`:
             * «los festivos enteros viven aquí, no en 07»). Vacío = no se pinta el bloque.
             */
            'holidays' => $schedule->pricingCalendar(),
            'registrationSvg' => $registro === '' ? null : QrCode::svg($registro),
        ]);
    }
}
