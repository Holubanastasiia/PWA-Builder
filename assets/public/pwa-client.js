(function () {
  if (!window.wpPwaBuilder) {
    return;
  }

  const config = window.wpPwaBuilder;
  let deferredPrompt = null;

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
      window.matchMedia('(display-mode: standalone)').matches ||
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

  async function handleInstallClick(event, target) {
    event.preventDefault();

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

    if (deferredPrompt) {
      track('install_prompt_shown');
      deferredPrompt.prompt();

      try {
        const choice = await deferredPrompt.userChoice;
        installType = choice.outcome === 'accepted' ? 'install' : 'redirect';
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

    redirectToStart({ installType: installType });
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
    track('app_installed');
  });

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
