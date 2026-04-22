<?php
declare(strict_types=1);

namespace Stain\Repositories;

use PDO;

final class GameRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function createGame(string $gameId, int $createdBy, int $maxPlayers, bool $allowBots): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO games (id, created_by, max_players, allow_bots, status)
             VALUES (:id, :created_by, :max_players, :allow_bots, :status)
             RETURNING *'
        );
        $stmt->bindValue(':id', $gameId);
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->bindValue(':max_players', $maxPlayers, PDO::PARAM_INT);
        $stmt->bindValue(':allow_bots', $allowBots, PDO::PARAM_BOOL);
        $stmt->bindValue(':status', 'waiting');
        $stmt->execute();

        return (array) $stmt->fetch();
    }

    public function findGameById(string $gameId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $gameId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listGamesForUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*,
                    gp.cash AS final_cash,
                    gp.nickname_snapshot,
                    (
                      SELECT COUNT(*)::int
                      FROM game_players p2
                      WHERE p2.game_id = g.id
                    ) AS players_count
             FROM games g
             JOIN game_players gp ON gp.game_id = g.id
             WHERE gp.user_id = :user_id
             ORDER BY g.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function createPlayer(string $gameId, ?int $userId, int $seatNo, string $nickname, bool $isBot = false): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_players (game_id, user_id, seat_no, nickname_snapshot, is_bot)
             VALUES (:game_id, :user_id, :seat_no, :nickname, :is_bot)
             RETURNING *'
        );
        $stmt->bindValue(':game_id', $gameId);
        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':seat_no', $seatNo, PDO::PARAM_INT);
        $stmt->bindValue(':nickname', $nickname);
        $stmt->bindValue(':is_bot', $isBot, PDO::PARAM_BOOL);
        $stmt->execute();

        return (array) $stmt->fetch();
    }

    public function listPlayers(string $gameId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM game_players WHERE game_id = :game_id ORDER BY seat_no ASC');
        $stmt->execute(['game_id' => $gameId]);

        return $stmt->fetchAll() ?: [];
    }

    public function findPlayerByGameAndUser(string $gameId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM game_players WHERE game_id = :game_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['game_id' => $gameId, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getNextSeatNo(string $gameId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(seat_no), 0) + 1 FROM game_players WHERE game_id = :game_id');
        $stmt->execute(['game_id' => $gameId]);

        return (int) $stmt->fetchColumn();
    }

    public function findInviteByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM game_invites WHERE token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createInvite(string $gameId, string $token, int $createdBy, ?int $invitedUserId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_invites (game_id, token, created_by_user_id, invited_user_id, status)
             VALUES (:game_id, :token, :created_by_user_id, :invited_user_id, :status)
             RETURNING *'
        );
        $stmt->bindValue(':game_id', $gameId);
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':created_by_user_id', $createdBy, PDO::PARAM_INT);
        $stmt->bindValue(':invited_user_id', $invitedUserId, $invitedUserId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':status', 'active');
        $stmt->execute();

        return (array) $stmt->fetch();
    }

    public function listActiveInvitesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*
             FROM game_invites i
             WHERE i.invited_user_id = :uid AND i.status = :status
             ORDER BY i.created_at DESC'
        );
        $stmt->execute([
            'uid' => $userId,
            'status' => 'active',
        ]);

        return $stmt->fetchAll() ?: [];
    }

    public function markInviteAccepted(int $inviteId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE game_invites SET status = :status, accepted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'id' => $inviteId,
            'status' => 'accepted',
        ]);
    }

    public function insertEvent(
        string $gameId,
        string $eventType,
        ?int $actorUserId,
        ?int $actorPlayerId,
        ?string $clientMsgId,
        array $payload
    ): array {
        $nextSeq = $this->nextEventSeq($gameId);
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_events (game_id, event_seq, event_type, actor_user_id, actor_player_id, client_msg_id, payload_json)
             VALUES (:game_id, :event_seq, :event_type, :actor_user_id, :actor_player_id, :client_msg_id, :payload::jsonb)
             RETURNING *'
        );
        $stmt->bindValue(':game_id', $gameId);
        $stmt->bindValue(':event_seq', $nextSeq, PDO::PARAM_INT);
        $stmt->bindValue(':event_type', $eventType);
        $stmt->bindValue(':actor_user_id', $actorUserId, $actorUserId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':actor_player_id', $actorPlayerId, $actorPlayerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':client_msg_id', $clientMsgId, $clientMsgId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':payload', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $stmt->execute();

        return (array) $stmt->fetch();
    }

    public function listEventsSince(string $gameId, int $sinceSeq, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM game_events
             WHERE game_id = :game_id AND event_seq > :since_seq
             ORDER BY event_seq ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':game_id', $gameId);
        $stmt->bindValue(':since_seq', $sinceSeq, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function saveSnapshot(string $gameId, int $versionNo, array $state): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_state_snapshots (game_id, version_no, state_json)
             VALUES (:game_id, :version_no, :state_json::jsonb)'
        );
        $stmt->execute([
            'game_id' => $gameId,
            'version_no' => $versionNo,
            'state_json' => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function latestSnapshot(string $gameId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM game_state_snapshots
             WHERE game_id = :game_id
             ORDER BY version_no DESC
             LIMIT 1'
        );
        $stmt->execute(['game_id' => $gameId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createChatMessage(string $gameId, ?int $fromPlayerId, ?int $toPlayerId, bool $isPrivate, string $message): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_chat_messages (game_id, from_player_id, to_player_id, is_private, message)
             VALUES (:game_id, :from_player_id, :to_player_id, :is_private, :message)
             RETURNING *'
        );
        $stmt->bindValue(':game_id', $gameId);
        $stmt->bindValue(':from_player_id', $fromPlayerId, $fromPlayerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':to_player_id', $toPlayerId, $toPlayerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':is_private', $isPrivate, PDO::PARAM_BOOL);
        $stmt->bindValue(':message', $message);
        $stmt->execute();

        return (array) $stmt->fetch();
    }

    public function listChatMessages(string $gameId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, fp.nickname_snapshot AS from_name, tp.nickname_snapshot AS to_name
             FROM game_chat_messages m
             LEFT JOIN game_players fp ON fp.id = m.from_player_id
             LEFT JOIN game_players tp ON tp.id = m.to_player_id
             WHERE m.game_id = :game_id
             ORDER BY m.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':game_id', $gameId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function updatePlayerState(int $playerId, int $cash, int $position, bool $inJail, int $jailTurns): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE game_players
             SET cash = :cash, position = :position, in_jail = :in_jail, jail_turns = :jail_turns
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue(':cash', $cash, PDO::PARAM_INT);
        $stmt->bindValue(':position', $position, PDO::PARAM_INT);
        $stmt->bindValue(':in_jail', $inJail, PDO::PARAM_BOOL);
        $stmt->bindValue(':jail_turns', $jailTurns, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findPropertyState(string $gameId, int $position): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM game_property_state WHERE game_id = :game_id AND cell_position = :pos LIMIT 1'
        );
        $stmt->execute(['game_id' => $gameId, 'pos' => $position]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertPropertyOwner(string $gameId, int $position, ?int $ownerPlayerId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_property_state (game_id, cell_position, owner_player_id, houses, has_hotel, mortgaged)
             VALUES (:game_id, :pos, :owner, 0, FALSE, FALSE)
             ON CONFLICT (game_id, cell_position)
             DO UPDATE SET owner_player_id = EXCLUDED.owner_player_id, updated_at = NOW()'
        );
        $stmt->bindValue(':game_id', $gameId);
        $stmt->bindValue(':pos', $position, PDO::PARAM_INT);
        $stmt->bindValue(':owner', $ownerPlayerId, $ownerPlayerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
    }

    public function listPropertyStates(string $gameId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ps.*, gp.nickname_snapshot AS owner_name
             FROM game_property_state ps
             LEFT JOIN game_players gp ON gp.id = ps.owner_player_id
             WHERE ps.game_id = :game_id
             ORDER BY ps.cell_position ASC'
        );
        $stmt->execute(['game_id' => $gameId]);
        return $stmt->fetchAll() ?: [];
    }

    public function touchGame(string $gameId): void
    {
        $stmt = $this->pdo->prepare('UPDATE games SET updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $gameId]);
    }

    public function listBoardTemplates(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM game_board_templates ORDER BY created_at DESC');
        return $stmt->fetchAll() ?: [];
    }

    public function countGamesToday(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*)::int FROM games WHERE created_at::date = CURRENT_DATE");
        return (int) $stmt->fetchColumn();
    }

    public function countGamesAll(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*)::int FROM games");
        return (int) $stmt->fetchColumn();
    }

    public function listGamesToday(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT g.*, u.nickname AS creator_nickname
             FROM games g
             JOIN users u ON u.id = g.created_by
             WHERE g.created_at::date = CURRENT_DATE
             ORDER BY g.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listGamesAll(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT g.*, u.nickname AS creator_nickname
             FROM games g
             JOIN users u ON u.id = g.created_by
             ORDER BY g.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function createBoardTemplate(int $createdBy, string $name, bool $published): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_board_templates (created_by, name, is_published)
             VALUES (:created_by, :name, :is_published)
             RETURNING *'
        );
        $stmt->execute([
            'created_by' => $createdBy,
            'name' => $name,
            'is_published' => $published,
        ]);
        return (array) $stmt->fetch();
    }

    private function nextEventSeq(string $gameId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(event_seq), 0) + 1 FROM game_events WHERE game_id = :game_id');
        $stmt->execute(['game_id' => $gameId]);

        return (int) $stmt->fetchColumn();
    }
}
