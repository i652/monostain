<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/posts/' . $post['id'] . '/edit';
$categories = $categories ?? [];
$currentCat = (int) ($post['category_id'] ?? 0);
$postUpdatedAtIso = (new DateTimeImmutable((string) ($post['updated_at'] ?? 'now')))->format(DATE_ATOM);
ob_start();
?>
<section class="quick-create">
  <h1>Редактирование поста</h1>
  <form method="post" action="/panel/posts/<?= (int) $post['id'] ?>/edit" class="quick-form" data-post-draft-form data-draft-key="edit-<?= (int) $post['id'] ?>" data-post-updated-at="<?= htmlspecialchars($postUpdatedAtIso, ENT_QUOTES, 'UTF-8') ?>">
    <input type="text" name="title" placeholder="Заголовок" required value="<?= htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8') ?>">
    <label for="edit_cat">Категория</label>
    <select id="edit_cat" name="category_id" required>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $currentCat ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <input type="datetime-local" name="created_at" value="<?= htmlspecialchars((new DateTimeImmutable((string) $post['created_at']))->format('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>">
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
    <div class="editor-area" contenteditable="true" data-field="preview_text"><?= $post['preview_text'] ?></div>
    <input type="hidden" name="preview_text" value="">
    <div class="media-preview" data-media-list-for="preview_text"></div>

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
    <div class="editor-area" contenteditable="true" data-field="full_text"><?= $post['full_text'] ?></div>
    <input type="hidden" name="full_text" value="">
    <div class="media-preview" data-media-list-for="full_text"></div>
    <select name="status">
      <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Черновик</option>
      <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Опубликован</option>
    </select>
    <button type="submit" class="btn btn-outline">Сохранить</button>
  </form>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
