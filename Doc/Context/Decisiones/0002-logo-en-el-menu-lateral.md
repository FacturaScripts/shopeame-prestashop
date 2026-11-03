# 0002. Logo de Shopeame en el menú lateral

- **Fecha**: 03/11/2026
- **Aprobada por**: el usuario, al pedir el logo de Shopeame en el menú lateral de PrestaShop, como en WooCommerce.

## Contexto

El campo `icon` de una pestaña (`Tab`) solo admite el nombre de un icono de Material Icons: las plantillas del menú
(`nav_bar.html.twig` en las páginas Symfony y `nav.tpl` en las antiguas) lo pintan como
`<i class="material-icons mi-<icon>"><icon></i>` dentro de `li#subtab-<class_name>`, y solo en el segundo nivel del
menú. No hay forma de indicar una imagen propia.

## Decisión

- La pestaña conserva `icon = settings` como reserva.
- `views/css/menu.css` oculta el texto de la ligadura (`font-size: 0`) del icono de `#subtab-Shopeame` y dibuja el logo
  (`views/img/menu-icon.svg`, el mismo SVG monocromo que usa el plugin de WooCommerce) como `mask` con
  `background-color: currentColor`, de modo que toma el color del enlace en reposo, al pasar el ratón y activo.
- El SVG va incrustado como `data:` en el CSS porque PrestaShop puede combinar las hojas de estilo y cambiar las rutas
  relativas. Si se cambia el SVG, hay que copiarlo también en el CSS.
- La misma regla sustituye el icono de la miga de pan de la página del módulo
  (`body.shopeame .breadcrumb-item.active > i.material-icons`; PrestaShop pone en el `body` la clase del controlador).
- El CSS se carga con el hook `actionAdminControllerSetMedia` (`addCSS()`), que PrestaShop 9 ejecuta tanto en las
  páginas antiguas como en las Symfony. Se carga en todo el back-office porque el menú está en todas las páginas; son
  unas pocas líneas, sin JavaScript.

## Alternativas descartadas

- Crear un icono de Material Icons propio: no es posible sin modificar la fuente del núcleo.
- Inyectar el logo con JavaScript: más pesado y con parpadeo al cargar.

## Resultado

Comprobado en PrestaShop 9.1.4 en una página antigua (Inicio) y en la página Symfony del módulo, en el menú y en la
miga de pan.
