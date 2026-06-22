(function () {
  'use strict';

  const DAY_SHORT = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

  const DEFAULT_API_BASE = 'https://апи.хануманфест.рф/api';

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-hf-schedule]').forEach(initWidget);
  });

  function resolveApiBase(config) {
    if (config.apiBase) {
      return config.apiBase.replace(/\/$/, '');
    }

    const host = window.location.hostname;
    if (host === 'localhost' || host === '127.0.0.1') {
      return `${window.location.origin}/api`;
    }

    return DEFAULT_API_BASE;
  }

  function initWidget(root) {
    const config = window.hfSchedule || {};
    const apiBase = resolveApiBase(config);
    const productSlug = config.productSlug || 'hanuman-fest-2026';
    const layout = root.dataset.layout || 'compact';

    if (!apiBase) {
      showError(root, 'Не настроен адрес API расписания.');
      return;
    }

    fetch(`${apiBase}/products/${encodeURIComponent(productSlug)}/schedule`)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then((data) => render(root, data, layout))
      .catch((error) => {
        console.error('Schedule load failed:', apiBase, error);
        showError(root, 'Не удалось загрузить расписание. Попробуйте позже.');
      });
  }

  function render(root, data, layout) {
    const days = data.days || [];
    if (!days.length) {
      showError(root, 'Расписание пока пусто.');
      return;
    }

    const state = {
      dayDate: parseParam('day') || days[0].date,
      venueSlug: parseParam('venue') || '',
    };

    if (!days.some((day) => day.date === state.dayDate)) {
      state.dayDate = days[0].date;
    }

    const allVenues = collectVenues(days);
    let nowPlayingId = findNowPlayingId(days);

    root.innerHTML = '';
    root.classList.add('hf-schedule--ready');
    if (layout === 'full') {
      root.classList.add('hf-schedule--full');
    }

    const tabs = document.createElement('div');
    tabs.className = 'hf-schedule__days';
    tabs.setAttribute('role', 'tablist');

    days.forEach((day) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'hf-schedule__day-tab' + (day.date === state.dayDate ? ' is-active' : '');
      button.setAttribute('role', 'tab');
      button.setAttribute('aria-selected', day.date === state.dayDate ? 'true' : 'false');
      button.dataset.date = day.date;
      button.innerHTML =
        `<span class="hf-schedule__day-short">${dayShortFromDate(day.date)}</span>` +
        `<span class="hf-schedule__day-date">${formatDayDate(day.date)}</span>`;
      button.addEventListener('click', () => {
        state.dayDate = day.date;
        syncUrl(state);
        updateTabs();
        rerenderEvents();
      });
      tabs.appendChild(button);
    });
    root.appendChild(tabs);

    const venuesWrap = document.createElement('div');
    venuesWrap.className = 'hf-schedule__venues';

    venuesWrap.appendChild(
      createVenueChip('Все площадки', '', !state.venueSlug, () => {
        state.venueSlug = '';
        syncUrl(state);
        updateVenueChips();
        rerenderEvents();
      }),
    );

    allVenues.forEach((venue) => {
      venuesWrap.appendChild(
        createVenueChip(venue.name, venue.slug, state.venueSlug === venue.slug, () => {
          state.venueSlug = venue.slug;
          syncUrl(state);
          updateVenueChips();
          rerenderEvents();
        }),
      );
    });
    root.appendChild(venuesWrap);

    const list = document.createElement('div');
    list.className = 'hf-schedule__events';
    root.appendChild(list);

    function updateTabs() {
      tabs.querySelectorAll('.hf-schedule__day-tab').forEach((button) => {
        const active = button.dataset.date === state.dayDate;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    }

    function updateVenueChips() {
      venuesWrap.querySelectorAll('.hf-schedule__venue-chip').forEach((chip) => {
        chip.classList.toggle('is-active', (chip.dataset.slug || '') === state.venueSlug);
      });
    }

    function rerenderEvents() {
      const day = days.find((item) => item.date === state.dayDate);
      list.innerHTML = '';

      if (!day) {
        return;
      }

      let events = flattenDayEvents(day);
      if (state.venueSlug) {
        events = events.filter((event) => event.venueSlug === state.venueSlug);
      }

      if (!events.length) {
        list.innerHTML = '<p class="hf-schedule__empty text-muted text-center py-4">Нет событий для выбранных фильтров.</p>';
        return;
      }

      events.sort((a, b) => new Date(a.startsAt) - new Date(b.startsAt));

      list.classList.toggle(
        'hf-schedule__events--timeline',
        layout === 'full' && window.matchMedia('(min-width: 992px)').matches,
      );

      events.forEach((event) => {
        list.appendChild(renderEventCard(event, event.id === nowPlayingId));
      });
    }

    rerenderEvents();

    window.addEventListener('resize', rerenderEvents);

    setInterval(() => {
      const nextNowPlayingId = findNowPlayingId(days);
      if (nextNowPlayingId !== nowPlayingId) {
        nowPlayingId = nextNowPlayingId;
        rerenderEvents();
      }
    }, 60000);
  }

  function createVenueChip(label, slug, active, onClick) {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'hf-schedule__venue-chip' + (active ? ' is-active' : '');
    chip.dataset.slug = slug;
    chip.textContent = label;
    chip.addEventListener('click', onClick);
    return chip;
  }

  function renderEventCard(event, isNow) {
    const card = document.createElement('article');
    card.className = 'hf-schedule__event';
    if (event.type === 'meal') {
      card.classList.add('hf-schedule__event--meal');
    }
    if (event.type === 'service') {
      card.classList.add('hf-schedule__event--service');
    }
    if (isNow) {
      card.classList.add('hf-schedule__event--now');
    }

    const time = document.createElement('time');
    time.className = 'hf-schedule__event-time';
    time.dateTime = event.startsAt;
    time.textContent = formatTimeRange(event.startsAt, event.endsAt);

    const body = document.createElement('div');
    body.className = 'hf-schedule__event-body';

    const title = document.createElement('h3');
    title.className = 'hf-schedule__event-title';
    title.textContent = event.title;

    const badge = document.createElement('span');
    badge.className = 'hf-schedule__event-venue';
    badge.textContent = event.venueName;

    body.appendChild(title);
    body.appendChild(badge);
    card.appendChild(time);
    card.appendChild(body);

    return card;
  }

  function collectVenues(days) {
    const map = new Map();
    days.forEach((day) => {
      (day.venues || []).forEach((venue) => {
        if (!map.has(venue.slug)) {
          map.set(venue.slug, { slug: venue.slug, name: venue.name });
        }
      });
    });
    return Array.from(map.values());
  }

  function flattenDayEvents(day) {
    const events = [];
    (day.venues || []).forEach((venue) => {
      (venue.events || []).forEach((event) => {
        events.push({
          ...event,
          venueSlug: venue.slug,
          venueName: venue.name,
        });
      });
    });
    return events;
  }

  function findNowPlayingId(days) {
    const now = Date.now();
    for (const day of days) {
      for (const venue of day.venues || []) {
        for (const event of venue.events || []) {
          const start = new Date(event.startsAt).getTime();
          const end = new Date(event.endsAt).getTime();
          if (now >= start && now < end) {
            return event.id;
          }
        }
      }
    }
    return null;
  }

  function parseParam(name) {
    return new URLSearchParams(window.location.search).get(name) || '';
  }

  function syncUrl(state) {
    const url = new URL(window.location.href);
    url.searchParams.set('day', state.dayDate);
    if (state.venueSlug) {
      url.searchParams.set('venue', state.venueSlug);
    } else {
      url.searchParams.delete('venue');
    }
    history.replaceState(null, '', url.toString());
  }

  function dayShortFromDate(isoDate) {
    const date = new Date(`${isoDate}T12:00:00`);
    return DAY_SHORT[date.getDay()] || isoDate.slice(5);
  }

  function formatDayDate(isoDate) {
    const [, month, day] = isoDate.split('-');
    return `${day}.${month}`;
  }

  function formatTimeRange(startIso, endIso) {
    return `${formatTime(startIso)}–${formatTime(endIso)}`;
  }

  function formatTime(iso) {
    const date = new Date(iso);
    return date.toLocaleTimeString('ru-RU', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone: 'Europe/Moscow',
    });
  }

  function showError(root, message) {
    root.innerHTML = `<p class="hf-schedule__error text-center text-muted py-4">${message}</p>`;
  }
})();
