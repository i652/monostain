<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = \Stain\Database::pdo();

$count = (int) ($argv[1] ?? 25);
$authorEmail = (string) ($argv[2] ?? 'admin@stain.local');

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => strtolower($authorEmail)]);
$row = $stmt->fetch();
if (!$row) {
    throw new RuntimeException("User not found: {$authorEmail}");
}
$authorId = (int) $row['id'];

$newsId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'news' LIMIT 1")->fetchColumn();
if ($newsId === 0) {
    throw new RuntimeException('Category "news" not found. Run migrate.');
}

for ($i = 0; $i < $count; $i++) {
    $title = "Тестовый пост " . ($i + 1);
    $slugBase = "testovyy-post-" . ($i + 1);
    $slug = $slugBase . '-' . bin2hex(random_bytes(3)) . '.html';
    $preview = "<p>Превью для {$title}</p>";
    $full = "<p>Полный текст для {$title}</p>";

    $insert = $pdo->prepare(
        "INSERT INTO posts (author_id, content_type, slug, title, category_id, body, preview_text, full_text, seo_description, status, created_at, published_at)
         VALUES (:author_id, 'post', :slug, :title, :category_id, :body, :preview_text, :full_text, :seo_description, 'published', NOW(), NOW())"
    );
    $insert->execute([
        'author_id' => $authorId,
        'slug' => $slug,
        'title' => $title,
        'category_id' => $newsId,
        'body' => $full,
        'preview_text' => $preview,
        'full_text' => $full,
        'seo_description' => '',
    ]);
}

echo "Seeded {$count} posts.\n";

