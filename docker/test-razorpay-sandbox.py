import sys
import requests

base = 'http://localhost:8080'
product_id = int(sys.argv[1])
session = requests.Session()
session.get(base + '/?againbuy_test_country=IN', timeout=20)
session.get(f'{base}/?add-to-cart={product_id}', timeout=20)
cart = session.get(base + '/wp-json/wc/store/v1/cart', timeout=20)
cart.raise_for_status()
assert len(cart.json()['items']) == 1
headers = {'Cart-Token': cart.headers['Cart-Token'], 'Nonce': cart.headers['Nonce']}
checkout = session.post(
    base + '/wp-json/wc/store/v1/checkout',
    json={
        'billing_address': {
            'first_name': 'Test', 'last_name': 'Buyer', 'address_1': 'Local test',
            'city': 'Mumbai', 'state': 'MH', 'postcode': '400001', 'country': 'IN',
            'email': 'againbuy-checkout-test@example.com', 'phone': '9999999999',
        },
        'payment_method': 'razorpay', 'payment_data': [],
    },
    headers=headers,
    timeout=30,
)
body = checkout.json()
assert checkout.status_code == 200, f'Checkout HTTP {checkout.status_code}: {body.get("code", "unknown")}'
payment = body.get('payment_result', {})
redirect = payment.get('redirect_url', '')
assert payment.get('payment_status') == 'success' and '/checkout/order-pay/' in redirect, 'Razorpay payment handoff failed'
pay_page = session.get(redirect, timeout=20)
assert pay_page.status_code == 200 and 'razorpay' in pay_page.text.lower(), 'Razorpay payment page did not load'
print('Razorpay sandbox checkout reached the payment page; no payment was completed.')
