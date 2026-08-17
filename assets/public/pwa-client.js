(function () {
  if (!window.wpPwaBuilder) {
    return;
  }

  const config = window.wpPwaBuilder;
  const standaloneQuery = window.matchMedia ? window.matchMedia('(display-mode: standalone)') : null;
  let deferredPrompt = null;
  let installed = false;
  let installTracked = false;
  let deferredPromptWaiters = [];
  const DEFERRED_PROMPT_WAIT_MS = 1500;

  function waitForDeferredPrompt(timeoutMs) {
    if (deferredPrompt) {
      return Promise.resolve(deferredPrompt);
    }

    return new Promise(function (resolve) {
      let settled = false;

      const waiter = function (event) {
        if (settled) {
          return;
        }

        settled = true;
        resolve(event);
      };

      deferredPromptWaiters.push(waiter);

      window.setTimeout(function () {
        if (settled) {
          return;
        }

        settled = true;
        deferredPromptWaiters = deferredPromptWaiters.filter(function (item) {
          return item !== waiter;
        });
        resolve(null);
      }, timeoutMs);
    });
  }

  let appInstalledWaiters = [];
  const APP_INSTALLED_WAIT_MS = 10000;
  const MIN_INSTALLING_DELAY_MS = 2500;

  function waitForAppInstalled(timeoutMs) {
    if (installed) {
      return Promise.resolve(true);
    }

    return new Promise(function (resolve) {
      let settled = false;

      const waiter = function () {
        if (settled) {
          return;
        }

        settled = true;
        resolve(true);
      };

      appInstalledWaiters.push(waiter);

      window.setTimeout(function () {
        if (settled) {
          return;
        }

        settled = true;
        appInstalledWaiters = appInstalledWaiters.filter(function (item) {
          return item !== waiter;
        });
        resolve(false);
      }, timeoutMs);
    });
  }

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

  // Тимчасовий QA-інструмент: вмикає детальне логування в консоль через ?pwa_debug=1,
  // щоб перевіряти таймінги install-флоу на реальних пристроях/проді.
  // Прибрати після підтвердження коректної роботи на продакшені — цей інструмент більше не буде потрібен.
  function isDebugEnabled() {
    try {
      if (/[?&]pwa_debug=1\b/.test(window.location.search)) {
        window.localStorage.setItem(storageKey('debug'), '1');
      }

      return window.localStorage.getItem(storageKey('debug')) === '1';
    } catch (error) {
      return false;
    }
  }

  function track(type, payload) {
    if (isDebugEnabled()) {
      console.log('[wp-pwa-builder]', type, payload || {});
    }
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

  function trackAppInstall(payload) {
    if (installTracked) {
      return;
    }

    installTracked = true;
    track('app_install', payload);
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

    const promptEvent = await waitForDeferredPrompt(DEFERRED_PROMPT_WAIT_MS);

    if (promptEvent) {
      track('install_prompt_shown');
      promptEvent.prompt();

      try {
        const choice = await promptEvent.userChoice;
        installType = choice.outcome === 'accepted' ? 'install' : 'redirect';
        installAccepted = choice.outcome === 'accepted';
        track(choice.outcome === 'accepted' ? 'install_prompt_accepted' : 'install_prompt_dismissed', {
          outcome: choice.outcome,
        });

        if (installAccepted) {
          trackAppInstall({ source: 'prompt_user_choice', outcome: choice.outcome });
        }
      } catch (error) {
        track('install_prompt_failed', { message: error.message });
      }

      deferredPrompt = null;
    } else {
      track('install_prompt_unavailable');
    }

    rememberLaunchUrl(target, installType);

    if (installAccepted) {
      const minDelay = new Promise(function (resolve) {
        window.setTimeout(resolve, MIN_INSTALLING_DELAY_MS);
      });
      const [reallyInstalled] = await Promise.all([
        waitForAppInstalled(APP_INSTALLED_WAIT_MS),
        minDelay,
      ]);
      track(reallyInstalled ? 'app_installed_confirmed' : 'app_installed_wait_timeout');
      installed = true;
      setButtonState(target, 'ready');
      return;
    }

    setButtonState(target, 'default');
    redirectToStart({ installType: installType, href: target.href || '' });
  }

  if ('serviceWorker' in navigator) {
      navigator.serviceWorker
        .register(config.serviceWorkerUrl, { scope: config.serviceWorkerScope })
        .then(function (registration) {
          track('service_worker_registered', { scope: registration.scope });
        })
        .catch(function (error) {
          track('service_worker_failed', { message: error.message });
        });
  }

  window.addEventListener('appinstalled', function () {
    installed = true;

    if (appInstalledWaiters.length) {
      const waiters = appInstalledWaiters;
      appInstalledWaiters = [];
      waiters.forEach(function (waiter) {
        waiter();
      });
    }

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

    if (deferredPromptWaiters.length) {
      const waiters = deferredPromptWaiters;
      deferredPromptWaiters = [];
      waiters.forEach(function (waiter) {
        waiter(event);
      });
    }
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
