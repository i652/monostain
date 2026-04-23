<?php
declare(strict_types=1);

namespace Stain\Services;

use Stain\Repositories\GameRepository;
use Stain\Repositories\UserRepository;

final class GameService
{
    private const BAIL_AMOUNT = 50;
    private const MAX_JAIL_TRIES = 3;
    /** @var array<int, array{type:string,name:string,price?:int,rent?:int,tax?:int,group?:int,building_cost?:int}> */
    private const BOARD = [
        0 => ['type' => 'go', 'name' => 'Старт'],
        1 => ['type' => 'property', 'name' => 'Средиземноморский пр.', 'price' => 60, 'rent' => 2, 'group' => 0, 'building_cost' => 50],
        2 => ['type' => 'chest', 'name' => 'Казна'],
        3 => ['type' => 'property', 'name' => 'Балтик-авеню', 'price' => 60, 'rent' => 4, 'group' => 0, 'building_cost' => 50],
        4 => ['type' => 'tax', 'name' => 'Подоходный налог', 'tax' => 200],
        5 => ['type' => 'railroad', 'name' => 'Читающая Ж/Д', 'price' => 200, 'rent' => 25],
        6 => ['type' => 'property', 'name' => 'Ориентал-авеню', 'price' => 100, 'rent' => 6, 'group' => 1, 'building_cost' => 50],
        7 => ['type' => 'chance', 'name' => 'Шанс'],
        8 => ['type' => 'property', 'name' => 'Вермонт-авеню', 'price' => 100, 'rent' => 6, 'group' => 1, 'building_cost' => 50],
        9 => ['type' => 'property', 'name' => 'Коннектикут-авеню', 'price' => 120, 'rent' => 8, 'group' => 1, 'building_cost' => 50],
        10 => ['type' => 'jail', 'name' => 'Тюрьма'],
        11 => ['type' => 'property', 'name' => 'Сент-Чарльз-плейс', 'price' => 140, 'rent' => 10, 'group' => 2, 'building_cost' => 100],
        12 => ['type' => 'utility', 'name' => 'Электрокомпания', 'price' => 150, 'rent' => 10],
        13 => ['type' => 'property', 'name' => 'Стейтс-авеню', 'price' => 140, 'rent' => 10, 'group' => 2, 'building_cost' => 100],
        14 => ['type' => 'property', 'name' => 'Вирджиния-авеню', 'price' => 160, 'rent' => 12, 'group' => 2, 'building_cost' => 100],
        15 => ['type' => 'railroad', 'name' => 'Пенсильвания Ж/Д', 'price' => 200, 'rent' => 25],
        16 => ['type' => 'property', 'name' => 'Сент-Джеймс-плейс', 'price' => 180, 'rent' => 14, 'group' => 3, 'building_cost' => 100],
        17 => ['type' => 'chest', 'name' => 'Казна'],
        18 => ['type' => 'property', 'name' => 'Теннесси-авеню', 'price' => 180, 'rent' => 14, 'group' => 3, 'building_cost' => 100],
        19 => ['type' => 'property', 'name' => 'Нью-Йорк-авеню', 'price' => 200, 'rent' => 16, 'group' => 3, 'building_cost' => 100],
        20 => ['type' => 'free', 'name' => 'Бесплатная парковка'],
        21 => ['type' => 'property', 'name' => 'Кентукки-авеню', 'price' => 220, 'rent' => 18, 'group' => 4, 'building_cost' => 150],
        22 => ['type' => 'property', 'name' => 'Индиана-авеню', 'price' => 220, 'rent' => 18, 'group' => 4, 'building_cost' => 150],
        23 => ['type' => 'chance', 'name' => 'Шанс'],
        24 => ['type' => 'property', 'name' => 'Иллинойс-авеню', 'price' => 240, 'rent' => 20, 'group' => 4, 'building_cost' => 150],
        25 => ['type' => 'railroad', 'name' => 'Ж/д B&O', 'price' => 200, 'rent' => 25],
        26 => ['type' => 'property', 'name' => 'Атлантик-авеню', 'price' => 260, 'rent' => 22, 'group' => 5, 'building_cost' => 150],
        27 => ['type' => 'property', 'name' => 'Вентнор-авеню', 'price' => 260, 'rent' => 22, 'group' => 5, 'building_cost' => 150],
        28 => ['type' => 'utility', 'name' => 'Водоканал', 'price' => 150, 'rent' => 10],
        29 => ['type' => 'property', 'name' => 'Мэрвин-гарденс', 'price' => 280, 'rent' => 24, 'group' => 5, 'building_cost' => 150],
        30 => ['type' => 'go_to_jail', 'name' => 'Идите в тюрьму'],
        31 => ['type' => 'property', 'name' => 'Пасифик-авеню', 'price' => 300, 'rent' => 26, 'group' => 6, 'building_cost' => 200],
        32 => ['type' => 'property', 'name' => 'Сев. Каролина-авеню', 'price' => 300, 'rent' => 26, 'group' => 6, 'building_cost' => 200],
        33 => ['type' => 'chest', 'name' => 'Казна'],
        34 => ['type' => 'property', 'name' => 'Пенсильвания-авеню', 'price' => 320, 'rent' => 28, 'group' => 6, 'building_cost' => 200],
        35 => ['type' => 'railroad', 'name' => 'Короткая линия', 'price' => 200, 'rent' => 25],
        36 => ['type' => 'chance', 'name' => 'Шанс'],
        37 => ['type' => 'property', 'name' => 'Парк-плейс', 'price' => 350, 'rent' => 35, 'group' => 7, 'building_cost' => 200],
        38 => ['type' => 'tax', 'name' => 'Налог на роскошь', 'tax' => 100],
        39 => ['type' => 'property', 'name' => 'Бродвей', 'price' => 400, 'rent' => 50, 'group' => 7, 'building_cost' => 200],
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
                $cash = (int) $player['cash'];
                $newPos = $oldPos;
                if ($inJail && $d1 !== $d2) {
                    $jailTurns++;
                    if ($jailTurns >= self::MAX_JAIL_TRIES) {
                        $cash -= self::BAIL_AMOUNT;
                        $inJail = false;
                        $jailTurns = 0;
                        $move = $d1 + $d2;
                        $newPos = ($oldPos + $move) % 40;
                        if (($oldPos + $move) >= 40) {
                            $cash += 200;
                        }
                        $landing = $this->resolveLanding($gameId, $player, $newPos, $cash);
                        $newPos = $landing['position'];
                        $cash = $landing['cash'];
                        $inJail = $landing['in_jail'];
                        $jailTurns = $landing['jail_turns'];
                        $payload = array_merge([
                            'dice' => [$d1, $d2],
                            'from_position' => $oldPos,
                            'position' => $newPos,
                            'cash' => $cash,
                            'bail_paid' => self::BAIL_AMOUNT,
                            'forced_bail' => true,
                        ], $landing['payload']);
                        $this->games->updatePlayerState((int) $player['id'], $cash, $newPos, $inJail, $jailTurns);
                        break;
                    }
                    $payload = [
                        'dice' => [$d1, $d2],
                        'position' => $oldPos,
                        'from_position' => $oldPos,
                        'cash' => $cash,
                        'still_in_jail' => true,
                        'jail_tries_left' => self::MAX_JAIL_TRIES - $jailTurns,
                    ];
                    $this->games->updatePlayerState((int) $player['id'], $cash, $oldPos, true, $jailTurns);
                    break;
                }
                if ($inJail && $d1 === $d2) {
                    $inJail = false;
                    $jailTurns = 0;
                    $payload['jail_freed_by_doubles'] = true;
                }
                $move = $d1 + $d2;
                $newPos = ($oldPos + $move) % 40;
                if (($oldPos + $move) >= 40) {
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
                $payload = [
                    'to_player_id' => $toPlayerId,
                    'amount' => $amount,
                    'from_player_id' => (int) $player['id'],
                    'from_cash' => $fromCash,
                    'to_cash' => $toCash,
                ];
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
            case 'pay_rent':
                $position = (int) $player['position'];
                $space = self::BOARD[$position] ?? ['type' => 'free', 'name' => ''];
                if (!in_array($space['type'], ['property', 'railroad', 'utility'], true)) {
                    throw new \InvalidArgumentException('На этой клетке нет ренты');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                $ownerPlayerId = (int) ($prop['owner_player_id'] ?? 0);
                if ($ownerPlayerId <= 0 || $ownerPlayerId === (int) $player['id']) {
                    throw new \InvalidArgumentException('Рента не требуется');
                }
                $owner = null;
                foreach ($allPlayers as $p) {
                    if ((int) $p['id'] === $ownerPlayerId) {
                        $owner = $p;
                        break;
                    }
                }
                if ($owner === null) {
                    throw new \InvalidArgumentException('Владелец клетки не найден');
                }
                $rent = $this->calculateRent($space, $prop);
                $payerCash = (int) $player['cash'] - $rent;
                $ownerCash = (int) $owner['cash'] + $rent;
                $this->games->updatePlayerState((int) $player['id'], $payerCash, (int) $player['position'], $this->normalizeBool($player['in_jail'] ?? false), (int) $player['jail_turns']);
                $this->games->updatePlayerState((int) $owner['id'], $ownerCash, (int) $owner['position'], $this->normalizeBool($owner['in_jail'] ?? false), (int) $owner['jail_turns']);
                $payload = [
                    'position' => $position,
                    'to_player_id' => $ownerPlayerId,
                    'rent_paid' => $rent,
                    'cash' => $payerCash,
                    'owner_cash' => $ownerCash,
                ];
                break;
            case 'buyout':
                $position = (int) $player['position'];
                $space = self::BOARD[$position] ?? ['type' => 'free', 'name' => ''];
                if (!in_array($space['type'], ['property', 'railroad', 'utility'], true)) {
                    throw new \InvalidArgumentException('Эту клетку нельзя выкупить');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                $ownerPlayerId = (int) ($prop['owner_player_id'] ?? 0);
                if ($ownerPlayerId <= 0 || $ownerPlayerId === (int) $player['id']) {
                    throw new \InvalidArgumentException('Эта клетка не принадлежит другому игроку');
                }
                $owner = null;
                foreach ($allPlayers as $p) {
                    if ((int) $p['id'] === $ownerPlayerId) {
                        $owner = $p;
                        break;
                    }
                }
                if ($owner === null) {
                    throw new \InvalidArgumentException('Владелец клетки не найден');
                }
                $offered = (int) ($data['offer_amount'] ?? 0);
                $approvedByOwner = $this->normalizeBool($data['approved_by_owner'] ?? false);
                $buildingCost = (int) ($space['building_cost'] ?? 0);
                $houses = (int) ($prop['houses'] ?? 0);
                $hasHotel = $this->normalizeBool($prop['has_hotel'] ?? false);
                $buildingValue = $buildingCost * ($houses + ($hasHotel ? 5 : 0));
                $minOffer = (((int) ($space['price'] ?? 0)) + $buildingValue) * 2;
                if ($offered < $minOffer) {
                    throw new \InvalidArgumentException('Сумма выкупа меньше допустимого минимума');
                }
                if ((int) $player['cash'] < $offered) {
                    throw new \InvalidArgumentException('Недостаточно средств для выкупа');
                }
                if (!$approvedByOwner) {
                    $payload = ['position' => $position, 'offer_amount' => $offered, 'seller_player_id' => $ownerPlayerId, 'buyer_player_id' => (int) $player['id'], 'buyout_declined' => true];
                    break;
                }
                $buyerCash = (int) $player['cash'] - $offered;
                $ownerCash = (int) $owner['cash'] + $offered;
                $this->games->updatePlayerState((int) $player['id'], $buyerCash, (int) $player['position'], $this->normalizeBool($player['in_jail'] ?? false), (int) $player['jail_turns']);
                $this->games->updatePlayerState((int) $owner['id'], $ownerCash, (int) $owner['position'], $this->normalizeBool($owner['in_jail'] ?? false), (int) $owner['jail_turns']);
                $this->games->upsertPropertyOwner($gameId, $position, (int) $player['id']);
                $payload = [
                    'position' => $position,
                    'offer_amount' => $offered,
                    'seller_player_id' => $ownerPlayerId,
                    'buyer_player_id' => (int) $player['id'],
                    'cash' => $buyerCash,
                    'seller_cash' => $ownerCash,
                    'buyout_approved' => true,
                ];
                break;
            case 'build':
                $position = (int) ($data['position'] ?? $player['position']);
                $buildType = (string) ($data['build_type'] ?? 'house');
                $space = self::BOARD[$position] ?? ['type' => 'free', 'name' => ''];
                if (($space['type'] ?? '') !== 'property') {
                    throw new \InvalidArgumentException('Строительство доступно только на улицах');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                if ($prop === null || (int) ($prop['owner_player_id'] ?? 0) !== (int) $player['id']) {
                    throw new \InvalidArgumentException('Вы не владелец этой клетки');
                }
                if (!$this->ownsWholeGroup($gameId, (int) $player['id'], (int) ($space['group'] ?? -1))) {
                    throw new \InvalidArgumentException('Строительство возможно только при полном наборе группы');
                }
                $houses = (int) ($prop['houses'] ?? 0);
                $hasHotel = $this->normalizeBool($prop['has_hotel'] ?? false);
                $cost = (int) ($space['building_cost'] ?? 0);
                $cash = (int) $player['cash'];
                if ($cash < $cost) {
                    throw new \InvalidArgumentException('Недостаточно средств для строительства');
                }
                if ($buildType === 'house') {
                    if ($hasHotel || $houses >= 4) {
                        throw new \InvalidArgumentException('Нельзя добавить дом');
                    }
                    $houses++;
                } else {
                    if ($hasHotel || $houses < 4) {
                        throw new \InvalidArgumentException('Отель строится только после 4 домов');
                    }
                    $hasHotel = true;
                }
                $cash -= $cost;
                $this->games->updatePropertyBuildings($gameId, $position, $houses, $hasHotel);
                $this->games->updatePlayerState((int) $player['id'], $cash, (int) $player['position'], $this->normalizeBool($player['in_jail'] ?? false), (int) $player['jail_turns']);
                $payload = ['position' => $position, 'build_type' => $buildType, 'houses' => $houses, 'has_hotel' => $hasHotel, 'build_cost' => $cost, 'cash' => $cash];
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
            case 'pay_bail':
                if (!$this->normalizeBool($player['in_jail'] ?? false)) {
                    throw new \InvalidArgumentException('Игрок не находится в тюрьме');
                }
                $cash = (int) $player['cash'] - self::BAIL_AMOUNT;
                $this->games->updatePlayerState(
                    (int) $player['id'],
                    $cash,
                    (int) $player['position'],
                    false,
                    0
                );
                $payload = [
                    'bail_paid' => self::BAIL_AMOUNT,
                    'position' => (int) $player['position'],
                    'cash' => $cash,
                    'can_roll' => true,
                ];
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
    private function resolveLanding(string $gameId, array $player, int $position, int $cash, int $depth = 0): array
    {
        if ($depth > 3) {
            return ['position' => $position, 'cash' => $cash, 'in_jail' => false, 'jail_turns' => 0, 'payload' => []];
        }
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
                $rent = $this->calculateRent($space, $state);
                $buildingCost = (int) ($space['building_cost'] ?? 0);
                $houses = (int) ($state['houses'] ?? 0);
                $hasHotel = $this->normalizeBool($state['has_hotel'] ?? false);
                $minBuyout = (((int) ($space['price'] ?? 0)) + ($buildingCost * ($houses + ($hasHotel ? 5 : 0)))) * 2;
                $payload['rent_due'] = $rent;
                $payload['to_player_id'] = $ownerPlayerId;
                $payload['buyout_min'] = $minBuyout;
                $payload['owner_player_id'] = $ownerPlayerId;
            } elseif (($space['type'] ?? '') === 'property') {
                $state = $this->games->findPropertyState($gameId, $position);
                $ownsGroup = $this->ownsWholeGroup($gameId, (int) $player['id'], (int) ($space['group'] ?? -1));
                $houses = (int) ($state['houses'] ?? 0);
                $hasHotel = $this->normalizeBool($state['has_hotel'] ?? false);
                $payload['can_build_house'] = $ownsGroup && !$hasHotel && $houses < 4;
                $payload['can_build_hotel'] = $ownsGroup && !$hasHotel && $houses >= 4;
                $payload['building_cost'] = (int) ($space['building_cost'] ?? 0);
            }
        } elseif (in_array(($space['type'] ?? ''), ['chance', 'chest'], true)) {
            $cardResult = $this->applyDeckCard($gameId, $player, (string) $space['type'], $position, $cash);
            $position = $cardResult['position'];
            $cash = $cardResult['cash'];
            $inJail = $cardResult['in_jail'];
            $jailTurns = $cardResult['jail_turns'];
            $payload['card'] = $cardResult['card'];
            if ($position !== (int) ($cardResult['source_position'] ?? $position) && !$inJail) {
                $next = $this->resolveLanding($gameId, $player, $position, $cash, $depth + 1);
                $position = $next['position'];
                $cash = $next['cash'];
                $inJail = $next['in_jail'];
                $jailTurns = $next['jail_turns'];
                $payload = array_merge($payload, $next['payload']);
            }
            foreach ($cardResult['payload'] as $k => $v) {
                $payload[$k] = $v;
            }
        }
        return ['position' => $position, 'cash' => $cash, 'in_jail' => $inJail, 'jail_turns' => $jailTurns, 'payload' => $payload];
    }

    /** @return array{position:int,cash:int,in_jail:bool,jail_turns:int,card:string,source_position:int,payload:array} */
    private function applyDeckCard(string $gameId, array $player, string $deckType, int $position, int $cash): array
    {
        $allPlayers = $this->games->listPlayers($gameId);
        $card = '';
        $payload = [];
        $inJail = false;
        $jailTurns = 0;
        $sourcePosition = $position;
        if ($deckType === 'chance') {
            $cards = [
                ['label' => 'Перейдите на Бродвей', 'type' => 'move_to', 'to' => 39],
                ['label' => 'Перейдите на старт (получите 200)', 'type' => 'move_to', 'to' => 0],
                ['label' => 'Перейдите на Иллинойс-авеню', 'type' => 'move_to', 'to' => 24],
                ['label' => 'Перейдите на Сент-Чарльз-плейс', 'type' => 'move_to', 'to' => 11],
                ['label' => 'Ближайшая железная дорога', 'type' => 'nearest_railroad'],
                ['label' => 'Ближайшая коммунальная служба', 'type' => 'nearest_utility'],
                ['label' => 'Банк выплатил дивиденд 50', 'type' => 'money', 'amount' => 50],
                ['label' => 'Вернитесь на 3 клетки назад', 'type' => 'move_by', 'delta' => -3],
                ['label' => 'Штраф за превышение скорости 15', 'type' => 'money', 'amount' => -15],
                ['label' => 'Поездка до Читающей Ж/Д', 'type' => 'move_to', 'to' => 5],
                ['label' => 'Кредит на строительство 150', 'type' => 'money', 'amount' => 150],
                ['label' => 'Идите в тюрьму', 'type' => 'go_jail'],
            ];
        } else {
            $cards = [
                ['label' => 'Перейдите на старт (получите 200)', 'type' => 'move_to', 'to' => 0],
                ['label' => 'Ошибка банка в вашу пользу. Получите 200', 'type' => 'money', 'amount' => 200],
                ['label' => 'Оплатите услуги врача 50', 'type' => 'money', 'amount' => -50],
                ['label' => 'Продажа акций: получите 50', 'type' => 'money', 'amount' => 50],
                ['label' => 'Фонд отпуска созрел: получите 100', 'type' => 'money', 'amount' => 100],
                ['label' => 'Возврат подоходного налога: получите 20', 'type' => 'money', 'amount' => 20],
                ['label' => 'Вам начислено наследство 100', 'type' => 'money', 'amount' => 100],
                ['label' => 'Праздничный взнос: получите по 10 с каждого', 'type' => 'collect_from_players', 'amount' => 10],
                ['label' => 'Оплатите больничный сбор 100', 'type' => 'money', 'amount' => -100],
                ['label' => 'Оплатите школьный сбор 50', 'type' => 'money', 'amount' => -50],
                ['label' => 'Идите в тюрьму', 'type' => 'go_jail'],
            ];
        }
        $drawn = $cards[array_rand($cards)];
        $card = (string) ($drawn['label'] ?? '');
        $type = (string) ($drawn['type'] ?? 'money');
        if ($type === 'money') {
            $cash += (int) ($drawn['amount'] ?? 0);
        } elseif ($type === 'move_to') {
            $to = (int) ($drawn['to'] ?? $position);
            if ($to < $position) {
                $cash += 200;
            }
            $position = $to;
            $payload['moved_by_card'] = true;
        } elseif ($type === 'move_by') {
            $delta = (int) ($drawn['delta'] ?? 0);
            $position = ($position + $delta + 40) % 40;
            $payload['moved_by_card'] = true;
        } elseif ($type === 'nearest_railroad') {
            $targets = [5, 15, 25, 35];
            foreach ($targets as $target) {
                if ($target > $position) {
                    $position = $target;
                    $payload['moved_by_card'] = true;
                    break;
                }
            }
            if (!isset($payload['moved_by_card'])) {
                $position = 5;
                $cash += 200;
                $payload['moved_by_card'] = true;
            }
        } elseif ($type === 'nearest_utility') {
            $targets = [12, 28];
            $position = $position < 12 ? 12 : 28;
            if ($sourcePosition >= 28) {
                $position = 12;
                $cash += 200;
            }
            $payload['moved_by_card'] = true;
        } elseif ($type === 'collect_from_players') {
            $amount = (int) ($drawn['amount'] ?? 0);
            foreach ($allPlayers as $p) {
                if ((int) $p['id'] === (int) $player['id']) {
                    continue;
                }
                $otherCash = (int) $p['cash'] - $amount;
                $this->games->updatePlayerState(
                    (int) $p['id'],
                    $otherCash,
                    (int) $p['position'],
                    $this->normalizeBool($p['in_jail'] ?? false),
                    (int) $p['jail_turns']
                );
                $cash += $amount;
            }
            $payload['money_from_players'] = true;
        } elseif ($type === 'go_jail') {
            $position = 10;
            $inJail = true;
            $jailTurns = 0;
            $payload['sent_to_jail'] = true;
        }
        return [
            'position' => $position,
            'cash' => $cash,
            'in_jail' => $inJail,
            'jail_turns' => $jailTurns,
            'card' => $card,
            'source_position' => $sourcePosition,
            'payload' => $payload,
        ];
    }

    private function calculateRent(array $space, ?array $propState): int
    {
        $base = (int) ($space['rent'] ?? 0);
        if (($space['type'] ?? '') !== 'property') {
            return $base;
        }
        $houses = (int) (($propState['houses'] ?? 0));
        $hasHotel = $this->normalizeBool($propState['has_hotel'] ?? false);
        if ($hasHotel) {
            return $base * 25;
        }
        if ($houses > 0) {
            return $base * (1 + ($houses * 2));
        }
        return $base;
    }

    private function ownsWholeGroup(string $gameId, int $playerId, int $groupId): bool
    {
        if ($groupId < 0) {
            return false;
        }
        $groupPositions = [];
        foreach (self::BOARD as $pos => $space) {
            if (($space['type'] ?? '') === 'property' && (int) ($space['group'] ?? -1) === $groupId) {
                $groupPositions[] = $pos;
            }
        }
        if ($groupPositions === []) {
            return false;
        }
        foreach ($groupPositions as $pos) {
            $state = $this->games->findPropertyState($gameId, $pos);
            if ((int) ($state['owner_player_id'] ?? 0) !== $playerId) {
                return false;
            }
        }
        return true;
    }
}
