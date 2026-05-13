(function () {
  const button = document.querySelector('.ewb-button');
  if (!button || typeof ewbData === 'undefined') return;

  button.addEventListener('click', function (event) {
    event.preventDefault();

    const sourcePage = window.location.href;
    const appendSource = button.dataset.appendSource === '1';
    const phone = button.dataset.phone || '';
    let message = button.dataset.message || '';

    if (appendSource) {
      if (message.includes('{page_url}')) {
        message = message.replaceAll('{page_url}', sourcePage);
      } else {
        message += '\n\nעמוד מקור: ' + sourcePage;
      }
    } else {
      message = message.replaceAll('{page_url}', '');
    }

    const whatsappUrl = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message.trim());

    const params = new URLSearchParams();
    params.append('action', 'ewb_track_click');
    params.append('nonce', ewbData.nonce);
    params.append('sourcePage', sourcePage);

    fetch(ewbData.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString(),
    }).finally(function () {
      window.open(whatsappUrl, '_blank', 'noopener');
    });
  });
})();
