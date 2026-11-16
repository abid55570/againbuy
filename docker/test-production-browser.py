import json
import importlib.util
import os
import shutil
import subprocess
import tempfile
import time
import urllib.request
from pathlib import Path

from websockets.sync.client import connect

mobile_spec = importlib.util.spec_from_file_location("againbuy_mobile", Path(__file__).with_name("test-mobile-browser.py"))
mobile = importlib.util.module_from_spec(mobile_spec)
mobile_spec.loader.exec_module(mobile)
Browser = mobile.Browser
wait_for = mobile.wait_for


BASE = os.environ.get("AGAINBUY_TEST_URL", "https://againbuy.in").rstrip("/")
CHROME = os.environ.get("CHROME_PATH") or shutil.which("chrome") or r"C:\Program Files\Google\Chrome\Application\chrome.exe"


def run():
    if not Path(CHROME).is_file():
        raise RuntimeError("Chrome missing; set CHROME_PATH")
    with tempfile.TemporaryDirectory(prefix="againbuy-production-") as profile:
        process = subprocess.Popen(
            [CHROME, "--headless=new", "--no-first-run", "--disable-gpu", "--remote-debugging-port=0", f"--user-data-dir={profile}", "about:blank"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        try:
            port_file = Path(profile, "DevToolsActivePort")
            port = wait_for(lambda: port_file.read_text().splitlines()[0] if port_file.exists() else None, "Chrome did not start")
            pages = json.load(urllib.request.urlopen(f"http://127.0.0.1:{port}/json/list", timeout=5))
            page = next(item for item in pages if item["type"] == "page")
            with connect(page["webSocketDebuggerUrl"], origin=f"http://127.0.0.1:{port}", max_size=5_000_000) as socket:
                browser = Browser(socket)
                browser.call("Page.enable")
                browser.call("Runtime.enable")
                paths = ("/", "/shop/", "/product/muzzo-copper-kit/", "/cart/", "/my-account/")
                for width in (320, 390, 768, 1280):
                    browser.call("Emulation.setDeviceMetricsOverride", {"width": width, "height": 850, "deviceScaleFactor": 1, "mobile": width < 768})
                    for path in paths:
                        browser.navigate(BASE + path)
                        state = browser.evaluate("({protocol:location.protocol,width:innerWidth,scroll:document.documentElement.scrollWidth,main:!!document.querySelector('main'),broken:[...document.images].filter(i=>i.complete&&i.naturalWidth===0).length})")
                        assert state["protocol"] == "https:" and state["main"] and state["broken"] == 0, f"Page failed at {width}px: {path}, {state}"
                        assert state["scroll"] <= state["width"] + 1, f"Horizontal overflow at {width}px: {path}, {state}"
                    print(f"Production layout passed: {width}px")
                    browser.navigate(BASE + "/shop/")
                    browser.evaluate("window.scrollTo(0, 650)")
                    wait_for(lambda: browser.evaluate("scrollY >= 600"), "Shop did not scroll")
                    header = browser.evaluate("(()=>{const h=document.querySelector('.site-header');const r=h.getBoundingClientRect();return {top:r.top,bottom:r.bottom,position:getComputedStyle(h).position,nav:!!h.querySelector('.site-nav'),cart:!!h.querySelector('.cart-link')}})()")
                    assert header['position'] == 'sticky' and abs(header['top']) <= 1 and header['bottom'] > 0 and header['nav'] and header['cart'], f"Header disappeared after scroll at {width}px: {header}"
                assert browser.evaluate("document.querySelectorAll('ul.products li.product').length") >= 11, "Shop catalog is unexpectedly incomplete"
                browser.navigate(BASE + "/product/muzzo-copper-kit/")
                product = browser.evaluate("({title:!!document.querySelector('.product_title'),price:!!document.querySelector('.summary .price'),cart:!!document.querySelector('form.cart'),photos:document.querySelectorAll('.woocommerce-product-gallery__image').length,related:document.querySelectorAll('.related li.product').length})")
                assert product["title"] and product["price"] and product["cart"] and product["photos"] > 1 and product["related"] > 0, f"Product detail failed: {product}"
                browser.navigate(BASE + "/my-account/")
                assert browser.evaluate("!!document.querySelector('.woocommerce-form-register')"), "Customer registration form is missing"
                assert browser.evaluate("getComputedStyle(document.querySelector('#customer_login .u-column1')).display !== 'none' && getComputedStyle(document.querySelector('#customer_login .u-column2')).display === 'none'"), "Sign-in must show only the login form"
                browser.navigate(BASE + "/my-account/?account=create")
                assert browser.evaluate("getComputedStyle(document.querySelector('#customer_login .u-column1')).display === 'none' && getComputedStyle(document.querySelector('#customer_login .u-column2')).display !== 'none'"), "Create account must show only the registration form"
                for width in (320, 390, 768, 1280):
                    browser.call("Emulation.setDeviceMetricsOverride", {"width": width, "height": 850, "deviceScaleFactor": 1, "mobile": width < 768})
                    assert browser.evaluate("document.documentElement.scrollWidth <= innerWidth + 1"), f"Registration overflow at {width}px"
                browser.navigate(BASE + "/")
                assert browser.evaluate("document.querySelectorAll('.featured-slide').length") == 2, "Featured products missing"
                wait_for(lambda: browser.evaluate("!document.querySelectorAll('.featured-slide')[1].hidden"), "DriftOn banner did not rotate", seconds=5)
                assert browser.evaluate("document.querySelectorAll('.featured-slide')[1].querySelector('.hero-price').innerText.length > 0 && document.querySelectorAll('.featured-slide')[1].querySelector('.hero-art img').naturalWidth > 0"), "DriftOn image or price missing"
                browser.evaluate("document.querySelector('a.add_to_cart_button')?.click()")
                wait_for(lambda: browser.evaluate("Number(document.querySelector('.header-cart-count')?.innerText) === 1"), "AJAX cart count did not update")
                wait_for(lambda: browser.evaluate("document.querySelector('.againbuy-cart-toast')?.classList.contains('is-visible')"), "Cart confirmation toast did not appear")
                print("Production catalog and product detail passed.")
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()


if __name__ == "__main__":
    run()
