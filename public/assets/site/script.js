document.addEventListener("DOMContentLoaded", () => {
  // === ОБЩАЯ МОДАЛКА (мастера / гости / отзывы) ===
  const modal = document.querySelector("[data-hf-master-modal]");
  const modalOverlay = document.querySelector("[data-hf-master-overlay]");
  const modalClose = document.querySelector("[data-hf-master-close]");
  const modalContent = document.querySelector("[data-hf-master-body]");

  const openHfModal = (payloadRoot) => {
    if (!modal || !modalOverlay || !modalClose || !modalContent || !payloadRoot) return;
    const payload = payloadRoot.querySelector(".hf-modal-payload");
    modalContent.innerHTML = payload ? payload.innerHTML : payloadRoot.innerHTML;
    modal.classList.add("is-open");
    modalOverlay.classList.add("is-open");
    modalClose.classList.add("is-open");
    document.body.style.overflow = "hidden";
  };

  const closeHfModal = () => {
    if (!modal || !modalOverlay || !modalClose || !modalContent) return;
    modal.classList.remove("is-open");
    modalOverlay.classList.remove("is-open");
    modalClose.classList.remove("is-open");
    modalContent.innerHTML = "";
    document.body.style.overflow = "";
  };

  if (modal && modalOverlay && modalClose && modalContent) {
    document.querySelectorAll(".master-card").forEach((card) => {
      card.addEventListener("click", () => {
        openHfModal(card.querySelector(".master-modal-data"));
      });
    });

    document.querySelectorAll(".hf-review-readmore").forEach((btn) => {
      btn.addEventListener("click", () => {
        openHfModal(btn.closest(".hf-review-card")?.querySelector(".review-modal-data"));
      });
    });

    document.querySelectorAll(".hf-guest-readmore").forEach((btn) => {
      btn.addEventListener("click", () => {
        openHfModal(btn.closest(".guest-card")?.querySelector(".guest-modal-data"));
      });
    });

    [modalOverlay, modalClose].forEach((el) => {
      el.addEventListener("click", closeHfModal);
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("is-open")) {
        closeHfModal();
      }
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

  // === ПЕРЕХОДЫ ПО ЯКОРЯМ МЕНЮ ===
  function headerOffset() {
    const headerEl = document.getElementById("header");
    if (!headerEl) {
      return 88;
    }
    // После прыжка шапка станет компактной; не брать высоту оверлея на герое.
    if (document.body.classList.contains("is-home") && !headerEl.classList.contains("header-background--white")) {
      return 88;
    }
    return Math.round(headerEl.getBoundingClientRect().height) + 8;
  }

  function scrollToAnchor(id) {
    if (id === "hero") {
      window.scrollTo(0, 0);
      return true;
    }
    const target = document.getElementById(id);
    if (!target) {
      return false;
    }
    const top = Math.max(0, window.scrollY + target.getBoundingClientRect().top - headerOffset());
    window.scrollTo(0, top);
    return true;
  }

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[href*='#']");
    if (!link || link.getAttribute("href") === "#") {
      return;
    }
    const url = new URL(link.href, window.location.href);
    if (url.pathname !== window.location.pathname) {
      return;
    }
    const id = decodeURIComponent(url.hash.replace(/^#/, ""));
    if (!id) {
      return;
    }
    if (scrollToAnchor(id)) {
      event.preventDefault();
      if (history.replaceState) {
        history.replaceState(null, "", url.hash);
      }
    }
  });

  if (window.location.hash) {
    const id = decodeURIComponent(window.location.hash.replace(/^#/, ""));
    if (id && document.getElementById(id)) {
      if ("scrollRestoration" in history) {
        history.scrollRestoration = "manual";
      }
      scrollToAnchor(id);
    }
  }
});