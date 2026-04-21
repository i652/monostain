# stain

Stain is a lightweight blog CMS built with native PHP, PostgreSQL, native CSS, and vanilla JavaScript.

## Stack

- PHP 8.3 (without third-party libraries)
- PostgreSQL 16
- Nginx + php-fpm (или Apache на shared-хостинге)
- Docker Compose — **только для локальной разработки**; production обычно разворачивается на VPS или **обычном shared-хостинге** с PHP и PostgreSQL (или внешней БД).

Дополнительно: [интеграция с VK — обзор](docs/VK_INTEGRATION.md), [аудит CSS](docs/CSS_AUDIT.md).

## Quick start (локально через Docker)

1. Copy env file:

```bash
cp .env.example .env
```

2. Start containers:

```bash
docker compose up -d --build
```

3. Apply DB schema:

```bash
docker compose exec app php bin/migrate.php
```

4. Create first admin:

```bash
docker compose exec app php bin/seed_admin.php admin@example.com StrongPass123!
```

## Развёртывание на shared-хостинге / без Docker

Цель: типичный хостинг с PHP 8+, доступом к PostgreSQL и настраиваемым `.env`.

1. Загрузите код в корень сайта (или подпапку, если так настроен веб-сервер).
2. Укажите `Document Root` на каталог `public/` (или эквивалент: только публичная папка должна отдаваться напрямую).
3. Скопируйте `.env.example` в `.env`, заполните `DB_*`, `JWT_SECRET`, `APP_URL`, при необходимости `MAIL_*` и `AUTH_COOKIE_*` (при HTTPS — `AUTH_COOKIE_SECURE=1`).
4. Выполните миграции: `php bin/migrate.php` (через SSH, cron-хостинга или одноразовый скрипт).
5. Создайте администратора: `php bin/seed_admin.php email@example.com YourPassword`.
6. Проверьте лимиты загрузки: в панели хостинга или в `php.ini` / `.user.ini` выровняйте `upload_max_filesize` и `post_max_size` под `UPLOAD_MAX_*` в `.env`.

Отправка почты на shared-хостинге чаще всего настраивается в панели (SMTP) или через уже доступный PHP `mail()` — не завязана на Docker.

## API

Ошибки в JSON обычно имеют вид `{ "error": "...", "code": "UNAUTHORIZED" | "FORBIDDEN" | "VALIDATION_ERROR" | "NOT_FOUND" | "BAD_REQUEST" | "MEDIA_IN_USE" | ... }` и соответствующий HTTP-код (401, 403, 404, 409, 422 и т.д.).

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `GET /api/v1/public/posts?offset=0&limit=10`
- `GET /api/v1/posts` (JWT required)
- `POST /api/v1/posts` (JWT required)
- `PUT /api/v1/posts/{id}` (JWT required, owner or admin)
- `GET /api/v1/pages` (JWT required, admin only)
- `POST /api/v1/pages` (JWT required, admin only)
- `PUT /api/v1/pages/{id}` (JWT required, admin only)
- `POST /api/v1/media` (JWT required, admin or author, multipart `file`)
- `DELETE /api/v1/media/{id}` (admin only; `409` + `code: MEDIA_IN_USE`, если файл вставлен в пост/страницу)
 - `GET /api/v1/users` (JWT required, admin only)
 - `PATCH /api/v1/users/{id}` (JWT required, admin only)

## Admin panel

- Вход / регистрация: `/auth`
- Панель (роль `admin`): `/panel/posts`
- Пользователи: `/panel/users`
- Новый пост: `/panel/posts/new`
- Новая страница: `/panel/pages/new`
- Главная с лентой: `/`
- Статические страницы: `/{slug}.html` (как в шаблонах футера) или по slug из БД

## CMS features

- **Posts**: title, preview text, full text, SEO description, publish status
- **Auto-slug**: generated from title with Cyrillic transliteration (e.g. `dobavili-novoe-pole.html`)
- **Scheduling**: if publish is selected with a future creation date, post is stored as draft and will be auto-promoted on the next public request once the time has come
- **Pages**: static pages stored in the same table but excluded from the posts list
- **Auth**: JWT-based auth stored in HttpOnly cookie for web UI, and supported via `Authorization: Bearer ...` for API calls
- **Roles**: `admin` and `author`; `admin` — панель `/panel/*`, пользователи, медиатека
- **Media uploads**: images/videos stored under `storage/media/images` and `storage/media/video`, recorded in PostgreSQL and can be attached to a post
- **SEO**: каноникал, Open Graph, `sitemap.xml` (главная, категории с постами, посты, страницы + `lastmod`), `robots.txt` (закрыты `/panel/`, `/auth`, `/api/`), `Last-Modified` / условный 304 для публичных страниц, фавикон `public/favicon.svg`, `SITE_LANG` в `.env`

## Security choices

- Password hashing via `password_hash(..., PASSWORD_ARGON2ID)`
- JWT HS256 implementation with strict signature and `exp` checks
- SQL only via PDO prepared statements
- Media uploads use MIME whitelist, size limits, random file names, storage outside webroot
- Legacy `eval` and flat-file `unserialize` architecture removed

## SEO and indexing

- Публичные URL постов: `/{category_slug}/{post_slug}` (slug обычно оканчивается на `.html`).
- [Яндекс Вебмастер](https://yandex.ru/support/webmaster/ru/recommendations/indexing): добавьте сайт и укажите `sitemap.xml` из корня (`{APP_URL}/sitemap.xml`).
- `robots.txt` отдаётся с `APP_URL`; служебные разделы в `Disallow`, `/media/` остаётся доступным для превью и иллюстраций.
- Автотесты маршрутизации и SEO-хелперов: `php tests/run.php`.
