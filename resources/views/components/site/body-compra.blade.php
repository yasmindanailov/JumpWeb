{{-- LAS MARCAS DE LA COMPRA EN EL `<body>` QUE LEE EL CONTROLADOR DEL CAJÓN AL CARGAR (`cajon/controller.js`: `isOpen`,
     `reanudando` y la zona de la cuenta), en los DOS layouts públicos (`components/layout.blade.php` y
     `components/pagina.blade.php`), como `body-state`. Sacadas TAL CUAL del layout de siempre (`#785`): las páginas nuevas
     no las llevaban, y la compra que salía a Google desde Kids o Jump volvía a `?compra=reanudar` y NO se reabría —el
     owner, 26-09: «al volver de Google te lleva a Mi cuenta»—. Un parcial, para que los dos layouts no puedan volver a
     separarse.
     · `data-purchase-open`: la compra ABIERTA al cargar. Tres detonadores: el enlace profundo a `/entradas` (#66), la
       compra que vuelve de Google (`?compra=reanudar`, T3e·4, `Http\Sidebar\PurchaseResume`) y un desenlace de la vuelta
       de Redsys pendiente de enseñar (#104/#106). ⚠️ `peek()` y NO `consume()`: quién consume depende del motor, y
       `Http\Sidebar\SidebarEntry` lo explica en un solo sitio. En una página nueva, `/entradas` y las puertas no aplican.
     · `data-purchase-resume`: esa apertura se cuenta como lo que es (`resume`, no un enlace profundo).
     · `data-account-zone`: la ZONA del área de cliente con la que abrir, cuando se ha entrado por una de las rutas que
       sobreviven a la retirada de `/mi-cuenta/…` (`AccountDoor`). Vacío = no es una puerta. ⚠️ Se CONSUME al abrir. --}}
      data-purchase-open="{{ (((request()->routeIs('entradas') || \App\Http\Sidebar\PurchaseResume::requested()) && $site['sales_online']) || \App\Http\Sidebar\AccountDoor::isDoor() || \App\Http\Sidebar\SidebarEntry::peek()->pending()) ? '1' : '' }}"
      data-purchase-resume="{{ \App\Http\Sidebar\PurchaseResume::requested() && $site['sales_online'] ? '1' : '' }}"
      data-account-zone="{{ \App\Http\Sidebar\AccountDoor::zone() }}"
