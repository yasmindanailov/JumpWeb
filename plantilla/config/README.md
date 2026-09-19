# `config/` — la configuración NO secreta de esta instalación

Lo que distingue a esta instalación y se puede leer sin riesgo: idiomas activos, fuentes del tema, rutas del
material propio.

⚠️⚠️ **Aquí no entra ni un secreto.** Las credenciales de Redsys, las de Google y las claves de la aplicación
viven en el `.env` del servidor, que no está en ningún repo. Un secreto en un repo de instancia es un
secreto publicado, aunque el repo sea privado: se queda en el historial para siempre.

⚠️ Y los ajustes que el negocio OPERA (horario, aforo, precios, textos legales) no están aquí tampoco: viven
en la base de datos y se tocan desde el panel. Si algo se puede cambiar sin desplegar, no es configuración.
