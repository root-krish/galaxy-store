"""Scoped native product-cart and pending-payment panel regressions."""
import http.cookiejar
import json
import os
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = 0


def check(condition, message):
    global checks
    assert condition, message
    checks += 1


class Client:
    def __init__(self, base):
        self.base = base
        self.token = ''
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path='/', data=None):
        payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.opener.open(self.base + path, payload, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode()
        check(not any(text in body for text in ['Fatal error', 'Warning:', 'Deprecated:']), 'No PHP errors')
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
        if token:
            self.token = token[1]
        return response.status, body, urllib.parse.urlparse(response.url)

    def post(self, **fields):
        return self.request(data={'csrf_token': self.token, **fields})

    def login(self, role, password):
        self.request('/login')
        self.post(action='login', email=role+'@sportsgalaxy.local', password=password)


with tempfile.TemporaryDirectory(prefix='galaxy-review-http-') as directory:
    data_path = Path(directory) / 'data.json'
    env = {**os.environ, 'SG_DATA_PATH': str(data_path), 'SG_DB_DSN': '', 'SG_UPLOAD_PATH': str(Path(directory) / 'uploads')}
    subprocess.run(['php', '-r', 'require "app/bootstrap.php"; store()->settings();'], cwd=ROOT, env=env, check=True)
    data = json.loads(data_path.read_text())
    order = data['orders'][0]
    order.update(status='placed', payment_method='razorpay', payment_status='pending', delivery_partner_id=3)
    data_path.write_text(json.dumps(data))
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    with (Path(directory) / 'server.log').open('w') as log:
        server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', 'router.php'], cwd=ROOT, env=env, stdout=log, stderr=log)
        try:
            for _ in range(100):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1):
                        break
                except OSError:
                    if server.poll() is not None:
                        raise RuntimeError('PHP server failed to start')
                    time.sleep(.05)
            base = f'http://127.0.0.1:{port}'
            guest = Client(base)
            _, product, _ = guest.request('/product/astro-vision-football')
            check('name="return_page" value="product"' in product, 'Product form requests native return')
            _, body, location = guest.request('/product/astro-vision-football', {
                'csrf_token': guest.token, 'action': 'add_to_cart', 'product_id': 1, 'quantity': 1, 'return_page': 'product'})
            check(location.path == '/product/astro-vision-football-1', 'Native add-to-cart returns to submitted product')
            check('Astro Vision Football was added to your cart.' in body, 'Product return confirms cart mutation')
            _, _, location = guest.post(action='add_to_cart', product_id=2, return_page='product')
            check(location.path == '/product/prolite-english-willow-bat-2', 'Return URL uses submitted ID')
            _, _, location = guest.post(action='add_to_cart', product_id=1, return_page='https://example.test')
            check(location.path == '/shop', 'Unsafe return pages remain rejected')
            check(guest.request(data={'action':'add_to_cart', 'product_id':1, 'return_page':'product'})[0] == 419, 'Native product cart still requires CSRF')

            buyer, admin, courier = (Client(base) for _ in range(3))
            buyer.login('customer', 'Customer@123')
            admin.login('admin', 'Admin@123')
            courier.login('delivery', 'Delivery@123')
            panel = 'name="action" value="pay_order"'
            check(panel in buyer.request('/invoice?id=1')[1], 'Open pending order owner sees payment controls')
            check(panel not in admin.request('/invoice?id=1')[1], 'Non-owner admin does not see payment controls')
            check(panel not in courier.request('/invoice?id=1')[1], 'Assigned non-owner courier does not see payment controls')
            check(guest.request('/invoice?id=1')[2].path == '/login', 'Anonymous invoice access still requires login')
            for status, payment_status, method in [('cancelled','pending','razorpay'), ('placed','paid','razorpay'), ('placed','pending','cod')]:
                data = json.loads(data_path.read_text())
                data['orders'][0].update(status=status, payment_status=payment_status, payment_method=method)
                data_path.write_text(json.dumps(data))
                check(panel not in buyer.request('/invoice?id=1')[1], 'No panel for closed, paid or non-Razorpay order')
            data = json.loads(data_path.read_text())
            data['orders'][0].update(user_id=3, status='placed', payment_status='pending', payment_method='razorpay')
            data_path.write_text(json.dumps(data))
            check(panel in courier.request('/invoice?id=1')[1], 'Delivery account sees controls on its own purchase')
            print(f'Passed {checks} scoped HTTP repair checks.')
        finally:
            server.terminate()
            server.wait(timeout=5)
