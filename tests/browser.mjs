// Optional browser regression suite: Node 22+ and a locally installed Chrome.
import {spawn} from 'node:child_process';
import {mkdtempSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {createServer} from 'node:net';
import {fileURLToPath} from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const directory = mkdtempSync(join(tmpdir(), 'galaxy-feature-browser-'));
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function freePort() {
  const socket = createServer();
  await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  return port;
}
const port = await freePort(), debugPort = await freePort();
const base = 'http://127.0.0.1:' + port;
const photo = join(directory, 'photo.png');
writeFileSync(photo, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAEAAAAAwCAIAAAAuKetIAAAAZElEQVR4nO3PUQkAIBTAwBfJmMYymiH8OITBAtxmn/V1wwUNaEEDWtCAFjSgBQ1oQQNa0IAWNKAFDWhBA1rQgBY0oAUNaEEDWtCAFjSgBQ1oQQNa0IAWNKAFDWhBA1rQgBY8dgH1A6EAAfEHMAAAAABJRU5ErkJggg==', 'base64'));
const php = spawn('php', ['-S', '127.0.0.1:' + port, 'router.php'], {
  cwd: root,
  env: {...process.env, SG_DATA_PATH: join(directory, 'data.json'), SG_UPLOAD_PATH: join(directory, 'uploads')},
  stdio: 'ignore',
});
const chrome = spawn(process.env.CHROME_BINARY || 'google-chrome', [
  '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
  '--remote-debugging-port=' + debugPort, '--user-data-dir=' + join(directory, 'chrome'), 'about:blank',
], {stdio: 'ignore'});
let socket, checks = 0;
let startupError;
php.on('error', error => startupError = error);
chrome.on('error', error => startupError = error);
function check(condition, message) {
  if (!condition) throw Error(message);
  checks++;
  console.log('PASS ' + message);
}
try {
  let tabs;
  for (let i = 0; i < 100; i++) {
    if (startupError) throw startupError;
    try {
      await fetch(base);
      tabs = await (await fetch('http://127.0.0.1:' + debugPort + '/json')).json();
      if (tabs.some(tab => tab.type === 'page')) break;
    } catch {}
    await delay(100);
  }
  if (!tabs) throw Error('PHP or Chrome did not start');
  socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
  let sequence = 0, loads = 0;
  const pending = new Map(), errors = [];
  socket.onmessage = event => {
    const message = JSON.parse(event.data);
    if (message.id) {
      const promise = pending.get(message.id);
      pending.delete(message.id);
      if (message.error) promise.reject(Error(JSON.stringify(message.error)));
      else promise.resolve(message.result);
    } else if (message.method === 'Page.loadEventFired') loads++;
    else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
  };
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++sequence;
    pending.set(id, {resolve, reject});
    socket.send(JSON.stringify({id, method, params}));
  });
  const evaluate = async expression => {
    const result = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
  };
  const loaded = async before => {
    for (let i = 0; i < 150; i++) {
      await delay(40);
      if (loads > before && await evaluate("document.readyState === 'complete'")) return;
    }
    throw Error('Page load timed out');
  };
  const navigate = async path => {
    const before = loads;
    await send('Page.navigate', {url: base + '/' + path});
    await loaded(before);
  };
  const submit = async selector => {
    const before = loads;
    await evaluate('document.querySelector(' + JSON.stringify(selector) + ').requestSubmit()');
    await loaded(before);
  };
  const fill = (selector, value) => evaluate('document.querySelector(' + JSON.stringify(selector) + ').value=' + JSON.stringify(value));
  const selectPhoto = async selector => {
    const {root: dom} = await send('DOM.getDocument');
    const {nodeId} = await send('DOM.querySelector', {nodeId: dom.nodeId, selector});
    await send('DOM.setFileInputFiles', {nodeId, files: [photo]});
    await delay(80);
  };
  const login = async role => {
    await navigate('?page=login');
    await evaluate('document.querySelector("[data-demo-login][data-email=\\"' + role + '@sportsgalaxy.local\\"]").click()');
    await submit('.auth-form');
  };
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
  await login('admin');
  await navigate('?page=admin&tab=partners');
  check(await evaluate('!!document.querySelector("[name=action][value=create_delivery_partner]")'), 'Admin can open partner creation');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Partner screen fits mobile');
  await navigate('?page=admin&tab=partners&edit_partner=3');
  await fill('.partner-editor-form [name=name]', 'Rohan Updated');
  await submit('.partner-editor-form');
  check(await evaluate('document.querySelector(".partner-list").textContent.includes("Rohan Updated")'), 'Admin can edit existing partner contact details');
  await navigate('?page=admin&tab=settings');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Store settings fit mobile');
  await fill('[name=shop_name]', 'Orbit Sports');
  await fill('[name=accent_color]', '#2563eb');
  await evaluate('document.querySelector("[name=shop_name]").dispatchEvent(new Event("input")); document.querySelector("[name=accent_color]").dispatchEvent(new Event("input"))');
  check(await evaluate('document.querySelector("[data-brand-name-preview]").textContent === "Orbit Sports" && getComputedStyle(document.querySelector("[data-brand-preview]")).getPropertyValue("--lime").trim() === "#2563eb"'), 'Branding preview responds to name and colour changes');
  await selectPhoto('[name=shop_logo_file]');
  await submit('.branding-form');
  check(await evaluate('document.title.includes("Orbit Sports") && document.querySelector(".brand-mark-custom img")?.naturalWidth === 64'), 'Saved shop name and uploaded logo appear in website branding');
  check(await evaluate('getComputedStyle(document.querySelector(".branding-form .button-primary")).color === "rgb(255, 255, 255)"'), 'Button text adapts to a blue accent colour');
  await submit('.branding-reset-form');
  check(await evaluate('document.title.includes("Sports Galaxy") && !document.querySelector(".brand-mark-custom")'), 'Admin can restore default branding');
  await navigate('?page=admin&tab=orders');
  const cancellationForm = '.order-action-form:has([name=order_id][value="2"])';
  check(await evaluate('document.querySelector(' + JSON.stringify(cancellationForm) + ').querySelector("[name=note]").required'), 'Cancellation requires a reason in the browser');
  await fill(cancellationForm + ' [name=note]', '   ');
  await evaluate('document.querySelector(' + JSON.stringify(cancellationForm) + ').querySelector("[name=note]").dispatchEvent(new Event("input"))');
  check(await evaluate('!document.querySelector(' + JSON.stringify(cancellationForm) + ').checkValidity()'), 'Whitespace alone cannot satisfy the cancellation reason');
  await fill(cancellationForm + ' [name=note]', 'Customer requested cancellation in browser test');
  await evaluate('document.querySelector(' + JSON.stringify(cancellationForm) + ').querySelector("[name=note]").dispatchEvent(new Event("input"))');
  await submit(cancellationForm);
  check(await evaluate('document.body.textContent.includes("Customer requested cancellation in browser test")'), 'Saved cancellation reason appears in admin order history');
  await navigate('?page=admin&tab=finance');
  check(await evaluate('!!document.querySelector("[name=action][value=add_income]")'), 'Finance includes external income form');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Finance fits mobile');
  await navigate('?page=admin&tab=products&edit=1');
  await selectPhoto('[name=product_photo]');
  check(await evaluate('!document.querySelector("[data-photo-preview]").hidden'), 'Product file selection shows an image preview');
  await submit('.product-editor-form');
  await navigate('?page=shop');
  check(await evaluate('[...document.querySelectorAll(".product-photo")].some(image => image.complete && image.naturalWidth === 64)'), 'Uploaded product photo renders in the catalogue');
  await navigate('?page=profile');
  await selectPhoto('[name=profile_photo]');
  await submit('.photo-settings-form');
  check(await evaluate('!!document.querySelector(".nav-actions .avatar img") && document.querySelector(".nav-actions .avatar img").naturalWidth === 64'), 'Saved admin photo appears in navigation');
  await selectPhoto('[name=profile_photo]');
  await evaluate('document.querySelector("[name=default_photo]").click()');
  check(await evaluate('document.querySelector("[name=profile_photo]").files.length === 0 && document.querySelector("[data-photo-preview]").hidden'), 'Selecting default clears a pending replacement photo');
  await submit('.photo-settings-form');
  check(await evaluate('!document.querySelector(".profile-card .avatar img")'), 'Restoring default displays initials');
  await submit('.logout-form');
  await login('delivery');
  check(await evaluate("location.search.includes('page=delivery')"), 'Normal delivery sign-in opens the delivery hub');
  await navigate('?page=shop');
  await evaluate('document.querySelector("input[name=product_id][value=\\"1\\"]").form.requestSubmit()');
  await delay(250);
  await navigate('?page=cart');
  check(await evaluate('document.body.textContent.includes("Secure checkout")'), 'Delivery partner can proceed to checkout');
  await navigate('?page=checkout');
  check(await evaluate('!!document.querySelector(".checkout-form")'), 'Delivery partner can enter purchase details');
  await navigate('?page=account');
  check(await evaluate('document.body.textContent.includes("YOUR ORDER HISTORY")'), 'Delivery partner has personal order history');
  await navigate('?page=profile');
  check(await evaluate('!document.querySelector(".contact-settings-form") && document.body.textContent.includes("store administrator")'), 'Partner contact details remain restricted to the admin');
  await selectPhoto('[name=profile_photo]');
  await submit('.photo-settings-form');
  check(await evaluate('document.querySelector(".profile-card .avatar img")?.naturalWidth === 64'), 'Delivery partner profile photo renders');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Profile screen fits mobile');
  await send('Emulation.setDeviceMetricsOverride', {width: 1225, height: 900, deviceScaleFactor: 1, mobile: false});
  await navigate('?page=shop');
  check(await evaluate('document.querySelector(".nav-wrap").scrollWidth <= document.querySelector(".nav-wrap").clientWidth'), 'Delivery shopping navigation fits desktop');
  check(await evaluate('!!document.querySelector(".cart-link svg")'), 'Navigation uses the new shopping trolley icon');
  await submit('.logout-form');
  await login('customer');
  await navigate('?page=profile');
  await fill('.contact-settings-form [name=name]', 'Updated Browser Customer');
  await fill('.contact-settings-form [name=email]', 'browser.customer@example.com');
  await fill('.contact-settings-form [name=phone]', '9876543210');
  await submit('.contact-settings-form');
  check(await evaluate('document.querySelector(".contact-settings-form [name=name]").value === "Updated Browser Customer" && document.querySelector(".contact-settings-form [name=email]").value === "browser.customer@example.com"'), 'Customer can save their own contact details');
  check(errors.length === 0, 'No browser JavaScript exceptions');
  console.log('Passed ' + checks + ' browser checks.');
} finally {
  socket?.close();
  php.kill(); chrome.kill();
  await delay(400);
  rmSync(directory, {recursive: true, force: true});
}
