"""Genera readme_es.pdf y readme_en.pdf del módulo Shopeame, para el zip y para la ficha de PrestaShop Addons.

Construye un HTML por idioma con las capturas de Doc/Assets y lo imprime a PDF con Chrome sin interfaz. El texto
resume las páginas de Doc/User: si cambian, hay que cambiar también este archivo. La versión se lee de shopeame.php.

Uso: python3 Doc/Addons/readme.py [carpeta de salida]
(por defecto, docs/ del módulo, que va en el zip; los mismos archivos se suben a la ficha)
"""

import os
import pathlib
import re
import subprocess
import sys
import tempfile

# raíz del repositorio: este archivo está en Doc/Addons
REPO = pathlib.Path(__file__).resolve().parents[2]
ASSETS = REPO / 'Doc' / 'Assets'
OUT = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else REPO / 'docs'
CHROME = os.environ.get('CHROME', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')
# misma versión que $this->version del módulo
VERSION = re.search(r"\$this->version = '([^']+)'", (REPO / 'shopeame.php').read_text()).group(1)
# enlace de soporte de la ficha de Addons; la guía de validación pide incluirlo en el zip. Vacío mientras no se
# conozca: el pie remite entonces a la página del módulo sin enlace
SUPPORT_URL = ''
SUPPORT_LINK = f' <a href="{SUPPORT_URL}">{SUPPORT_URL}</a>' if SUPPORT_URL else ''

LOGO = (
    '<svg class="logo" xmlns="http://www.w3.org/2000/svg" viewBox="10 8 74 74"><path fill="currentColor" '
    'd="M47 27C48 22 54 19.5 63 19.5L72 19.5C78 19.7 82 21 81.5 24L79.5 33L60 33L59.5 37L66 37.5C74 38 79.5 41 '
    '79 47L78 56L77 59C75 66 71 69 64 70.5C58 71.5 50 71.5 44 70.5C40 70 37.5 68.5 38 66L40 57L62.5 56.5L63 53L55 '
    '52.5C50 52 47.5 51 45 48.5C43 46.5 42.5 43 43 40ZM22 21L49 20.5L46 24ZM12 28L43 27.5L42 31.5ZM12 35L43 35L40 '
    '38.5ZM13 43L41 43L40 46.5ZM16 50.5L46 50.5L49 53.5ZM14 58L38 58L36 61.5ZM13 65.5L36 65.5L34 68.5Z"/></svg>'
)

CSS = """
@page { size: A4; margin: 16mm 17mm 18mm 17mm; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { font-family: -apple-system, "Helvetica Neue", Arial, sans-serif; font-size: 10.2pt; line-height: 1.5;
       color: #1f2430; margin: 0; }
.cover { display: flex; align-items: center; gap: 14px; padding-bottom: 14px; border-bottom: 3px solid #2f45c5;
         margin-bottom: 14px; }
.logo { width: 46px; height: 46px; color: #2f45c5; flex: none; }
.cover h1 { font-size: 23pt; margin: 0; line-height: 1.1; }
.cover p { margin: 3px 0 0; color: #5b6170; font-size: 10pt; }
.meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 0 0 16px; }
.meta div { background: #f2f4fc; border-radius: 6px; padding: 7px 10px; font-size: 8.8pt; color: #5b6170; }
.meta b { display: block; color: #1f2430; font-size: 10pt; }
.toc { border: 1px solid #dfe3ee; border-radius: 6px; padding: 10px 16px; margin-bottom: 8px; }
.toc h3 { margin: 0 0 4px; font-size: 10pt; text-transform: uppercase; letter-spacing: .04em; color: #5b6170; }
.toc ol { margin: 0; padding-left: 20px; columns: 2; column-gap: 28px; }
.toc li { margin: 1px 0; }
h2 { font-size: 14.5pt; color: #2f45c5; margin: 22px 0 6px; padding-top: 4px; break-after: avoid; }
h3 { font-size: 11.5pt; margin: 16px 0 4px; break-after: avoid; }
p { margin: 5px 0 7px; }
ol, ul { margin: 5px 0 8px; padding-left: 22px; }
li { margin: 2px 0; }
code { font-family: "SF Mono", Menlo, monospace; font-size: 8.8pt; background: #f2f4f7; padding: 1px 4px;
       border-radius: 3px; }
figure { margin: 8px 0 12px; break-inside: avoid; }
figure img { display: block; max-width: 100%; max-height: 105mm; border: 1px solid #dfe3ee; border-radius: 5px; }
figure.small img { max-width: 78%; }
figcaption { font-size: 8.6pt; color: #6b7180; margin-top: 4px; }
.box { border-radius: 6px; padding: 8px 12px; margin: 8px 0 10px; break-inside: avoid; }
.box p { margin: 2px 0; }
.note { background: #eef1fd; border-left: 4px solid #2f45c5; }
.warn { background: #fff6e6; border-left: 4px solid #e0a100; }
table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; font-size: 9.2pt; break-inside: avoid; }
th, td { text-align: left; vertical-align: top; padding: 5px 8px; border-bottom: 1px solid #e4e7ee; }
th { background: #f2f4fc; font-weight: 600; }
.foot { margin-top: 24px; padding-top: 10px; border-top: 1px solid #dfe3ee; font-size: 8.8pt; color: #6b7180; }
.break { break-before: page; }
"""


def fig(path: str, caption: str, small: bool = False) -> str:
    """Devuelve el HTML de una captura de Doc/Assets con su pie."""
    cls = ' class="small"' if small else ''
    return f'<figure{cls}><img src="{(ASSETS / path).as_uri()}"><figcaption>{caption}</figcaption></figure>'


ES = {
    'lang': 'es',
    'title': 'Shopeame para PrestaShop',
    'subtitle': 'Manual de instalación y uso',
    'meta': [('Versión', VERSION), ('PrestaShop', '9.0 o superior'), ('PHP', '8.1 o superior'),
             ('Idiomas', 'Inglés y español')],
    'toc_title': 'Contenido',
    'body': f"""
<h2>1. Qué hace el módulo</h2>
<p>El módulo <b>Shopeame</b> conecta tu tienda PrestaShop con <b>shopea.me</b>, el servicio que lleva los pedidos de
tu tienda online a tu ERP FacturaScripts. Una vez vinculada la tienda, cada pedido nuevo o modificado se avisa a
shopea.me al momento, y shopea.me lo descarga y lo crea en el ERP sin que tengas que copiar nada a mano.</p>
<p>Para que shopea.me pueda leer los pedidos y mantener al día el catálogo, el stock y los precios, el módulo activa
el webservice de PrestaShop y crea una clave solo para shopea.me, con los permisos justos.</p>
<ul>
  <li>Entrada <b>Shopeame</b> en el menú <b>Configurar</b> del panel de PrestaShop.</li>
  <li>Dos formas de vincular la tienda: copiando sus datos en shopea.me o indicando el UUID y el token del ERP.</li>
  <li>Aviso de los pedidos en tiempo real: al crearlos, al cambiar su estado y al editarlos desde el panel.</li>
  <li>Botones para comprobar la conexión, desvincular la tienda y borrar los datos del módulo.</li>
  <li>Descarga de las facturas del ERP de cada pedido en el panel y, para tus clientes, en Mi cuenta.</li>
  <li>Registro de cada aviso en <b>Parámetros Avanzados → Registros/Logs</b>.</li>
  <li>Un fallo al avisar a shopea.me nunca bloquea el pedido: el cliente termina su compra igual.</li>
</ul>

<h2>2. Requisitos</h2>
<ul>
  <li>PrestaShop 9.0 o superior y PHP 8.1 o superior.</li>
  <li>La tienda servida por HTTPS y un servidor con salida a internet.</li>
  <li>Una cuenta en <b>shopea.me</b> con tu ERP ya registrado.</li>
  <li>Un empleado con permisos de administrador para instalar el módulo, porque este activa el webservice y crea su
      clave.</li>
</ul>

<h2>3. Instalación</h2>
<ol>
  <li>Ve a <b>Módulos → Gestor de módulo</b>.</li>
  <li>Pulsa <b>Subir un módulo</b> y arrastra el zip descargado de PrestaShop Addons.</li>
  <li>Espera al aviso de que el módulo se ha instalado correctamente.</li>
</ol>
<p>En el gestor de módulos verás <b>Shopeame</b> (1) con el botón <b>Configurar</b> (2), que abre su página.</p>
{fig('como-instalar-el-modulo-shopeame-en-prestashop/01-modulo-instalado.png',
     'El módulo Shopeame en el gestor de módulos.')}
<p>También aparece la entrada <b>Shopeame</b> (1) en la sección <b>Configurar</b> del menú lateral. Mientras la tienda
no está vinculada, la página <b>Conectar con shopea.me</b> muestra <b>Tu tienda ya está lista para conectar</b> y las
dos formas de vincularla.</p>
{fig('como-instalar-el-modulo-shopeame-en-prestashop/02-menu-shopeame.png',
     'La entrada Shopeame del menú y la página del módulo antes de vincular la tienda.', small=True)}

<h2>4. Vincular la tienda con shopea.me</h2>
<p>Elige una de las dos opciones. Las dos terminan con la tienda creada en tu ERP de shopea.me y la página
<b>Shopeame</b> indicando <b>Tienda vinculada</b>.</p>

<h3>4.1 Opción 1: copiar los datos en shopea.me</h3>
<p>Generas en PrestaShop la clave del webservice y la pegas en shopea.me junto con la URL de la tienda. shopea.me
comprueba que puede conectarse, crea la tienda y envía a PrestaShop los datos de la vinculación.</p>
<ol>
  <li>Ve a <b>Configurar → Shopeame</b>.</li>
  <li>En <b>Opción 1: copiar los datos en shopea.me</b>, pulsa <b>Generar clave de API</b>. Aparece
      <b>Clave de API generada correctamente.</b> y, si el webservice estaba desactivado, el módulo lo activa.</li>
  <li>Pulsa <b>Copiar</b> junto a <b>URL de la tienda</b>.</li>
  <li>Pulsa <b>Copiar</b> junto a <b>Clave del webservice</b>.</li>
</ol>
{fig('como-vincular-prestashop-copiando-los-datos-en-shopeame/02-copiar-url-y-clave.png',
     'La URL de la tienda y la clave del webservice, con sus botones Copiar.', small=True)}
<ol start="5">
  <li>En shopea.me, abre tu ERP, ve a la pestaña de tiendas, pulsa <b>Conectar</b> en <b>Conectar una tienda online o
      marketplace</b> y elige <b>PrestaShop</b>.</li>
  <li>En <b>Paso 2</b>, marca <b>El módulo me muestra la URL de la tienda y la clave del webservice</b>.</li>
  <li>Pega la URL de la tienda en <b>Url de la tienda</b>.</li>
  <li>Pega la clave en <b>Clave de webservice</b>.</li>
  <li>Pulsa <b>Conectar</b>.</li>
</ol>
{fig('como-vincular-prestashop-copiando-los-datos-en-shopeame/03-pegar-datos-en-shopeame.png',
     'Paso 2 de la pantalla de shopea.me para conectar una tienda PrestaShop.')}
<div class="box warn"><p>Trata la clave como una contraseña: con ella se puede acceder a los pedidos, los productos y
los clientes de la tienda. No la borres en <b>Parámetros Avanzados → Webservice</b>: shopea.me dejaría de poder
sincronizar la tienda.</p></div>
<p>Si después de conectar la página <b>Shopeame</b> sigue mostrando las dos opciones, shopea.me no ha podido enviar
los datos a PrestaShop (por ejemplo, porque la tienda no usa HTTPS o un cortafuegos bloquea la petición). Termina la
vinculación con la opción 2: el módulo recupera la tienda ya creada sin duplicarla.</p>

<h3>4.2 Opción 2: vincular indicando tu ERP</h3>
<p>Copias de shopea.me el UUID y el token de tu ERP y los pegas en PrestaShop. El módulo genera la clave del webservice
si aún no existe y registra la tienda en shopea.me.</p>
<ol>
  <li>En shopea.me, abre tu ERP, ve a la pestaña de tiendas, pulsa <b>Conectar</b> en <b>Conectar una tienda online o
      marketplace</b> y elige <b>PrestaShop</b>.</li>
  <li>En <b>Paso 2</b>, deja marcada <b>El módulo me pide un UUID y un token del ERP</b>.</li>
  <li>Copia el <b>UUID del ERP</b> con su botón de copiar.</li>
  <li>Copia el <b>Token del ERP</b> con su botón de copiar. Deja esa página abierta.</li>
  <li>En PrestaShop, ve a <b>Configurar → Shopeame</b>.</li>
  <li>En <b>Opción 2: vincular indicando tu ERP</b>, pega el UUID en <b>UUID del ERP</b>.</li>
  <li>Pega el token en <b>Token del ERP</b>.</li>
  <li>Pulsa <b>Vincular tienda</b>.</li>
</ol>
{fig('como-vincular-prestashop-con-el-uuid-y-el-token-del-erp/02-vincular-tienda.png',
     'Los campos UUID del ERP y Token del ERP y el botón Vincular tienda.', small=True)}
<p>La página indica <b>Tienda vinculada</b> con uno de estos avisos:</p>
<ul>
  <li><b>Tienda vinculada con shopea.me correctamente.</b>: se ha creado la tienda en tu ERP.</li>
  <li><b>La tienda ya estaba vinculada. Se recuperaron sus datos sin crear una tienda nueva.</b></li>
  <li><b>La tienda se reactivó y quedó vinculada con shopea.me correctamente.</b>: se había desvinculado y vuelve con
      su configuración.</li>
</ul>
{fig('como-vincular-prestashop-con-el-uuid-y-el-token-del-erp/03-tienda-vinculada.png',
     'La página Shopeame con la tienda vinculada.')}
<div class="box warn"><p>El token da acceso a tu ERP en shopea.me: no lo compartas.</p></div>

<h2>5. Gestionar la tienda vinculada</h2>
<p>Con la tienda vinculada, la sección <b>Acciones</b> de la página <b>Shopeame</b> tiene tres botones.</p>
{fig('como-comprobar-desvincular-o-borrar-los-datos-de-shopeame-en-prestashop/01-acciones-de-la-tienda.png',
     'Comprobar conexión (1), Desvincular tienda (2) y Borrar datos del módulo (3).', small=True)}
<table>
  <tr><th style="width:28%">Botón</th><th>Qué hace</th></tr>
  <tr><td><b>Comprobar conexión</b></td><td>Pregunta a shopea.me si reconoce la tienda. Responde <b>La conexión con
      shopea.me funciona correctamente.</b> o <b>No se pudo confirmar la conexión con shopea.me.</b></td></tr>
  <tr><td><b>Desvincular tienda</b></td><td>Da de baja la tienda en shopea.me, deja de sincronizar pedidos y borra los
      datos de la vinculación. La clave del webservice se conserva para poder volver a vincularla.</td></tr>
  <tr><td><b>Borrar datos del módulo</b></td><td>Borra solo los datos de la vinculación guardados en PrestaShop, sin
      avisar a shopea.me ni tocar la clave. Úsalo si ya desvinculaste o borraste la tienda en shopea.me o si
      restauraste una copia de la tienda.</td></tr>
</table>
<p>Con la tienda sin vincular, la opción 1 muestra <b>Regenerar clave de API</b>: borra la clave anterior y crea otra.
Después hay que volver a vincular la tienda para que shopea.me reciba la nueva.</p>

<h2>6. Facturas del ERP de los pedidos</h2>
<p>Cuando facturas en el ERP un pedido de la tienda, puedes descargar sus facturas en PDF desde PrestaShop: las
parciales, la que agrupa ese pedido con otros del mismo cliente y las rectificativas. En la ficha del pedido se llaman <b>Facturas del
ERP</b> para no confundirlas con las facturas propias de PrestaShop, que el módulo no toca.</p>
<ul>
  <li><b>Listado de pedidos</b>: el botón <b>Facturas</b> (1) abre la lista del pedido; cada código (2)
      descarga su PDF. Las rectificativas llevan la etiqueta <b>Rectificativa</b> y el total en rojo.</li>
  <li><b>Ficha del pedido</b>: el cuadro <b>Facturas del ERP</b> las muestra al abrirla.</li>
  <li><b>Mi cuenta</b>: tus clientes ven el botón <b>Facturas</b> en su historial de pedidos y la sección
      <b>Facturas</b> en el detalle de cada pedido, solo con sus propios pedidos. Se desactiva desmarcando
      <b>Permitir que los clientes descarguen las facturas de sus pedidos desde Mi cuenta</b> en la página
      <b>Shopeame</b>.</li>
</ul>
{fig('como-descargar-las-facturas-de-un-pedido-en-prestashop/01-listado-de-pedidos.png', 'Facturas del ERP de un pedido desplegadas en el listado de pedidos.')}
<p>Las facturas no se guardan en PrestaShop: shopea.me las pide al ERP cada vez, así que hace falta que el ERP esté
encendido. Si facturas en el ERP, desactiva las facturas de PrestaShop en <b>Pedidos → Facturas</b> para que tus
clientes no vean dos facturas del mismo pedido.</p>

<h2>7. Datos que se envían y permisos</h2>
<ul>
  <li>Antes de vincular la tienda, el módulo no envía nada.</li>
  <li>Al vincular: la URL de la tienda y la clave del webservice y, en la opción 2, el UUID y el token del ERP.</li>
  <li>Con cada pedido nuevo o modificado: solo el número del pedido. El resto de datos los lee shopea.me por el
      webservice.</li>
  <li>Al ver o descargar las facturas de un pedido: el número del pedido y el de la factura.</li>
</ul>
<p>La clave del webservice que crea el módulo solo tiene estos permisos, sin <code>DELETE</code> ni
<code>PATCH</code>:</p>
<table>
  <tr><th style="width:52%">Recursos</th><th style="width:20%">Métodos</th><th>Para qué</th></tr>
  <tr><td><code>products</code>, <code>combinations</code></td><td>GET, POST, PUT</td>
      <td>Productos, variantes y precios</td></tr>
  <tr><td><code>stock_availables</code></td><td>GET, PUT</td><td>Stock</td></tr>
  <tr><td><code>categories</code>, <code>product_options</code>, <code>product_option_values</code></td>
      <td>GET, POST</td><td>Categorías y atributos</td></tr>
  <tr><td><code>orders</code>, <code>order_states</code>, <code>order_payments</code>,
      <code>customers</code>, <code>addresses</code>,
      <code>countries</code>, <code>states</code>, <code>currencies</code>, <code>languages</code>,
      <code>taxes</code>, <code>tax_rule_groups</code></td><td>GET</td><td>Copiar los pedidos al ERP</td></tr>
  <tr><td><code>configurations</code></td><td>GET</td><td>Idioma por defecto y zona horaria</td></tr>
</table>
<p>El módulo no modifica tablas del núcleo ni de otros módulos y no descarga nada después de instalarse.</p>

<h2>8. Resolución de problemas</h2>
<table>
  <tr><th style="width:38%">Aviso o síntoma</th><th>Qué hacer</th></tr>
  <tr><td><b>No se pudo generar la clave de API.</b> o <b>No se pudieron establecer los permisos de la clave de
      API.</b></td><td>Entra con un empleado administrador y vuelve a pulsar <b>Generar clave de API</b>. Revisa en
      <b>Parámetros Avanzados → Webservice</b> que el webservice está activado.</td></tr>
  <tr><td><b>El UUID del ERP no es válido.</b> o <b>El token del ERP no es válido.</b></td><td>El UUID tiene 13
      caracteres y el token 32, solo letras y números. Cópialos de nuevo sin espacios.</td></tr>
  <tr><td><b>No se pudo vincular la tienda: …</b></td><td>shopea.me no acepta los datos del ERP; el texto que sigue
      indica el motivo.</td></tr>
  <tr><td><b>No se pudo conectar con shopea.me. Error: …</b></td><td>El servidor de la tienda no llega a shopea.me.
      Comprueba que tiene salida a internet y repite.</td></tr>
  <tr><td>Los pedidos no llegan al ERP</td><td>Pulsa <b>Comprobar conexión</b>, revisa en shopea.me que la tienda
      está activa y busca en <b>Parámetros Avanzados → Registros/Logs</b> los mensajes que empiezan por
      <code>Shopeame:</code>.</td></tr>
</table>
<p>Cada aviso de pedido deja un mensaje en el registro:</p>
<ul>
  <li><b>Gravedad 1</b> (<code>event sent for order …</code>): el aviso llegó a shopea.me.</li>
  <li><b>Gravedad 2</b> (<code>shopea.me rejected the … event</code>): shopea.me lo rechazó. Si indica
      <code>unauthorized</code> o <code>HTTP 401</code>, pulsa <b>Borrar datos del módulo</b> y vuelve a vincular la
      tienda.</li>
  <li><b>Gravedad 3</b> (<code>could not be sent</code>): no hubo conexión con shopea.me. Los pedidos se recuperan en
      la siguiente sincronización.</li>
</ul>

<h2>9. Actualizar y desinstalar</h2>
<p>Las actualizaciones se instalan desde <b>Módulos → Gestor de módulo</b>, como en cualquier otro módulo.</p>
<p>Al desinstalarlo se borran su configuración, la clave del webservice que creó y la entrada del menú, y shopea.me
deja de tener acceso a la tienda.</p>

<div class="foot">Para cualquier duda, escríbenos desde la página del módulo en PrestaShop Addons.{SUPPORT_LINK}</div>
""",
}

EN = {
    'lang': 'en',
    'title': 'Shopeame for PrestaShop',
    'subtitle': 'Installation and user guide',
    'meta': [('Version', VERSION), ('PrestaShop', '9.0 or later'), ('PHP', '8.1 or later'),
             ('Languages', 'English and Spanish')],
    'toc_title': 'Contents',
    'body': f"""
<h2>1. What the module does</h2>
<p>The <b>Shopeame</b> module connects your PrestaShop store with <b>shopea.me</b>, the service that brings the orders
of your online store into your FacturaScripts ERP. Once the store is linked, every new or modified order is reported to
shopea.me straight away, and shopea.me downloads it and creates it in the ERP, with no manual copying.</p>
<p>So that shopea.me can read the orders and keep the catalog, stock and prices up to date, the module enables the
PrestaShop webservice and creates a key just for shopea.me, with only the permissions it needs.</p>
<ul>
  <li>A <b>Shopeame</b> entry in the <b>Configure</b> menu of the PrestaShop back office.</li>
  <li>Two ways to link the store: copying its details into shopea.me, or entering the ERP UUID and token.</li>
  <li>Real-time order notifications when an order is created, changes status or is edited in the back office.</li>
  <li>Buttons to check the connection, unlink the store and delete the module data.</li>
  <li>Download of the ERP invoices of each order in the back office and, for your customers, in My account.</li>
  <li>Every notification is logged in <b>Advanced Parameters → Logs</b>.</li>
  <li>A failed notification never blocks the order: the customer still completes the purchase.</li>
</ul>

<h2>2. Requirements</h2>
<ul>
  <li>PrestaShop 9.0 or later and PHP 8.1 or later.</li>
  <li>The store served over HTTPS, on a server with outbound internet access.</li>
  <li>A <b>shopea.me</b> account with your ERP already registered.</li>
  <li>An employee with administrator permissions to install the module, because it enables the webservice and creates
      its key.</li>
</ul>

<h2>3. Installation</h2>
<ol>
  <li>Go to <b>Modules → Module Manager</b>.</li>
  <li>Click <b>Upload a module</b> and drop the zip file downloaded from PrestaShop Addons.</li>
  <li>Wait for the message confirming that the module was installed.</li>
</ol>
<p>The Module Manager now lists <b>Shopeame</b> (1) with a <b>Configure</b> button (2) that opens its page.</p>
{fig('como-instalar-el-modulo-shopeame-en-prestashop/01-modulo-instalado.png',
     'The Shopeame module in the Module Manager.')}
<p>A <b>Shopeame</b> entry (1) also appears in the <b>Configure</b> section of the side menu. While the store is not
linked, the <b>Connect with shopea.me</b> page shows <b>Your store is ready to connect</b> and the two ways to link
it.</p>
{fig('como-instalar-el-modulo-shopeame-en-prestashop/02-menu-shopeame.png',
     'The Shopeame menu entry and the module page before linking the store.', small=True)}

<h2>4. Linking the store with shopea.me</h2>
<p>Choose one of the two options. Both end with the store created in your shopea.me ERP and the <b>Shopeame</b> page
showing <b>Linked store</b>.</p>
<div class="box note"><p>The screenshots in this guide show the back office in Spanish. The labels quoted in the text
are the ones you will see with the back office in English. The shopea.me screens may be shown in Spanish; their
labels are given in English with the original text in brackets.</p></div>

<h3>4.1 Option 1: copy the data into shopea.me</h3>
<p>You generate the webservice key in PrestaShop and paste it into shopea.me together with the store URL. shopea.me
checks that it can connect, creates the store and sends the linking data back to PrestaShop.</p>
<ol>
  <li>Go to <b>Configure → Shopeame</b>.</li>
  <li>Under <b>Option 1: copy the data into shopea.me</b>, click <b>Generate API key</b>. The message <b>API key
      generated successfully.</b> appears and, if the webservice was disabled, the module enables it.</li>
  <li>Click <b>Copy</b> next to <b>Store URL</b>.</li>
  <li>Click <b>Copy</b> next to <b>Webservice key</b>.</li>
</ol>
{fig('como-vincular-prestashop-copiando-los-datos-en-shopeame/02-copiar-url-y-clave.png',
     'The store URL and the webservice key, with their Copy buttons.', small=True)}
<ol start="5">
  <li>In shopea.me, open your ERP, go to the stores tab, click <b>Connect</b> (<i>Conectar</i>) under <b>Connect an
      online store or marketplace</b> (<i>Conectar una tienda online o marketplace</i>) and choose
      <b>PrestaShop</b>.</li>
  <li>In <b>Step 2</b> (<i>Paso 2</i>), select <b>The module shows me the store URL and the webservice key</b>
      (<i>El módulo me muestra la URL de la tienda y la clave del webservice</i>).</li>
  <li>Paste the store URL into <b>Store URL</b> (<i>Url de la tienda</i>).</li>
  <li>Paste the key into <b>Webservice key</b> (<i>Clave de webservice</i>).</li>
  <li>Click <b>Connect</b> (<i>Conectar</i>).</li>
</ol>
{fig('como-vincular-prestashop-copiando-los-datos-en-shopeame/03-pegar-datos-en-shopeame.png',
     'Step 2 of the shopea.me screen to connect a PrestaShop store.')}
<div class="box warn"><p>Treat the key like a password: it gives access to the orders, products and customers of the
store. Do not delete it in <b>Advanced Parameters → Webservice</b>, or shopea.me will no longer be able to sync the
store.</p></div>
<p>If, after connecting, the <b>Shopeame</b> page still shows both options, shopea.me could not send the data to
PrestaShop (for example, because the store does not use HTTPS or a firewall blocks the request). Finish linking with
option 2: the module recovers the store already created, without duplicating it.</p>

<h3>4.2 Option 2: link using your ERP details</h3>
<p>You copy your ERP UUID and token from shopea.me and paste them into PrestaShop. The module generates the webservice
key if it does not exist yet and registers the store on shopea.me.</p>
<ol>
  <li>In shopea.me, open your ERP, go to the stores tab, click <b>Connect</b> (<i>Conectar</i>) under <b>Connect an
      online store or marketplace</b> and choose <b>PrestaShop</b>.</li>
  <li>In <b>Step 2</b>, keep <b>The module asks me for an ERP UUID and token</b> (<i>El módulo me pide un UUID y un
      token del ERP</i>) selected.</li>
  <li>Copy the <b>ERP UUID</b> (<i>UUID del ERP</i>) with its copy button.</li>
  <li>Copy the <b>ERP token</b> (<i>Token del ERP</i>) with its copy button. Leave that page open.</li>
  <li>In PrestaShop, go to <b>Configure → Shopeame</b>.</li>
  <li>Under <b>Option 2: link using your ERP details</b>, paste the UUID into <b>ERP UUID</b>.</li>
  <li>Paste the token into <b>ERP token</b>.</li>
  <li>Click <b>Link store</b>.</li>
</ol>
{fig('como-vincular-prestashop-con-el-uuid-y-el-token-del-erp/02-vincular-tienda.png',
     'The ERP UUID and ERP token fields and the Link store button.', small=True)}
<p>The page shows <b>Linked store</b> with one of these messages:</p>
<ul>
  <li><b>Store linked with shopea.me successfully.</b>: the store was created in your ERP.</li>
  <li><b>The store was already linked. Its data was recovered without creating a new store.</b></li>
  <li><b>The store was reactivated and linked with shopea.me successfully.</b>: it had been unlinked and comes back
      with its settings.</li>
</ul>
{fig('como-vincular-prestashop-con-el-uuid-y-el-token-del-erp/03-tienda-vinculada.png',
     'The Shopeame page with the store linked.')}
<div class="box warn"><p>The token gives access to your ERP on shopea.me: do not share it.</p></div>

<h2>5. Managing the linked store</h2>
<p>With the store linked, the <b>Actions</b> section of the <b>Shopeame</b> page has three buttons.</p>
{fig('como-comprobar-desvincular-o-borrar-los-datos-de-shopeame-en-prestashop/01-acciones-de-la-tienda.png',
     'Check connection (1), Unlink store (2) and Delete module data (3).', small=True)}
<table>
  <tr><th style="width:28%">Button</th><th>What it does</th></tr>
  <tr><td><b>Check connection</b></td><td>Asks shopea.me whether it recognises the store. It answers <b>The connection
      with shopea.me is working correctly.</b> or <b>The connection with shopea.me could not be confirmed.</b></td></tr>
  <tr><td><b>Unlink store</b></td><td>Removes the store from shopea.me, stops syncing orders and deletes the linking
      data. The webservice key is kept so the store can be linked again.</td></tr>
  <tr><td><b>Delete module data</b></td><td>Deletes only the linking data saved in PrestaShop, without notifying
      shopea.me or touching the key. Use it if you already unlinked or deleted the store on shopea.me, or if you
      restored a backup of the store.</td></tr>
</table>
<p>While the store is not linked, option 1 shows <b>Regenerate API key</b>: it deletes the previous key and creates a
new one. You then need to link the store again so that shopea.me receives the new key.</p>

<h2>6. ERP invoices of the orders</h2>
<p>When you invoice a store order in the ERP, you can download its invoices as PDF from PrestaShop: partial invoices,
the invoice that groups the order with other orders of the same customer, and corrective invoices. On the order page they are called
<b>ERP invoices</b> so they are not confused with PrestaShop's own invoices, which the module does not touch.</p>
<ul>
  <li><b>Orders list</b>: the <b>Invoices</b> button (1) opens the list of the order; each code (2) downloads its
      PDF. Corrective invoices have the <b>Corrective</b> label and the total in red.</li>
  <li><b>Order page</b>: the <b>ERP invoices</b> box shows them when the page opens.</li>
  <li><b>My account</b>: your customers see the <b>Invoices</b> button in their order history and the <b>Invoices</b>
      section on each order page, only for their own orders. Untick <b>Allow customers to download the invoices of
      their orders from My account</b> on the <b>Shopeame</b> page to turn it off.</li>
</ul>
{fig('como-descargar-las-facturas-de-un-pedido-en-prestashop/01-listado-de-pedidos.png', 'ERP invoices of an order opened in the orders list (back office in Spanish).')}
<p>The invoices are not stored in PrestaShop: shopea.me requests them from the ERP each time, so the ERP must be
online. If you invoice in the ERP, turn off the PrestaShop invoices in <b>Orders → Invoices</b> so your customers do not
see two invoices for the same order.</p>

<h2>7. Data sent and permissions</h2>
<ul>
  <li>Before the store is linked, the module sends nothing.</li>
  <li>When linking: the store URL and the webservice key and, with option 2, the ERP UUID and token.</li>
  <li>For each new or modified order: only the order number. shopea.me reads the rest of the data through the
      webservice.</li>
</ul>
<p>The webservice key created by the module only has these permissions, with no <code>DELETE</code> or
<code>PATCH</code>:</p>
<table>
  <tr><th style="width:52%">Resources</th><th style="width:20%">Methods</th><th>Used for</th></tr>
  <tr><td><code>products</code>, <code>combinations</code></td><td>GET, POST, PUT</td>
      <td>Products, combinations and prices</td></tr>
  <tr><td><code>stock_availables</code></td><td>GET, PUT</td><td>Stock</td></tr>
  <tr><td><code>categories</code>, <code>product_options</code>, <code>product_option_values</code></td>
      <td>GET, POST</td><td>Categories and attributes</td></tr>
  <tr><td><code>orders</code>, <code>order_states</code>, <code>order_payments</code>,
      <code>customers</code>, <code>addresses</code>,
      <code>countries</code>, <code>states</code>, <code>currencies</code>, <code>languages</code>,
      <code>taxes</code>, <code>tax_rule_groups</code></td><td>GET</td><td>Copying orders to the ERP</td></tr>
  <tr><td><code>configurations</code></td><td>GET</td><td>Default language and time zone</td></tr>
</table>
<p>The module does not modify core tables or other modules, and it downloads nothing after installation.</p>

<h2>8. Troubleshooting</h2>
<table>
  <tr><th style="width:38%">Message or symptom</th><th>What to do</th></tr>
  <tr><td><b>The API key could not be generated.</b> or <b>The API key permissions could not be set.</b></td>
      <td>Log in with an administrator employee and click <b>Generate API key</b> again. Check in <b>Advanced
      Parameters → Webservice</b> that the webservice is enabled.</td></tr>
  <tr><td><b>The ERP UUID is not valid.</b> or <b>The ERP token is not valid.</b></td><td>The UUID has 13 characters
      and the token 32, letters and numbers only. Copy them again without spaces.</td></tr>
  <tr><td><b>The store could not be linked: …</b></td><td>shopea.me does not accept the ERP details; the text that
      follows gives the reason.</td></tr>
  <tr><td><b>Could not connect to shopea.me. Error: …</b></td><td>The store server cannot reach shopea.me. Check that
      it has internet access and try again.</td></tr>
  <tr><td>Orders do not reach the ERP</td><td>Click <b>Check connection</b>, check on shopea.me that the store is
      active, and look in <b>Advanced Parameters → Logs</b> for messages starting with <code>Shopeame:</code>.</td></tr>
</table>
<p>Every order notification leaves a log message:</p>
<ul>
  <li><b>Severity 1</b> (<code>event sent for order …</code>): the notification reached shopea.me.</li>
  <li><b>Severity 2</b> (<code>shopea.me rejected the … event</code>): shopea.me rejected it. If it says
      <code>unauthorized</code> or <code>HTTP 401</code>, click <b>Delete module data</b> and link the store
      again.</li>
  <li><b>Severity 3</b> (<code>could not be sent</code>): shopea.me could not be reached. The orders are picked up in
      the next sync.</li>
</ul>

<h2>9. Updating and uninstalling</h2>
<p>Updates are installed from <b>Modules → Module Manager</b>, like any other module.</p>
<p>Uninstalling removes its configuration, the webservice key it created and the menu entry, and shopea.me no longer
has access to the store.</p>

<div class="foot">For any question, contact us from the module page on PrestaShop Addons.{SUPPORT_LINK}</div>
""",
}

TOC = {
    'es': ['Qué hace el módulo', 'Requisitos', 'Instalación', 'Vincular la tienda con shopea.me',
           'Gestionar la tienda vinculada', 'Datos que se envían y permisos', 'Resolución de problemas',
           'Actualizar y desinstalar'],
    'en': ['What the module does', 'Requirements', 'Installation', 'Linking the store with shopea.me',
           'Managing the linked store', 'Data sent and permissions', 'Troubleshooting',
           'Updating and uninstalling'],
}


def build(doc: dict, work: pathlib.Path) -> None:
    """Escribe el HTML de un idioma en la carpeta temporal y lo imprime como readme_<idioma>.pdf."""
    meta = ''.join(f'<div>{k}<b>{v}</b></div>' for k, v in doc['meta'])
    toc = ''.join(f'<li>{t}</li>' for t in TOC[doc['lang']])
    html = f"""<!doctype html><html lang="{doc['lang']}"><head><meta charset="utf-8">
<title>{doc['title']}</title><style>{CSS}</style></head><body>
<div class="cover">{LOGO}<div><h1>{doc['title']}</h1><p>{doc['subtitle']}</p></div></div>
<div class="meta">{meta}</div>
<div class="toc"><h3>{doc['toc_title']}</h3><ol>{toc}</ol></div>
{doc['body']}
</body></html>"""
    src = work / f"readme_{doc['lang']}.html"
    src.write_text(html, encoding='utf-8')
    pdf = OUT / f"readme_{doc['lang']}.pdf"
    subprocess.run([CHROME, '--headless', '--disable-gpu', '--no-pdf-header-footer',
                    f'--print-to-pdf={pdf}', src.as_uri()], check=True, capture_output=True)
    print(pdf)


OUT.mkdir(parents=True, exist_ok=True)
with tempfile.TemporaryDirectory() as tmp:
    build(ES, pathlib.Path(tmp))
    build(EN, pathlib.Path(tmp))
