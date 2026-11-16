(function ($) {
    'use strict';
    var timer;
    $(document.body).on('added_to_cart', function () {
        var toast = document.querySelector('.againbuy-cart-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'againbuy-cart-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = 'Added to your cart';
        toast.classList.add('is-visible');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.remove('is-visible'); }, 2500);
    });
})(jQuery);
