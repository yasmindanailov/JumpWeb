# `datos/` — las semillas del catálogo de esta instalación

El catálogo con el que arranca la instalación: productos, zonas, tarifas, complementos, normas.

⚠️⚠️ **Nunca usuarios, pedidos, pagos ni secretos.** Un volcado «entero» de una base de producción trae
datos personales de clientes reales a un repo, y de ahí no se pueden sacar: el historial de git los conserva
aunque se borre el fichero. Se exporta por tablas, y solo las del catálogo.

⚠️ Son semillas de ARRANQUE, no una copia de seguridad. Lo que el negocio cambie después vive en su base de
datos; volver a aplicar estas semillas sobre una instalación en marcha pisa su trabajo.
