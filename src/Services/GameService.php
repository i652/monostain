<?php
declare(strict_types=1);

namespace Stain\Services;

use Stain\Repositories\GameRepository;
use Stain\Repositories\UserRepository;

final class GameService
{
    /** @var array<int, array{type:string,name:string,price?:int,rent?:int,tax?:int}> */
    private const BOARD = [
        0 => ['type' => 'go', 'name' => 'Старт'],
        1 => ['type' => 'property', 'name' => 'Средиземноморский пр.', 'price' => 60, 'rent' => 2],
        2 => ['type' => 'chest', 'name' => 'Казна'],
        3 => ['type' => 'property', 'name' => 'Балтик-авеню', 'price' => 60, 'rent' => 4],
        4 => ['type' => 'tax', 'name' => 'Подоходный налог', 'tax' => 200],
        5 => ['type' => 'railroad', 'name' => 'Читающая Ж/Д', 'price' => 200, 'rent' => 25],
        6 => ['type' => 'property', 'name' => 'Ориентал-авеню', 'price' => 100, 'rent' => 6],
        7 => ['type' => 'chance', 'name' => 'Шанс'],
        8 => ['type' => 'property', 'name' => 'Вермонт-авеню', 'price' => 100, 'rent' => 6],
        9 => ['type' => 'property', 'name' => 'Коннектикут-авеню', 'price' => 120, 'rent' => 8],
        10 => ['type' => 'jail', 'name' => 'Тюрьма'],
        11 => ['type' => 'property', 'name' => 'Сент-Чарльз-плейс', 'price' => 140, 'rent' => 10],
        12 => ['type' => 'utility', 'name' => 'Электрокомпания', 'price' => 150, 'rent' => 10],
        13 => ['type' => 'property', 'name' => 'Стейтс-авеню', 'price' => 140, 'rent' => 10],
        14 => ['type' => 'property', 'name' => 'Вирджиния-авеню', 'price' => 160, 'rent' => 12],
        15 => ['type' => 'railroad', 'name' => 'Пенсильвания Ж/Д', 'price' => 200, 'rent' => 25],
        16 => ['type' => 'property', 'name' => 'Сент-Джеймс-плейс', 'price' => 180, 'rent' => 14],
        17 => ['type' => 'chest', 'name' => 'Казна'],
        18 => ['type' => 'property', 'name' => 'Теннесси-авеню', 'price' => 180, 'rent' => 14],
        19 => ['type' => 'property', 'name' => 'Нью-Йорк-авеню', 'price' => 200, 'rent' => 16],
        20 => ['type' => 'free', 'name' => 'Бесплатная парковка'],
        21 => ['type' => 'property', 'name' => 'Кентукки-авеню', 'price' => 220, 'rent' => 18],
        22 => ['type' => 'property', 'name' => 'Индиана-авеню', 'price' => 220, 'rent' => 18],
        23 => ['type' => 'chance', 'name' => 'Шанс'],
        24 => ['type' => 'property', 'name' => 'Иллинойс-авеню', 'price' => 240, 'rent' => 20],
        25 => ['type' => 'railroad', 'name' => 'Ж/д B&O', 'price' => 200, 'rent' => 25],
        26 => ['type' => 'property', 'name' => 'Атлантик-авеню', 'price' => 260, 'rent' => 22],
        27 => ['type' => 'property', 'name' => 'Вентнор-авеню', 'price' => 260, 'rent' => 22],
        28 => ['type' => 'utility', 'name' => 'Водоканал', 'price' => 150, 'rent' => 10],
        29 => ['type' => 'property', 'name' => 'Мэрвин-гарденс', 'price' => 280, 'rent' => 24],
        30 => ['type' => 'go_to_jail', 'name' => 'Идите в тюрьму'],
        31 => ['type' => 'property', 'name' => 'Пасифик-авеню', 'price' => 300, 'rent' => 26],
        32 => ['type' => 'property', 'name' => 'Сев. Каролина-авеню', 'price' => 300, 'rent' => 26],
        33 => ['type' => 'chest', 'name' => 'Казна'],
        34 => ['type' => 'property', 'name' => 'Пенсильвания-авеню', 'price' => 320, 'rent' => 28],
        35 => ['type' => 'railroad', 'name' => 'Короткая линия', 'price' => 200, 'rent' => 25],
        36 => ['type' => 'chance', 'name' => 'Шанс'],
        37 => ['type' => 'property', 'name' => 'Парк-плейс', 'price' => 350, 'rent' => 35],
        38 => ['type' => 'tax', 'name' => 'Налог на роскошь', 'tax' => 100],
        39 => ['type' => 'property', 'name' => 'Бродвей', 'price' => 400, 'rent' => 50],
    ];
    public function __construct(
        private readonly GameRepository $games,
        private readonly UserRepository $users
    ) {}

