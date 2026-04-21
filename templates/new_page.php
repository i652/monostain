<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/pages/new';
ob_start();
?>
<section class="quick-create">
  <h1>Новая страница</h1>
  <form method="post" action="/panel/pages" class="quick-form">
    <input type="text" name="title" placeholder="Заголовок" required>
    <input type="text" name="slug" placeholder="Slug (например, services)" required>
    <div class="editor-toolbar" data-editor="full_text">
      <button class="btn btn-outline js-ed" type="button" data-cmd="bold">Жирный</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="italic">Курсив</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="underline">Подчёркивание</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h1">1</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h2">2</button>
      <button class="btn btn-outline js-ed" type="button" data-cmd="formatBlock" data-arg="h3">3</button>
      <button class="btn btn-outline js-link" type="button">Ссылка</button>
      <label class="btn btn-outline">
        Медиа
        <input class="js-img" type="file" accept="image/*,video/*,application/pdf,.pdf" hidden>
      </label>
    </div>
    <div class="editor-area" contenteditable="true" data-field="full_text"></div>
    <input type="hidden" name="full_text" value="">
    <div class="media-preview" data-media-list-for="full_text"></div>
    <button type="submit" class="btn btn-outline">Создать</button>
  </form>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
