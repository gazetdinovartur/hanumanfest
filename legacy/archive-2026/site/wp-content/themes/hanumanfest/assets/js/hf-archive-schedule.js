(function () {
  const root = document.querySelector('[data-hf-archive-schedule]');
  if (!root) return;
  const events = Array.from(root.querySelectorAll('.hf-schedule__event'));
  const empty = root.querySelector('.hf-schedule__empty');
  const dayTabs = root.querySelectorAll('.hf-schedule__day-tab');
  const venueChips = root.querySelectorAll('.hf-schedule__venue-chip');
  const state = {
    day: (dayTabs[0] && dayTabs[0].dataset.day) || '',
    venue: ''
  };

  function apply() {
    let visible = 0;
    events.forEach((card) => {
      const matchDay = card.dataset.day === state.day;
      const matchVenue = !state.venue || card.dataset.venue === state.venue;
      const show = matchDay && matchVenue;
      card.classList.toggle('is-hidden', !show);
      if (show) visible += 1;
    });
    if (empty) empty.classList.toggle('is-hidden', visible > 0);
    dayTabs.forEach((tab) => {
      const on = tab.dataset.day === state.day;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    venueChips.forEach((chip) => {
      chip.classList.toggle('is-active', (chip.dataset.venue || '') === state.venue);
    });
  }

  dayTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      state.day = tab.dataset.day || '';
      apply();
    });
  });
  venueChips.forEach((chip) => {
    chip.addEventListener('click', () => {
      state.venue = chip.dataset.venue || '';
      apply();
    });
  });
  apply();
})();
