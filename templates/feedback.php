<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/feedback';
ob_start();
?>
<section class="quick-create">
  <h1>Обратная связь</h1>
  <form id="feedback-form" class="quick-form">
    <label for="feedback_email">Email</label>
    <input id="feedback_email" type="email" name="email" required>
    <label for="feedback_message">Сообщение</label>
    <textarea id="feedback_message" name="message" maxlength="256" required></textarea>
    <button type="submit" class="btn btn-outline">Отправить</button>
  </form>
  <p id="feedback-result" class="meta"></p>
</section>
<script>
(() => {
  const form = document.getElementById('feedback-form');
  const result = document.getElementById('feedback-result');
  if (!form || !result) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    result.textContent = '';
    const fd = new FormData(form);
    const payload = {
      email: String(fd.get('email') || '').trim(),
      message: String(fd.get('message') || '').trim(),
    };

    const res = await fetch('/api/v1/feedback', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) {
      const err = data.error || 'Ошибка отправки';
      if (window.uiAlert) await window.uiAlert(err);
      else result.textContent = err;
      return;
    }
    if (window.uiAlert) await window.uiAlert('Сообщение отправлено');
    else result.textContent = 'Сообщение отправлено';
    form.reset();
  });
})();
</script>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
