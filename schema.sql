CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'author', 'player')),
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

ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'author', 'player'));

-- Core game tables
CREATE TABLE IF NOT EXISTS games (
  id UUID PRIMARY KEY,
  created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  board_template_id BIGINT,
  status VARCHAR(20) NOT NULL DEFAULT 'waiting' CHECK (status IN ('waiting', 'active', 'finished', 'cancelled')),
  max_players SMALLINT NOT NULL DEFAULT 8 CHECK (max_players BETWEEN 1 AND 8),
  allow_bots BOOLEAN NOT NULL DEFAULT TRUE,
  winner_player_id BIGINT,
  started_at TIMESTAMPTZ,
  finished_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

ALTER TABLE games DROP CONSTRAINT IF EXISTS games_max_players_check;
ALTER TABLE games ADD CONSTRAINT games_max_players_check CHECK (max_players BETWEEN 1 AND 8);
ALTER TABLE games DROP COLUMN IF EXISTS title;

CREATE TABLE IF NOT EXISTS game_players (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  seat_no SMALLINT NOT NULL,
  nickname_snapshot VARCHAR(64) NOT NULL,
  is_bot BOOLEAN NOT NULL DEFAULT FALSE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  cash INT NOT NULL DEFAULT 1500,
  position SMALLINT NOT NULL DEFAULT 0 CHECK (position BETWEEN 0 AND 120),
  in_jail BOOLEAN NOT NULL DEFAULT FALSE,
  jail_turns SMALLINT NOT NULL DEFAULT 0,
  bankrupt BOOLEAN NOT NULL DEFAULT FALSE,
  joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  left_at TIMESTAMPTZ,
  UNIQUE (game_id, seat_no),
  UNIQUE (game_id, user_id)
);

CREATE TABLE IF NOT EXISTS game_state_snapshots (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  version_no BIGINT NOT NULL,
  state_json JSONB NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (game_id, version_no)
);

CREATE TABLE IF NOT EXISTS game_events (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  event_seq BIGINT NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  actor_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  actor_player_id BIGINT REFERENCES game_players(id) ON DELETE SET NULL,
  client_msg_id VARCHAR(64),
  payload_json JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (game_id, event_seq),
  UNIQUE (game_id, client_msg_id)
);

CREATE TABLE IF NOT EXISTS game_transactions (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  from_player_id BIGINT REFERENCES game_players(id) ON DELETE SET NULL,
  to_player_id BIGINT REFERENCES game_players(id) ON DELETE SET NULL,
  amount INT NOT NULL,
  reason VARCHAR(80) NOT NULL,
  event_id BIGINT REFERENCES game_events(id) ON DELETE SET NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS game_chat_messages (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  from_player_id BIGINT REFERENCES game_players(id) ON DELETE SET NULL,
  to_player_id BIGINT REFERENCES game_players(id) ON DELETE SET NULL,
  is_private BOOLEAN NOT NULL DEFAULT FALSE,
  message TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS game_invites (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  token VARCHAR(72) NOT NULL UNIQUE,
  created_by_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  invited_user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'accepted', 'revoked', 'expired')),
  expires_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  accepted_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS game_board_templates (
  id BIGSERIAL PRIMARY KEY,
  created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  name VARCHAR(120) NOT NULL UNIQUE,
  is_default BOOLEAN NOT NULL DEFAULT FALSE,
  is_published BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS game_board_cells (
  id BIGSERIAL PRIMARY KEY,
  board_template_id BIGINT NOT NULL REFERENCES game_board_templates(id) ON DELETE CASCADE,
  position SMALLINT NOT NULL CHECK (position BETWEEN 0 AND 120),
  cell_type VARCHAR(24) NOT NULL,
  title VARCHAR(120) NOT NULL,
  buy_price INT NOT NULL DEFAULT 0,
  rent_rules JSONB NOT NULL DEFAULT '{}'::jsonb,
  extra_json JSONB NOT NULL DEFAULT '{}'::jsonb,
  UNIQUE (board_template_id, position)
);

ALTER TABLE game_board_cells DROP CONSTRAINT IF EXISTS game_board_cells_position_check;
ALTER TABLE game_board_cells ADD CONSTRAINT game_board_cells_position_check CHECK (position BETWEEN 0 AND 120);
ALTER TABLE game_players DROP CONSTRAINT IF EXISTS game_players_position_check;
ALTER TABLE game_players ADD CONSTRAINT game_players_position_check CHECK (position BETWEEN 0 AND 120);
ALTER TABLE game_property_state DROP CONSTRAINT IF EXISTS game_property_state_cell_position_check;
ALTER TABLE game_property_state ADD CONSTRAINT game_property_state_cell_position_check CHECK (cell_position BETWEEN 0 AND 120);

CREATE TABLE IF NOT EXISTS game_property_state (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  cell_position SMALLINT NOT NULL CHECK (cell_position BETWEEN 0 AND 120),
  owner_player_id BIGINT REFERENCES game_players(id) ON DELETE SET NULL,
  houses SMALLINT NOT NULL DEFAULT 0 CHECK (houses BETWEEN 0 AND 4),
  has_hotel BOOLEAN NOT NULL DEFAULT FALSE,
  mortgaged BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (game_id, cell_position)
);

CREATE TABLE IF NOT EXISTS game_turns (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  turn_no BIGINT NOT NULL,
  player_id BIGINT REFERENCES game_players(id) ON DELETE SET NULL,
  dice_1 SMALLINT,
  dice_2 SMALLINT,
  started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  ended_at TIMESTAMPTZ,
  UNIQUE (game_id, turn_no)
);

CREATE TABLE IF NOT EXISTS game_trades (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  proposer_player_id BIGINT NOT NULL REFERENCES game_players(id) ON DELETE CASCADE,
  target_player_id BIGINT NOT NULL REFERENCES game_players(id) ON DELETE CASCADE,
  status VARCHAR(20) NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'accepted', 'rejected', 'cancelled')),
  note TEXT NOT NULL DEFAULT '',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS game_trade_items (
  id BIGSERIAL PRIMARY KEY,
  trade_id BIGINT NOT NULL REFERENCES game_trades(id) ON DELETE CASCADE,
  offered_by_player_id BIGINT NOT NULL REFERENCES game_players(id) ON DELETE CASCADE,
  item_type VARCHAR(20) NOT NULL CHECK (item_type IN ('cash', 'property')),
  cash_amount INT,
  property_position SMALLINT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS game_player_stats (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL REFERENCES games(id) ON DELETE CASCADE,
  player_id BIGINT NOT NULL REFERENCES game_players(id) ON DELETE CASCADE,
  turns_count INT NOT NULL DEFAULT 0,
  rent_paid INT NOT NULL DEFAULT 0,
  rent_received INT NOT NULL DEFAULT 0,
  taxes_paid INT NOT NULL DEFAULT 0,
  cards_drawn INT NOT NULL DEFAULT 0,
  jail_visits INT NOT NULL DEFAULT 0,
  distance_moved INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (game_id, player_id)
);

CREATE TABLE IF NOT EXISTS game_history_archive (
  id BIGSERIAL PRIMARY KEY,
  game_id UUID NOT NULL UNIQUE REFERENCES games(id) ON DELETE CASCADE,
  summary_json JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_games_status ON games (status);
CREATE INDEX IF NOT EXISTS idx_games_created_at ON games (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_game_players_game ON game_players (game_id);
CREATE INDEX IF NOT EXISTS idx_game_events_game_seq ON game_events (game_id, event_seq);
CREATE INDEX IF NOT EXISTS idx_game_events_created_at ON game_events (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_game_chat_game_created ON game_chat_messages (game_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_game_invites_user_status ON game_invites (invited_user_id, status);
CREATE INDEX IF NOT EXISTS idx_game_transactions_game_created ON game_transactions (game_id, created_at DESC);
