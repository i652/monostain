<?php
declare(strict_types=1);

namespace Stain;

use Stain\Auth\Jwt;
use Stain\Http\ApiErrorMapper;
use Stain\Http\PublicDocumentHeaders;
use Stain\Http\Router;
use Stain\Repositories\CategoryRepository;
use Stain\Repositories\MediaRepository;
use Stain\Repositories\PostRepository;
use Stain\Repositories\UserRepository;
use Stain\Services\AuthService;
use Stain\Services\MediaService;
use Stain\Services\PostService;
use Stain\Util\HtmlPublicContent;
use Throwable;

final class App
{
    use AppRouteRegistration;

    private AuthService $authService;
    private PostService $postService;
    private MediaService $mediaService;
    private CategoryRepository $categoryRepository;
    private Jwt $jwt;

    public function __construct()
    {
        $pdo = Database::pdo();
        $this->jwt = new Jwt(Config::get('JWT_SECRET', 'dev-secret-change-me'));
        $ttl = (int) Config::get('JWT_TTL_SECONDS', '3600');
        $this->authService = new AuthService(new UserRepository($pdo), $this->jwt, $ttl);
        $this->categoryRepository = new CategoryRepository($pdo);
        $mediaRepository = new MediaRepository($pdo);
        $postRepository = new PostRepository($pdo);
        $this->postService = new PostService($postRepository, $this->categoryRepository, $mediaRepository);
        $this->mediaService = new MediaService($mediaRepository, $postRepository);
        // No background worker/cron: promote scheduled posts on requests.
        $this->postService->promoteScheduled();
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        try {
            $router = new Router();
            $this->registerHttpRoutes($router);
            if ($router->dispatch($method, $path)) {
                return;
            }

            if (str_starts_with($path, '/api/')) {
                $this->respondJson(['error' => 'Not found', 'code' => 'NOT_FOUND'], 404);

                return;
            }
            http_response_code(404);
            $title = '404 - Stain';
            $description = 'Запрошенная страница не найдена';
            $viewer = $this->getCurrentUser();
            require dirname(__DIR__) . '/templates/404.php';

            return;
        } catch (Throwable $e) {
            $status = ($e instanceof \InvalidArgumentException) ? 422 : (($e->getMessage() === 'Unauthorized') ? 401 : (($e->getMessage() === 'Forbidden') ? 403 : 400));
            if (str_starts_with($path, '/api/')) {
                [$apiStatus, $payload] = ApiErrorMapper::fromThrowable($e);
                $this->respondJson($payload, $apiStatus);
                return;
            }
            if ($method === 'POST') {
                $back = $_SERVER['HTTP_REFERER'] ?? '/';
                $sep = str_contains($back, '?') ? '&' : '?';
                header('Location: ' . $back . $sep . 'error=' . rawurlencode($e->getMessage()));
                exit();
            }
            http_response_code($status);
            $title = 'Ошибка';
            $description = $e->getMessage();
            $viewer = $this->getCurrentUser();
            require dirname(__DIR__) . '/templates/404.php';
        }
    }

    private function requireAuth(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            $claims = $this->jwt->verify($matches[1]);
            if ($claims !== null && isset($claims['sub'], $claims['role'])) {
                return $claims;
            }
        }

        $token = $_COOKIE['stain_auth'] ?? '';
        if ($token !== '') {
            $claims = $this->jwt->verify($token);
            if ($claims !== null && isset($claims['sub'], $claims['role'])) {
                return $claims;
            }
        }

