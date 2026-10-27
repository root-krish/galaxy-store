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
  await navigate('?page=admin&tab=categories');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Category editor fits mobile');
  await fill('.category-editor-form [name=name]', 'Swimming');
  await fill('.category-editor-form [name=icon]', '🏊');
  await selectPhoto('[name=category_photo]');
  await submit('.category-editor-form');
  check(await evaluate('document.querySelector(".category-admin-list").textContent.includes("Swimming")'), 'Admin can create a category with a photo');
  await navigate('?page=home');
  check(await evaluate('[...document.querySelectorAll(".category-card")].some(card => card.textContent.includes("Swimming") && card.querySelector("img")?.naturalWidth === 64)'), 'Created category photo renders on the homepage');
  await navigate('?page=admin&tab=products&category_id=6');
  check(await evaluate('document.querySelector("[name=category_id]").value === "6"'), 'Add gear link preselects the new category');
  for (const [name, value] of Object.entries({name: 'Swim Goggles', sku: 'SWIM-BROWSER-001', brand: 'Aqua', price: '499', cost: '250', stock: '10', visual: '🏊', description: 'Comfortable swimming goggles.'})) await fill('.product-editor-form [name=' + name + ']', value);
  await submit('.product-editor-form');
  await navigate('?page=shop&category=6');
  check(await evaluate('document.querySelector(".product-card h3")?.textContent === "Swim Goggles"'), 'New gear appears under the new category');
  await navigate('?page=admin&tab=admins');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Administrator screen fits mobile');
  for (const [name, value] of Object.entries({name: 'Browser Admin', email: 'browser.admin@example.com', phone: '9876543210', password: 'BrowserAdmin@123'})) await fill('.admin-account-form [name=' + name + ']', value);
  await submit('.admin-account-form');
  check(await evaluate('document.body.textContent.includes("browser.admin@example.com")'), 'Existing admin can create another administrator');
  await navigate('?page=admin&tab=partners');
  check(await evaluate('!!document.querySelector("[name=action][value=create_delivery_partner]")'), 'Admin can open partner creation');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Partner screen fits mobile');
  await navigate('?page=admin&tab=partners&edit_partner=3');
  await fill('.partner-editor-form [name=name]', 'Rohan Updated');
  await submit('.partner-editor-form');
  check(await evaluate('document.querySelector(".partner-list").textContent.includes("Rohan Updated")'), 'Admin can edit existing partner contact details');
  await navigate('?page=admin&tab=settings');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Store settings fit mobile');
  await fill('[name=hero_title]', 'Get ready for your next match');
  await fill('[name=hero_button_text]', 'Find your equipment');
  await fill('[name=heading_font]', 'modern');
  await fill('[name=card_style]', 'square');
  await fill('[name=catalogue_columns]', '3');
  await fill('[name=support_email]', 'help@example.com');
  await evaluate('document.querySelector("[type=checkbox][name=show_story]").click()');
  await fill('[name=shop_name]', 'Orbit Sports');
  await fill('[name=accent_color]', '#2563eb');
  await evaluate('document.querySelector("[name=shop_name]").dispatchEvent(new Event("input")); document.querySelector("[name=accent_color]").dispatchEvent(new Event("input"))');
  check(await evaluate('document.querySelector("[data-brand-name-preview]").textContent === "Orbit Sports" && getComputedStyle(document.querySelector("[data-brand-preview]")).getPropertyValue("--lime").trim() === "#2563eb"'), 'Branding preview responds to name and colour changes');
  await selectPhoto('[name=shop_logo_file]');
  await submit('.branding-form');
  check(await evaluate('document.title.includes("Orbit Sports") && document.querySelector(".brand-mark-custom img")?.naturalWidth === 64'), 'Saved shop name and uploaded logo appear in website branding');
  check(await evaluate('getComputedStyle(document.querySelector(".branding-form .button-primary")).color === "rgb(255, 255, 255)"'), 'Button text adapts to a blue accent colour');
  await navigate('?page=home');
  check(await evaluate('document.querySelector(".hero h1").textContent === "Get ready for your next match" && !document.querySelector(".story-panel")'), 'Homepage text and section settings are applied');
  check(await evaluate('document.body.classList.contains("cards-square") && document.body.classList.contains("catalogue-cols-3") && getComputedStyle(document.querySelector(".hero h1")).fontFamily.includes("Arial")'), 'Website font and layout settings are applied');
  check(await evaluate('[...document.querySelectorAll("a")].some(link => link.getAttribute("href") === "mailto:help@example.com")'), 'Support email appears in the footer');
  await navigate('?page=admin&tab=settings');
  await submit('.branding-reset-form');
  check(await evaluate('document.title.includes("Sports Galaxy") && !document.querySelector(".brand-mark-custom")'), 'Admin can restore default branding');
  await navigate('?page=admin&tab=orders');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Order command cards fit mobile without horizontal scrolling');
  await navigate('?page=admin&tab=orders&order_status=delivered');
  check(await evaluate('document.querySelectorAll(".order-command-card").length === 1 && !document.querySelector(".order-action-form")'), 'Closed order filter hides unavailable actions');
  await fill('[name=order_q]', 'no-such-order');
  await submit('.order-search-form');
  check(await evaluate('document.body.textContent.includes("No orders match these filters")'), 'Order search displays a useful empty state');
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
  await navigate('?page=admin&tab=partners');
  for (const [name, value] of Object.entries({name: 'Temporary Partner', email: 'temporary.partner@example.com', phone: '9876543210', password: 'Temporary@123'})) await fill('.partner-editor-form [name=' + name + ']', value);
  await submit('.partner-editor-form');
  const statusForm = '.partner-account:last-child .partner-status-form';
  await evaluate('document.querySelector(".partner-account:last-child .partner-account-controls").open = true');
  check(await evaluate('!document.querySelector(' + JSON.stringify(statusForm) + ').checkValidity()'), 'Partner account action requires a reason');
  await fill(statusForm + ' [name=reason]', 'Repeated missed delivery commitments');
  await evaluate('document.querySelector(' + JSON.stringify(statusForm) + ').querySelector("[name=reason]").dispatchEvent(new Event("input"))');
  await submit(statusForm);
  check(await evaluate('document.querySelector(".partner-account:last-child").textContent.includes("Blocked") && document.querySelector(".partner-account:last-child").textContent.includes("Repeated missed delivery commitments")'), 'Blocked account shows status and saved reason');
  await evaluate('document.querySelector(".partner-account:last-child .partner-account-controls").open = true');
  await fill(statusForm + ' [name=operation]', 'restore');
  await fill(statusForm + ' [name=reason]', 'Account review completed');
  await evaluate('document.querySelector(' + JSON.stringify(statusForm) + ').querySelector("[name=reason]").dispatchEvent(new Event("input"))');
  await submit(statusForm);
  check(await evaluate('document.querySelector(".partner-account:last-child .status-pill").textContent === "Active"'), 'Admin can restore a blocked account');
  await evaluate('document.querySelector(".partner-account:last-child .partner-account-controls").open = true');
  await fill(statusForm + ' [name=operation]', 'delete');
  await fill(statusForm + ' [name=reason]', 'Partner has left the delivery team');
  await evaluate('document.querySelector(' + JSON.stringify(statusForm) + ').querySelector("[name=reason]").dispatchEvent(new Event("input"))');
  await submit(statusForm);
  check(await evaluate('document.querySelector(".partner-account:last-child .status-pill").textContent === "Deleted" && !document.querySelector(".partner-account:last-child .partner-status-form")'), 'Deleted account shows history without restoration controls');
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
  await navigate('?page=shop');
  await evaluate('document.querySelector(' + JSON.stringify('input[name=product_id][value="6"]') + ').form.requestSubmit()');
  await delay(250);
  await navigate('?page=checkout');
  for (const [name, value] of Object.entries({recipient: 'Browser Order Customer', phone: '9876543210', address: '12 Browser Road', city: 'Kolkata', postal_code: '700001'})) await fill('.checkout-form [name=' + name + ']', value);
  await submit('.checkout-form');
  const freshOrderId = await evaluate('new URLSearchParams(location.search).get("id")');
  check(!!freshOrderId, 'Customer can purchase newly managed catalogue gear');
  await submit('.logout-form');
  await navigate('?page=login');
  await fill('.auth-form [name=email]', 'browser.admin@example.com');
  await fill('.auth-form [name=password]', 'BrowserAdmin@123');
  await submit('.auth-form');
  check(await evaluate('location.search.includes("page=admin")'), 'Created administrator can sign in to control room');
  await navigate('?page=profile');
  await fill('.contact-settings-form [name=name]', 'Browser Admin Updated');
  await fill('.contact-settings-form [name=email]', 'browser.admin.updated@example.com');
  await fill('.contact-settings-form [name=phone]', '9876543211');
  await submit('.contact-settings-form');
  check(await evaluate('document.querySelector(".contact-settings-form [name=email]").value === "browser.admin.updated@example.com" && document.querySelector(".profile-card").textContent.includes("Browser Admin Updated")'), 'Admin can update their own name, email, and phone');
  await navigate('?page=admin&tab=orders');
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Order command fits desktop');
  const freshForm = '.order-action-form:has([name=order_id][value="' + freshOrderId + '"])';
  check(await evaluate('document.querySelector(' + JSON.stringify(freshForm) + ').querySelector("[name=note]").disabled'), 'Packing an order does not ask for a cancellation reason');
  await fill(freshForm + ' [name=status]', 'cancelled');
  await evaluate('document.querySelector(' + JSON.stringify(freshForm) + ').querySelector("[name=status]").dispatchEvent(new Event("change"))');
  check(await evaluate('document.querySelector(' + JSON.stringify(freshForm) + ').querySelector("[name=note]").required && !document.querySelector(' + JSON.stringify(freshForm) + ').querySelector("[data-cancel-reason]").hidden'), 'Choosing cancellation reveals the mandatory reason');
  await fill(freshForm + ' [name=status]', 'packed');
  await evaluate('document.querySelector(' + JSON.stringify(freshForm) + ').querySelector("[name=status]").dispatchEvent(new Event("change"))');
  await submit(freshForm);
  check(await evaluate('document.querySelector(' + JSON.stringify('[data-order-id="' + freshOrderId + '"] .status-pill') + ').textContent === "Packed"'), 'New order command can pack an order');
  const assignForm = '.order-command-card[data-order-id="' + freshOrderId + '"] .order-assign-form';
  await fill(assignForm + ' [name=delivery_partner_id]', '3');
  await submit(assignForm);
  check(await evaluate('document.querySelector(' + JSON.stringify('[data-order-id="' + freshOrderId + '"] .status-pill') + ').textContent === "Assigned"'), 'New order command can assign a delivery partner');

  check(errors.length === 0, 'No browser JavaScript exceptions');
  console.log('Passed ' + checks + ' browser checks.');
} finally {
  socket?.close();
  php.kill(); chrome.kill();
  await delay(400);
  rmSync(directory, {recursive: true, force: true});
}
