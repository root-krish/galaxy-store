"""Isolated HTTP journey tests using only Python's standard library and PHP."""
import base64
import http.cookiejar
import hashlib
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
            _, login_markup, _ = guest.request('/login')
            check('data-demo-login' not in login_markup and 'data-password' not in login_markup, 'Login has no demo shortcuts or embedded passwords')
            for asset in ['assets/css/app.css', 'assets/js/app.js']:
                version = hashlib.sha256((ROOT / asset).read_bytes()).hexdigest()[:12]
                check('/' + asset + '?v=' + version in login_markup, 'Asset URL tracks deployed content: ' + asset)
                check(guest.request('/' + asset + '?v=' + version)[0] == 200, 'Versioned asset is served: ' + asset)
            for credential in ['customer@sportsgalaxy.local', 'admin@sportsgalaxy.local', 'delivery@sportsgalaxy.local', 'Customer@123', 'Admin@123', 'Delivery@123']:
                check(credential not in login_markup, 'Demo credential is documented outside the public login page')
            check(guest.request(data={'action': 'logout', 'csrf_token': 'wrong'})[0] == 419, 'Incorrect CSRF blocked')
            for private in ['/storage/data.json', '/app/Store.php', '/database/seed.sql', '/tests/http.py', '/.git/config', '/assets/../../storage/data.json']:
                check(guest.request(private)[0] == 404, 'Private files blocked: '+private)
            check(guest.request('/assets/css/app.css')[0] == 200, 'Public stylesheet served')
            check('/login' in guest.request('/?page=admin')[2], 'Guest admin access requires sign in')
            check('/login' in guest.post(action='login', email='missing@example.com', password='wrong', return_page='login')[2], 'Login errors return to login')
            check('/register' in guest.post(action='register', name='x', return_page='register')[2], 'Registration errors return to form')
            check('/account' in guest.post(action='register', name='New Player', email='new@example.com', phone='9876543210', password='Testing@123')[2], 'Registration signs customer in')
            check(urllib.parse.urlparse(guest.request('/?page=admin')[2]).path == '/', 'Customer cannot enter admin workspace')
            check('That order record' in guest.request('/?page=invoice&id=1')[1], 'Customer cannot read another customer invoice')
            check('/cart' in guest.request('/?page=checkout')[2], 'Empty checkout redirects before rendering')
            guest.post(action='add_to_cart', product_id=6, quantity=1, return_page='cart')
            check('Pulse Flex Yoga Mat' in guest.request('/?page=cart')[1], 'Add to cart works')
            _, body, invoice_url = guest.post(action='checkout', recipient='New Player', phone='9876543210', address='12 Test Road', city='Kolkata', postal_code='700001', payment_method='cod', coupon='GALAXY10', return_page='checkout')
            check('/invoice' in invoice_url and '928.56' in body, 'Checkout produces correct invoice')
            check('DELIVERY VERIFICATION CODE' in body, 'Customer sees handover code')
            data = json.loads(data_path.read_text())
            order = data['orders'][-1]
            check(data['products'][5]['stock'] == 24, 'Checkout stock persisted')
            courier = Client(base)
            _, login_body, _ = courier.request('/?page=login')
            next_page = re.search(r'name="next" value="([^"]*)"', login_body)[1]
            check('/delivery' in courier.post(action='login', email='delivery@sportsgalaxy.local', password='Delivery@123', next=next_page)[2], 'Ordinary delivery login opens the delivery hub')
            check('That order record' in courier.request('/?page=invoice&id='+str(order['id']))[1], 'Unassigned courier cannot read invoice')
            admin = Client(base)
            admin.login('admin', 'Admin@123')
            for tab in ['dashboard', 'products', 'inventory', 'orders', 'finance', 'partners', 'settings', 'categories', 'admins']:
                check(admin.request('/?page=admin&tab='+tab)[0] == 200, 'Admin tab renders: '+tab)
            admin.post(action='assign_delivery', order_id=order['id'], delivery_partner_id=3, return_page='admin', return_tab='orders')
            check('DELIVERY VERIFICATION CODE' not in courier.request('/?page=invoice&id='+str(order['id']))[1], 'Courier cannot see customer verification code')
            courier.post(action='update_order_status', order_id=order['id'], status='out_for_delivery', return_page='delivery')
            check('six-digit verification code' in courier.post(action='update_order_status', order_id=order['id'], status='delivered', verification_code='wrong', return_page='delivery')[1], 'Wrong code shows error')
            courier.post(action='update_order_status', order_id=order['id'], status='delivered', verification_code=order['delivery_code'], return_page='delivery')
            updated = json.loads(data_path.read_text())['orders'][-1]
            check(updated['status'] == 'delivered' and updated['payment_status'] == 'paid', 'Verified delivery completes order and COD')
            guest.post(action='logout')
            check('/login' in guest.request('/?page=invoice&id='+str(order['id']))[2], 'Logout protects invoice')

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
            check('/delivery' in new_partner.post(action='login', email='newpartner@example.com', password='Partner@123')[2], 'Created delivery account can sign in')
            new_partner.post(action='add_to_cart', product_id=6, quantity=1, return_page='cart')
            new_partner.post(action='logout')
            _, checkout_login, _ = new_partner.request('/?page=login&next=checkout')
            checkout_next = re.search(r'name="next" value="([^"]*)"', checkout_login)[1]
            check('/checkout' in new_partner.post(action='login', email='newpartner@example.com', password='Partner@123', next=checkout_next)[2], 'Delivery sign-in returns to checkout when purchasing')
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
            check('/invoice' in purchase_url and 'DELIVERY VERIFICATION CODE' in purchase_body, 'Courier gets own purchase receipt and buyer verification code')
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
            check('/account' in guest.post(action='login', email='updated.customer@example.com', password='Customer@123')[2], 'Customer can sign in using changed email')
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
            check('/delivery' in new_partner.post(action='login', email='edited.partner@example.com', password='NewPartner@123')[2], 'Edited partner credentials work')
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

            # Admin account provisioning, catalogue organisation, and extended settings.
            more_admin = dict(action='create_admin', name='Store Manager', email='manager@example.com', phone='9876543210', password='Manager@123', return_page='admin', return_tab='admins')
            users_before = len(json.loads(data_path.read_text())['users'])
            guest.post(**more_admin)
            courier.post(**more_admin)
            check(len(json.loads(data_path.read_text())['users']) == users_before, 'Only admins can create another admin')
            admin.post(**more_admin)
            manager_record = json.loads(data_path.read_text())['users'][-1]
            check(manager_record['role']=='admin', 'New administrator is persisted with admin role')
            manager = Client(base)
            manager.request('/?page=login')
            check('/admin' in manager.post(action='login', email='manager@example.com', password='Manager@123')[2], 'Created admin can sign in to portal')
            check('manager@example.com' in manager.request('/?page=admin&tab=admins')[1], 'Administrator directory renders')
            admin_contact = dict(action='update_admin_profile', name='Store Manager Updated', email='manager.updated@example.com', phone='9876543211', id=1, role='customer', return_page='profile')
            guest.post(**admin_contact)
            check(json.loads(data_path.read_text())['users'][0]['name']=='Aarav Mehta', 'Customer cannot edit admin profile')
            manager.post(**admin_contact)
            changed_manager = next(u for u in json.loads(data_path.read_text())['users'] if u['id']==manager_record['id'])
            check(changed_manager['name']=='Store Manager Updated' and changed_manager['role']=='admin' and json.loads(data_path.read_text())['users'][0]['name']=='Aarav Mehta', 'Admin edits own details without targeting another account or changing role')
            manager.post(action='logout')
            manager.request('/?page=login')
            check('/admin' in manager.post(action='login', email='manager.updated@example.com', password='Manager@123')[2], 'Admin can sign in using updated email')

            category_fields = dict(action='save_category', name='Swimming', icon='🏊', color='blue', return_page='admin', return_tab='categories')
            count_categories = len(json.loads(data_path.read_text())['categories'])
            guest.post(**category_fields)
            courier.post(**category_fields)
            check(len(json.loads(data_path.read_text())['categories'])==count_categories, 'Category editor is admin only')
            manager.upload(category_fields, 'category_photo', 'swimming.png', png)
            category = json.loads(data_path.read_text())['categories'][-1]
            check(category['name']=='Swimming' and re.fullmatch(r'[a-f0-9]{32}\.png', category['image_path']) is not None, 'Admin creates category with a photo')
            home = guest.request('/')[1]
            check(category['image_path'] in home and 'Swimming' in home, 'New category and photo appear on homepage')
            check('Swimming' in manager.request('/?page=admin&tab=products&category_id='+str(category['id']))[1], 'New category is available for gear creation')
            manager.post(**category_fields, id=category['id'], default_photo=1)
            check(json.loads(data_path.read_text())['categories'][-1]['image_path'] is None, 'Category can return to its icon')
            check('🏊' in guest.request('/')[1], 'Restored category icon appears in storefront')
            count_files = len(list(uploads.iterdir()))
            manager.upload({**category_fields, 'name': ''}, 'category_photo', 'failed.png', png)
            check(len(list(uploads.iterdir()))==count_files, 'Rejected category cleans up failed upload')
            gear_fields = {**product_fields, 'id': 0, 'sku': 'SWIM-HTTP-001', 'name': 'Swim Goggles', 'category_id': category['id'], 'visual': '🏊', 'stock': 5}
            manager.post(**gear_fields)
            gear = json.loads(data_path.read_text())['products'][-1]
            check(gear['category_id']==category['id'] and gear['visual']=='🏊', 'Admin can add gear to a new category with a custom icon')
            check('Swim Goggles' in guest.request('/?page=shop&category='+str(category['id']))[1], 'New gear is visible in its category')

            extended = dict(action='save_branding', hero_title='Ready for your next match', hero_description='New homepage description', hero_button_text='Browse equipment', announcement_text='Weekend gear offers', footer_description='Your local sports specialists', heading_font='modern', card_style='square', catalogue_columns='3', show_benefits='0', show_story='0', show_quote='0', support_email='help@example.com', support_phone='9876543210', secondary_color='#123abc', return_page='admin', return_tab='settings')
            manager.post(**extended)
            home = guest.request('/')[1]
            check('Ready for your next match' in home and 'Weekend gear offers' in home and 'Browse equipment' in home, 'Homepage content customization is rendered')
            check('benefit-strip' not in home and 'story-panel' not in home and 'quote-strip' not in home, 'Homepage section visibility settings work')
            check('cards-square catalogue-cols-3' in home and 'Arial, Helvetica, sans-serif' in home and '--violet:#123abc' in home, 'Layout, font, and secondary colour apply')
            check('mailto:help@example.com' in home and 'tel:9876543210' in home, 'Public contact links appear in footer')
            manager.post(**{**extended, 'hero_title': '<script>alert(1)</script>'})
            check('&lt;script&gt;alert(1)&lt;/script&gt;' in guest.request('/')[1], 'Custom homepage text is escaped')
            manager.post(action='reset_branding', return_page='admin', return_tab='settings')
            check('story-panel' in guest.request('/')[1], 'Reset restores default homepage sections')

            # Blocking revokes existing sessions, releases jobs, and preserves order records.
            access = dict(action='manage_delivery_partner', partner_id=3, operation='block', reason='Repeated missed delivery commitments', return_page='admin', return_tab='partners')
            guest.post(**access)
            check(json.loads(data_path.read_text())['users'][2]['is_active'], 'Customer cannot block a partner')
            manager.post(**{**access, 'reason': '  '})
            check(json.loads(data_path.read_text())['users'][2]['is_active'], 'Partner blocking requires a proper reason')
            active_order = guest.post(action='add_to_cart', product_id=6, quantity=1, return_page='cart')
            guest.post(action='checkout', recipient='HTTP Account Test', phone='9876543210', address='Account Test Road', city='Kolkata', postal_code='700001', payment_method='cod', return_page='checkout')
            to_reassign = json.loads(data_path.read_text())['orders'][-1]
            manager.post(action='assign_delivery', order_id=to_reassign['id'], delivery_partner_id=3, return_page='admin', return_tab='orders')
            manager.post(**access)
            blocked = json.loads(data_path.read_text())['users'][2]
            reassigned = json.loads(data_path.read_text())['orders'][-1]
            check(not blocked['is_active'] and blocked['account_events'][-1]['reason']==access['reason'], 'Admin blocks partner and saves reason')
            check(reassigned['status']=='packed' and reassigned['delivery_partner_id'] is None, 'Partner blocking returns active jobs for reassignment')
            check('/login' in courier.request('/?page=delivery')[2], 'Blocked partner session loses access immediately')
            manager.post(**{**access, 'operation': 'restore', 'reason': 'Review completed and approved'})
            check('/login' in courier.request('/?page=delivery')[2], 'Restoring account does not reactivate old sessions')
            check('/delivery' in courier.login('delivery', 'Delivery@123')[2], 'Restored partner can sign in again')
            manager.post(**{**access, 'operation': 'delete', 'reason': 'Partner has left the delivery team'})
            deleted = json.loads(data_path.read_text())['users'][2]
            check(deleted['deleted_at'] and not deleted['is_active'], 'Admin can delete partner account with reason')
            check('/login' in courier.request('/?page=delivery')[2], 'Deleted partner session cannot access delivery workspace')
            manager.post(**{**access, 'operation': 'restore', 'reason': 'Attempt to restore deleted account'})
            check(not json.loads(data_path.read_text())['users'][2]['is_active'], 'Deleted account cannot be restored')
            check(guest.request('/?page=invoice&id=1')[0]==200, 'Historical invoice remains accessible after partner deletion')
            orders_body = manager.request('/?page=admin&tab=orders')[1]
            check('order-command-card' in orders_body and 'order-filter-tabs' in orders_body and 'order-search-form' in orders_body, 'Modern order command controls render')
            packed_body = manager.request('/?page=admin&tab=orders&order_status=packed')[1]
            check('data-order-id="'+str(to_reassign['id'])+'"' in packed_body and 'data-order-id="1"' not in packed_body, 'Order status filtering returns matching cards')
            empty_body = manager.request('/?page=admin&tab=orders&order_q=no-such-order-abc')[1]
            check('No orders match these filters' in empty_body and 'class="order-command-card"' not in empty_body, 'Order search provides a clear empty state')

            other_manager_session = Client(base)
            other_manager_session.request('/?page=login')
            other_manager_session.post(action='login', email='manager.updated@example.com', password='Manager@123')
            _, profile_body, profile_url = manager.post(**admin_contact, password='ManagerChanged@123', current_password='Manager@123')
            check('/profile' in profile_url and 'Store Manager Updated' in profile_body, 'Admin password change keeps current session active')
            check('/login' in other_manager_session.request('/?page=admin')[2], 'Admin password change revokes other sessions')
            other_manager_session.request('/?page=login')
            check('/login' in other_manager_session.post(action='login', email='manager.updated@example.com', password='Manager@123')[2], 'Previous admin password no longer signs in')
            check('/admin' in other_manager_session.post(action='login', email='manager.updated@example.com', password='ManagerChanged@123')[2], 'New admin password signs in successfully')

            search = json.loads(guest.request('/search?q=NIVIA%20Football')[1])
            check(any('Football' in p['name'] for p in search['items']), 'Header search combines brand and product terms')
            check(guest.request('/product/astro-vision-football')[0] == 200, 'Clean product route resolves')
            check(guest.request('/shop/team-sports')[0] == 200, 'Clean category route resolves')
            check(guest.request('/product/not-a-product')[0] == 404, 'Unknown product slug returns 404')
            check('og:title' in guest.request('/product/astro-vision-football')[1], 'Product metadata rendered')
            guest.post(action='wishlist', product_id=1)
            check('Astro Vision Football' in guest.request('/wishlist')[1], 'Saved wishlist renders')
            check(guest.request(data={'action':'wishlist', 'product_id':1})[0] == 419, 'Wishlist requires CSRF')
            _, export, _ = manager.post(action='export_orders')
            check(next(__import__('csv').reader(export.lstrip('\ufeff').splitlines()))[:7] == ['Order', 'Customer', 'Email', 'Status', 'Payment', 'Total INR', 'Created'], 'Admin export contains columns')
            check('18 Lake View Road' in export, 'Export includes delivery address')
            _, denied_export, _ = guest.post(action='export_orders')
            check('Order,Customer,Email,Status,Payment' not in denied_export, 'Customers cannot export orders')
            _, yearly_finance, _ = manager.request('/admin?tab=finance&finance_period=year&finance_date=2024-02-29')
            check('Financial graph · 2024' in yearly_finance and 'finance_date=2024-01-01' in yearly_finance, 'Year graph includes month drilldown')
            _, monthly_finance, _ = manager.request('/admin?tab=finance&finance_period=month&finance_date=2024-02-29')
            check('Financial graph · February 2024' in monthly_finance and 'finance_date=2024-02-29' in monthly_finance, 'Leap month and day drilldown render')
            _, daily_finance, _ = manager.request('/admin?tab=finance&finance_period=day&finance_date=2024-02-29')
            check('Financial graph · 29 February 2024' in daily_finance and '23:00' in daily_finance, 'Specific day graph renders hourly details')
            check('Choose day, month, or year.' in manager.request('/admin?tab=finance&finance_period=bad')[1], 'Invalid finance period handled')
            check('Enter a valid date' in manager.request('/admin?tab=finance&finance_date=2024-02-31')[1], 'Invalid finance date handled')
            check('Financial graph' not in guest.request('/admin?tab=finance')[1], 'Customer cannot view finance report')
            _, historical_expense, historical_url = manager.post(action='add_expense', date='2024-02-29', category='Historical test', description='Selected date expense', amount='17.50', return_page='admin', return_tab='finance', finance_period='day', finance_date='2024-02-29')
            check('finance_date=2024-02-29' in historical_url and 'Selected date expense' in historical_expense, 'Expense returns to selected historical day')
            _, historical_income, historical_url = manager.post(action='add_income', date='2024-02-29', category='Historical test', description='Selected date income', amount='31.50', return_page='admin', return_tab='finance', finance_period='day', finance_date='2024-02-29')
            check('finance_date=2024-02-29' in historical_url and 'Selected date income' in historical_income, 'Income returns to selected historical day')
            check('Save demo access' in manager.request('/admin?tab=settings')[1], 'Dedicated demo access control visible')
            check(manager.request(data={'action':'set_demo_access','demo_access':'0'})[0] == 419, 'Demo switch requires CSRF')
            guest.post(action='set_demo_access', demo_access='0', return_page='admin')
            check(json.loads(data_path.read_text())['settings'].get('demo_access', '1') == '1', 'Customers cannot switch demo access')
            manager.post(action='set_demo_access', demo_access='0', return_page='admin', return_tab='settings')
            check('data-demo-login' not in Client(base).request('/login')[1], 'Disabled demo credentials are not exposed')
            probe = Client(base)
            check('/login' in probe.login('admin', 'Admin@123')[2], 'Disabled seeded administrator cannot sign in manually')
            check('/login' in admin.request('/admin')[2], 'Existing seeded administrator session revoked')
            check('/admin' in manager.request('/admin')[2], 'Personal administrator remains available')
            manager.post(action='reset_branding')
            check('/login' in Client(base).login('admin', 'Admin@123')[2], 'Reset preserves disabled demo account access')
            manager.post(action='set_demo_access', demo_access='1', return_page='admin', return_tab='settings')
            check('data-demo-login' not in Client(base).request('/login')[1], 'Enabling demo access does not expose credentials')
            # This journey changed the seeded customer's email in the profile checks above.
            demo_accounts = [('admin@sportsgalaxy.local', 'Admin@123', '/admin'), ('updated.customer@example.com', 'Customer@123', '/account')]
            for email, password, destination in demo_accounts:
                probe = Client(base)
                probe.request('/login')
                check(destination in probe.post(action='login', email=email, password=password)[2], 'Enabled demo account can sign in manually: ' + destination)
            check('/login' in Client(base).login('delivery', 'Delivery@123')[2], 'Enabling demo access does not reactivate the deleted delivery account')
            manager.post(action='set_demo_access', demo_access='0', return_page='admin', return_tab='settings')
            for email, password, destination in demo_accounts:
                probe = Client(base)
                probe.request('/login')
                check('/login' in probe.post(action='login', email=email, password=password)[2], 'Admin can disable manual demo sign-in again: ' + destination)
            print(f'Passed {checks} HTTP checks.')
        finally:
            server.terminate()
            server.wait(timeout=5)
