(function () {
  if (!window.wpPwaBuilder) {
    return;
  }

  const config = window.wpPwaBuilder;
  const redirectDelay = Number(config.redirectDelay) >= 0 ? Number(config.redirectDelay) : 1200;

  function isStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
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
    const targetUrl = link && link.href ? link.href : config.fallbackUrl;

    if (!targetUrl) {
      track('redirect_failed', { reason: 'missing_target_url' });
      return;
    }

    track('redirect_started', { href: targetUrl });

    if (config.isPreview) {
      return;
    }

    window.location.replace(targetUrl);
  }

  const payload = {
    path: window.location.pathname,
    referrer: document.referrer || '',
    standalone: isStandalone(),
  };

  track('installed_launch', payload);
  launch(payload);

  window.setTimeout(redirect, redirectDelay);
})();
