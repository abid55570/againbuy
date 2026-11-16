import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.request
from pathlib import Path

from websockets.sync.client import connect


BASE = os.environ.get("AGAINBUY_TEST_URL", "http://localhost:8080")
CHROME = os.environ.get("CHROME_PATH") or shutil.which("chrome") or r"C:\Program Files\Google\Chrome\Application\chrome.exe"


def wait_for(check, message, seconds=20):
    end = time.monotonic() + seconds
    while time.monotonic() < end:
        try:
            value = check()
        except (AssertionError, RuntimeError):
            value = False
        if value:
            return value
        time.sleep(0.2)
    raise AssertionError(message)


class Browser:
    def __init__(self, socket):
        self.socket = socket
        self.next_id = 0

    def call(self, method, params=None):
        self.next_id += 1
        request_id = self.next_id
        self.socket.send(json.dumps({"id": request_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv(timeout=20))
            if response.get("id") == request_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def evaluate(self, expression):
        response = self.call("Runtime.evaluate", {"expression": expression, "returnByValue": True, "awaitPromise": True})
        if "exceptionDetails" in response:
            raise AssertionError(response["exceptionDetails"].get("text"))
        return response.get("result", {}).get("value")

    def navigate(self, url):
        previous = self.evaluate("location.href")
        if previous == url:
            return
        self.call("Page.navigate", {"url": url})
        wait_for(lambda: self.evaluate(f"location.href !== {json.dumps(previous)} && document.readyState === 'complete' && document.body && document.body.innerText.length > 0"), f"Page did not load: {url}")


def run(product_id):
    if not Path(CHROME).is_file():
        raise RuntimeError("Chrome missing; set CHROME_PATH to the Chrome executable")
    with tempfile.TemporaryDirectory(prefix="againbuy-browser-") as profile:
        process = subprocess.Popen([CHROME, "--headless=new", "--no-first-run", "--no-default-browser-check", "--disable-gpu", "--remote-debugging-port=0", f"--user-data-dir={profile}", "about:blank"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            port_file = Path(profile, "DevToolsActivePort")
            port = wait_for(lambda: port_file.read_text().splitlines()[0] if port_file.exists() else None, "Chrome debugging port did not open")
            pages = json.load(urllib.request.urlopen(f"http://127.0.0.1:{port}/json/list", timeout=5))
            page = next(item for item in pages if item["type"] == "page")
            with connect(page["webSocketDebuggerUrl"], origin=f"http://127.0.0.1:{port}", max_size=5_000_000) as socket:
                browser = Browser(socket)
                browser.call("Page.enable")
                browser.call("Runtime.enable")
                for width in (320, 390, 768, 1280):
                    browser.call("Emulation.setDeviceMetricsOverride", {"width": width, "height": 850, "deviceScaleFactor": 1, "mobile": width < 768})
                    for url in (BASE + "/", BASE + "/shop/", BASE + "/product-category/bottle/", BASE + "/my-account/", f"{BASE}/?p={product_id}&post_type=product"):
                        browser.navigate(url)
                        layout = browser.evaluate("({width:innerWidth,scroll:document.documentElement.scrollWidth,main:!!document.querySelector('main'),search:!!document.querySelector('input[type=search]'),tabs:!!document.querySelector('.mobile-tabs') && getComputedStyle(document.querySelector('.mobile-tabs')).display !== 'none',tabCount:document.querySelectorAll('.mobile-tabs a').length})")
                        assert layout["scroll"] <= layout["width"] + 1, f"Horizontal overflow at {width}px: {url}, {layout}"
                        assert layout["main"] and layout["search"], f"Missing mobile navigation at {width}px: {url}"
                        assert layout["tabCount"] == 4 and layout["tabs"] == (width <= 650), f"Phone bottom tabs failed at {width}px: {layout}"
                        broken = browser.evaluate("[...document.images].filter(img=>{const r=img.getBoundingClientRect();return r.width>0&&r.height>0&&r.top<innerHeight&&r.bottom>0&&img.complete&&img.naturalWidth===0}).map(img=>img.src).slice(0,3)")
                        assert not broken, f"Broken visible images at {width}px: {url}, {broken}"
                        if width <= 650 and url in (BASE + "/", BASE + "/shop/", BASE + "/product-category/bottle/"):
                            cards = browser.evaluate("({card:document.querySelector('.woocommerce ul.products li.product')?.getBoundingClientRect().width,button:document.querySelector('.woocommerce ul.products li.product .button')?.getBoundingClientRect().width})")
                            assert cards['card'] >= 130 and cards['button'] >= 110, f"Product cards are too narrow at {width}px: {url}, {cards}"
                            button_style = browser.evaluate("(()=>{const s=getComputedStyle(document.querySelector('ul.products li.product .button'));return {display:s.display,align:s.textAlign,justify:s.justifyContent}})()")
                            assert button_style['display'] == 'flex' and button_style['justify'] == 'center', f"Add to cart text is not centered: {button_style}"
                            if width == 390:
                                slides = browser.evaluate("(()=>{const gallery=[...document.querySelectorAll('.againbuy-card-gallery')].find(el=>el.querySelectorAll('img').length>1);return {count:gallery?.querySelectorAll('img').length,active:gallery?.querySelector('img:not([hidden])')?.getAttribute('src')}})()")
                                assert slides['count'] > 1 and slides['active'], f"Product card gallery is missing at {width}px: {slides}"
                                wait_for(lambda: browser.evaluate(f"[...document.querySelectorAll('.againbuy-card-gallery')].find(el=>el.querySelectorAll('img').length>1)?.querySelector('img:not([hidden])')?.getAttribute('src') !== {json.dumps(slides['active'])}"), "Product card gallery did not advance", seconds=5)
                    detail = browser.evaluate("({name:document.querySelector('.product_title')?.innerText,price:document.querySelector('.summary .price')?.innerText,button:!!document.querySelector('form.cart button.single_add_to_cart_button')})")
                    assert detail["name"] == "AgainBuy test bottle" and "1,499" in detail["price"] and detail["button"], f"Product detail failed at {width}px: {detail}"
                    print(f"Mobile layout passed: {width}px")
                browser.call("Emulation.setDeviceMetricsOverride", {"width": 390, "height": 850, "deviceScaleFactor": 1, "mobile": True})
                browser.navigate(BASE + "/shop/")
                shop = browser.evaluate("({categories:document.querySelectorAll('.shop-category-links a').length,sort:document.querySelector('select.orderby')?.getBoundingClientRect().width,card:document.querySelector('ul.products li.product')?.getBoundingClientRect().width})")
                assert shop['categories'] >= 3 and shop['sort'] >= 250 and shop['card'] >= 130, f"Shop controls failed: {shop}"
                assert browser.evaluate("getComputedStyle(document.querySelector('select.orderby')).appearance") == 'none', "Shop sort styling did not apply"
                sort_options = browser.evaluate("[...document.querySelector('select.orderby').options].map(option=>option.value)")
                assert sort_options == ['menu_order', 'date', 'price', 'price-desc'], f"Unexpected sorting choices: {sort_options}"
                browser.evaluate("document.querySelector('select.orderby').value='price';document.querySelector('form.woocommerce-ordering').requestSubmit()")
                wait_for(lambda: browser.evaluate("location.search.includes('orderby=price') && document.querySelector('select.orderby')?.value==='price'"), "Shop sort did not apply")
                browser.navigate(BASE + "/")
                browser.evaluate("document.querySelector('.section-heading>a[href$=\"/shop/\"]').click()")
                wait_for(lambda: browser.evaluate("location.pathname === '/shop/'"), "Homepage View all products link failed")
                browser.evaluate("document.querySelector('.shop-category-links a').click()")
                wait_for(lambda: browser.evaluate("location.pathname.startsWith('/product-category/')"), "Shop category link failed")
                browser.navigate(BASE + "/?againbuy_test_country=US")
                assert browser.evaluate("!document.querySelector('.currency-switch') && document.querySelector('.hero-price')?.innerText.includes('$')"), "US country pricing failed"
                browser.call("Page.navigate", {"url": BASE + "/?againbuy_test_country=IN"})
                wait_for(lambda: browser.evaluate("document.querySelector('.hero-price')?.innerText.includes('₹')"), "India country pricing failed")
                print("Shop categories, sorting, and country pricing passed.")
                browser.navigate(f"{BASE}/?p={product_id}&post_type=product")
                browser.evaluate("document.querySelector('form.cart button.single_add_to_cart_button').click()")
                wait_for(lambda: browser.evaluate("Number(document.querySelector('.cart-link span')?.innerText) === 1"), "Add to cart did not update the header")
                browser.evaluate("document.querySelector('.cart-link').click()")
                wait_for(lambda: browser.evaluate("location.pathname.includes('/cart') && document.body.innerText.includes('AgainBuy test bottle')"), "Cart navigation or item display failed")
                print("Mobile add-to-cart and cart navigation passed.")
                for width in (320, 390, 768, 1280):
                    browser.call("Emulation.setDeviceMetricsOverride", {"width": width, "height": 850, "deviceScaleFactor": 1, "mobile": width < 768})
                    cart_layout = browser.evaluate("({width:innerWidth,scroll:document.documentElement.scrollWidth,checkout:!!document.querySelector('.checkout-button, .wc-block-cart__submit-button')})")
                    assert cart_layout["scroll"] <= cart_layout["width"] + 1 and cart_layout["checkout"], f"Cart layout failed at {width}px: {cart_layout}"
                browser.call("Emulation.setDeviceMetricsOverride", {"width": 390, "height": 850, "deviceScaleFactor": 1, "mobile": True})
                browser.evaluate("document.querySelector('.checkout-button, .wc-block-cart__submit-button').click()")
                wait_for(lambda: browser.evaluate("location.pathname.includes('/checkout') && !!document.querySelector('form.checkout, .wc-block-checkout')"), "Checkout form did not open")
                wait_for(lambda: browser.evaluate("!!document.querySelector('#billing_email, #email, input[type=email]')"), "Checkout email field did not render")
                location_enabled = browser.evaluate("!!window.againbuyLocation")
                if not location_enabled:
                    assert browser.evaluate("!document.querySelector('.checkout-location, script[src*=\"checkout-location.js\"], link[href*=\"checkout-location.css\"]')"), "Disabled location feature leaked into checkout"
                for width in (320, 390, 768, 1280):
                    browser.call("Emulation.setDeviceMetricsOverride", {"width": width, "height": 850, "deviceScaleFactor": 1, "mobile": width < 768})
                    checkout_layout = browser.evaluate("({width:innerWidth,scroll:document.documentElement.scrollWidth,billing:!!document.querySelector('#billing_email, #email, input[type=email]')})")
                    assert checkout_layout["scroll"] <= checkout_layout["width"] + 1 and checkout_layout["billing"], f"Checkout layout failed at {width}px: {checkout_layout}"
                print("Mobile checkout form passed.")
                browser.call("Emulation.setDeviceMetricsOverride", {"width": 390, "height": 850, "deviceScaleFactor": 1, "mobile": True})
                if location_enabled:
                    wait_for(lambda: browser.evaluate("!!document.querySelector('.checkout-location button')"), "Checkout location control did not render")
                    browser.evaluate("""document.querySelector('.checkout-location button').disabled=false;
                        navigator.geolocation.getCurrentPosition=success=>success({coords:{latitude:37.42255,longitude:-122.08463}});
                        const realFetch=window.fetch.bind(window);
                        window.fetch=(url,options)=>String(url).includes('/againbuy/v1/reverse-geocode')
                          ? Promise.resolve(new Response(JSON.stringify({country:'US',state:'CA',city:'Mountain View',postcode:'94043',address_1:'1600 Amphitheatre Parkway',approximate:false}),{status:200,headers:{'Content-Type':'application/json'}}))
                          : realFetch(url,options);
                        document.querySelector('.checkout-location button').click()""")
                    wait_for(lambda: browser.evaluate("document.querySelector('#billing-country')?.value==='US' && document.querySelector('#billing-address_1')?.value==='1600 Amphitheatre Parkway' && document.querySelector('#billing-city')?.value==='Mountain View'"), "Location autofill did not update the checkout fields")
                    print("Checkout location autofill passed with a mocked nearby address.")
                browser.navigate(BASE + "/")
                browser.evaluate("document.querySelector('#site-search').value = 'AgainBuy test bottle'; document.querySelector('.site-search').requestSubmit()")
                wait_for(lambda: browser.evaluate("(location.search.includes('post_type=product') || location.pathname.includes('/product/')) && document.body.innerText.includes('AgainBuy test bottle')"), "Product search failed")
                print("Mobile product search passed.")
                browser.navigate(BASE + "/product/muzzo-copper-kit/")
                wait_for(lambda: browser.evaluate("!!document.querySelector('.woocommerce-product-gallery .flex-active-slide')"), "Bottle gallery did not initialize")
                gallery = browser.evaluate("({speed:wc_single_product_params.flexslider.slideshowSpeed,auto:wc_single_product_params.flexslider.slideshow,photos:document.querySelectorAll('.woocommerce-product-gallery__image').length,active:[...document.querySelectorAll('.woocommerce-product-gallery__image')].findIndex(el=>el.classList.contains('flex-active-slide'))})")
                assert gallery['speed'] == 2000 and gallery['auto'] and gallery['photos'] > 1, f"Bottle gallery options failed: {gallery}"
                wait_for(lambda: browser.evaluate(f"[...document.querySelectorAll('.woocommerce-product-gallery__image')].findIndex(el=>el.classList.contains('flex-active-slide')) !== {gallery['active']}"), "Bottle gallery did not advance", seconds=5)
                product_details = browser.evaluate("({intro:!!document.querySelector('.againbuy-product-intro'),related:document.querySelectorAll('.related ul.products li.product').length})")
                assert product_details['intro'] and product_details['related'] > 0, f"Product summary or recommendations missing: {product_details}"
                print("Bottle gallery advances automatically at two seconds.")
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()


if __name__ == "__main__":
    run(int(sys.argv[1]))
