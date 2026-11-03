/**
 * Cliente CDP mínimo para hacer las capturas de Doc/Addons/capturas.
 *
 * Maneja una ventana de Chrome abierta a mano con un perfil temporal y el puerto de depuración 9333, en la que se ha
 * iniciado sesión en el back-office (ver Doc/Context/04-publicacion-en-prestashop-addons.md, 4.6). Usa el WebSocket
 * que trae Node 22 o superior, sin dependencias. Fija la ventana en 1280 × 900 a escala 2.
 *
 * Uso:
 *   node cdp.mjs eval '<js>'                            evalúa JS en la pestaña y muestra el resultado
 *   node cdp.mjs go '<url o js que devuelve una url>'    navega y espera a que cargue
 *   node cdp.mjs shot <archivo.png> [selector] [margen]  captura la ventana o el recorte de un selector
 */
import fs from 'node:fs';

const [cmd, arg, sel, marginArg] = process.argv.slice(2);
const tabs = await (await fetch('http://127.0.0.1:9333/json')).json();
const tab = tabs.find((t) => t.type === 'page');
const ws = new WebSocket(tab.webSocketDebuggerUrl);
await new Promise((ok) => ws.addEventListener('open', ok));
let id = 0;
const pending = new Map();
const events = [];
ws.addEventListener('message', (m) => {
  const d = JSON.parse(m.data);
  if (d.id && pending.has(d.id)) { pending.get(d.id)(d); pending.delete(d.id); } else events.push(d);
});

/** Envía un comando CDP y devuelve su resultado o lanza su error. */
function send(method, params = {}) {
  return new Promise((ok, ko) => {
    const n = ++id;
    pending.set(n, (d) => (d.error ? ko(new Error(JSON.stringify(d.error))) : ok(d.result)));
    ws.send(JSON.stringify({ id: n, method, params }));
  });
}

/** Evalúa una expresión en la página y devuelve su valor. */
async function evaluate(expr) {
  const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
  if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || 'error JS');
  return r.result.value;
}

const sleep = (ms) => new Promise((ok) => setTimeout(ok, ms));
await send('Page.enable');
// ventana fija de 1280 × 900 a escala 2 para que las capturas salgan nítidas
await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 2, mobile: false });

if (cmd === 'eval') {
  console.log(JSON.stringify(await evaluate(arg), null, 1));
} else if (cmd === 'go') {
  const url = arg.startsWith('http') ? arg : await evaluate(arg);
  await send('Page.navigate', { url });
  await sleep(500);
  for (let i = 0; i < 60 && (await evaluate('document.readyState')) !== 'complete'; i++) await sleep(250);
  await sleep(1200);
  console.log(await evaluate('location.href'));
} else if (cmd === 'shot') {
  let clip;
  if (sel) {
    const m = Number(marginArg || 16);
    const r = await evaluate(`(() => { const e = document.querySelector(${JSON.stringify(sel)}); e.scrollIntoView({block: 'center'});
      const b = e.getBoundingClientRect(); return {x: b.left + scrollX, y: b.top + scrollY, w: b.width, h: b.height}; })()`);
    await sleep(400);
    clip = { x: Math.max(0, r.x - m), y: Math.max(0, r.y - m), width: r.w + 2 * m, height: r.h + 2 * m, scale: 1 };
  }
  const r = await send('Page.captureScreenshot', { format: 'png', ...(clip ? { clip, captureBeyondViewport: true } : {}) });
  fs.writeFileSync(arg, Buffer.from(r.data, 'base64'));
  console.log(arg);
}
ws.close();
