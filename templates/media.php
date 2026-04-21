<?php
declare(strict_types=1);
$canonical = $canonical ?? (rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/media');
$mediaItems = $mediaItems ?? [];
$mediaHasMore = $mediaHasMore ?? false;
ob_start();
?>
<section class="panel-grid">
  <h1>Панель управления</h1>
  <div class="panel-nav">
    <div class="segmented" role="tablist" aria-label="Разделы панели">
      <input type="radio" name="panel_section" id="panel_posts" value="posts">
      <label for="panel_posts">Посты</label>
      <input type="radio" name="panel_section" id="panel_pages" value="pages">
      <label for="panel_pages">Страницы</label>
      <input type="radio" name="panel_section" id="panel_categories" value="categories">
      <label for="panel_categories">Категории</label>
      <input type="radio" name="panel_section" id="panel_users" value="users">
      <label for="panel_users">Пользователи</label>
      <input type="radio" name="panel_section" id="panel_media" value="media" checked>
      <label for="panel_media">Медиа</label>
    </div>
  </div>
  <div class="panel-block">
    <div class="panel-head">
      <h2>Медиа</h2>
    </div>
    <div class="media-photostream media-photostream--library" id="media-photostream">
      <?php foreach ($mediaItems as $m): ?>
        <?php
          $mid = (int) ($m['id'] ?? 0);
          $kind = (string) ($m['kind'] ?? 'file');
          $url = '/media/' . $mid;
        ?>
        <div class="media-photostream__cell" data-media-id="<?= $mid ?>">
          <div class="media-photostream__thumb">
            <div class="media-photostream__inner">
              <?php if ($kind === 'video'): ?>
                <video src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" preload="metadata" muted playsinline></video>
              <?php elseif ($kind === 'file'): ?>
                <div class="media-thumb-file" aria-hidden="true">PDF</div>
              <?php else: ?>
                <img src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
              <?php endif; ?>
            </div>
            <button type="button" class="media-photostream__delete" data-media-delete="<?= $mid ?>">Удалить</button>
          </div>
          <?php
            $srcTitle = trim((string) ($m['source_title'] ?? ''));
            $srcDisplay = trim((string) ($m['source_title_display'] ?? ''));
            if ($srcDisplay === '' && $srcTitle !== '') {
                $srcDisplay = mb_strlen($srcTitle) <= 20 ? $srcTitle : (mb_substr($srcTitle, 0, 20) . '...');
            }
            $srcUrl = (string) ($m['source_url'] ?? '');
            $srcExtra = (int) ($m['source_extra_count'] ?? 0);
          ?>
          <?php if ($srcDisplay !== '' && $srcUrl !== ''): ?>
            <?php
              $captionTitle = $srcTitle . ($srcExtra > 0 ? ' (ещё материалов: ' . $srcExtra . ')' : '');
            ?>
            <a class="media-photostream__caption" href="<?= htmlspecialchars($srcUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($captionTitle, ENT_QUOTES, 'UTF-8') ?>">
              <span class="media-photostream__caption-text"><?= htmlspecialchars($srcDisplay, ENT_QUOTES, 'UTF-8') ?></span><?php if ($srcExtra > 0): ?><span class="media-photostream__caption-more" aria-hidden="true">+<?= $srcExtra ?></span><?php endif; ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($mediaHasMore): ?>
      <div class="load-more-wrap panel-load-more-wrap">
        <button id="media-load-more" type="button" class="blog-nav">Загрузить ещё</button>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
(() => {
  const root = document.getElementById('media-photostream');
  if (!root) return;
  root.addEventListener('click', async (e) => {
    const btn = e.target && e.target.closest && e.target.closest('[data-media-delete]');
    if (!btn) return;
    const id = Number(btn.getAttribute('data-media-delete'));
    if (!id) return;
    const ok = window.uiConfirm ? await window.uiConfirm('Удалить этот файл?') : window.confirm('Удалить этот файл?');
    if (!ok) return;
    const res = await fetch('/api/v1/media/' + id, { method: 'DELETE', credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (res.status === 409 && data.code === 'MEDIA_IN_USE') {
      const refs = data.references || [];
      const lines = refs.map((r) => {
        const kind = r.content_type === 'page' ? 'Страница' : 'Пост';
        return `${kind}: ${r.title}`;
      });
      const msgParts = [data.error];
      if (lines.length) {
        msgParts.push('', ...lines);
      }
      const msg = msgParts.join('\n');
      if (window.uiAlert) await window.uiAlert(msg);
      else window.alert(msg);
      return;
    }
    if (!res.ok || !data.ok) {
      const msg = data.error || 'Не удалось удалить';
      if (window.uiAlert) await window.uiAlert(msg);
      else window.alert(msg);
      return;
    }
    const cell = btn.closest('.media-photostream__cell');
    if (cell) cell.remove();
  });
})();
</script>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
