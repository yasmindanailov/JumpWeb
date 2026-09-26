/*
 * LA AUTORIZACIÓN de un menor invitado — el comportamiento de la página (`specs/fiesta-sistema-nuevo.md` §4.5, T3).
 * Lo poco que `AutPagina` hace con React y aquí se hace a mano: el selector de menores a cargo (rellena la ficha y NO
 * envía; «a mano» la vacía), «Firmando» mientras el formulario viaja, y el foco en el primer campo con error cuando el
 * servidor devuelve la hoja. Los dos últimos viven en `comun.js` desde F6a: el recibo firma con el mismo formulario.
 * (El idioma, desde `#748`, son enlaces al pie: sin JavaScript.) ⚠️ Nada de esto ESCRIBE: escribe el formulario. Sin
 * JavaScript firma igual.
 */
import './fiesta.css';
import { arranca, firma, menores } from './comun.js';

arranca(menores, firma);
