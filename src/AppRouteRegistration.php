<?php
declare(strict_types=1);

namespace Stain;

use Stain\Http\Router;
use Stain\Http\SeoCrawlerHints;
use Stain\Config;
use Stain\Repositories\UserRepository;

trait AppRouteRegistration
{
    private function registerHttpRoutes(Router $r): void
    {
        $r->map('GET', '#^/$#', function (): void {
            $this->renderHome();
        });
        $r->map('POST', '#^/panel/posts$#', function (): void {
            $this->createPostFromHome();
        });
        $r->map('POST', '#^/panel/pages$#', function (): void {
            $this->createPageFromAdmin();
        });
        $r->map('GET', '#^/auth$#', function (): void {
            $this->renderAuth();
        });
        $r->map('GET', '#^/profile$#', function (): void {
            $this->renderProfile();
        });
        $r->map('POST', '#^/profile$#', function (): void {
            $this->handleProfileUpdate();
        });
        $r->map('POST', '#^/profile/password$#', function (): void {
            $this->handleProfilePasswordUpdate();
        });
        $r->map('GET', '#^/game/new$#', function (): void {
            $this->renderNewGame();
        });
        $r->map('GET', '#^/game/invites$#', function (): void {
            $this->renderInvites();
        });
        $r->map('GET', '#^/game$#', function (): void {
            $this->renderGames();
        });
        $r->map('GET', '#^/game/([a-f0-9-]+)$#', function (array $m): void {
            $this->renderGameRoom((string) $m[1]);
        });
        // legacy redirects
        $r->map('GET', '#^/games/new$#', function (): void { header('Location: /game/new', true, 301); exit(); });
        $r->map('GET', '#^/games/invites$#', function (): void { header('Location: /game/invites', true, 301); exit(); });
        $r->map('GET', '#^/games$#', function (): void { header('Location: /game', true, 301); exit(); });
        $r->map('GET', '#^/games/([a-f0-9-]+)$#', function (array $m): void { header('Location: /game/' . $m[1], true, 301); exit(); });
        $r->map('GET', '#^/feedback$#', function (): void {
            $this->renderFeedback();
        });
        $r->map('GET', '#^/auth/logout$#', function (): void {
            $this->handleLogout();
        });
        $r->map('POST', '#^/auth/login$#', function (): void {
            $this->handleAuthLogin();
        });
        $r->map('POST', '#^/auth/register$#', function (): void {
            $this->handleAuthRegister();
        });
        $r->map('GET', '#^/panel$#', function (): void {
            $this->renderPanel('posts');
        });
        $r->map('GET', '#^/panel/posts$#', function (): void {
            $this->renderPanel('posts');
        });
        $r->map('GET', '#^/panel/pages$#', function (): void {
            $this->renderPanel('pages');
        });
        $r->map('GET', '#^/panel/games$#', function (): void {
            $this->renderPanel('games');
        });
        $r->map('GET', '#^/panel/users$#', function (): void {
            $this->renderUsers();
        });
        $r->map('GET', '#^/panel/users/new$#', function (): void {
            $this->renderNewUser();
        });
        $r->map('POST', '#^/panel/users/new$#', function (): void {
            $this->handleCreateUser();
        });
        $r->map('POST', '#^/panel/users/([0-9]+)/delete$#', function (array $m): void {
            $this->handleDeleteUser((int) $m[1]);
        });
        $r->map('GET', '#^/panel/media$#', function (): void {
            $this->renderMediaLibrary();
        });
        $r->map('GET', '#^/panel/game-boards$#', function (): void {
            $this->renderBoardTemplatesAdmin();
        });
        $r->map('POST', '#^/panel/game-boards$#', function (): void {
            $this->handleBoardTemplateCreate();
        });
        $r->map('GET', '#^/panel/game-boards/new$#', function (): void {
            $this->renderBoardTemplateEditor(null);
        });
        $r->map('GET', '#^/panel/game-boards/([0-9]+)/edit$#', function (array $m): void {
            $this->renderBoardTemplateEditor((int) $m[1]);
        });
        $r->map('POST', '#^/panel/game-boards/new$#', function (): void {
            $this->handleBoardTemplateSave(null);
        });
        $r->map('POST', '#^/panel/game-boards/([0-9]+)/edit$#', function (array $m): void {
            $this->handleBoardTemplateSave((int) $m[1]);
        });
        $r->map('GET', '#^/panel/categories$#', function (): void {
            $this->renderCategories();
        });
        $r->map('GET', '#^/panel/categories/new$#', function (): void {
            $this->renderNewCategory();
        });
        $r->map('POST', '#^/panel/categories$#', function (): void {
            $this->handleCreateCategory();
        });
        $r->map('POST', '#^/panel/categories/([0-9]+)/delete$#', function (array $m): void {
            $this->handleDeleteCategory((int) $m[1]);
        });
        $r->map('GET', '#^/panel/categories/([0-9]+)/edit$#', function (array $m): void {
            $this->renderEditCategory((int) $m[1]);
        });
        $r->map('POST', '#^/panel/categories/([0-9]+)/edit$#', function (array $m): void {
            $this->handleEditCategory((int) $m[1]);
        });
        $r->map('POST', '#^/panel/users/([0-9]+)/role$#', function (array $m): void {
            $this->handleUserRoleUpdate((int) $m[1]);
        });
        $r->map('GET', '#^/panel/posts/new$#', function (): void {
            $this->renderNewPost();
        });
        $r->map('GET', '#^/panel/pages/new$#', function (): void {
            $this->renderNewPage();
        });
        $r->map('GET', '#^/panel/pages/([0-9]+)/edit$#', function (array $m): void {
            $this->renderEditPage((int) $m[1]);
        });
        $r->map('POST', '#^/panel/pages/([0-9]+)/edit$#', function (array $m): void {
            $this->handleEditPage((int) $m[1]);
        });
        $r->map('POST', '#^/panel/pages/([0-9]+)/delete$#', function (array $m): void {
            $this->handleDeletePage((int) $m[1]);
        });
        $r->map('POST', '#^/panel/posts/([0-9]+)/media$#', function (array $m): void {
            $this->handlePostMediaUpload((int) $m[1]);
        });
        $r->map('GET', '#^/post/([a-z0-9\.-]+\.html)$#', function (array $m): void {
            $this->redirectLegacyPostSlug($m[1]);
        });
        $r->map('GET', '#^/([a-z0-9\.-]+\.html)$#', function (array $m): ?bool {
            $reserved = ['services.html', 'contacts.html', 'about.html'];
            if (in_array($m[1], $reserved, true) || !str_starts_with($m[1], 'panel')) {
                $this->renderPage($m[1]);

                return true;
            }

            return false;
        });
        $r->map('GET', '#^/([a-z0-9-]+)/([a-z0-9\.-]+\.html)$#', function (array $m): ?bool {
            $reserved = ['panel', 'auth', 'api', 'page', 'post', 'assets', 'media'];
            if (!in_array($m[1], $reserved, true)) {
                $this->renderPostInCategory($m[1], $m[2]);

                return true;
            }

            return false;
        });
        $r->map('GET', '#^/panel/posts/([0-9]+)/edit$#', function (array $m): void {
            $this->renderEditPost((int) $m[1]);
        });
        $r->map('POST', '#^/panel/posts/([0-9]+)/edit$#', function (array $m): void {
            $this->handleEditPost((int) $m[1]);
        });
        $r->map('POST', '#^/panel/posts/([0-9]+)/delete$#', function (array $m): void {
            $this->handleDeletePost((int) $m[1]);
        });
        $r->map('GET', '#^/sitemap\.xml$#', function (): void {
            $this->renderSitemap();
        });
        $r->map('GET', '#^/robots\.txt$#', function (): void {
            $this->respondText(SeoCrawlerHints::robotsTxtBody(Config::get('APP_URL', 'http://localhost:8080')));
        });
        $r->map('GET', '#^/site-map$#', function (): void {
            $this->renderHtmlSitemap();
        });
        $r->map('GET', '#^/media/([0-9]+)$#', function (array $m): void {
            $this->serveMedia((int) $m[1]);
        });

        $r->map('GET', '#^/api/v1/auth/availability$#', function (): void {
            $email = (string) ($_GET['email'] ?? '');
            $nickname = (string) ($_GET['nickname'] ?? '');
            $this->respondJson($this->authService->checkRegistrationAvailability($email, $nickname));
        });
        $r->map('POST', '#^/api/v1/auth/register$#', function (): void {
            $data = $this->jsonInput();
            $this->respondJson(
                $this->authService->register(
                    (string) ($data['email'] ?? ''),
                    (string) ($data['password'] ?? ''),
                    (string) ($data['nickname'] ?? '')
                ),
                201
            );
        });
        $r->map('POST', '#^/api/v1/auth/login$#', function (): void {
            $data = $this->jsonInput();
            $result = $this->authService->login((string) ($data['email'] ?? ''), (string) ($data['password'] ?? ''));
            $this->setWebAuthCookie($result['token']);
            $this->respondJson($result);
        });
        $r->map('GET', '#^/api/v1/public/posts$#', function (): void {
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            $limit = max(1, min(10, (int) ($_GET['limit'] ?? 10)));
            $category = trim((string) ($_GET['category'] ?? ''));
            if ($category !== '' && $this->categoryRepository->findBySlug($category) !== null) {
                $items = $this->postService->listPublishedInCategory($category, $limit, $offset);
                $this->respondJson([
                    'items' => $items,
                    'has_more' => $this->postService->hasMorePublishedInCategory($category, $offset + count($items)),
                ]);
            } else {
                $items = $this->postService->listPublished($limit, $offset);
                $this->respondJson(['items' => $items, 'has_more' => $this->postService->hasMorePublished($offset + count($items))]);
            }
        });
        $r->map('GET', '#^/api/v1/posts$#', function (): void {
            $actor = $this->requireAuth();
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            $limit = max(1, min(50, (int) ($_GET['limit'] ?? 50)));
            $items = $this->postService->listForActor($actor, 'post', $limit, $offset);
            $this->respondJson([
                'items' => $items,
                'has_more' => count($items) === $limit,
            ]);
        });
        $r->map('GET', '#^/api/v1/pages$#', function (): void {
            $actor = $this->requireAuth();
            if (($actor['role'] ?? '') !== 'admin') {
                throw new \RuntimeException('Forbidden');
            }
            $this->respondJson(['items' => $this->postService->listForActor($actor, 'page')]);
        });
        $r->map('GET', '#^/api/v1/users$#', function (): void {
            $actor = $this->requireAuth();
            if (($actor['role'] ?? '') !== 'admin') {
                throw new \RuntimeException('Forbidden');
            }
            $usersRepo = new UserRepository(Database::pdo());
            $this->respondJson(['items' => $usersRepo->listAll()]);
        });
        $r->map(['PUT', 'PATCH'], '#^/api/v1/users/([0-9]+)$#', function (array $m): void {
            $actor = $this->requireAuth();
            if (($actor['role'] ?? '') !== 'admin') {
                throw new \RuntimeException('Forbidden');
            }
            $data = $this->jsonInput();
            $role = (string) ($data['role'] ?? '');
            if (!in_array($role, ['admin', 'author', 'player'], true)) {
                throw new \InvalidArgumentException('Invalid role');
            }
            $usersRepo = new UserRepository(Database::pdo());
            $usersRepo->updateRole((int) $m[1], $role);
            $this->respondJson(['ok' => true]);
        });
        $r->map('POST', '#^/api/v1/posts$#', function (): void {
            $actor = $this->requireAuth();
            $data = $this->jsonInput();
            $this->respondJson($this->postService->create($actor, $data, 'post'), 201);
        });
        $r->map('POST', '#^/api/v1/pages$#', function (): void {
            $actor = $this->requireAuth();
            if (($actor['role'] ?? '') !== 'admin') {
                throw new \RuntimeException('Forbidden');
            }
            $data = $this->jsonInput();
            $this->respondJson($this->postService->create($actor, $data, 'page'), 201);
        });
        $r->map(['PUT', 'PATCH'], '#^/api/v1/posts/([0-9]+)$#', function (array $m): void {
            $actor = $this->requireAuth();
            $data = $this->jsonInput();
            $this->respondJson($this->postService->update($actor, (int) $m[1], $data, 'post'));
        });
        $r->map(['PUT', 'PATCH'], '#^/api/v1/pages/([0-9]+)$#', function (array $m): void {
            $actor = $this->requireAuth();
            $data = $this->jsonInput();
            $this->respondJson($this->postService->update($actor, (int) $m[1], $data, 'page'));
        });
        $r->map('GET', '#^/api/v1/media$#', function (): void {
            $actor = $this->requireAuth();
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 10)));
            $slice = $this->mediaService->listSliceForAdmin($actor, $offset, $limit);
            $this->respondJson($slice);
        });
        $r->map('POST', '#^/api/v1/media$#', function (): void {
            $actor = $this->requireAuth();
            $file = $_FILES['file'] ?? [];
            $media = $this->mediaService->store($actor, is_array($file) ? $file : []);
            $this->respondJson([
                'id' => (int) $media['id'],
                'url' => '/media/' . $media['id'],
                'kind' => (string) $media['kind'],
                'mime_type' => $media['mime_type'],
                'size_bytes' => (int) $media['size_bytes'],
                'original_name' => (string) ($media['original_name'] ?? ''),
            ], 201);
        });
        $r->map('DELETE', '#^/api/v1/media/([0-9]+)$#', function (array $m): void {
            $actor = $this->requireAuth();
            $this->mediaService->delete($actor, (int) $m[1]);
            $this->respondJson(['ok' => true]);
        });
        $r->map('POST', '#^/api/v1/feedback$#', function (): void {
            $this->handleFeedbackApi();
        });
        $r->map('POST', '#^/api/v1/game$#', function (): void {
            $this->handleCreateGameApi();
        });
        $r->map('POST', '#^/api/v1/game/join$#', function (): void {
            $this->handleJoinByInviteApi();
        });
        $r->map('POST', '#^/api/v1/game/([a-f0-9-]+)/invites$#', function (array $m): void {
            $this->handleGameInviteApi((string) $m[1]);
        });
        $r->map('GET', '#^/api/v1/game/([a-f0-9-]+)/events$#', function (array $m): void {
            $this->handleGamePollApi((string) $m[1]);
        });
        $r->map('POST', '#^/api/v1/game/([a-f0-9-]+)/commands$#', function (array $m): void {
            $this->handleGameCommandApi((string) $m[1]);
        });
        $r->map('POST', '#^/api/v1/game/([a-f0-9-]+)/chat$#', function (array $m): void {
            $this->handleGameChatApi((string) $m[1]);
        });

        $r->map('GET', '#^/([a-z0-9-]+)$#', function (array $m): ?bool {
            $seg = $m[1];
            $reservedSingle = ['panel', 'auth', 'api', 'assets', 'media', 'feedback', 'post', 'page', 'sitemap', 'robots', 'txt', 'games', 'game', 'profile'];
            if (in_array($seg, $reservedSingle, true)) {
                return false;
            }
            $catRow = $this->categoryRepository->findBySlug($seg);
            if ($catRow !== null) {
                $this->renderCategoryArchive($seg, $catRow);

                return true;
            }

            return false;
        });
    }
}
