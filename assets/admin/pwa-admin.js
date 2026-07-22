(function () {
  const config = window.wpPwaBuilderAdmin || {};
  const templatesByNiche = config.templatesByNiche || {};
  const nicheSelect = document.getElementById('wp-pwa-niche');
  const templateSelect = document.getElementById('wp-pwa-template');

  if (!nicheSelect || !templateSelect) {
    return;
  }

  function renderTemplates() {
    const niche = nicheSelect.value;
    const templates = templatesByNiche[niche] || [];
    const currentTemplate = templateSelect.value;

    templateSelect.innerHTML = '';

    templates.forEach((template) => {
      const option = document.createElement('option');
      option.value = template.key;
      option.textContent = template.name;
      option.selected = template.key === currentTemplate;
      templateSelect.appendChild(option);
    });

    if (!templateSelect.value && templateSelect.options.length > 0) {
      templateSelect.options[0].selected = true;
    }
  }

  nicheSelect.addEventListener('change', renderTemplates);
  renderTemplates();

  document.querySelectorAll('.wp-pwa-media-select').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.dataset.target;
      const input = document.getElementById(targetId);
      const preview = document.querySelector(`[data-preview="${targetId}"]`);
      const removeButton = document.querySelector(`.wp-pwa-media-remove[data-target="${targetId}"]`);

      if (!input || !window.wp || !window.wp.media) {
        return;
      }

      const frame = window.wp.media({
        title: 'Choose image',
        button: { text: 'Use this image' },
        multiple: false,
        library: { type: 'image' },
      });

      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        const thumbnail = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

        input.value = attachment.id;

        if (preview) {
          preview.innerHTML = `<img src="${thumbnail}" alt="" style="max-width:100%;height:auto;margin-top:8px;">`;
        }

        if (removeButton) {
          removeButton.disabled = false;
        }
      });

      frame.open();
    });
  });

  document.querySelectorAll('.wp-pwa-media-remove').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.dataset.target;
      const input = document.getElementById(targetId);
      const preview = document.querySelector(`[data-preview="${targetId}"]`);

      if (input) {
        input.value = '';
      }

      if (preview) {
        preview.innerHTML = '';
      }

      button.disabled = true;
    });
  });
})();
