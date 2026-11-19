(() => {
    const root = document.querySelector('.featured-carousel');
    if (!root) return;
    const slides = [...root.querySelectorAll('.featured-slide')];
    if (slides.length < 2) return;
    const controls = root.querySelector('.featured-controls');
    const dots = [...root.querySelectorAll('[data-slide]')];
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let active = 0;
    let paused = reduced.matches;
    let timer;
    const show = index => {
        active = index;
        slides.forEach((slide, i) => { slide.hidden = i !== active; });
        dots.forEach((dot, i) => dot.setAttribute('aria-pressed', String(i === active)));
    };
    const schedule = () => {
        clearInterval(timer);
        if (!paused && !document.hidden && !root.matches(':hover') && !root.contains(document.activeElement)) {
            timer = setInterval(() => show((active + 1) % slides.length), 3000);
        }
    };
    controls.hidden = false;
    dots.forEach(dot => dot.addEventListener('click', () => { show(Number(dot.dataset.slide)); schedule(); }));
    root.addEventListener('mouseenter', schedule);
    root.addEventListener('mouseleave', schedule);
    root.addEventListener('focusin', schedule);
    root.addEventListener('focusout', () => setTimeout(schedule, 0));
    document.addEventListener('visibilitychange', schedule);
    reduced.addEventListener('change', () => { paused = reduced.matches; schedule(); });
    schedule();
})();
