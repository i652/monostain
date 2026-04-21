CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'author')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS posts (
  id BIGSERIAL PRIMARY KEY,
  author_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  content_type VARCHAR(20) NOT NULL DEFAULT 'post' CHECK (content_type IN ('post', 'page')),
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(120) NOT NULL DEFAULT '',
  body TEXT NOT NULL DEFAULT '',
  preview_text TEXT NOT NULL DEFAULT '',
  full_text TEXT NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL CHECK (status IN ('draft', 'published')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  published_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS media (
  id BIGSERIAL PRIMARY KEY,
  post_id BIGINT REFERENCES posts(id) ON DELETE CASCADE,
  owner_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL DEFAULT '',
  kind VARCHAR(20) NOT NULL DEFAULT 'file' CHECK (kind IN ('image', 'video', 'file')),
  mime_type VARCHAR(120) NOT NULL,
  size_bytes BIGINT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_posts_status_published_at ON posts(status, published_at DESC);

ALTER TABLE posts ADD COLUMN IF NOT EXISTS content_type VARCHAR(20) NOT NULL DEFAULT 'post';
ALTER TABLE posts ADD COLUMN IF NOT EXISTS body TEXT NOT NULL DEFAULT '';
ALTER TABLE posts ADD COLUMN IF NOT EXISTS preview_text TEXT NOT NULL DEFAULT '';
ALTER TABLE posts ADD COLUMN IF NOT EXISTS full_text TEXT NOT NULL DEFAULT '';
ALTER TABLE posts ADD COLUMN IF NOT EXISTS category VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE posts ALTER COLUMN author_id DROP NOT NULL;
ALTER TABLE posts ALTER COLUMN body SET DEFAULT '';

DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_name = 'posts' AND column_name = 'body'
  ) THEN
    EXECUTE 'UPDATE posts SET full_text = body WHERE full_text = '''' AND COALESCE(body, '''') <> ''''';
  END IF;
END $$;

UPDATE posts SET body = full_text WHERE body = '' AND COALESCE(full_text, '') <> '';
UPDATE posts SET preview_text = seo_description WHERE preview_text = '' AND COALESCE(seo_description, '') <> '';

CREATE INDEX IF NOT EXISTS idx_posts_type_status_published_at ON posts(content_type, status, published_at DESC);

ALTER TABLE media ADD COLUMN IF NOT EXISTS post_id BIGINT;
ALTER TABLE media ADD COLUMN IF NOT EXISTS stored_path VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE media ADD COLUMN IF NOT EXISTS kind VARCHAR(20) NOT NULL DEFAULT 'file';

INSERT INTO posts (author_id, content_type, slug, title, body, preview_text, full_text, seo_description, status, created_at, published_at)
SELECT NULL, 'page', 'o-proekte.html', 'О проекте', 'Это статичная страница о проекте.', '', 'Это статичная страница о проекте.', '', 'published', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE slug = 'o-proekte.html');

INSERT INTO posts (author_id, content_type, slug, title, body, preview_text, full_text, seo_description, status, created_at, published_at)
SELECT NULL, 'page', 'uslugi.html', 'Услуги', 'Здесь будет описание услуг.', '', 'Здесь будет описание услуг.', '', 'published', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE slug = 'uslugi.html');

INSERT INTO posts (author_id, content_type, slug, title, body, preview_text, full_text, seo_description, status, created_at, published_at)
SELECT NULL, 'page', 'kontakty.html', 'Контакты', 'Контактная информация.', '', 'Контактная информация.', '', 'published', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE slug = 'kontakty.html');

INSERT INTO posts (author_id, content_type, slug, title, body, preview_text, full_text, seo_description, status, created_at, published_at)
SELECT NULL, 'page', 'rekvizity.html', 'Реквизиты', 'Реквизиты компании.', '', 'Реквизиты компании.', '', 'published', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE slug = 'rekvizity.html');

CREATE TABLE IF NOT EXISTS categories (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(64) NOT NULL UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO categories (name, slug)
SELECT 'Новости', 'news'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'news');

ALTER TABLE posts ADD COLUMN IF NOT EXISTS category_id BIGINT REFERENCES categories(id) ON DELETE SET NULL;

UPDATE posts
SET category_id = (SELECT id FROM categories WHERE slug = 'news' LIMIT 1)
WHERE content_type = 'post' AND category_id IS NULL;

ALTER TABLE posts DROP COLUMN IF EXISTS category;

ALTER TABLE posts DROP CONSTRAINT IF EXISTS posts_must_have_category_for_post;
ALTER TABLE posts ADD CONSTRAINT posts_must_have_category_for_post
  CHECK (content_type <> 'post' OR category_id IS NOT NULL);

-- Никнеймы пользователей (уникальность без учёта регистра)
ALTER TABLE users ADD COLUMN IF NOT EXISTS nickname VARCHAR(64);
UPDATE users SET nickname = 'user_' || id::text WHERE nickname IS NULL OR trim(nickname) = '';
ALTER TABLE users ALTER COLUMN nickname SET NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_nickname_lower ON users (LOWER(nickname));
