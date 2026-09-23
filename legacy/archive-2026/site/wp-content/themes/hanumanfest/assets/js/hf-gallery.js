document.addEventListener("DOMContentLoaded", () => {
  const carousels = document.querySelectorAll(".hf-gallery-carousel, .hf-reviews-carousel");

  carousels.forEach(gallery => {
    const track = gallery.querySelector(".hf-gallery-track");
    const prevBtn = gallery.querySelector(".hf-gallery-prev");
    const nextBtn = gallery.querySelector(".hf-gallery-next");
    if (!track || !prevBtn || !nextBtn) return;

    let currentIndex = 0;
    const items = Array.from(track.children);
    const itemWidth = items[0]?.offsetWidth || 320;

    const scrollToIndex = (index) => {
      currentIndex = (index + items.length) % items.length; // зацикливание
      track.scrollTo({
        left: currentIndex * (itemWidth + 10),
        behavior: "smooth"
      });
    };

    prevBtn.addEventListener("click", () => scrollToIndex(currentIndex - 1));
    nextBtn.addEventListener("click", () => scrollToIndex(currentIndex + 1));
  });
});