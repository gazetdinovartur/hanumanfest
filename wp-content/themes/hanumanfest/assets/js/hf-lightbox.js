document.addEventListener('DOMContentLoaded', function () {
  const galleryImgs = Array.from(document.querySelectorAll('.hf-gallery-carousel img, .wp-block-image img'));

  // если уже есть лайтбокс или нет изображений
  if (document.querySelector('.hf-lightbox')) return;
  if (!galleryImgs.length) return;

  // создаём DOM лайтбокса
  const lb = document.createElement('div');
  lb.className = 'hf-lightbox';
  lb.innerHTML = `
    <div class="hf-lightbox__inner" role="dialog" aria-modal="true">
      <img src="" alt="" class="hf-lightbox__img">
    </div>

    <button class="hf-lightbox__arrow hf-lightbox__arrow--prev" aria-label="Предыдущее">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="19 9 12 16 5 9"></polyline>
      </svg>
    </button>

    <button class="hf-lightbox__arrow hf-lightbox__arrow--next" aria-label="Следующее">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="5 15 12 8 19 15"></polyline>
      </svg>
    </button>

    <button class="hf-lightbox__close" aria-label="Закрыть">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>
  `;
  document.body.appendChild(lb);

  const lightbox = lb;
  const lbImg = lightbox.querySelector('.hf-lightbox__img');
  const prevBtn = lightbox.querySelector('.hf-lightbox__arrow--prev');
  const nextBtn = lightbox.querySelector('.hf-lightbox__arrow--next');
  const closeBtn = lightbox.querySelector('.hf-lightbox__close');

  let currentIndex = 0;
  let isOpen = false;

  const openLightbox = (index) => {
    currentIndex = index;
    lbImg.src = galleryImgs[currentIndex].currentSrc || galleryImgs[currentIndex].src;
    lightbox.classList.add('active');
    isOpen = true;
    document.body.style.overflow = 'hidden';
  };

  const closeLightbox = () => {
    lightbox.classList.remove('active');
    isOpen = false;
    lbImg.src = '';
    document.body.style.overflow = '';
  };

  const showNext = (dir = 1) => {
    currentIndex = (currentIndex + dir + galleryImgs.length) % galleryImgs.length;
    lbImg.style.opacity = 0;
    setTimeout(() => {
      lbImg.src = galleryImgs[currentIndex].currentSrc || galleryImgs[currentIndex].src;
      lbImg.style.opacity = 1;
    }, 120);
  };

  galleryImgs.forEach((img, i) => {
    img.addEventListener('click', (e) => {
      e.preventDefault();
      openLightbox(i);
    });
  });

  prevBtn.addEventListener('click', (e) => { e.stopPropagation(); showNext(-1); });
  nextBtn.addEventListener('click', (e) => { e.stopPropagation(); showNext(1); });
  closeBtn.addEventListener('click', (e) => { e.stopPropagation(); closeLightbox(); });

  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
  });

  document.addEventListener('keydown', (e) => {
    if (!isOpen) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') showNext(-1);
    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') showNext(1);
  });

  // свайп на мобилках
  let startX = 0, startY = 0;
  lbImg.addEventListener('touchstart', function (e) {
    const t = e.touches[0];
    startX = t.clientX; startY = t.clientY;
  }, { passive: true });

  lbImg.addEventListener('touchend', function (e) {
    const t = e.changedTouches[0];
    const dx = t.clientX - startX;
    const dy = t.clientY - startY;
    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
      if (dx < 0) showNext(1); else showNext(-1);
    }
  }, { passive: true });
});