    public function createGame(array $actor, array $data): array
    {
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
        $game = $this->games->createGame($gameId, (int) $actor['sub'], $maxPlayers, $allowBots);

        $seat = $this->games->getNextSeatNo($gameId);
        $player = $this->games->createPlayer(
            $gameId,
            (int) $actor['sub'],
            $seat,
            (string) ($actor['nickname'] ?? ('user_' . (int) $actor['sub']))
        );
        $this->games->insertEvent($gameId, 'game_created', (int) $actor['sub'], (int) $player['id'], null, [
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
            'property_state' => $this->games->listPropertyStates($gameId),
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
            'message' => $message,
            'author_nickname' => (string) ($player['nickname_snapshot'] ?? ''),
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
        $selfPlayer = $this->games->findPlayerByGameAndUser($gameId, (int) $actor['sub']);
        if ($selfPlayer === null) {
            throw new \RuntimeException('Forbidden');
        }
        $allPlayers = $this->games->listPlayers($gameId);
        $player = $selfPlayer;
        $asPlayerId = (int) ($data['as_player_id'] ?? 0);
        if ($asPlayerId > 0) {
            foreach ($allPlayers as $p) {
                if ((int) $p['id'] === $asPlayerId) {
                    $player = $p;
                    break;
                }
            }
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
                $oldPos = (int) $player['position'];
                $inJail = $this->normalizeBool($player['in_jail'] ?? false);
                $jailTurns = (int) $player['jail_turns'];
                if ($inJail && $d1 !== $d2) {
                    $jailTurns++;
                    $payload = ['dice' => [$d1, $d2], 'position' => (int) $player['position'], 'from_position' => $oldPos, 'cash' => (int) $player['cash'], 'still_in_jail' => true];
                    $this->games->updatePlayerState((int) $player['id'], (int) $player['cash'], (int) $player['position'], true, $jailTurns);
                    break;
                }
                $move = $d1 + $d2;
                $newPos = (((int) $player['position']) + $move) % 40;
                $cash = (int) $player['cash'];
                if ((((int) $player['position']) + $move) >= 40) {
                    $cash += 200;
                }
                $jailNow = false;
                $jailTurns = 0;
                $landing = $this->resolveLanding($gameId, $player, $newPos, $cash);
                $newPos = $landing['position'];
                $cash = $landing['cash'];
                $jailNow = $landing['in_jail'];
                $jailTurns = $landing['jail_turns'];
                $this->games->updatePlayerState(
                    (int) $player['id'],
                    $cash,
                    $newPos,
                    $jailNow,
                    $jailTurns
                );
                $payload = array_merge(['dice' => [$d1, $d2], 'from_position' => $oldPos, 'position' => $newPos, 'cash' => $cash], $landing['payload']);
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
                $target = null;
                foreach ($allPlayers as $p) {
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
                $this->games->updatePlayerState(
                    (int) $player['id'],
                    $fromCash,
                    (int) $player['position'],
                    $this->normalizeBool($player['in_jail'] ?? false),
                    (int) $player['jail_turns']
                );
                $this->games->updatePlayerState(
                    (int) $target['id'],
                    $toCash,
                    (int) $target['position'],
                    $this->normalizeBool($target['in_jail'] ?? false),
                    (int) $target['jail_turns']
                );
                $payload = ['to_player_id' => $toPlayerId, 'amount' => $amount];
                break;
            case 'buy':
                $position = (int) $player['position'];
                $space = self::BOARD[$position] ?? ['type' => 'free', 'name' => ''];
                if (!in_array($space['type'], ['property', 'railroad', 'utility'], true)) {
                    throw new \InvalidArgumentException('На этой клетке нельзя купить собственность');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                if ($prop !== null && (int) ($prop['owner_player_id'] ?? 0) > 0) {
                    throw new \InvalidArgumentException('Клетка уже выкуплена');
                }
                $price = (int) ($space['price'] ?? 0);
                $cash = (int) $player['cash'];
                if ($cash < $price) {
                    throw new \InvalidArgumentException('Недостаточно средств для покупки');
                }
                $cash -= $price;
                $this->games->updatePlayerState(
                    (int) $player['id'],
                    $cash,
                    (int) $player['position'],
                    $this->normalizeBool($player['in_jail'] ?? false),
                    (int) $player['jail_turns']
                );
                $this->games->upsertPropertyOwner($gameId, $position, (int) $player['id']);
                $payload = ['buyer_player_id' => (int) $player['id'], 'position' => $position, 'price' => $price, 'cash' => $cash];
                break;
            case 'sell':
                $position = (int) $player['position'];
                $space = self::BOARD[$position] ?? ['type' => 'free', 'name' => ''];
                if (!in_array($space['type'], ['property', 'railroad', 'utility'], true)) {
                    throw new \InvalidArgumentException('На этой клетке нечего продавать');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                if ($prop === null || (int) ($prop['owner_player_id'] ?? 0) !== (int) $player['id']) {
                    throw new \InvalidArgumentException('Вы не владелец этой клетки');
                }
                $price = (int) floor(((int) ($space['price'] ?? 0)) / 2);
                $cash = (int) $player['cash'] + $price;
                $this->games->updatePlayerState(
                    (int) $player['id'],
                    $cash,
                    (int) $player['position'],
                    $this->normalizeBool($player['in_jail'] ?? false),
                    (int) $player['jail_turns']
                );
                $this->games->upsertPropertyOwner($gameId, $position, null);
                $payload = ['seller_player_id' => (int) $player['id'], 'position' => $position, 'price' => $price, 'cash' => $cash];
                break;
            case 'trade':
                $payload = ['initiator_player_id' => (int) $player['id']];
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

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        $raw = strtolower(trim((string) $value));
        return in_array($raw, ['1', 't', 'true', 'yes', 'y', 'on'], true);
    }

    /** @return array{position:int,cash:int,in_jail:bool,jail_turns:int,payload:array} */
    private function resolveLanding(string $gameId, array $player, int $position, int $cash): array
    {
        $space = self::BOARD[$position] ?? ['type' => 'free', 'name' => ''];
        $payload = ['space_type' => $space['type'], 'space_name' => $space['name']];
        $inJail = false;
        $jailTurns = 0;
        if (($space['type'] ?? '') === 'tax') {
            $tax = (int) ($space['tax'] ?? 0);
            $cash -= $tax;
            $payload['tax_paid'] = $tax;
        } elseif (($space['type'] ?? '') === 'go_to_jail') {
            $position = 10;
            $inJail = true;
            $payload['sent_to_jail'] = true;
        } elseif (in_array(($space['type'] ?? ''), ['property', 'railroad', 'utility'], true)) {
            $state = $this->games->findPropertyState($gameId, $position);
            $ownerPlayerId = (int) ($state['owner_player_id'] ?? 0);
            if ($ownerPlayerId <= 0) {
                $payload['offer_purchase'] = true;
                $payload['price'] = (int) ($space['price'] ?? 0);
            } elseif ($ownerPlayerId !== (int) $player['id']) {
                $rent = (int) ($space['rent'] ?? 0);
                $cash -= $rent;
                $all = $this->games->listPlayers($gameId);
                foreach ($all as $p) {
                    if ((int) $p['id'] !== $ownerPlayerId) {
                        continue;
                    }
                    $ownerCash = (int) $p['cash'] + $rent;
                    $this->games->updatePlayerState(
                        (int) $p['id'],
                        $ownerCash,
                        (int) $p['position'],
                        $this->normalizeBool($p['in_jail'] ?? false),
                        (int) $p['jail_turns']
                    );
                    break;
                }
                $payload['rent_paid'] = $rent;
                $payload['to_player_id'] = $ownerPlayerId;
            }
        } elseif (in_array(($space['type'] ?? ''), ['chance', 'chest'], true)) {
            $card = random_int(1, 4);
            if ($card === 1) {
                $cash += 50;
                $payload['card'] = 'Банк выплачивает дивиденд 50';
            } elseif ($card === 2) {
                $cash -= 50;
                $payload['card'] = 'Оплатите сбор 50';
            } elseif ($card === 3) {
                $position = 0;
                $cash += 200;
                $payload['card'] = 'Перейдите на старт и получите 200';
            } else {
                $position = 10;
                $inJail = true;
                $payload['card'] = 'Отправляйтесь в тюрьму';
            }
        }
        return ['position' => $position, 'cash' => $cash, 'in_jail' => $inJail, 'jail_turns' => $jailTurns, 'payload' => $payload];
    }
}
