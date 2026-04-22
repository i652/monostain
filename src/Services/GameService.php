<?php
declare(strict_types=1);

namespace Stain\Services;

use Stain\Repositories\GameRepository;
use Stain\Repositories\UserRepository;

final class GameService
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly UserRepository $users
    ) {}

    public function createGame(array $actor, array $data): array
    {
        $title = 'Игра Монополия';
        $maxPlayers = (int) ($data['max_players'] ?? 8);
        if ($maxPlayers < 1 || $maxPlayers > 8) {
            throw new \InvalidArgumentException('Количество участников должно быть от 1 до 8');
        }
        $allowBots = array_key_exists('allow_bots', $data)
            ? filter_var($data['allow_bots'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : true;
        if ($allowBots === null) {
            $allowBots = false;
        }
        // Если выбран 1 участник, игра должна идти с ботом/ботами.
        if ($maxPlayers === 1) {
            $allowBots = true;
        }
        $gameId = $this->newUuidV4();
        $game = $this->games->createGame($gameId, (int) $actor['sub'], $title, $maxPlayers, $allowBots);

        $seat = $this->games->getNextSeatNo($gameId);
        $player = $this->games->createPlayer(
            $gameId,
            (int) $actor['sub'],
            $seat,
            (string) ($actor['nickname'] ?? ('user_' . (int) $actor['sub']))
        );
        $this->games->insertEvent($gameId, 'game_created', (int) $actor['sub'], (int) $player['id'], null, [
            'title' => $title,
            'max_players' => $maxPlayers,
            'allow_bots' => $allowBots,
        ]);

        if ($maxPlayers === 1) {
            // Минимум один бот, чтобы игра не была в одиночку.
            $botSeat = $this->games->getNextSeatNo($gameId);
            $bot = $this->games->createPlayer($gameId, null, $botSeat, 'Бот #' . $botSeat, true);
            $this->games->insertEvent($gameId, 'bot_added', (int) $actor['sub'], (int) $player['id'], null, [
                'bot_player_id' => (int) $bot['id'],
                'bot_name' => $bot['nickname_snapshot'],
            ]);
        }

        return [
            'game' => $game,
            'player' => $player,
        ];
    }

    public function listGamesForUser(array $actor): array
    {
        return $this->games->listGamesForUser((int) $actor['sub']);
    }

    public function getGameView(array $actor, string $gameId): array
    {
        $game = $this->games->findGameById($gameId);
        if ($game === null) {
            throw new \RuntimeException('Game not found');
        }
        $player = $this->games->findPlayerByGameAndUser($gameId, (int) $actor['sub']);
        if ($player === null) {
            throw new \RuntimeException('Forbidden');
        }

        return [
            'game' => $game,
            'self' => $player,
            'players' => $this->games->listPlayers($gameId),
            'chat' => $this->games->listChatMessages($gameId),
            'events' => $this->games->listEventsSince($gameId, 0, 300),
        ];
    }

    public function createInvite(array $actor, string $gameId, ?string $nickname): array
    {
        $player = $this->games->findPlayerByGameAndUser($gameId, (int) $actor['sub']);
        if ($player === null) {
            throw new \RuntimeException('Forbidden');
        }
        $invitedUserId = null;
        if ($nickname !== null && trim($nickname) !== '') {
            $user = $this->users->findByNicknameIgnoreCase(trim($nickname));
            if ($user === null) {
                throw new \InvalidArgumentException('Пользователь с таким никнеймом не найден');
            }
            $invitedUserId = (int) $user['id'];
        }
        $token = $this->makeInviteToken();
        $invite = $this->games->createInvite($gameId, $token, (int) $actor['sub'], $invitedUserId);
        $this->games->insertEvent($gameId, 'invite_created', (int) $actor['sub'], (int) $player['id'], null, [
            'invite_token' => $token,
            'invited_user_id' => $invitedUserId,
        ]);

        return $invite;
    }

    public function listMyInvites(array $actor): array
    {
        return $this->games->listActiveInvitesForUser((int) $actor['sub']);
    }

    public function joinByInvite(array $actor, string $token): array
    {
        $invite = $this->games->findInviteByToken($token);
        if ($invite === null || ($invite['status'] ?? '') !== 'active') {
            throw new \RuntimeException('Invite not found');
        }
        $gameId = (string) $invite['game_id'];
        if ($this->games->findPlayerByGameAndUser($gameId, (int) $actor['sub']) !== null) {
            return ['game_id' => $gameId, 'already_joined' => true];
        }
        $currentPlayers = $this->games->listPlayers($gameId);
        $game = $this->games->findGameById($gameId);
        if ($game === null) {
            throw new \RuntimeException('Game not found');
        }
        if (count($currentPlayers) >= (int) $game['max_players']) {
            throw new \RuntimeException('Game is full');
        }
        $seat = $this->games->getNextSeatNo($gameId);
        $player = $this->games->createPlayer(
            $gameId,
            (int) $actor['sub'],
            $seat,
            (string) ($actor['nickname'] ?? ('user_' . (int) $actor['sub']))
        );
        $this->games->markInviteAccepted((int) $invite['id']);
        $this->games->insertEvent($gameId, 'player_joined', (int) $actor['sub'], (int) $player['id'], null, [
            'nickname' => $player['nickname_snapshot'],
        ]);

        return ['game_id' => $gameId, 'already_joined' => false];
    }

    public function postChat(array $actor, string $gameId, string $message, ?int $toPlayerId = null): array
    {
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > 500) {
            throw new \InvalidArgumentException('Сообщение должно быть от 1 до 500 символов');
        }
        $player = $this->games->findPlayerByGameAndUser($gameId, (int) $actor['sub']);
        if ($player === null) {
            throw new \RuntimeException('Forbidden');
        }
        $chat = $this->games->createChatMessage(
            $gameId,
            (int) $player['id'],
            $toPlayerId,
            $toPlayerId !== null,
            $message
        );
        $this->games->insertEvent($gameId, 'chat_sent', (int) $actor['sub'], (int) $player['id'], null, [
            'chat_id' => (int) $chat['id'],
            'is_private' => $toPlayerId !== null,
        ]);

        return $chat;
    }

    public function pollEvents(array $actor, string $gameId, int $sinceSeq): array
    {
        $player = $this->games->findPlayerByGameAndUser($gameId, (int) $actor['sub']);
        if ($player === null) {
            throw new \RuntimeException('Forbidden');
        }

        $deadline = microtime(true) + 20.0;
        do {
            $events = $this->games->listEventsSince($gameId, $sinceSeq, 300);
            if ($events !== []) {
                return $events;
            }
            usleep(500000);
        } while (microtime(true) < $deadline);

        return [];
    }

    public function handleCommand(array $actor, string $gameId, array $data): array
    {
        $player = $this->games->findPlayerByGameAndUser($gameId, (int) $actor['sub']);
        if ($player === null) {
            throw new \RuntimeException('Forbidden');
        }
        $action = (string) ($data['action'] ?? '');
        $clientMsgId = (string) ($data['client_msg_id'] ?? '');
        if ($action === '') {
            throw new \InvalidArgumentException('Не указано действие');
        }
        $payload = [];
        switch ($action) {
            case 'roll':
                $d1 = random_int(1, 6);
                $d2 = random_int(1, 6);
                $move = $d1 + $d2;
                $newPos = (((int) $player['position']) + $move) % 40;
                $cash = (int) $player['cash'];
                if ((((int) $player['position']) + $move) >= 40) {
                    $cash += 200;
                }
                $this->games->updatePlayerState((int) $player['id'], $cash, $newPos, (bool) $player['in_jail'], (int) $player['jail_turns']);
                $payload = ['dice' => [$d1, $d2], 'position' => $newPos, 'cash' => $cash];
                break;
            case 'end_turn':
                $payload = ['ended_by' => (int) $player['id']];
                break;
            case 'pay':
                $toPlayerId = (int) ($data['to_player_id'] ?? 0);
                $amount = (int) ($data['amount'] ?? 0);
                if ($toPlayerId <= 0 || $amount <= 0) {
                    throw new \InvalidArgumentException('Неверные параметры платежа');
                }
                $all = $this->games->listPlayers($gameId);
                $target = null;
                foreach ($all as $p) {
                    if ((int) $p['id'] === $toPlayerId) {
                        $target = $p;
                        break;
                    }
                }
                if ($target === null) {
                    throw new \InvalidArgumentException('Получатель не найден');
                }
                $fromCash = (int) $player['cash'] - $amount;
                $toCash = (int) $target['cash'] + $amount;
                $this->games->updatePlayerState((int) $player['id'], $fromCash, (int) $player['position'], (bool) $player['in_jail'], (int) $player['jail_turns']);
                $this->games->updatePlayerState((int) $target['id'], $toCash, (int) $target['position'], (bool) $target['in_jail'], (int) $target['jail_turns']);
                $payload = ['to_player_id' => $toPlayerId, 'amount' => $amount];
                break;
            default:
                throw new \InvalidArgumentException('Неизвестная команда');
        }

        $event = $this->games->insertEvent(
            $gameId,
            'command_' . $action,
            (int) $actor['sub'],
            (int) $player['id'],
            $clientMsgId !== '' ? $clientMsgId : null,
            $payload
        );
        $this->games->touchGame($gameId);
        if (((int) $event['event_seq']) % 10 === 0) {
            $this->snapshot($gameId);
        }

        return $event;
    }

    public function snapshot(string $gameId): void
    {
        $players = $this->games->listPlayers($gameId);
        $events = $this->games->listEventsSince($gameId, 0, 1_000_000);
        $version = 0;
        if ($events !== []) {
            $last = $events[count($events) - 1];
            $version = (int) ($last['event_seq'] ?? 0);
        }
        $state = [
            'players' => $players,
            'version' => $version,
        ];
        $this->games->saveSnapshot($gameId, $version, $state);
    }

    public function listBoardTemplates(array $actor): array
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        return $this->games->listBoardTemplates();
    }

    public function createBoardTemplate(array $actor, array $data): array
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Укажите название доски');
        }
        return $this->games->createBoardTemplate((int) $actor['sub'], $name, (bool) ($data['is_published'] ?? false));
    }

    public function adminGamesDashboard(array $actor): array
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        return [
            'today_count' => $this->games->countGamesToday(),
            'all_count' => $this->games->countGamesAll(),
            'today_games' => $this->games->listGamesToday(),
            'all_games' => $this->games->listGamesAll(),
        ];
    }

    private function makeInviteToken(): string
    {
        return bin2hex(random_bytes(18));
    }

    private function newUuidV4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
