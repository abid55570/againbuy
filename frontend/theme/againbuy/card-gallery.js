document.addEventListener('DOMContentLoaded', () => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const galleries = [...document.querySelectorAll('.againbuy-card-gallery')]
    .map(gallery => [...gallery.querySelectorAll('img')])
    .filter(images => images.length > 1);
  if (!galleries.length) return;
  let current = 0;
  window.setInterval(() => {
    if (document.hidden) return;
    current += 1;
    for (const images of galleries) {
      images.forEach((image, index) => { image.hidden = index !== current % images.length; });
    }
  }, 2000);
});
