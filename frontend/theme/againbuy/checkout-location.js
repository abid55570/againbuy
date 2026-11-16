document.addEventListener('DOMContentLoaded', () => {
  const config = window.againbuyLocation;
  if (!config) return;

  function updateField(id, value) {
    if (!value) return;
    const field = document.getElementById(id);
    if (!field) return;
    if (field.tagName === 'SELECT') {
      const option = [...field.options].find(item => item.value.toLowerCase() === value.toLowerCase() || item.text.toLowerCase() === value.toLowerCase());
      if (!option) return;
      value = option.value;
    }
    const prototype = field.tagName === 'SELECT' ? HTMLSelectElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, 'value').set.call(field, value);
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }

  async function fillAddress(address) {
    document.querySelector('#billing-fields .wc-block-components-address-card__edit')?.click();
    document.querySelector('#shipping-fields .wc-block-components-address-card__edit')?.click();
    for (const prefix of ['billing', 'shipping']) updateField(`${prefix}-country`, address.country);
    await new Promise(resolve => setTimeout(resolve, 300));
    for (const prefix of ['billing', 'shipping']) {
      updateField(`${prefix}-state`, address.state);
      updateField(`${prefix}-city`, address.city);
      updateField(`${prefix}-postcode`, address.postcode);
      updateField(`${prefix}-address_1`, address.address_1);
    }
  }

  function mount() {
    const step = document.querySelector('#billing-fields .wc-block-components-checkout-step__content');
    if (!step || step.querySelector('.checkout-location')) return;
    const box = document.createElement('div');
    box.className = 'checkout-location';
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Use my current location';
    button.disabled = !config.available || !navigator.geolocation;
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    status.textContent = config.available ? 'Allow location access to look up a nearby address with Google. Check it before placing your order.' : 'Location autofill is unavailable here. Enter your address below.';
    box.append(button, status);
    step.prepend(box);

    button.addEventListener('click', () => {
      button.disabled = true;
      status.textContent = 'Finding your address…';
      navigator.geolocation.getCurrentPosition(async position => {
        try {
          const response = await fetch(config.url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
          });
          const address = await response.json();
          if (!response.ok) throw new Error(address.message || 'Address lookup failed.');
          await fillAddress(address);
          status.textContent = address.approximate ? 'Approximate location added. Enter your building and street details, then check the address.' : 'Address added. Check the building number and delivery details before placing your order.';
        } catch (error) {
          status.textContent = error.message || 'Address lookup failed. Enter your address below.';
        } finally { button.disabled = false; }
      }, () => {
        status.textContent = 'Location permission was not granted. Enter your address below.';
        button.disabled = false;
      }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    });
  }

  mount();
  new MutationObserver(mount).observe(document.body, { childList: true, subtree: true });
});
