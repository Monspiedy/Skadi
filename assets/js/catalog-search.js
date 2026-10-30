(() => {
  'use strict';

  const search = document.querySelector('[data-catalog-search]');
  if (!search) return;

  const input = search.querySelector('[data-catalog-search-input]');
  const list = search.querySelector('[data-catalog-suggestions]');
  const status = search.querySelector('[data-catalog-search-status]');
  let suggestions = [];
  let activeIndex = -1;
  let requestController = null;
  let debounceTimer = null;
  let requestGeneration = 0;

  const normalizedInput = () => input.value.trim().replace(/\s+/g, ' ');

  const closeSuggestions = () => {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    activeIndex = -1;
  };

  const setActiveSuggestion = (index) => {
    const options = [...list.querySelectorAll('[role="option"]')];
    if (options.length === 0) return;
    activeIndex = (index + options.length) % options.length;
    options.forEach((option, optionIndex) => {
      option.setAttribute('aria-selected', optionIndex === activeIndex ? 'true' : 'false');
    });
    input.setAttribute('aria-activedescendant', options[activeIndex].id);
    options[activeIndex].scrollIntoView({ block: 'nearest' });
  };

  const renderSuggestions = () => {
    list.replaceChildren();
    activeIndex = -1;
    if (suggestions.length === 0) {
      closeSuggestions();
      status.textContent = 'No matching game suggestions.';
      return;
    }

    suggestions.forEach((game, index) => {
      const item = document.createElement('li');
      item.setAttribute('role', 'presentation');
      const link = document.createElement('a');
      link.id = `catalog-suggestion-${index}`;
      link.href = `${search.dataset.gameUrl}?id=${encodeURIComponent(game.id)}`;
      link.setAttribute('role', 'option');
      link.setAttribute('aria-selected', 'false');
      link.textContent = game.title;
      item.append(link);
      list.append(item);
    });
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    status.textContent = `${suggestions.length} game ${suggestions.length === 1 ? 'suggestion' : 'suggestions'} available.`;
  };

  const requestSuggestions = async (query, generation) => {
    if (generation !== requestGeneration || normalizedInput() !== query) return;

    const controller = new AbortController();
    requestController = controller;
    try {
      const url = `${search.dataset.suggestionsUrl}?q=${encodeURIComponent(query)}`;
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: controller.signal
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok || !Array.isArray(payload.suggestions)) {
        throw new Error('Suggestions unavailable.');
      }
      if (generation !== requestGeneration || normalizedInput() !== query || requestController !== controller) {
        return;
      }
      suggestions = payload.suggestions;
      renderSuggestions();
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (generation !== requestGeneration || normalizedInput() !== query || requestController !== controller) {
        return;
      }
      suggestions = [];
      list.replaceChildren();
      closeSuggestions();
      status.textContent = 'Live suggestions are unavailable. Submit the form to search.';
    } finally {
      if (requestController === controller) requestController = null;
    }
  };

  input.addEventListener('input', () => {
    window.clearTimeout(debounceTimer);
    requestController?.abort();
    requestController = null;
    requestGeneration += 1;
    suggestions = [];
    list.replaceChildren();
    closeSuggestions();
    status.textContent = '';

    const query = normalizedInput();
    if (query.length < 2) return;
    const generation = requestGeneration;
    debounceTimer = window.setTimeout(() => requestSuggestions(query, generation), 180);
  });
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSuggestions();
    } else if (!list.hidden && event.key === 'ArrowDown') {
      event.preventDefault();
      setActiveSuggestion(activeIndex + 1);
    } else if (!list.hidden && event.key === 'ArrowUp') {
      event.preventDefault();
      setActiveSuggestion(activeIndex - 1);
    } else if (!list.hidden && event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault();
      list.querySelectorAll('[role="option"]')[activeIndex].click();
    }
  });
  input.addEventListener('focus', () => {
    if (suggestions.length > 0) {
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }
  });
  document.addEventListener('pointerdown', (event) => {
    if (!search.contains(event.target)) closeSuggestions();
  });
})();
