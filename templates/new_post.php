<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/posts/new';
$categories = $categories ?? [];
$isAdmin = (($viewer['role'] ?? '') === 'admin');
ob_start();
?>
<section class="quick-create">
  <h1>Новый пост</h1>
  <form method="post" action="/panel/posts" class="quick-form" data-post-draft-form data-draft-key="new">
    <input type="text" name="title" placeholder="Заголовок" required>
    <?php if ($isAdmin): ?>
      <label for="post_cat">Категория</label>
      <select id="post_cat" name="category_id" required>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (($c['slug'] ?? '') === 'news') ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <input type="datetime-local" name="created_at">
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <div class="editor-toolbar" data-editor="preview_text">
        <button class="btn btn-outline js-ed" type="button" data-cmd="bold">Жирный</button>
        <button class="btn btn-outline js-ed" type="button" data-cmd="italic">Курсив</button>
        <button class="btn btn-outline js-ed" type="button" data-cmd="underline">Подчёркивание</button>
        <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h1">1</button>
        <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h2">2</button>
        <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h3">3</button>
        <button class="btn btn-outline js-link" type="button">Ссылка</button>
        <label class="btn btn-outline">
          Файл
          <input class="js-img" type="file" accept="image/*,video/*,application/pdf,.pdf" hidden>
        </label>
      </div>
      <div class="editor-area" contenteditable="true" data-field="preview_text"></div>
      <input type="hidden" name="preview_text" value="">
    <?php else: ?>
      <input type="hidden" name="preview_text" value="">
    <?php endif; ?>

    <div class="editor-toolbar" data-editor="full_text">
      <button class="btn btn-outline js-ed" type="button" data-cmd="bold">Жирный</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="italic">Курсив</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="underline">Подчёркивание</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h1">1</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h2">2</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h3">3</button>
      <button class="btn btn-outline js-link" type="button">Ссылка</button>
      <label class="btn btn-outline">
        Файл
        <input class="js-img" type="file" accept="image/*,video/*,application/pdf,.pdf" hidden>
      </label>
    </div>
    <div class="editor-area" contenteditable="true" data-field="full_text"></div>
    <input type="hidden" name="full_text" value="">
    <?php if ($isAdmin): ?>
      <select name="status">
        <option value="draft">Черновик</option>
        <option value="published">Опубликован</option>
      </select>
    <?php endif; ?>
    <button type="submit" class="btn btn-outline">Создать</button>
  </form>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
