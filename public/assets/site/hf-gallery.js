document.addEventListener("DOMContentLoaded", () => {
  const carousels = document.querySelectorAll(".hf-gallery-carousel, .hf-reviews-carousel");

  carousels.forEach((gallery) => {
    const track = gallery.querySelector(".hf-gallery-track");
    const prevBtn = gallery.querySelector(".hf-gallery-prev");
    const nextBtn = gallery.querySelector(".hf-gallery-next");
    if (!track || !prevBtn || !nextBtn) return;

    const items = Array.from(track.children);
    if (!items.length) return;

    const step = () => {
      const first = items[0];
      const styles = getComputedStyle(track);
      const gap = parseFloat(styles.columnGap || styles.gap) || 0;
      return first.getBoundingClientRect().width + gap;
    };

    const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);

    const currentIndex = () => {
      const size = step();
      if (size <= 0) return 0;
      return Math.round(track.scrollLeft / size);
    };

    const scrollToIndex = (index, behavior = "smooth") => {
      const last = Math.max(0, items.length - 1);
      const next = ((index % items.length) + items.length) % items.length;
      const left = Math.min(next * step(), maxScroll());
      track.scrollTo({ left, behavior });
    };

    const applyDelta = (delta) => {
      const max = maxScroll();
      if (max <= 0 || Math.abs(delta) < 0.4) return false;
      const next = Math.min(max, Math.max(0, track.scrollLeft + delta));
      if (next === track.scrollLeft) return false;
      track.scrollLeft = next;
      return true;
    };

    const wheelDelta = (e) => {
      let dx = e.deltaX;
      let dy = e.deltaY;
      if (e.deltaMode === 1) {
        dx *= 16;
        dy *= 16;
      } else if (e.deltaMode === 2) {
        dx *= track.clientWidth;
        dy *= track.clientHeight;
      }
      return { dx, dy };
    };

    prevBtn.addEventListener("click", () => scrollToIndex(currentIndex() - 1));
    nextBtn.addEventListener("click", () => scrollToIndex(currentIndex() + 1));

    gallery.addEventListener(
      "wheel",
      (e) => {
        const { dx, dy } = wheelDelta(e);
        const sideways = e.shiftKey ? dy || dx : dx;
        if (!e.shiftKey && Math.abs(dx) < Math.abs(dy)) return;
        if (applyDelta(sideways)) {
          e.preventDefault();
        }
      },
      { passive: false }
    );
  });
});
