(function () {
  if (!window.wpPwaBuilder) {
    return;
  }

  const config = window.wpPwaBuilder;
  const fallbackUiDelay = Number(config.fallbackUiDelay) >= 0 ? Number(config.fallbackUiDelay) : 1000;
  const standaloneQuery = window.matchMedia ? window.matchMedia('(display-mode: standalone)') : null;

  function storageKey(name) {
    return ['wpPwaBuilder', config.appSlug || config.appId || 'app', name].join(':');
  }

  function isStandalone() {
    return (
      (standaloneQuery && standaloneQuery.matches) ||
      window.navigator.standalone === true
    );
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
  }

  function launch(payload) {
    window.dispatchEvent(
      new CustomEvent('wp-pwa-builder:launch', {
        detail: {
          appId: config.appId,
          appSlug: config.appSlug,
          payload: payload || {},
        },
      })
    );
  }

  function redirect() {
    const link = document.querySelector('[data-pwa-launch].analytic-url');
    const targetUrl = storedLaunchUrl() || (link && link.href ? link.href : config.fallbackUrl);

    if (!targetUrl) {
      track('redirect_failed', { reason: 'missing_target_url' });
      showFallbackUi();
      return;
    }

    track('redirect_started', { href: targetUrl });

    if (config.isPreview) {
      return;
    }

    window.location.replace(targetUrl);
  }

  function storedLaunchUrl() {
    try {
      return window.localStorage.getItem(storageKey('launchUrl')) || '';
    } catch (error) {
      track('launch_url_read_failed', { message: error.message });
      return '';
    }
  }

  function showFallbackUi() {
    const start = document.querySelector('[data-pwa-start]');

    if (start) {
      start.classList.add('is-fallback-visible');
    }
  }

  const payload = {
    path: window.location.pathname,
    referrer: document.referrer || '',
    standalone: isStandalone(),
  };

  track('app_open', payload);
  launch(payload);

  const launchUrl = storedLaunchUrl();
  const link = document.querySelector('[data-pwa-launch].analytic-url');

  if (launchUrl && link) {
    link.href = launchUrl;
  }

  window.setTimeout(showFallbackUi, fallbackUiDelay);
  redirect();
})();
