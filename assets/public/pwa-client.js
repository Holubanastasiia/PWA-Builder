(function () {
  if (!window.wpPwaBuilder) {
    return;
  }

  const config = window.wpPwaBuilder;
  const standaloneQuery = window.matchMedia ? window.matchMedia('(display-mode: standalone)') : null;
  let deferredPrompt = null;
  let installed = false;

  function storageKey(name) {
    return ['wpPwaBuilder', config.appSlug || config.appId || 'app', name].join(':');
  }

  function rememberLaunchUrl(target, installType) {
    if (!target || !target.href) {
      return;
    }

    try {
      window.localStorage.setItem(storageKey('launchUrl'), target.href);
      window.localStorage.setItem(storageKey('installType'), installType);
    } catch (error) {
      track('launch_url_storage_failed', { message: error.message });
    }
  }

  function buttonLabel(target, key, fallback) {
    const value = target.getAttribute('data-pwa-' + key + '-label');

    return value || fallback;
  }

  function setButtonState(target, state) {
    if (!target) {
      return;
    }

    if (!target.dataset.pwaOriginalLabel) {
      target.dataset.pwaOriginalLabel = target.textContent ? target.textContent.trim() : '';
    }

    target.classList.remove('is-loading', 'is-ready');
    target.removeAttribute('aria-busy');
    target.dataset.pwaState = state;

    if (state === 'loading') {
      target.classList.add('is-loading');
      target.setAttribute('aria-busy', 'true');
      target.textContent = buttonLabel(target, 'loading', '');
      return;
    }

    if (state === 'ready') {
      target.classList.add('is-ready');
      target.textContent = buttonLabel(target, 'open', 'Open');
      return;
    }

    target.textContent = target.dataset.pwaOriginalLabel || buttonLabel(target, 'default', 'Continue');
  }

  function track(type, payload) {
    window.dispatchEvent(
      new CustomEvent('wp-pwa-builder:track', {
        detail: {
          appId: config.appId,
          appSlug: config.appSlug,
          type: type,
          payload: payload || {},
        },
      })
    );

    return Promise.resolve();
  }

  function isStandalone() {
    return (
      (standaloneQuery && standaloneQuery.matches) ||
      window.navigator.standalone === true
    );
  }

  function redirectToStart(payload) {
    track('redirect_to_start', payload);

    if (config.isPreview || !config.startUrl) {
      return;
    }

    window.location.href = config.startUrl;
  }

  function redirectStandaloneLanding(action) {
    if (!isStandalone() || config.isPreview) {
      return false;
    }

    redirectToStart({ installType: 'install', action: action });
    return true;
  }

  if (redirectStandaloneLanding('standalone_landing_open')) {
    return;
  }

  async function handleInstallClick(event, target) {
    event.preventDefault();

    if (target.dataset.pwaState === 'ready' || installed) {
      redirectToStart({ installType: 'install', href: target.href || '', action: 'open' });
      return;
    }

    track('click', {
      key: target.getAttribute('data-pwa-track') || 'install_cta',
      href: target.href || '',
      text: target.textContent ? target.textContent.trim().slice(0, 120) : '',
    });

    if (config.isPreview) {
      track('preview_install_click', {
        key: target.getAttribute('data-pwa-track') || 'install_cta',
      });
      return;
    }

    let installType = 'redirect';
    let installAccepted = false;

    setButtonState(target, 'loading');
    rememberLaunchUrl(target, installType);

    if (deferredPrompt) {
      track('install_prompt_shown');
      deferredPrompt.prompt();

      try {
        const choice = await deferredPrompt.userChoice;
        installType = choice.outcome === 'accepted' ? 'install' : 'redirect';
        installAccepted = choice.outcome === 'accepted';
        track(choice.outcome === 'accepted' ? 'install_prompt_accepted' : 'install_prompt_dismissed', {
          outcome: choice.outcome,
        });
      } catch (error) {
        track('install_prompt_failed', { message: error.message });
      }

      deferredPrompt = null;
    } else {
      track('install_prompt_unavailable');
    }

    rememberLaunchUrl(target, installType);

    if (installAccepted) {
      installed = true;
      setButtonState(target, 'ready');
      return;
    }

    setButtonState(target, 'default');
    redirectToStart({ installType: installType, href: target.href || '' });
  }

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker
        .register(config.serviceWorkerUrl, { scope: config.serviceWorkerScope })
        .then(function (registration) {
          track('service_worker_registered', { scope: registration.scope });
        })
        .catch(function (error) {
          track('service_worker_failed', { message: error.message });
        });
    });
  }

  window.addEventListener('appinstalled', function () {
    installed = true;
    track('app_install');

    document.querySelectorAll('[data-pwa-install]').forEach(function (target) {
      setButtonState(target, 'ready');
    });

    window.setTimeout(function () {
      redirectStandaloneLanding('appinstalled_standalone_open');
    }, 250);
  });

  if (standaloneQuery && typeof standaloneQuery.addEventListener === 'function') {
    standaloneQuery.addEventListener('change', function (event) {
      if (event.matches) {
        redirectStandaloneLanding('display_mode_standalone');
      }
    });
  }

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredPrompt = event;
    window.wpPwaBuilderInstallPrompt = event;
    track('install_prompt_available');
  });

  document.addEventListener('click', function (event) {
    const installTarget = event.target.closest('[data-pwa-install]');

    if (installTarget) {
      handleInstallClick(event, installTarget);
      return;
    }

    const target = event.target.closest('[data-pwa-track]');

    if (!target) {
      return;
    }

    track('click', {
      key: target.getAttribute('data-pwa-track'),
      href: target.href || '',
      text: target.textContent ? target.textContent.trim().slice(0, 120) : '',
    });
  });

  track('visit', {
    path: window.location.pathname,
    referrer: document.referrer || '',
    standalone: isStandalone(),
  });
})();
