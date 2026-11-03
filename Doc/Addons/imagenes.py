"""Compone las imágenes de 1200 × 1200 de la ficha de PrestaShop Addons a partir de Doc/Addons/capturas.

Cada imagen lleva un titular en español o en inglés sobre el color de Shopeame y una captura del back-office en una
ventana; la última resume las ventajas del módulo sin captura. Addons pide al menos 1000 × 1000 px. Las capturas se
hacen a mano con cdp.mjs (ver Doc/Context/04-publicacion-en-prestashop-addons.md, 4.6).

Uso: python3 Doc/Addons/imagenes.py [carpeta de salida]
(por defecto, zip-plugins/shopeame-prestashop-addons/imagenes junto al repositorio; crea es/ y en/)
"""

import os
import pathlib
import struct
import subprocess
import sys
import tempfile

HERE = pathlib.Path(__file__).resolve().parent
RAW = HERE / 'capturas'
# raíz del repositorio: este archivo está en Doc/Addons
REPO = HERE.parents[1]
DEFAULT_OUT = REPO.parent / 'zip-plugins' / 'shopeame-prestashop-addons' / 'imagenes'
OUT = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_OUT
CHROME = os.environ.get('CHROME', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')
SIZE = 1200

LOGO = (
    '<svg class="logo" viewBox="10 8 74 74"><path fill="currentColor" d="M47 27C48 22 54 19.5 63 19.5L72 19.5C78 '
    '19.7 82 21 81.5 24L79.5 33L60 33L59.5 37L66 37.5C74 38 79.5 41 79 47L78 56L77 59C75 66 71 69 64 70.5C58 71.5 50 '
    '71.5 44 70.5C40 70 37.5 68.5 38 66L40 57L62.5 56.5L63 53L55 52.5C50 52 47.5 51 45 48.5C43 46.5 42.5 43 43 '
    '40ZM22 21L49 20.5L46 24ZM12 28L43 27.5L42 31.5ZM12 35L43 35L40 38.5ZM13 43L41 43L40 46.5ZM16 50.5L46 50.5L49 '
    '53.5ZM14 58L38 58L36 61.5ZM13 65.5L36 65.5L34 68.5Z"/></svg>'
)

# iconos de trazo de 24 × 24 para la imagen de ventajas
ICONS = {
    'bolt': '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    'shield': '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    'cart': '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>'
            '<path d="M2 3h3l2.5 12h11L21 7H6.2"/><path d="m11 10 2 2 3-3"/>',
    'broom': '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/><path d="M10 10v6M14 10v6"/>',
}

CSS = f"""
* {{ box-sizing: border-box; margin: 0; }}
html, body {{ width: {SIZE}px; height: {SIZE}px; overflow: hidden; }}
body {{ font-family: -apple-system, "Helvetica Neue", Arial, sans-serif; color: #fff;
       background: radial-gradient(circle at 85% 0%, #4d63e0 0%, #2f45c5 45%, #1f2f96 100%); }}
.top {{ padding: 64px 72px 0; }}
.brand {{ display: flex; align-items: center; gap: 12px; font-size: 24px; font-weight: 600; opacity: .95; }}
.logo {{ width: 38px; height: 38px; color: #fff; }}
h1 {{ font-size: 54px; line-height: 1.08; font-weight: 800; letter-spacing: -.02em; margin-top: 34px; }}
p.sub {{ font-size: 25px; line-height: 1.4; margin-top: 18px; color: rgba(255,255,255,.82); max-width: 980px; }}
.win {{ position: absolute; left: 72px; right: 72px; background: #fff; border-radius: 18px; overflow: hidden;
        box-shadow: 0 30px 70px rgba(5, 12, 60, .45); }}
.bar {{ height: 38px; background: #eef0f6; display: flex; align-items: center; gap: 8px; padding: 0 16px; }}
.bar i {{ width: 12px; height: 12px; border-radius: 50%; background: #c9cedb; }}
.win img {{ display: block; width: 100%; }}
.crop {{ overflow: hidden; }}
.pad {{ padding: 26px; background: #f5f6fa; }}
.pad img {{ border-radius: 10px; box-shadow: 0 2px 10px rgba(20,30,80,.12); }}
.grid {{ position: absolute; left: 72px; right: 72px; top: 400px; display: grid; grid-template-columns: 1fr 1fr;
         gap: 28px; }}
.tile {{ background: #fff; color: #1f2430; border-radius: 18px; padding: 34px 32px; min-height: 330px;
         box-shadow: 0 24px 50px rgba(5, 12, 60, .35); }}
.tile svg {{ width: 54px; height: 54px; padding: 12px; border-radius: 14px; background: #eef1fd; color: #2f45c5;
             fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }}
.tile h2 {{ font-size: 30px; margin: 22px 0 10px; letter-spacing: -.01em; }}
.tile p {{ font-size: 24px; line-height: 1.45; color: #4b5263; }}
"""

# cada imagen: archivo, captura, recorte (fracción de alto que se muestra o None), con marco interior y textos
SLIDES = [
    {'file': '01-portada', 'img': '01-sin-vincular.png', 'crop': 1.0, 'pad': False,
     'es': ('Lleva los pedidos de PrestaShop a tu ERP',
            'Shopeame conecta tu tienda con FacturaScripts a través de shopea.me.'),
     'en': ('Bring your PrestaShop orders into your ERP',
            'Shopeame connects your store to FacturaScripts through shopea.me.')},
    {'file': '02-vincular-copiando-datos', 'img': '02-opcion-1.png', 'crop': None, 'pad': True,
     'es': ('Vincula la tienda en dos minutos',
            'Copia la URL de la tienda y la clave del webservice y pégalas en shopea.me.'),
     'en': ('Link your store in two minutes',
            'Copy the store URL and the webservice key and paste them into shopea.me.')},
    {'file': '03-vincular-con-uuid-y-token', 'img': '03-opcion-2.png', 'crop': None, 'pad': True,
     'es': ('O vincúlala desde PrestaShop',
            'Pega el UUID y el token de tu ERP y el módulo registra la tienda por ti.'),
     'en': ('Or link it from PrestaShop',
            'Paste your ERP UUID and token and the module registers the store for you.')},
    {'file': '04-tienda-vinculada', 'img': '04-tienda-vinculada.png', 'crop': 0.58, 'pad': False,
     'es': ('Pedidos sincronizados automáticamente',
            'Comprueba la conexión, desvincula la tienda o borra sus datos con un clic.'),
     'en': ('Orders synced automatically',
            'Check the connection, unlink the store or clear its data in one click.')},
    {'file': '05-registros', 'img': '05-registros.png', 'crop': None, 'pad': True,
     'es': ('Cada aviso queda registrado',
            'Consulta en Registros/Logs qué pedidos se han enviado a shopea.me.'),
     'en': ('Every notification is logged',
            'See in Advanced Parameters → Logs which orders were sent to shopea.me.')},
]

FEATURES = {
    'es': ('Seguro y sin sorpresas', 'Pensado para no interferir con tu tienda.', [
        ('bolt', 'Pedidos al momento', 'Avisa a shopea.me al crear, editar o cambiar el estado de un pedido.'),
        ('shield', 'Permisos mínimos', 'La clave del webservice no puede borrar nada ni ver empleados o carritos.'),
        ('cart', 'Nunca bloquea la compra', 'Si shopea.me no responde, el cliente termina su pedido igual.'),
        ('broom', 'Desinstalación limpia', 'Al desinstalarlo borra su configuración, su clave y su menú.'),
    ]),
    'en': ('Safe by design', 'Built not to get in the way of your store.', [
        ('bolt', 'Instant orders', 'Notifies shopea.me when an order is created, edited or changes status.'),
        ('shield', 'Minimal permissions', 'The webservice key cannot delete anything or see employees or carts.'),
        ('cart', 'Never blocks checkout', 'If shopea.me does not answer, the customer still completes the order.'),
        ('broom', 'Clean uninstall', 'Uninstalling removes its settings, its key and its menu entry.'),
    ]),
}


def header(title: str, sub: str) -> str:
    """Cabecera común: marca, titular y subtítulo."""
    return (f'<div class="top"><div class="brand">{LOGO}Shopeame · PrestaShop</div>'
            f'<h1>{title}</h1><p class="sub">{sub}</p></div>')


def png_size(path: pathlib.Path) -> tuple[int, int]:
    """Ancho y alto de un PNG, leídos de su cabecera IHDR."""
    with path.open('rb') as f:
        return struct.unpack('>II', f.read(24)[16:24])


def slide_html(slide: dict, lang: str) -> str:
    """HTML de una imagen con captura, con la ventana centrada en el espacio bajo los textos."""
    title, sub = slide[lang]
    path = RAW / slide['img']
    w, h = png_size(path)
    width = SIZE - 2 * 72 - (52 if slide['pad'] else 0)
    shown = round(width * h / w * (slide['crop'] or 1))
    height = 38 + shown + (52 if slide['pad'] else 0)
    # espacio libre entre el subtítulo (y = 290) y el borde inferior menos el margen
    top = 290 + max(40, (SIZE - 72 - 290 - height) // 2)
    img = f'<div class="crop" style="height:{shown}px"><img src="{path.as_uri()}"></div>'
    if slide['pad']:
        img = f'<div class="pad">{img}</div>'
    return header(title, sub) + f'<div class="win" style="top:{top}px"><div class="bar"><i></i><i></i><i></i></div>' \
        f'{img}</div>'



def features_html(lang: str) -> str:
    """HTML de la imagen de ventajas, sin captura."""
    title, sub, items = FEATURES[lang]
    tiles = ''.join(f'<div class="tile"><svg viewBox="0 0 24 24">{ICONS[i]}</svg><h2>{h}</h2><p>{t}</p></div>'
                    for i, h, t in items)
    return header(title, sub) + f'<div class="grid">{tiles}</div>'


def render(html: str, png: pathlib.Path, work: pathlib.Path) -> None:
    """Imprime el HTML como PNG de SIZE × SIZE con Chrome sin interfaz."""
    page = work / (png.stem + '.html')
    page.write_text(f'<!doctype html><html><head><meta charset="utf-8"><style>{CSS}</style></head>'
                    f'<body>{html}</body></html>', encoding='utf-8')
    subprocess.run([CHROME, '--headless', '--hide-scrollbars', '--force-device-scale-factor=1',
                    f'--window-size={SIZE},{SIZE}', f'--screenshot={png}', page.as_uri()],
                   check=True, capture_output=True)
    print(png)


with tempfile.TemporaryDirectory() as tmp:
    for lang in ('es', 'en'):
        folder = OUT / lang
        folder.mkdir(parents=True, exist_ok=True)
        for slide in SLIDES:
            render(slide_html(slide, lang), folder / f"{slide['file']}.png", pathlib.Path(tmp))
        render(features_html(lang), folder / '06-ventajas.png', pathlib.Path(tmp))
