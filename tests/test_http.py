"""Isolated HTTP journey tests using only Python's standard library and PHP."""
import base64
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
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = None

    def request(self, path='/', data=None):
        if data is not None:
            data = urllib.parse.urlencode(data).encode()
        try:
            response = self.opener.open(self.base + path, data, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode()
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
        if token:
            self.token = token[1]
        check('Fatal error' not in body and 'Warning:' not in body and 'Deprecated:' not in body, 'No PHP errors in response')
        return response.status, body, response.url

    def post(self, **data):
        return self.request(data={'csrf_token': self.token, **data})

    def upload(self, fields, field, filename, content, mime='image/png'):
        boundary = 'GalaxyMultipartBoundary1976'
        parts = []
        for name, value in {'csrf_token': self.token, **fields}.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        request = urllib.request.Request(self.base+'/', data=b''.join(parts), headers={'Content-Type': 'multipart/form-data; boundary='+boundary})
        response = self.opener.open(request, timeout=10)
        body = response.read().decode()
        check('Fatal error' not in body and 'Warning:' not in body, 'Upload response has no PHP errors')
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
        if token:
            self.token = token[1]
        return body

    def login(self, role, password):
        self.request('/?page=login')
        return self.post(action='login', email=role+'@sportsgalaxy.local', password=password)

with tempfile.TemporaryDirectory(prefix='galaxy-http-') as directory:
    data_path = Path(directory) / 'data.json'
    with socket.socket() as socket_:
        socket_.bind(('127.0.0.1', 0))
        port = socket_.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    with open(Path(directory) / 'server.log', 'w+') as log:
        server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', 'router.php'], cwd=ROOT,
                                  env={**os.environ, 'SG_DATA_PATH': str(data_path), 'SG_UPLOAD_PATH': str(Path(directory) / 'uploads')}, stdout=log, stderr=log)
        try:
            for _ in range(100):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1):
                        break
                except OSError:
                    time.sleep(.05)
            guest = Client(base)
            check(guest.request(data={'action': 'logout'})[0] == 419, 'Fresh session cannot POST without CSRF')
            guest.request()
            check(guest.request(data={'action': 'logout', 'csrf_token': 'wrong'})[0] == 419, 'Incorrect CSRF blocked')
            for private in ['/storage/data.json', '/app/Store.php', '/database/seed.sql', '/tests/http.py', '/.git/config', '/assets/../../storage/data.json']:
                check(guest.request(private)[0] == 404, 'Private files blocked: '+private)
            check(guest.request('/assets/css/app.css')[0] == 200, 'Public stylesheet served')
            check('page=login' in guest.request('/?page=admin')[2], 'Guest admin access requires sign in')
            check('page=login' in guest.post(action='login', email='missing@example.com', password='wrong', return_page='login')[2], 'Login errors return to login')
            check('page=register' in guest.post(action='register', name='x', return_page='register')[2], 'Registration errors return to form')
            check('page=account' in guest.post(action='register', name='New Player', email='new@example.com', phone='9876543210', password='Testing@123')[2], 'Registration signs customer in')
            check('page=home' in guest.request('/?page=admin')[2], 'Customer cannot enter admin workspace')
            check('That order record' in guest.request('/?page=invoice&id=1')[1], 'Customer cannot read another customer invoice')
            check('page=cart' in guest.request('/?page=checkout')[2], 'Empty checkout redirects before rendering')
            guest.post(action='add_to_cart', product_id=6, quantity=1, return_page='cart')
            check('Pulse Flex Yoga Mat' in guest.request('/?page=cart')[1], 'Add to cart works')
            _, body, invoice_url = guest.post(action='checkout', recipient='New Player', phone='9876543210', address='12 Test Road', city='Kolkata', postal_code='700001', payment_method='cod', coupon='GALAXY10', return_page='checkout')
            check('page=invoice' in invoice_url and '928.56' in body, 'Checkout produces correct invoice')
            check('DELIVERY VERIFICATION CODE' in body, 'Customer sees handover code')
            data = json.loads(data_path.read_text())
            order = data['orders'][-1]
            check(data['products'][5]['stock'] == 24, 'Checkout stock persisted')
            courier = Client(base)
            _, login_body, _ = courier.request('/?page=login')
            next_page = re.search(r'name="next" value="([^"]*)"', login_body)[1]
            check('page=delivery' in courier.post(action='login', email='delivery@sportsgalaxy.local', password='Delivery@123', next=next_page)[2], 'Ordinary delivery login opens the delivery hub')
            check('That order record' in courier.request('/?page=invoice&id='+str(order['id']))[1], 'Unassigned courier cannot read invoice')
            admin = Client(base)
            admin.login('admin', 'Admin@123')
            for tab in ['dashboard', 'products', 'inventory', 'orders', 'finance', 'partners', 'settings']:
                check(admin.request('/?page=admin&tab='+tab)[0] == 200, 'Admin tab renders: '+tab)
            admin.post(action='assign_delivery', order_id=order['id'], delivery_partner_id=3, return_page='admin', return_tab='orders')
            check('DELIVERY VERIFICATION CODE' not in courier.request('/?page=invoice&id='+str(order['id']))[1], 'Courier cannot see customer verification code')
            courier.post(action='update_order_status', order_id=order['id'], status='out_for_delivery', return_page='delivery')
            check('six-digit verification code' in courier.post(action='update_order_status', order_id=order['id'], status='delivered', verification_code='wrong', return_page='delivery')[1], 'Wrong code shows error')
            courier.post(action='update_order_status', order_id=order['id'], status='delivered', verification_code=order['delivery_code'], return_page='delivery')
            updated = json.loads(data_path.read_text())['orders'][-1]
            check(updated['status'] == 'delivered' and updated['payment_status'] == 'paid', 'Verified delivery completes order and COD')
            guest.post(action='logout')
            check('page=login' in guest.request('/?page=invoice&id='+str(order['id']))[2], 'Logout protects invoice')

            # Requested account, finance, shopping, and image features.
            guest.login('customer', 'Customer@123')
            partner_fields = dict(action='create_delivery_partner', name='New Partner', email='newpartner@example.com', phone='9876543210', password='Partner@123', return_page='admin', return_tab='partners')
            count_users = len(json.loads(data_path.read_text())['users'])
            guest.post(**partner_fields)
            courier.post(**partner_fields)
            check(len(json.loads(data_path.read_text())['users']) == count_users, 'Non-admin accounts cannot create partners')
            admin.post(**partner_fields)
            created_partner = json.loads(data_path.read_text())['users'][-1]
            check(created_partner['role'] == 'delivery', 'Admin can create a delivery account')
            new_partner = Client(base)
            new_partner.request('/?page=login')
            check('page=delivery' in new_partner.post(action='login', email='newpartner@example.com', password='Partner@123')[2], 'Created delivery account can sign in')
            new_partner.post(action='add_to_cart', product_id=6, quantity=1, return_page='cart')
            new_partner.post(action='logout')
            _, checkout_login, _ = new_partner.request('/?page=login&next=checkout')
            checkout_next = re.search(r'name="next" value="([^"]*)"', checkout_login)[1]
            check('page=checkout' in new_partner.post(action='login', email='newpartner@example.com', password='Partner@123', next=checkout_next)[2], 'Delivery sign-in returns to checkout when purchasing')
            new_partner.post(action='update_cart', remove_id=6, return_page='cart')
            check('Delivery team' in admin.request('/?page=admin&tab=partners')[1], 'Partner management screen available')
            admin.post(**partner_fields)
            check(len(json.loads(data_path.read_text())['users']) == count_users+1, 'Duplicate partner email rejected')

            new_public = Client(base)
            new_public.request('/?page=register')
            new_public.post(action='register', name='Public User', email='publicrole@example.com', phone='9876543210', password='Public@123', role='admin')
            check(json.loads(data_path.read_text())['users'][-1]['role'] == 'customer', 'Public signup cannot create staff')

            income_fields = dict(action='add_income', date=time.strftime('%Y-%m-%d'), category='Sponsorship', description='Tournament bonus', amount='123.45', return_page='admin', return_tab='finance')
            guest.post(**income_fields)
            courier.post(**income_fields)
            check(json.loads(data_path.read_text()).get('incomes', []) == [], 'Only admin can add external income')
            income_body = admin.post(**income_fields)[1]
            check('Tournament bonus' in income_body and '+₹123.45' in income_body, 'External income saved and displayed in ledger')
            admin.post(**{**income_fields, 'amount': '-1'})
            check(len(json.loads(data_path.read_text())['incomes']) == 1, 'Negative external income rejected')

            png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAAAwCAIAAAAuKetIAAAAZElEQVR4nO3PUQkAIBTAwBfJmMYymiH8OITBAtxmn/V1wwUNaEEDWtCAFjSgBQ1oQQNa0IAWNKAFDWhBA1rQgBY0oAUNaEEDWtCAFjSgBQ1oQQNa0IAWNKAFDWhBA1rQgBY8dgH1A6EAAfEHMAAAAABJRU5ErkJggg==')
            uploads = Path(directory) / 'uploads'
            for actor, user_id in [(guest, 2), (courier, 3), (admin, 1)]:
                actor.request('/?page=profile')
                body = actor.upload(dict(action='update_profile_photo', user_id=999, return_page='profile'), 'profile_photo', 'photo.png', png)
                image_name = next(u for u in json.loads(data_path.read_text())['users'] if u['id']==user_id)['profile_image']
                check(re.fullmatch(r'[a-f0-9]{32}\.png', image_name) is not None, 'Profile upload uses a safe generated name')
                check(image_name in body, 'Profile screen displays the saved photo')
                response = actor.opener.open(base+'/?page=media&image='+image_name)
                check(response.headers['Content-Type']=='image/png' and response.read()==png, 'Stored photo is served as an image')
                actor.post(action='update_profile_photo', return_page='profile')
                check(next(u for u in json.loads(data_path.read_text())['users'] if u['id']==user_id)['profile_image']==image_name, 'Empty photo input keeps current profile photo')
                actor.upload(dict(action='update_profile_photo', return_page='profile'), 'profile_photo', 'replacement.png', png)
                replacement = next(u for u in json.loads(data_path.read_text())['users'] if u['id']==user_id)['profile_image']
                check(replacement != image_name, 'Each role can replace a saved profile photo')
                actor.post(action='update_profile_photo', default_photo=1, return_page='profile')
                check(next(u for u in json.loads(data_path.read_text())['users'] if u['id']==user_id)['profile_image'] is None, 'Profile photo can return to default')

            product = json.loads(data_path.read_text())['products'][0]
            product_fields = {k:product[k] for k in ['id','name','sku','brand','category_id','description','price','cost','stock','min_stock','visual']}
            product_fields.update(action='save_product', return_page='admin', return_tab='products')
            previous_files = len(list(uploads.iterdir()))
            guest.upload(product_fields, 'product_photo', 'photo.png', png)
            courier.upload(product_fields, 'product_photo', 'photo.png', png)
            check(len(list(uploads.iterdir())) == previous_files, 'Non-admin product uploads rejected before saving files')
            admin.upload(product_fields, 'product_photo', '../../unsafe.php', png)
            product_image = json.loads(data_path.read_text())['products'][0]['image_path']
            check(re.fullmatch(r'[a-f0-9]{32}\.png', product_image) is not None, 'Original filename cannot control upload paths or executable extension')
            previous_image = product_image
            admin.upload(product_fields, 'product_photo', 'replacement.png', png)
            product_image = json.loads(data_path.read_text())['products'][0]['image_path']
            check(product_image != previous_image, 'Admin can replace a saved product photo')
            check(product_image in guest.request('/?page=shop')[1], 'Storefront shows custom product photo')
            check(product_image in admin.request('/?page=admin&tab=inventory')[1], 'Inventory shows custom product photo')
            previous_files = len(list(uploads.iterdir()))
            for filename, content, mime in [('fake.png', b'<?php echo "bad"; ?>', 'image/png'), ('photo.svg', b'<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'image/svg+xml'), ('large.png', png+b'x'*(2*1024*1024), 'image/png')]:
                admin.upload(product_fields, 'product_photo', filename, content, mime)
                check(json.loads(data_path.read_text())['products'][0]['image_path'] == product_image, 'Invalid upload preserves current product image')
                check(len(list(uploads.iterdir())) == previous_files, 'Rejected image leaves no files behind')
            duplicate = {**product_fields, 'sku': json.loads(data_path.read_text())['products'][1]['sku']}
            admin.upload(duplicate, 'product_photo', 'valid.png', png)
            check(len(list(uploads.iterdir())) == previous_files, 'Failed product validation cleans up its new upload')
            admin.post(**product_fields)
            check(json.loads(data_path.read_text())['products'][0]['image_path'] == product_image, 'Product edit without upload keeps photo')

            # A delivery partner shops as a buyer, with a separate personal order view.
            check('Secure checkout' in courier.post(action='add_to_cart', product_id=1, quantity=1, return_page='cart')[1], 'Delivery partner has a checkout link')
            _, purchase_body, purchase_url = courier.post(action='checkout', recipient='Rohan', phone='9876543210', address='Test Road', city='Kolkata', postal_code='700001', payment_method='cod', return_page='checkout')
            purchase = json.loads(data_path.read_text())['orders'][-1]
            check('page=invoice' in purchase_url and 'DELIVERY VERIFICATION CODE' in purchase_body, 'Courier gets own purchase receipt and buyer verification code')
            check(product_image in purchase_body, 'Receipt displays product photo')
            check(purchase['number'] in courier.request('/?page=account')[1], 'Courier sees personal purchase history')
            check(purchase['number'] not in courier.request('/?page=delivery')[1], 'Personal purchase is separate from delivery jobs')
            admin.post(action='assign_delivery', order_id=purchase['id'], delivery_partner_id=3, return_page='admin', return_tab='orders')
            check(json.loads(data_path.read_text())['orders'][-1]['delivery_partner_id'] is None, 'Courier cannot deliver own purchase')
            admin.post(action='assign_delivery', order_id=purchase['id'], delivery_partner_id=created_partner['id'], return_page='admin', return_tab='orders')
            check('DELIVERY VERIFICATION CODE' not in new_partner.request('/?page=invoice&id='+str(purchase['id']))[1], 'Assigned courier cannot see buyer verification code')
            admin.post(**{**product_fields, 'default_photo': 1})
            check(json.loads(data_path.read_text())['products'][0]['image_path'] is None, 'Admin can restore the default product visual')
            check(product_image in courier.request('/?page=invoice&id='+str(purchase['id']))[1], 'Historical receipt retains the original product photo')
            check(guest.request('/?page=media&image=../../app/bootstrap.php')[0] == 404, 'Image endpoint blocks traversal')


            # Editable contact details and admin-only partner account maintenance.
            contact = dict(action='update_customer_profile', name='Updated Customer', email='UPDATED.CUSTOMER@example.com', phone='+91 98765 43210', user_id=1, role='admin', return_page='profile')
            guest.post(**contact)
            saved = next(u for u in json.loads(data_path.read_text())['users'] if u['id'] == 2)
            check(saved['name'] == 'Updated Customer' and saved['email'] == 'updated.customer@example.com' and saved['role'] == 'customer', 'Customer saves own contact details without privilege escalation')
            check('value="Updated Customer"' in guest.request('/?page=profile')[1], 'Updated details appear on own profile')
            guest.post(action='logout')
            guest.request('/?page=login')
            check('page=account' in guest.post(action='login', email='updated.customer@example.com', password='Customer@123')[2], 'Customer can sign in using changed email')
            guest.post(**{**contact, 'email': 'admin@sportsgalaxy.local'})
            check(next(u for u in json.loads(data_path.read_text())['users'] if u['id']==2)['email'] == 'updated.customer@example.com', 'Duplicate email does not overwrite contact details')
            courier.post(**contact)
            check(next(u for u in json.loads(data_path.read_text())['users'] if u['id']==3)['name']=='Rohan Das', 'Courier cannot use the customer contact editor')
            partner_edit = dict(action='update_delivery_partner', partner_id=created_partner['id'], name='Edited Partner', email='edited.partner@example.com', phone='9876543210', password='NewPartner@123', return_page='admin', return_tab='partners')
            guest.post(**partner_edit)
            courier.post(**partner_edit)
            check(next(u for u in json.loads(data_path.read_text())['users'] if u['id']==created_partner['id'])['email']=='newpartner@example.com', 'Non-admin partner edits blocked')
            admin.post(**partner_edit)
            check('Edited Partner' in admin.request('/?page=admin&tab=partners&edit_partner='+str(created_partner['id']))[1], 'Admin partner editor loads saved contact fields')
            new_partner.post(action='logout')
            new_partner.request('/?page=login')
            check('page=delivery' in new_partner.post(action='login', email='edited.partner@example.com', password='NewPartner@123')[2], 'Edited partner credentials work')
            check('managed by' not in guest.request('/?page=profile')[1] and 'Save contact details' in guest.request('/?page=profile')[1], 'Customer profile offers contact editing')
            check('Save contact details' not in courier.request('/?page=profile')[1], 'Courier profile has no self-service contact form')

            theme = dict(action='save_branding', shop_name='Orbit Sports', accent_color='#2563eb', background_color='#f5f7ff', text_color='#111827', return_page='admin', return_tab='settings')
            guest.post(**theme)
            courier.post(**theme)
            check(json.loads(data_path.read_text())['settings'].get('shop_name', 'Sports Galaxy')=='Sports Galaxy', 'Only admins can change store branding')
            theme_body = admin.upload(theme, 'shop_logo_file', 'shop.png', png)
            logo = json.loads(data_path.read_text())['settings']['shop_logo']
            check('Orbit Sports' in theme_body and logo in theme_body, 'Admin saves shop name and uploaded logo')
            _, home_body, _ = guest.request('/')
            check('<title>Sport equipment for every orbit · Orbit Sports</title>' in home_body, 'Shop name appears in page title')
            check(home_body.count(logo) >= 2 and '--lime:#2563eb' in home_body and '--paper:#f5f7ff' in home_body, 'Logo and colours are shared across the website')
            check('Orbit Sports' in courier.request('/?page=invoice&id='+str(purchase['id']))[1] and logo in courier.request('/?page=invoice&id='+str(purchase['id']))[1], 'Receipts use store branding')
            check('<svg viewBox="0 0 24 24"' in home_body, 'Cart uses the new SVG shopping cart icon')
            previous_files = len(list(uploads.iterdir()))
            admin.upload({**theme, 'accent_color': '</style><script>'}, 'shop_logo_file', 'new.png', png)
            check(json.loads(data_path.read_text())['settings']['shop_logo']==logo and len(list(uploads.iterdir()))==previous_files, 'Invalid branding preserves current settings and removes failed logo upload')
            admin.post(**{**theme, 'default_photo': 1})
            check(json.loads(data_path.read_text())['settings']['shop_logo'] is None, 'Admin can restore the default shop logo')
            guest.post(action='reset_branding', return_page='admin', return_tab='settings')
            check(json.loads(data_path.read_text())['settings']['shop_name']=='Orbit Sports', 'Non-admin branding reset blocked')
            admin.post(action='reset_branding', return_page='admin', return_tab='settings')
            check(json.loads(data_path.read_text())['settings']['shop_name']=='Sports Galaxy', 'Admin can restore default store branding')

            # Cancellation requires a saved reason even when browser validation is bypassed.
            cancel_fields = dict(action='update_order_status', order_id=purchase['id'], status='cancelled', return_page='admin', return_tab='orders')
            stock_before = json.loads(data_path.read_text())['products'][0]['stock']
            for reason in ['', '   ', 'x'*501]:
                admin.post(**cancel_fields, note=reason)
                check(json.loads(data_path.read_text())['orders'][-1]['status']=='assigned', 'Missing or invalid cancellation reason is rejected')
            check(json.loads(data_path.read_text())['products'][0]['stock']==stock_before, 'Rejected cancellation does not restore stock')
            reason = 'Customer asked to cancel after ordering the wrong size.'
            admin.post(**cancel_fields, note=reason)
            last = json.loads(data_path.read_text())['orders'][-1]
            check(last['status']=='cancelled' and last['cancellation_reason']==reason, 'Cancellation stores its required reason')
            check(reason in courier.request('/?page=invoice&id='+str(purchase['id']))[1], 'Buyer can see the cancellation reason in order tracking')

            print(f'Passed {checks} HTTP checks.')
        finally:
            server.terminate()
            server.wait(timeout=5)
