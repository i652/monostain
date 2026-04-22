<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

try {
    $app = new Stain\App();
    $app->run();
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ошибка запуска</title></head><body>';
    echo '<h1>Ошибка запуска приложения</h1>';
    echo '<p>Произошла непредвиденная ошибка. Проверьте настройки среды и подключения к базе данных.</p>';
    echo '<pre style="white-space:pre-wrap;">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</body></html>';
}
