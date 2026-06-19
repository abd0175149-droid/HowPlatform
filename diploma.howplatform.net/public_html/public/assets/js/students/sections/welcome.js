(() => {
  const storageKey = 'welcome:active-section';

  const readStored = () => {
    try {
      return window.localStorage.getItem(storageKey);
    } catch (error) {
      return null;
    }
  };

  const writeStored = (value) => {
    try {
      window.localStorage.setItem(storageKey, value);
    } catch (error) {
      /* ignore storage issues */
    }
  };

  const focusHeading = (section) => {
    const heading = section?.querySelector('.section-title');
    if (heading) {
      heading.focus({ preventScroll: false });
    }
  };

  const initialiseTabs = () => {
    const navButtons = Array.from(document.querySelectorAll('.section-nav [data-section-target]'));
    const sections = Array.from(document.querySelectorAll('.content-section'));

    if (!navButtons.length || !sections.length) {
      return;
    }

    const findSection = (id) => sections.find((section) => section.id === id);

    const toggleSection = (id, options = {}) => {
      const targetSection = findSection(id);
      if (!targetSection) {
        return;
      }

      sections.forEach((section) => {
        const isActive = section === targetSection;
        section.classList.toggle('is-active', isActive);
        section.setAttribute('aria-hidden', String(!isActive));
      });

      navButtons.forEach((button) => {
        const isActive = button.dataset.sectionTarget === id;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', String(isActive));
        button.setAttribute('tabindex', isActive ? '0' : '-1');
      });

      if (options.focusContent) {
        focusHeading(targetSection);
      }

      if (options.persist !== false) {
        writeStored(id);
      }

      if (options.updateHash) {
        history.replaceState(null, '', `#${id}`);
      }
    };

    const activate = (id, options) => {
      const sectionExists = !!findSection(id);
      if (sectionExists) {
        toggleSection(id, options);
        return true;
      }
      return false;
    };

    let initial = window.location.hash.replace('#', '') || '';
    if (!activate(initial, { focusContent: false, updateHash: true, persist: false })) {
      const stored = readStored();
      if (!activate(stored, { focusContent: false, updateHash: true })) {
        activate(sections[0].id, { focusContent: false, updateHash: true, persist: false });
      }
    }

    const handleButtonClick = (event) => {
      const button = event.currentTarget;
      toggleSection(button.dataset.sectionTarget, { focusContent: true, updateHash: true });
    };

    const focusByIndex = (index) => {
      const button = navButtons[(index + navButtons.length) % navButtons.length];
      if (button) {
        button.focus();
      }
    };

    navButtons.forEach((button, index) => {
      button.addEventListener('click', handleButtonClick);
      button.addEventListener('keydown', (event) => {
        switch (event.key) {
          case 'ArrowDown':
          case 'ArrowRight':
            event.preventDefault();
            focusByIndex(index + 1);
            break;
          case 'ArrowUp':
          case 'ArrowLeft':
            event.preventDefault();
            focusByIndex(index - 1);
            break;
          case 'Home':
            event.preventDefault();
            focusByIndex(0);
            break;
          case 'End':
            event.preventDefault();
            focusByIndex(navButtons.length - 1);
            break;
          case 'Enter':
          case ' ':
            event.preventDefault();
            toggleSection(button.dataset.sectionTarget, { focusContent: true, updateHash: true });
            break;
          default:
            break;
        }
      });
    });

    window.addEventListener('hashchange', () => {
      const id = window.location.hash.replace('#', '');
      activate(id, { focusContent: true, updateHash: false });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseTabs, { once: true });
  } else {
    initialiseTabs();
  }
})();
