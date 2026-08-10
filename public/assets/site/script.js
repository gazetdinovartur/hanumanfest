document.addEventListener("DOMContentLoaded", () => {
  // === МОДАЛЬНОЕ ОКНО МАСТЕРОВ ===
  const modal = document.querySelector(".modal");
  const modalOverlay = document.querySelector(".modal-overlay");
  const modalClose = document.querySelector(".modal-close");
  const modalContent = document.querySelector(".modal-content");
  const masterCards = document.querySelectorAll(".master-card");

  if (modal && modalOverlay && modalClose && modalContent && masterCards.length) {
    masterCards.forEach(card => {
      card.addEventListener("click", () => {
        const modalData = card.querySelector(".master-modal-data");
        const content = modalData?.querySelector(".master-content");
        modalContent.innerHTML = content ? content.innerHTML : "";

        modal.classList.add("active");
        modalOverlay.classList.add("active");
        modalClose.classList.add("active");
        document.body.style.overflow = "hidden";
      });
    });

    [modalOverlay, modalClose].forEach(el => {
      el.addEventListener("click", () => {
        modal.classList.remove("active");
        modalOverlay.classList.remove("active");
        modalClose.classList.remove("active");
        document.body.style.overflow = "";
      });
    });
  }

  // === МОДАЛЬНОЕ ОКНО ОТЗЫВОВ ===
  const reviewButtons = document.querySelectorAll(".hf-review-readmore");

  if (reviewButtons.length && modal && modalOverlay && modalClose && modalContent) {
    reviewButtons.forEach(btn => {
      btn.addEventListener("click", () => {
        const data = btn.closest(".hf-review-card")?.querySelector(".review-modal-data");
        const content = data?.querySelector(".review-content");
        modalContent.innerHTML = content ? content.innerHTML : "";

        modal.classList.add("active");
        modalOverlay.classList.add("active");
        modalClose.classList.add("active");
        document.body.style.overflow = "hidden";
      });
    });
  }

    // === МОДАЛЬНОЕ ОКНО ГОСТЕЙ ===
  const guestButtons = document.querySelectorAll(".hf-guest-readmore");

  if (guestButtons.length && modal && modalOverlay && modalClose && modalContent) {
    guestButtons.forEach(btn => {
      btn.addEventListener("click", () => {
        const data = btn.closest(".guest-card")?.querySelector(".guest-modal-data");
        const content = data?.querySelector(".guest-content");
        modalContent.innerHTML = content ? content.innerHTML : "";

        modal.classList.add("active");
        modalOverlay.classList.add("active");
        modalClose.classList.add("active");
        document.body.style.overflow = "hidden";
      });
    });
  }

  // === МОБИЛЬНОЕ МЕНЮ ===
  const menu = document.getElementById("mobileMenu");
  const overlay = document.querySelector(".mobile-menu-overlay");
  const toggler = document.querySelector(".navbar-toggler");

  if (menu && overlay && toggler) {
    const menuLinks = menu.querySelectorAll("a");

    menu.addEventListener("show.bs.collapse", () => overlay.classList.add("active"));
    menu.addEventListener("hidden.bs.collapse", () => overlay.classList.remove("active"));

    overlay.addEventListener("click", () => {
      const bsCollapse = bootstrap.Collapse.getInstance(menu);
      if (bsCollapse) bsCollapse.hide();
    });

    menuLinks.forEach(link => {
      link.addEventListener("click", () => {
        const bsCollapse = bootstrap.Collapse.getInstance(menu);
        if (bsCollapse) bsCollapse.hide();
        overlay.classList.remove("active");
      });
    });
  }

  // === FAQ ===
  const faqItems = document.querySelectorAll(".faq-item");
  faqItems.forEach(item => {
    const btn = item.querySelector(".faq-question");
    const answer = item.querySelector(".faq-answer");
    if (!btn || !answer) return;

    btn.addEventListener("click", e => {
      e.preventDefault();
      const isActive = item.classList.contains("active");

      faqItems.forEach(i => {
        i.classList.remove("active");
        const ans = i.querySelector(".faq-answer");
        if (ans) ans.style.height = 0;
      });

      if (!isActive) {
        item.classList.add("active");
        answer.style.height = answer.scrollHeight + "px";
      }
    });
  });

  // === АНИМАЦИЯ ПОЯВЛЕНИЯ ===
  const fadeElements = document.querySelectorAll(".fade-in");
  if (fadeElements.length) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2 });

    fadeElements.forEach(el => observer.observe(el));
  }

  const festivalItems = document.querySelectorAll('.festival-list li');

  const festivalObserver = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          festivalObserver.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.15
    }
  );

  festivalItems.forEach(item => festivalObserver.observe(item));
  
});