        throw new \RuntimeException('Unauthorized');
    }

    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '{}', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function respondText(string $text): void
    {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo $text;
    }

    /** Лучшая метка времени из полей поста/страницы для Last-Modified. */
    private static function bestContentTimestamp(?string $updatedAt, ?string $publishedAt, ?string $createdAt): ?string
    {
        $best = null;
        $bestTs = 0;
        foreach ([$updatedAt, $publishedAt, $createdAt] as $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $ts = strtotime($v);
            if ($ts !== false && $ts >= $bestTs) {
                $bestTs = $ts;
                $best = $v;
            }
        }

        return $best;
    }

    private static function pluralRuPost(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return 'постов';
        }
        if ($n1 > 1 && $n1 < 5) {
            return 'поста';
        }
        if ($n1 === 1) {
            return 'пост';
        }

        return 'постов';
    }

    private function renderHome(): void
    {
        PublicDocumentHeaders::maybeSend304AndExit($this->postService->maxPublicContentLastTouch());
        $batch = $this->postService->listPublished(7, 0);
        $hasMore = count($batch) > 6;
        $posts = array_slice($batch, 0, 6);
        $viewer = $this->getCurrentUser();
        $title = 'Главная страница';
        $description = PostService::clipMetaDescription(
            'Лента публикаций stain — блог, материалы и заметки. Быстрая лента с превью постов.'
        );
        $canonical = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/') . '/';
        $ogType = 'website';
        $ogTitle = $title;
        $ogDescription = $description;
        $ogUrl = $canonical;
        require dirname(__DIR__) . '/templates/home.php';
    }

    /**
     * @param array{id:int, name:string, slug:string} $categoryRow
     */
    private function renderCategoryArchive(string $categorySlug, array $categoryRow): void
    {
        PublicDocumentHeaders::maybeSend304AndExit($this->postService->categoryArchiveLastModified($categorySlug));
        $batch = $this->postService->listPublishedInCategory($categorySlug, 7, 0);
        $hasMore = count($batch) > 6;
        $posts = array_slice($batch, 0, 6);
        $viewer = $this->getCurrentUser();
        $category = $categoryRow;
        $categorySlugForView = $categorySlug;
        $catName = (string) $categoryRow['name'];
        $publishedCount = $this->postService->countPublishedPostsByCategorySlug($categorySlug);
        $categoryPublishedCount = $publishedCount;
        $categoryPostCountText = $publishedCount . ' ' . self::pluralRuPost($publishedCount);
        $title = $catName . ' - Stain';
        $description = PostService::clipMetaDescription(
            'Категория «' . $catName . '»: ' . $publishedCount . ' ' . self::pluralRuPost($publishedCount) . ' в ленте.'
        );
        $canonical = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/') . '/' . rawurlencode($categorySlug);
        $ogType = 'website';
        $ogTitle = $title;
        $ogDescription = $description;
        $ogUrl = $canonical;
        require dirname(__DIR__) . '/templates/category.php';
    }

    private function redirectLegacyPostSlug(string $postSlug): void
    {
        $viewer = $this->getCurrentUser();
        $post = (($viewer['role'] ?? '') === 'admin')
            ? $this->postService->getLegacyPostBySlugForAdmin($postSlug)
            : $this->postService->getLegacyPublishedPostBySlug($postSlug);
        if ($post === null) {
            http_response_code(404);
            $title = 'Post not found';
            $description = 'This post does not exist.';
            require dirname(__DIR__) . '/templates/404.php';
            return;
        }
        $target = PostService::postPublicPath($post);
        header('Location: ' . $target, true, 301);
        exit();
    }

    private function renderPostInCategory(string $categorySlug, string $postSlug): void
    {
        $viewer = $this->getCurrentUser();
        $post = (($viewer['role'] ?? '') === 'admin')
            ? $this->postService->getPostForAdmin($categorySlug, $postSlug)
            : $this->postService->getPublishedPost($categorySlug, $postSlug);
        if ($post === null) {
            http_response_code(404);
            $title = 'Post not found';
            $description = 'This post does not exist.';
            require dirname(__DIR__) . '/templates/404.php';
            return;
        }
        $newerPost = $this->postService->getAdjacent((int) $post['id'], 'newer');
        $olderPost = $this->postService->getAdjacent((int) $post['id'], 'older');
        PublicDocumentHeaders::maybeSend304AndExit(self::bestContentTimestamp(
            (string) ($post['updated_at'] ?? ''),
            (string) ($post['published_at'] ?? ''),
            (string) ($post['created_at'] ?? ''),
        ));
        $title = $post['title'] . ' - Stain';
        $seo = trim((string) ($post['seo_description'] ?? ''));
        $fallback = (string) ($post['preview_text'] ?? $post['full_text'] ?? '');
        $description = PostService::clipMetaDescription($seo !== '' ? $seo : $fallback);
        $canonical = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/') . PostService::postPublicPath($post);
        $ogType = 'article';
        $ogTitle = $title;
        $ogDescription = $description;
        $ogUrl = $canonical;
        $base = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/');
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Главная',
                    'item' => $base . '/',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => (string) ($post['category_name'] ?? $categorySlug),
                    'item' => $base . '/' . rawurlencode($categorySlug),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => (string) ($post['title'] ?? ''),
                    'item' => $canonical,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        require dirname(__DIR__) . '/templates/post.php';
    }

    private function renderPage(string $slug): void
    {
        $viewer = $this->getCurrentUser();
        $page = (($viewer['role'] ?? '') === 'admin')
            ? $this->postService->getBySlug($slug, 'page')
            : $this->postService->getPublishedPageBySlug($slug);
        if ($page === null) {
            http_response_code(404);
            $title = 'Page not found';
            $description = 'This page does not exist.';
            require dirname(__DIR__) . '/templates/404.php';
            return;
        }
        PublicDocumentHeaders::maybeSend304AndExit(self::bestContentTimestamp(
            (string) ($page['updated_at'] ?? ''),
            (string) ($page['published_at'] ?? ''),
            (string) ($page['created_at'] ?? ''),
        ));
        $title = $page['title'] . ' - Stain';
        $description = PostService::clipMetaDescription((string) ($page['full_text'] ?? ''));
        $canonical = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/') . '/' . $page['slug'];
        $ogType = 'website';
        $ogTitle = $title;
        $ogDescription = $description;
        $ogUrl = $canonical;
        require dirname(__DIR__) . '/templates/page.php';
    }

    private function renderSitemap(): void
    {
        $base = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/');
        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        $homeLm = PublicDocumentHeaders::w3cLastmodFromOptional($this->postService->maxPublicContentLastTouch());
        echo '  <url><loc>' . htmlspecialchars($base . '/', ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><lastmod>' . htmlspecialchars($homeLm, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod></url>\n";

        foreach ($this->postService->listSitemapCategoryArchives() as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $loc = $base . '/' . rawurlencode($slug);
            $lm = PublicDocumentHeaders::w3cLastmodFromOptional((string) ($row['last_touch'] ?? ''));
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><lastmod>' . htmlspecialchars($lm, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod></url>\n";
        }

        foreach ($this->postService->listSitemapPublishedPosts() as $post) {
            $url = $base . PostService::postPublicPath($post);
            $lm = PublicDocumentHeaders::w3cLastmodFromTimestamps(
                isset($post['updated_at']) ? (string) $post['updated_at'] : null,
                isset($post['published_at']) ? (string) $post['published_at'] : null,
                (string) ($post['created_at'] ?? ''),
            );
            echo '  <url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><lastmod>' . htmlspecialchars($lm, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod></url>\n";
        }

        foreach ($this->postService->listSitemapPublishedPages() as $page) {
            $loc = $base . '/' . rawurlencode((string) ($page['slug'] ?? ''));
            $lm = PublicDocumentHeaders::w3cLastmodFromTimestamps(
                isset($page['updated_at']) ? (string) $page['updated_at'] : null,
                isset($page['published_at']) ? (string) $page['published_at'] : null,
                (string) ($page['created_at'] ?? ''),
            );
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><lastmod>' . htmlspecialchars($lm, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod></url>\n";
        }

        echo "</urlset>\n";
    }

    private function renderHtmlSitemap(): void
    {
        $viewer = $this->getCurrentUser();
        $pagesRaw = $this->postService->listPublishedPages(200);
        $pages = array_map(
            static fn (array $p): array => [
                'title' => (string) ($p['title'] ?? ''),
                'url' => '/' . (string) ($p['slug'] ?? ''),
            ],
            $pagesRaw
        );
        $posts = $this->postService->listPublished(200, 0);
        $title = 'Карта сайта - stain';
        $description = 'Site map';
        require dirname(__DIR__) . '/templates/site_map.php';
    }

    private function renderAuth(): void
    {
        $viewer = $this->getCurrentUser();
        $title = 'Auth - Stain';
        $description = 'Login or register.';
        require dirname(__DIR__) . '/templates/auth.php';
    }

    private function renderFeedback(): void
    {
        $viewer = $this->getCurrentUser();
        $title = 'Обратная связь - Stain';
        $description = 'Свяжитесь с нами';
        require dirname(__DIR__) . '/templates/feedback.php';
    }

    private function handleFeedbackApi(): void
    {
        $data = $this->jsonInput();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $message = trim((string) ($data['message'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Некорректный email');
        }
        if ($message === '' || mb_strlen($message) > 256) {
            throw new \InvalidArgumentException('Сообщение должно быть от 1 до 256 символов');
        }

        $emailsRaw = Config::get('ADMIN_EMAILS', 'admin@example.com');
        $targets = array_values(array_filter(array_map(
            static fn (string $v): string => trim($v),
            explode(',', $emailsRaw)
        )));
        if ($targets === []) {
            throw new \RuntimeException('Не задана почта администратора');
        }

        $subject = 'Письмо с сайта';
        $body = "Отправитель: {$email}\n\n{$message}";
        $fromAddr = trim((string) Config::get('MAIL_FROM', ''));
        if ($fromAddr === '' || !filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) {
            $fromAddr = 'noreply@example.com';
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $fromAddr,
            'Reply-To: ' . $email,
            'X-Mailer: PHP/' . phpversion(),
        ];

        $ok = @\mail(implode(',', $targets), $subject, $body, implode("\r\n", $headers));
        if (!$ok) {
            throw new \RuntimeException(
                'Не удалось отправить письмо: на сервере не настроена отправка почты (PHP mail()). ' .
                'Укажите MAIL_FROM в .env; на shared-хостинге подключите почту в панели хостинга или используйте SMTP через плагин/обёртку.'
            );
        }

        $this->respondJson(['ok' => true], 201);
    }

    private function renderPanel(string $section): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        if (!in_array($section, ['posts', 'pages', 'categories'], true)) {
            $section = 'posts';
        }
        $title = 'Панель - Stain';
        $description = 'Manage posts and static pages.';
        require dirname(__DIR__) . '/templates/panel.php';
    }

    private function renderMediaLibrary(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $mediaSlice = $this->mediaService->listSliceForAdmin($actor, 0, 10);
        $mediaItems = $mediaSlice['items'];
        $mediaHasMore = $mediaSlice['has_more'];
        $title = 'Медиа - Stain';
        $description = 'Файлы медиатеки';
        $canonical = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/media';
        require dirname(__DIR__) . '/templates/media.php';
    }

    private function renderCategories(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $categories = $this->postService->listCategoriesWithCounts();
        $title = 'Категории - Stain';
        $description = 'Manage categories.';
        require dirname(__DIR__) . '/templates/categories.php';
    }

    private function renderNewCategory(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $title = 'Новая категория - Stain';
        $description = 'Create category';
        require dirname(__DIR__) . '/templates/new_category.php';
    }

    private function handleCreateCategory(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        try {
            $this->categoryRepository->createWithNameAndSlug($name, $slug);
        } catch (\InvalidArgumentException $e) {
            header('Location: /panel/categories/new?error=' . rawurlencode($e->getMessage()));
            exit();
        }
        header('Location: /panel/categories?notice=' . rawurlencode('Категория создана'));
        exit();
    }

    private function handleDeleteCategory(int $categoryId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $newsId = $this->categoryRepository->findDefaultNewsId();
        $this->categoryRepository->deleteById($categoryId, $newsId);
        header('Location: /panel/categories?notice=' . rawurlencode('Категория удалена'));
        exit();
    }

    private function renderEditCategory(int $categoryId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $category = $this->categoryRepository->findById($categoryId);
        if ($category === null) {
            header('Location: /panel/categories?error=' . rawurlencode('Категория не найдена'));
            exit();
        }
        if (($category['slug'] ?? '') === 'news') {
            header('Location: /panel/categories?error=' . rawurlencode('Категорию «Новости» нельзя редактировать'));
            exit();
        }
        $title = 'Редактировать категорию - Stain';
        $description = 'Edit category';
        require dirname(__DIR__) . '/templates/edit_category.php';
    }

    private function handleEditCategory(int $categoryId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        try {
            $this->categoryRepository->updateWithNameAndSlug($categoryId, $name, $slug);
        } catch (\InvalidArgumentException $e) {
            header('Location: /panel/categories/' . $categoryId . '/edit?error=' . rawurlencode($e->getMessage()));
            exit();
        }
        header('Location: /panel/categories?notice=' . rawurlencode('Категория обновлена'));
        exit();
    }

    private function renderUsers(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $usersRepo = new UserRepository(Database::pdo());
        $users = $usersRepo->listAll();
        $title = 'Users - Stain';
        $description = 'Manage users';
        require dirname(__DIR__) . '/templates/users.php';
    }

    private function renderNewUser(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $title = 'Новый пользователь - Stain';
        $description = 'Create user';
        require dirname(__DIR__) . '/templates/new_user.php';
    }

    private function handleCreateUser(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'author');
        if (!in_array($role, ['admin', 'author'], true)) {
            throw new \InvalidArgumentException('Invalid role');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: /panel/users/new?error=' . rawurlencode('Некорректный email'));
            exit();
        }
        if (strlen($password) < 8) {
            header('Location: /panel/users/new?error=' . rawurlencode('Пароль не короче 8 символов'));
            exit();
        }
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        $nickErr = $this->authService->validateNickname($nickname);
        if ($nickErr !== null) {
            header('Location: /panel/users/new?error=' . rawurlencode($nickErr));
            exit();
        }
        $usersRepo = new UserRepository(Database::pdo());
        if ($usersRepo->findByEmail($email) !== null) {
            header('Location: /panel/users/new?error=' . rawurlencode('Email уже зарегистрирован'));
            exit();
        }
        if ($usersRepo->findByNicknameIgnoreCase($nickname) !== null) {
            header('Location: /panel/users/new?error=' . rawurlencode('Этот никнейм уже занят'));
            exit();
        }
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $usersRepo->create($email, $hash, $role, $nickname);
        header('Location: /panel/users?notice=' . rawurlencode('Пользователь создан'));
        exit();
    }

    private function handleDeleteUser(int $userId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        if ($userId === (int) ($actor['sub'] ?? 0)) {
            header('Location: /panel/users?error=' . rawurlencode('Нельзя удалить свою учётную запись'));
            exit();
        }
        $usersRepo = new UserRepository(Database::pdo());
        $user = $usersRepo->findById($userId);
        if ($user === null) {
            header('Location: /panel/users?error=' . rawurlencode('Пользователь не найден'));
            exit();
        }
        if (($user['role'] ?? '') === 'admin' && $usersRepo->countByRole('admin') <= 1) {
            header('Location: /panel/users?error=' . rawurlencode('Нельзя удалить последнего администратора'));
            exit();
        }
        $usersRepo->deleteById($userId);
        header('Location: /panel/users?notice=' . rawurlencode('Пользователь удалён'));
        exit();
    }

    private function handleUserRoleUpdate(int $userId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $role = (string) ($_POST['role'] ?? '');
        if (!in_array($role, ['admin', 'author'], true)) {
            throw new \InvalidArgumentException('Invalid role');
        }
        $usersRepo = new UserRepository(Database::pdo());
        $password = trim((string) ($_POST['password'] ?? ''));
        $passwordConfirm = trim((string) ($_POST['password_confirm'] ?? ''));
        if ($password !== '' || $passwordConfirm !== '') {
            if (strlen($password) < 8) {
                header('Location: /panel/users?error=' . rawurlencode('Пароль не короче 8 символов'));
                exit();
            }
            if ($password !== $passwordConfirm) {
                header('Location: /panel/users?error=' . rawurlencode('Пароль и подтверждение не совпадают'));
                exit();
            }
            $usersRepo->updatePasswordHash($userId, password_hash($password, PASSWORD_ARGON2ID));
        }
        $usersRepo->updateRole($userId, $role);
        $notice = ($password !== '' || $passwordConfirm !== '') ? 'Роль и пароль обновлены' : 'Роль обновлена';
        header('Location: /panel/users?notice=' . rawurlencode($notice));
        exit();
    }

    private function renderNewPost(): void
    {
        $actor = $this->requireAuth();
        if (!in_array(($actor['role'] ?? ''), ['admin', 'author'], true)) {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $categories = ($actor['role'] ?? '') === 'admin' ? $this->categoryRepository->listAll() : [];
        $title = 'Новый пост - Stain';
        $description = 'Create new post';
        require dirname(__DIR__) . '/templates/new_post.php';
    }

    private function renderNewPage(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $viewer = $actor;
        $title = 'Новая страница - Stain';
        $description = 'Create new page';
        require dirname(__DIR__) . '/templates/new_page.php';
    }

    private function renderEditPost(int $postId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $post = $this->postService->getById($postId);
        if ($post === null || $post['content_type'] !== 'post') {
            throw new \RuntimeException('Post not found');
        }

        $categories = $this->categoryRepository->listAll();
        $viewer = $actor;
        $title = 'Редактировать пост - Stain';
        $description = 'Edit post';
        require dirname(__DIR__) . '/templates/edit_post.php';
    }

    private function renderEditPage(int $pageId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $page = $this->postService->getById($pageId);
        if ($page === null || $page['content_type'] !== 'page') {
            throw new \RuntimeException('Page not found');
        }
        $viewer = $actor;
        $title = 'Редактировать страницу - Stain';
        $description = 'Edit page';
        require dirname(__DIR__) . '/templates/edit_page.php';
    }

    private function serveMedia(int $id): void
    {
        $repo = new MediaRepository(Database::pdo());
        $item = $repo->findById($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $storedPath = (string) ($item['stored_path'] ?: $item['stored_name']);
        $path = dirname(__DIR__) . '/storage/media/' . $storedPath;
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $mtime = filemtime($path);
        if ($mtime !== false) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        }
        header('Content-Type: ' . $item['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private function getCurrentUser(): ?array
    {
        $token = $_COOKIE['stain_auth'] ?? '';
        if ($token === '') {
            return null;
        }
        $claims = $this->jwt->verify($token);
        return is_array($claims) ? $claims : null;
    }

    private function createPostFromHome(): void
    {
        $actor = $this->requireAuth();
        if (!in_array(($actor['role'] ?? ''), ['admin', 'author'], true)) {
            throw new \RuntimeException('Forbidden');
        }

        $isAuthor = ($actor['role'] ?? '') === 'author';
        $data = [
            'title' => (string) ($_POST['title'] ?? ''),
            'category_id' => $isAuthor ? 0 : (int) ($_POST['category_id'] ?? 0),
            'created_at' => (string) ($_POST['created_at'] ?? ''),
            'preview_text' => (string) ($_POST['preview_text'] ?? ''),
            'full_text' => (string) ($_POST['full_text'] ?? ''),
            'status' => $isAuthor ? 'draft' : (string) ($_POST['status'] ?? 'draft'),
        ];

        $created = $this->postService->create($actor, $data, 'post');
        $this->attachMediaToPost((int) $created['id'], (string) ($created['full_text'] ?? ''));
        header('Location: ' . PostService::postPublicPath($created) . '?notice=' . rawurlencode('Пост создан'));
        exit();
    }

    private function createPageFromAdmin(): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }

        $data = [
            'title' => (string) ($_POST['title'] ?? ''),
            'slug' => (string) ($_POST['slug'] ?? ''),
            'full_text' => (string) ($_POST['full_text'] ?? ''),
            'preview_text' => '',
            'created_at' => '',
        ];
        $created = $this->postService->create($actor, $data, 'page');
        header('Location: /' . $created['slug'] . '?notice=' . rawurlencode('Страница создана'));
        exit();
    }

    private function handlePostMediaUpload(int $postId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $file = $_FILES['file'] ?? [];
        $media = $this->mediaService->storeForPost($actor, $postId, is_array($file) ? $file : []);
        header('Location: /panel/posts/' . $postId . '/edit?notice=' . rawurlencode('Медиа загружено') . '#media-' . (int) $media['id']);
        exit();
    }

    private function setWebAuthCookie(string $token): void
    {
        $ttl = (int) Config::get('JWT_TTL_SECONDS', '3600');
        setcookie('stain_auth', $token, array_merge($this->webAuthCookieOptions(), [
            'expires' => time() + $ttl,
        ]));
    }

    /**
     * Атрибуты cookie stain_auth (без expires). Для production: AUTH_COOKIE_SECURE=1, SAMESITE по необходимости.
     *
     * @return array{path: string, secure: bool, httponly: true, samesite: string, domain?: string}
     */
    private function webAuthCookieOptions(): array
    {
        $secure = $this->envFlag('AUTH_COOKIE_SECURE', false);
        $sameSite = $this->normalizeSameSite(Config::get('AUTH_COOKIE_SAMESITE', 'Lax'));
        if ($sameSite === 'None') {
            $secure = true;
        }

        $opts = [
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ];

        $domain = trim(Config::get('AUTH_COOKIE_DOMAIN', ''));
        if ($domain !== '') {
            $opts['domain'] = $domain;
        }

        return $opts;
    }

    private function envFlag(string $key, bool $default): bool
    {
        $raw = strtolower(trim(Config::get($key, $default ? '1' : '0')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeSameSite(string $raw): string
    {
        $s = ucfirst(strtolower(trim($raw)));
        if (!in_array($s, ['Strict', 'Lax', 'None'], true)) {
            return 'Lax';
        }

        return $s;
    }

    private function handleAuthLogin(): void
    {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = $this->authService->login($email, $password);
        $this->setWebAuthCookie($result['token']);
        header('Location: /');
        exit();
    }

    private function handleAuthRegister(): void
    {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $nickname = (string) ($_POST['nickname'] ?? '');
        $result = $this->authService->register($email, $password, $nickname);
        $this->setWebAuthCookie($result['token']);
        header('Location: /');
        exit();
    }

    private function handleLogout(): void
    {
        setcookie('stain_auth', '', array_merge($this->webAuthCookieOptions(), [
            'expires' => time() - 3600,
        ]));
        header('Location: /');
        exit();
    }

    private function handleEditPost(int $postId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $data = [
            'title' => (string) ($_POST['title'] ?? ''),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'created_at' => (string) ($_POST['created_at'] ?? ''),
            'preview_text' => (string) ($_POST['preview_text'] ?? ''),
            'full_text' => (string) ($_POST['full_text'] ?? ''),
            'status' => (string) ($_POST['status'] ?? 'draft'),
        ];
        $updated = $this->postService->update($actor, $postId, $data, 'post');
        $this->attachMediaToPost($postId, (string) ($updated['full_text'] ?? ''));
        header('Location: ' . PostService::postPublicPath($updated) . '?notice=' . rawurlencode('Пост отредактирован'));
        exit();
    }

    private function attachMediaToPost(int $postId, string $html): void
    {
        preg_match_all('#/media/([0-9]+)#', $html, $m);
        $ids = array_map('intval', $m[1] ?? []);
        if ($ids === []) {
            return;
        }
        $repo = new MediaRepository(Database::pdo());
        $repo->attachToPost($postId, $ids);
    }

    private function handleDeletePost(int $postId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $this->postService->delete($actor, $postId);
        header('Location: /?notice=' . rawurlencode('Пост удалён'));
        exit();
    }

    private function handleEditPage(int $pageId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $data = [
            'title' => (string) ($_POST['title'] ?? ''),
            'slug' => (string) ($_POST['slug'] ?? ''),
            'full_text' => (string) ($_POST['full_text'] ?? ''),
            'preview_text' => '',
        ];
        $updated = $this->postService->update($actor, $pageId, $data, 'page');
        header('Location: /' . $updated['slug'] . '?notice=' . rawurlencode('Страница обновлена'));
        exit();
    }

    private function handleDeletePage(int $pageId): void
    {
        $actor = $this->requireAuth();
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $this->postService->delete($actor, $pageId);
        header('Location: /panel/posts?notice=' . rawurlencode('Страница удалена'));
        exit();
    }
}
