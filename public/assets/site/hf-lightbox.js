document.addEventListener('DOMContentLoaded', function () {
  const galleryImgs = Array.from(document.querySelectorAll(
    '.hf-gallery-carousel img, .hf-gallery-archive__img, .wp-block-image img'
  ));

  if (document.querySelector('.hf-lightbox')) return;

  const lb = document.createElement('div');
  lb.className = 'hf-lightbox';
  lb.innerHTML = `
    <div class="hf-lightbox__inner" role="dialog" aria-modal="true">
      <img src="" alt="" class="hf-lightbox__img">
      <video class="hf-lightbox__video" controls playsinline></video>
    </div>

    <button class="hf-lightbox__arrow hf-lightbox__arrow--prev" aria-label="Предыдущее">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"></polyline>
      </svg>
    </button>

    <button class="hf-lightbox__arrow hf-lightbox__arrow--next" aria-label="Следующее">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="9 18 15 12 9 6"></polyline>
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
  const lbVideo = lightbox.querySelector('.hf-lightbox__video');
  const prevBtn = lightbox.querySelector('.hf-lightbox__arrow--prev');
  const nextBtn = lightbox.querySelector('.hf-lightbox__arrow--next');
  const closeBtn = lightbox.querySelector('.hf-lightbox__close');

  /** @type {'gallery'|'modal'} */
  let mode = 'gallery';
  let currentIndex = 0;
  let isOpen = false;
  /** @type {{src: string, kind: 'image'|'video'}[]} */
  let modalItems = [];

  const fullSrc = (img) => img.getAttribute('data-full-src') || img.currentSrc || img.src;

  const stopVideo = () => {
    lbVideo.pause();
    lbVideo.removeAttribute('src');
    lbVideo.load();
    lbVideo.classList.remove('is-visible');
  };

  const showMedia = (src, kind) => {
    if (kind === 'video') {
      lbImg.classList.remove('is-visible');
      lbImg.removeAttribute('src');
      lbVideo.src = src;
      lbVideo.classList.add('is-visible');
      lbVideo.play().catch(() => {});
    } else {
      stopVideo();
      lbImg.src = src;
      lbImg.classList.add('is-visible');
    }
  };

  const syncArrows = () => {
    const count = mode === 'modal' ? modalItems.length : galleryImgs.length;
    const show = count > 1;
    prevBtn.style.display = show ? '' : 'none';
    nextBtn.style.display = show ? '' : 'none';
  };

  const openGallery = (index) => {
    if (!galleryImgs.length) return;
    mode = 'gallery';
    currentIndex = index;
    showMedia(fullSrc(galleryImgs[currentIndex]), 'image');
    lightbox.classList.add('active');
    isOpen = true;
    document.body.style.overflow = 'hidden';
    syncArrows();
  };

  const openModalSet = (items, index) => {
    if (!items.length) return;
    mode = 'modal';
    modalItems = items;
    currentIndex = index;
    showMedia(modalItems[currentIndex].src, modalItems[currentIndex].kind);
    lightbox.classList.add('active');
    isOpen = true;
    document.body.style.overflow = 'hidden';
    syncArrows();
  };

  const closeLightbox = () => {
    lightbox.classList.remove('active');
    isOpen = false;
    stopVideo();
    lbImg.removeAttribute('src');
    lbImg.classList.remove('is-visible');
    modalItems = [];
    document.body.style.overflow = '';
  };

  const showNext = (dir = 1) => {
    if (mode === 'modal') {
      if (modalItems.length < 2) return;
      currentIndex = (currentIndex + dir + modalItems.length) % modalItems.length;
      const item = modalItems[currentIndex];
      lbImg.style.opacity = 0;
      lbVideo.style.opacity = 0;
      setTimeout(() => {
        showMedia(item.src, item.kind);
        lbImg.style.opacity = 1;
        lbVideo.style.opacity = 1;
      }, 120);
      return;
    }
    if (galleryImgs.length < 2) return;
    currentIndex = (currentIndex + dir + galleryImgs.length) % galleryImgs.length;
    lbImg.style.opacity = 0;
    setTimeout(() => {
      showMedia(fullSrc(galleryImgs[currentIndex]), 'image');
      lbImg.style.opacity = 1;
    }, 120);
  };

  galleryImgs.forEach((img, i) => {
    img.addEventListener('click', (e) => {
      e.preventDefault();
      openGallery(i);
    });
  });

  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-hf-modal-lightbox]');
    if (!link) return;
    e.preventDefault();
    e.stopPropagation();

    const root = link.closest('.hf-modal-payload__media')
      || link.closest('[data-hf-master-body]')
      || document;
    const links = Array.from(root.querySelectorAll('[data-hf-modal-lightbox]'));
    const items = links.map((el) => ({
      src: el.getAttribute('href') || '',
      kind: el.getAttribute('data-hf-media-kind') === 'video' ? 'video' : 'image',
    })).filter((item) => item.src !== '');
    const index = Math.max(0, links.indexOf(link));
    openModalSet(items, index);
  });

  prevBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    showNext(-1);
  });
  nextBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    showNext(1);
  });
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

  let startX = 0, startY = 0;
  const swipeTarget = lightbox.querySelector('.hf-lightbox__inner');
  swipeTarget.addEventListener('touchstart', function (e) {
    const t = e.touches[0];
    startX = t.clientX; startY = t.clientY;
  }, { passive: true });

  swipeTarget.addEventListener('touchend', function (e) {
    const t = e.changedTouches[0];
    const dx = t.clientX - startX;
    const dy = t.clientY - startY;
    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
      if (dx < 0) showNext(1); else showNext(-1);
    }
  }, { passive: true });
});
