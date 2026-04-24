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
        $boardTemplateId = null;
        if (isset($data['board_template_id']) && (int) $data['board_template_id'] > 0) {
            $boardTemplateId = (int) $data['board_template_id'];
        } else {
            $classic = $this->games->findBoardTemplateByName('Классическая Монополия');
            if ($classic !== null) {
                $boardTemplateId = (int) ($classic['id'] ?? 0);
            }
        }
        $gameId = $this->newUuidV4();
        $game = $this->games->createGame($gameId, (int) $actor['sub'], $maxPlayers, $allowBots, $boardTemplateId > 0 ? $boardTemplateId : null);

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

        $boardCells = [];
        $templateId = (int) ($game['board_template_id'] ?? 0);
        if ($templateId > 0) {
            $tpl = $this->games->findBoardTemplateById($templateId);
            if ($tpl !== null) {
                $game['board_template_name'] = (string) ($tpl['name'] ?? '');
            }
        }
        if ($templateId > 0) {
            $boardCells = $this->games->listBoardCells($templateId);
        }
        return [
            'game' => $game,
            'self' => $player,
            'players' => $this->games->listPlayers($gameId),
            'board_cells' => $boardCells,
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
                if ($inJail && $jailTurns >= self::MAX_JAIL_TRIES) {
                    // После 3 неудачных попыток игрок освобождается бесплатно в следующий свой ход.
                    $inJail = false;
                    $jailTurns = 0;
                    $payload['jail_released_free'] = true;
                }
                if ($inJail && $d1 !== $d2) {
                    $jailTurns++;
                    if ($jailTurns >= self::MAX_JAIL_TRIES) {
                        $payload = [
                            'dice' => [$d1, $d2],
                            'position' => $oldPos,
                            'from_position' => $oldPos,
                            'cash' => $cash,
                            'still_in_jail' => true,
                            'jail_tries_left' => 0,
                        ];
                        $this->games->updatePlayerState((int) $player['id'], $cash, $oldPos, true, self::MAX_JAIL_TRIES);
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
                $advance = $this->advancePositionByRoute($gameId, $oldPos, $move);
                $newPos = (int) ($advance['position'] ?? $oldPos);
                if (!empty($advance['passed_start'])) {
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
                $turnOptions = $this->resolveTurnOptionsForTemplate($gameId, $oldPos, $newPos);
                if ($turnOptions !== []) {
                    $payload['turn_options'] = $turnOptions;
                }
                break;
            case 'end_turn':
                $payload = ['ended_by' => (int) $player['id']];
                break;
            case 'choose_direction':
                $direction = (string) ($data['direction'] ?? '');
                if (!in_array($direction, ['left', 'straight', 'right'], true)) {
                    throw new \InvalidArgumentException('Некорректное направление');
                }
                $payload = ['direction' => $direction];
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
                $space = $this->spaceByPosition($gameId, $position);
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
                $space = $this->spaceByPosition($gameId, $position);
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
                $space = $this->spaceByPosition($gameId, $position);
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
                $space = $this->spaceByPosition($gameId, $position);
                if (($space['type'] ?? '') !== 'property') {
                    throw new \InvalidArgumentException('Строительство доступно только на улицах');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                if ($prop === null || (int) ($prop['owner_player_id'] ?? 0) !== (int) $player['id']) {
                    throw new \InvalidArgumentException('Вы не владелец этой клетки');
                }
                if ($this->normalizeBool($prop['mortgaged'] ?? false)) {
                    throw new \InvalidArgumentException('Нельзя строить на заложенной клетке');
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
            case 'sell_building':
                $position = (int) ($data['position'] ?? $player['position']);
                $space = $this->spaceByPosition($gameId, $position);
                if (($space['type'] ?? '') !== 'property') {
                    throw new \InvalidArgumentException('Продажа построек доступна только на улицах');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                if ($prop === null || (int) ($prop['owner_player_id'] ?? 0) !== (int) $player['id']) {
                    throw new \InvalidArgumentException('Вы не владелец этой клетки');
                }
                $houses = (int) ($prop['houses'] ?? 0);
                $hasHotel = $this->normalizeBool($prop['has_hotel'] ?? false);
                if (!$hasHotel && $houses <= 0) {
                    throw new \InvalidArgumentException('На клетке нет построек для продажи');
                }
                $buildCost = (int) ($space['building_cost'] ?? 0);
                $refund = (int) floor($buildCost / 2);
                $sellType = 'house';
                if ($hasHotel) {
                    $hasHotel = false;
                    $houses = 4;
                    $sellType = 'hotel';
                } else {
                    $houses--;
                }
                $cash = (int) $player['cash'] + $refund;
                $this->games->updatePropertyBuildings($gameId, $position, $houses, $hasHotel);
                $this->games->updatePlayerState((int) $player['id'], $cash, (int) $player['position'], $this->normalizeBool($player['in_jail'] ?? false), (int) $player['jail_turns']);
                $payload = ['position' => $position, 'sell_type' => $sellType, 'houses' => $houses, 'has_hotel' => $hasHotel, 'refund' => $refund, 'cash' => $cash];
                break;
            case 'sell':
                $position = (int) $player['position'];
                $space = $this->spaceByPosition($gameId, $position);
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
                $targetPlayerId = (int) ($data['target_player_id'] ?? 0);
                $offerPositions = $this->normalizeIntArray($data['offer_positions'] ?? []);
                $requestPositions = $this->normalizeIntArray($data['request_positions'] ?? []);
                $cashOffer = max(0, (int) ($data['cash_offer'] ?? 0));
                $approvedByTarget = $this->normalizeBool($data['approved_by_target'] ?? false);
                if ($targetPlayerId <= 0 || $targetPlayerId === (int) $player['id']) {
                    throw new \InvalidArgumentException('Укажите другого игрока для обмена');
                }
                if ($offerPositions === [] || $requestPositions === []) {
                    throw new \InvalidArgumentException('Выберите карточки для обмена с обеих сторон');
                }
                $target = null;
                foreach ($allPlayers as $p) {
                    if ((int) $p['id'] === $targetPlayerId) {
                        $target = $p;
                        break;
                    }
                }
                if ($target === null) {
                    throw new \InvalidArgumentException('Целевой игрок не найден');
                }
                $offerValue = 0;
                $requestValue = 0;
                foreach ($offerPositions as $pos) {
                    $state = $this->games->findPropertyState($gameId, $pos);
                    if ((int) ($state['owner_player_id'] ?? 0) !== (int) $player['id']) {
                        throw new \InvalidArgumentException('В списке обмена есть чужая карточка');
                    }
                    $offerValue += $this->propertyValue($gameId, $pos, $state);
                }
                foreach ($requestPositions as $pos) {
                    $state = $this->games->findPropertyState($gameId, $pos);
                    if ((int) ($state['owner_player_id'] ?? 0) !== $targetPlayerId) {
                        throw new \InvalidArgumentException('Запрошена карточка, не принадлежащая выбранному игроку');
                    }
                    $requestValue += $this->propertyValue($gameId, $pos, $state);
                }
                $totalOfferValue = $offerValue + $cashOffer;
                if ($totalOfferValue < $requestValue) {
                    throw new \InvalidArgumentException('Предложение обмена неравнозначно');
                }
                if ((int) $player['cash'] < $cashOffer) {
                    throw new \InvalidArgumentException('Недостаточно средств для денежной доплаты');
                }
                if (!$approvedByTarget) {
                    $payload = [
                        'initiator_player_id' => (int) $player['id'],
                        'target_player_id' => $targetPlayerId,
                        'offer_positions' => $offerPositions,
                        'request_positions' => $requestPositions,
                        'cash_offer' => $cashOffer,
                        'trade_declined' => true,
                    ];
                    break;
                }
                foreach ($offerPositions as $pos) {
                    $this->games->transferProperty($gameId, $pos, $targetPlayerId);
                }
                foreach ($requestPositions as $pos) {
                    $this->games->transferProperty($gameId, $pos, (int) $player['id']);
                }
                $initiatorCash = (int) $player['cash'] - $cashOffer;
                $targetCash = (int) $target['cash'] + $cashOffer;
                $this->games->updatePlayerState((int) $player['id'], $initiatorCash, (int) $player['position'], $this->normalizeBool($player['in_jail'] ?? false), (int) $player['jail_turns']);
                $this->games->updatePlayerState((int) $target['id'], $targetCash, (int) $target['position'], $this->normalizeBool($target['in_jail'] ?? false), (int) $target['jail_turns']);
                $payload = [
                    'initiator_player_id' => (int) $player['id'],
                    'target_player_id' => $targetPlayerId,
                    'offer_positions' => $offerPositions,
                    'request_positions' => $requestPositions,
                    'cash_offer' => $cashOffer,
                    'initiator_cash' => $initiatorCash,
                    'target_cash' => $targetCash,
                    'trade_approved' => true,
                ];
                break;
            case 'mortgage':
                $position = (int) ($data['position'] ?? $player['position']);
                $space = $this->spaceByPosition($gameId, $position);
                if (!in_array(($space['type'] ?? ''), ['property', 'railroad', 'utility'], true)) {
                    throw new \InvalidArgumentException('Эту клетку нельзя заложить');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                if ($prop === null || (int) ($prop['owner_player_id'] ?? 0) !== (int) $player['id']) {
                    throw new \InvalidArgumentException('Вы не владелец этой клетки');
                }
                if ($this->normalizeBool($prop['mortgaged'] ?? false)) {
                    throw new \InvalidArgumentException('Клетка уже в залоге');
                }
                $credit = (int) floor($this->propertyValue($gameId, $position, $prop) / 2);
                $cash = (int) $player['cash'] + $credit;
                $this->games->updatePropertyMortgage($gameId, $position, true);
                $this->games->updatePlayerState((int) $player['id'], $cash, (int) $player['position'], $this->normalizeBool($player['in_jail'] ?? false), (int) $player['jail_turns']);
                $payload = ['position' => $position, 'mortgaged' => true, 'credit' => $credit, 'cash' => $cash];
                break;
            case 'redeem_mortgage':
                $position = (int) ($data['position'] ?? $player['position']);
                $space = $this->spaceByPosition($gameId, $position);
                if (!in_array(($space['type'] ?? ''), ['property', 'railroad', 'utility'], true)) {
                    throw new \InvalidArgumentException('Эту клетку нельзя выкупить из залога');
                }
                $prop = $this->games->findPropertyState($gameId, $position);
                if ($prop === null || (int) ($prop['owner_player_id'] ?? 0) !== (int) $player['id']) {
                    throw new \InvalidArgumentException('Вы не владелец этой клетки');
                }
                if (!$this->normalizeBool($prop['mortgaged'] ?? false)) {
                    throw new \InvalidArgumentException('Клетка не находится в залоге');
                }
                $redeemCost = $this->redeemMortgageCost($gameId, (int) $player['id'], $position, $prop);
                if ((int) $player['cash'] < $redeemCost) {
                    throw new \InvalidArgumentException('Недостаточно средств для выкупа из залога');
                }
                $cash = (int) $player['cash'] - $redeemCost;
                $this->games->updatePropertyMortgage($gameId, $position, false);
                $this->games->updatePlayerState((int) $player['id'], $cash, (int) $player['position'], $this->normalizeBool($player['in_jail'] ?? false), (int) $player['jail_turns']);
                $payload = ['position' => $position, 'mortgaged' => false, 'redeem_cost' => $redeemCost, 'cash' => $cash];
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

    public function listBoardTemplatesForNewGame(array $actor): array
    {
        if (!isset($actor['sub'])) {
            throw new \RuntimeException('Unauthorized');
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

    public function getBoardTemplateEditorView(array $actor, ?int $templateId): array
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $template = null;
        $cells = [];
        if ($templateId !== null) {
            $template = $this->games->findBoardTemplateById($templateId);
            if ($template === null) {
                throw new \RuntimeException('Not found');
            }
            $cells = $this->games->listBoardCells($templateId);
        }
        return [
            'template' => $template,
            'cells' => $cells,
            'catalog' => $this->boardCardCatalog(),
        ];
    }

    public function saveBoardTemplateEditor(array $actor, array $data, ?int $templateId): array
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Укажите название доски');
        }
        $published = $this->normalizeBool($data['is_published'] ?? false);
        $cellsRaw = $data['cells'] ?? [];
        if (is_string($cellsRaw)) {
            $decoded = json_decode($cellsRaw, true);
            $cellsRaw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($cellsRaw)) {
            throw new \InvalidArgumentException('Некорректные данные клеток');
        }
        $cells = [];
        foreach ($cellsRaw as $row) {
            if (!is_array($row)) continue;
            $extra = $row['extra_json'] ?? [];
            if (is_string($extra)) {
                $decoded = json_decode($extra, true);
                $extra = is_array($decoded) ? $decoded : [];
            }
            $positionRaw = (int) ($row['position'] ?? -1);
            $x = (int) (($row['x'] ?? $extra['x'] ?? -1));
            $y = (int) (($row['y'] ?? $extra['y'] ?? -1));
            if (($x < 0 || $x > 10 || $y < 0 || $y > 10) && $positionRaw >= 0 && $positionRaw <= 120) {
                $x = $positionRaw % 11;
                $y = intdiv($positionRaw, 11);
            }
            if ($x < 0 || $x > 10 || $y < 0 || $y > 10) continue;
            $cellType = trim((string) ($row['cell_type'] ?? 'property'));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') continue;
            $buyPrice = max(0, (int) ($row['buy_price'] ?? 0));
            $orientation = trim((string) ($row['orientation'] ?? $extra['orientation'] ?? ''));
            if (!in_array($orientation, ['north', 'south', 'west', 'east'], true)) {
                $orientation = '';
            }
            $position = $positionRaw >= 0 && $positionRaw <= 120 ? $positionRaw : (($y * 11) + $x);
            $cells[] = [
                'position' => $position,
                'cell_type' => $cellType,
                'title' => $title,
                'buy_price' => $buyPrice,
                'rent_rules' => [],
                'extra_json' => ['x' => $x, 'y' => $y, 'group' => $row['group'] ?? $extra['group'] ?? null, 'orientation' => $orientation],
            ];
        }
        $this->validateBoardPath($cells);
        if ($templateId === null) {
            $created = $this->games->createBoardTemplate((int) $actor['sub'], $name, $published);
            $templateId = (int) ($created['id'] ?? 0);
            if ($templateId <= 0) {
                throw new \RuntimeException('Не удалось создать карту');
            }
            $template = $created;
        } else {
            $template = $this->games->findBoardTemplateById($templateId);
            if ($template === null) {
                throw new \RuntimeException('Not found');
            }
        }
        $this->games->replaceBoardCells($templateId, $cells);
        return ['id' => $templateId, 'name' => $name];
    }

    /** @param array<int,array{position:int,cell_type:string,title:string,buy_price:int,rent_rules:array,extra_json:array}> $cells */
    private function validateBoardPath(array $cells): void
    {
        if (count($cells) < 4) {
            throw new \InvalidArgumentException('На карте должно быть минимум 4 клетки');
        }
        $points = [];
        foreach ($cells as $cell) {
            $x = (int) (($cell['extra_json']['x'] ?? -1));
            $y = (int) (($cell['extra_json']['y'] ?? -1));
            if (($x < 0 || $y < 0) && isset($cell['position'])) {
                $pos = (int) $cell['position'];
                if ($pos >= 0 && $pos <= 120) {
                    $x = $pos % 11;
                    $y = intdiv($pos, 11);
                }
            }
            $points["{$x}:{$y}"] = true;
        }
        $neighbors = [];
        foreach (array_keys($points) as $key) {
            [$x, $y] = array_map('intval', explode(':', $key));
            $adj = [
                ($x - 1) . ':' . $y,
                ($x + 1) . ':' . $y,
                $x . ':' . ($y - 1),
                $x . ':' . ($y + 1),
            ];
            $deg = 0;
            foreach ($adj as $k) {
                if (isset($points[$k])) $deg++;
            }
            if ($deg < 2) {
                throw new \InvalidArgumentException('Маршрут не должен иметь тупиков (каждая клетка минимум с двумя соседями)');
            }
            $neighbors[$key] = $adj;
        }
        $seen = [];
        $stack = [array_key_first($points)];
        while ($stack !== []) {
            $k = array_pop($stack);
            if ($k === null || isset($seen[$k])) continue;
            $seen[$k] = true;
            foreach ($neighbors[$k] ?? [] as $n) {
                if (isset($points[$n]) && !isset($seen[$n])) $stack[] = $n;
            }
        }
        if (count($seen) !== count($points)) {
            throw new \InvalidArgumentException('Все клетки маршрута должны быть связаны');
        }
    }

    private function boardCardCatalog(): array
    {
        $catalog = [];
        foreach (self::BOARD as $pos => $space) {
            $catalog[] = [
                'id' => 'classic_' . $pos,
                'title' => (string) ($space['name'] ?? ('#' . $pos)),
                'cell_type' => (string) ($space['type'] ?? 'property'),
                'buy_price' => (int) ($space['price'] ?? 0),
                'group' => $space['group'] ?? null,
                'unlimited' => in_array(($space['type'] ?? ''), ['chance', 'chest', 'free', 'tax'], true),
            ];
        }
        return $catalog;
    }

    /** @return array<int,string> */
    private function resolveTurnOptionsForTemplate(string $gameId, int $fromPos, int $toPos): array
    {
        $game = $this->games->findGameById($gameId);
        $templateId = (int) ($game['board_template_id'] ?? 0);
        if ($templateId <= 0) return [];
        $cells = $this->games->listBoardCells($templateId);
        if ($cells === []) return [];
        $coords = [];
        foreach ($cells as $cell) {
            $p = (int) ($cell['position'] ?? -1);
            if ($p < 0) continue;
            $extra = $cell['extra_json'] ?? [];
            if (is_string($extra)) {
                $decoded = json_decode($extra, true);
                $extra = is_array($decoded) ? $decoded : [];
            }
            $x = (int) ($extra['x'] ?? -1);
            $y = (int) ($extra['y'] ?? -1);
            if ($x < 0 || $y < 0) continue;
            $coords[$p] = ['x' => $x, 'y' => $y];
        }
        if (!isset($coords[$toPos])) return [];
        $cur = $coords[$toPos];
        $prev = $coords[$fromPos] ?? null;
        $neighbors = [];
        foreach ($coords as $pos => $xy) {
            if ($pos === $toPos) continue;
            $dist = abs($xy['x'] - $cur['x']) + abs($xy['y'] - $cur['y']);
            if ($dist === 1) $neighbors[$pos] = $xy;
        }
        if (count($neighbors) <= 2) return [];
        if ($prev === null) return ['left', 'straight', 'right'];
        $inX = $cur['x'] - $prev['x'];
        $inY = $cur['y'] - $prev['y'];
        if ($inX === 0 && $inY === 0) return ['left', 'straight', 'right'];
        $out = [];
        foreach ($neighbors as $xy) {
            $vX = $xy['x'] - $cur['x'];
            $vY = $xy['y'] - $cur['y'];
            if ($vX === -$inX && $vY === -$inY) continue;
            $cross = ($inX * $vY) - ($inY * $vX);
            $dot = ($inX * $vX) + ($inY * $vY);
            if ($cross > 0) $out['left'] = true;
            elseif ($cross < 0) $out['right'] = true;
            elseif ($dot > 0) $out['straight'] = true;
        }
        if ($out === []) return [];
        $ordered = [];
        foreach (['left', 'straight', 'right'] as $k) {
            if (isset($out[$k])) $ordered[] = $k;
        }
        return $ordered;
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
        $space = $this->spaceByPosition($gameId, $position);
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
            $mortgaged = $this->normalizeBool($state['mortgaged'] ?? false);
            if ($ownerPlayerId <= 0) {
                $payload['offer_purchase'] = true;
                $payload['price'] = (int) ($space['price'] ?? 0);
            } elseif ($ownerPlayerId !== (int) $player['id'] && !$mortgaged) {
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
                $isMortgaged = $this->normalizeBool($state['mortgaged'] ?? false);
                $payload['can_build_house'] = $ownsGroup && !$isMortgaged && !$hasHotel && $houses < 4;
                $payload['can_build_hotel'] = $ownsGroup && !$isMortgaged && !$hasHotel && $houses >= 4;
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
            $route = $this->boardRoutePositions($gameId);
            $count = max(1, count($route));
            $idx = array_search($position, $route, true);
            $idx = $idx === false ? 0 : (int) $idx;
            $nextIdx = ($idx + $delta) % $count;
            if ($nextIdx < 0) {
                $nextIdx += $count;
            }
            $position = (int) ($route[$nextIdx] ?? $position);
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

    /** @return array<int> */
    private function boardRoutePositions(string $gameId): array
    {
        $game = $this->games->findGameById($gameId);
        $templateId = (int) ($game['board_template_id'] ?? 0);
        if ($templateId <= 0) {
            return array_keys(self::BOARD);
        }
        $cells = $this->games->listBoardCells($templateId);
        if ($cells === []) {
            return array_keys(self::BOARD);
        }
        $positions = array_values(array_unique(array_map(static fn(array $c): int => (int) ($c['position'] ?? -1), $cells)));
        $positions = array_values(array_filter($positions, static fn(int $p): bool => $p >= 0 && $p <= 120));
        sort($positions);
        return $positions === [] ? array_keys(self::BOARD) : $positions;
    }

    /** @return array{position:int,passed_start:bool} */
    private function advancePositionByRoute(string $gameId, int $fromPos, int $steps): array
    {
        $route = $this->boardRoutePositions($gameId);
        $count = count($route);
        if ($count === 0) {
            return ['position' => $fromPos, 'passed_start' => false];
        }
        $fromIdx = array_search($fromPos, $route, true);
        if ($fromIdx === false) {
            $fromIdx = 0;
        }
        $toIdxRaw = $fromIdx + $steps;
        $toIdx = $toIdxRaw % $count;
        return [
            'position' => $route[$toIdx],
            'passed_start' => $toIdxRaw >= $count,
        ];
    }

    private function spaceByPosition(string $gameId, int $position): array
    {
        $game = $this->games->findGameById($gameId);
        $templateId = (int) ($game['board_template_id'] ?? 0);
        if ($templateId > 0) {
            $cells = $this->games->listBoardCells($templateId);
            foreach ($cells as $cell) {
                if ((int) ($cell['position'] ?? -1) !== $position) {
                    continue;
                }
                $extra = $cell['extra_json'] ?? [];
                if (is_string($extra)) {
                    $decoded = json_decode($extra, true);
                    $extra = is_array($decoded) ? $decoded : [];
                }
                $type = (string) ($cell['cell_type'] ?? 'free');
                $price = (int) ($cell['buy_price'] ?? 0);
                $group = isset($extra['group']) ? (int) $extra['group'] : null;
                $buildingCostByGroup = [0 => 50, 1 => 50, 2 => 100, 3 => 100, 4 => 150, 5 => 150, 6 => 200, 7 => 200];
                $buildingCost = $group !== null ? (int) ($buildingCostByGroup[$group] ?? 100) : 0;
                $rent = $price > 0 ? max(2, (int) floor($price / 10)) : 0;
                if (in_array($type, ['tax', 'luxury'], true) && $rent <= 0) {
                    $rent = $price > 0 ? $price : 100;
                }
                return [
                    'type' => $type,
                    'name' => (string) ($cell['title'] ?? ('#' . $position)),
                    'price' => $price,
                    'rent' => $rent,
                    'tax' => $rent,
                    'group' => $group,
                    'building_cost' => $buildingCost,
                ];
            }
        }
        return self::BOARD[$position] ?? ['type' => 'free', 'name' => ''];
    }

    /** @return int[] */
    private function groupPositions(string $gameId, int $groupId): array
    {
        if ($groupId < 0) {
            return [];
        }
        $positions = [];
        foreach ($this->boardRoutePositions($gameId) as $pos) {
            $space = $this->spaceByPosition($gameId, $pos);
            if (($space['type'] ?? '') === 'property' && (int) ($space['group'] ?? -1) === $groupId) {
                $positions[] = $pos;
            }
        }
        return $positions;
    }

    private function ownsWholeGroup(string $gameId, int $playerId, int $groupId): bool
    {
        $groupPositions = $this->groupPositions($gameId, $groupId);
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

    private function propertyValue(string $gameId, int $position, ?array $state): int
    {
        $space = $this->spaceByPosition($gameId, $position);
        if ($space === null) {
            return 0;
        }
        $price = (int) ($space['price'] ?? 0);
        $buildCost = (int) ($space['building_cost'] ?? 0);
        $houses = (int) ($state['houses'] ?? 0);
        $hasHotel = $this->normalizeBool($state['has_hotel'] ?? false);
        $buildingsValue = $buildCost * ($houses + ($hasHotel ? 5 : 0));
        return $price + $buildingsValue;
    }

    /** @return int[] */
    private function normalizeIntArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            $n = (int) $item;
            if ($n >= 0) {
                $result[] = $n;
            }
        }
        return array_values(array_unique($result));
    }

    private function redeemMortgageCost(string $gameId, int $ownerPlayerId, int $position, ?array $prop): int
    {
        $baseCost = (int) floor($this->propertyValue($gameId, $position, $prop) / 2);
        $events = $this->games->listEventsSince($gameId, 0, 1_000_000);
        $mortgageSeq = 0;
        foreach ($events as $ev) {
            if (($ev['event_type'] ?? '') !== 'command_mortgage') {
                continue;
            }
            if ((int) ($ev['actor_player_id'] ?? 0) !== $ownerPlayerId) {
                continue;
            }
            $payload = [];
            try {
                $payload = json_decode((string) ($ev['payload_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $payload = [];
            }
            if ((int) ($payload['position'] ?? -1) === $position) {
                $mortgageSeq = (int) ($ev['event_seq'] ?? 0);
            }
        }
        if ($mortgageSeq <= 0) {
            return $baseCost;
        }
        $passes = 0;
        foreach ($events as $ev) {
            if ((int) ($ev['event_seq'] ?? 0) <= $mortgageSeq) {
                continue;
            }
            if (($ev['event_type'] ?? '') !== 'command_roll') {
                continue;
            }
            if ((int) ($ev['actor_player_id'] ?? 0) !== $ownerPlayerId) {
                continue;
            }
            $payload = [];
            try {
                $payload = json_decode((string) ($ev['payload_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $payload = [];
            }
            $from = (int) ($payload['from_position'] ?? -1);
            $to = (int) ($payload['position'] ?? -1);
            if ($this->didPassStartByRoute($gameId, $from, $to)) {
                $passes++;
            }
        }
        return $baseCost + ($passes * 200);
    }

    private function didPassStartByRoute(string $gameId, int $from, int $to): bool
    {
        if ($from < 0 || $to < 0) {
            return false;
        }
        $route = $this->boardRoutePositions($gameId);
        $fromIdx = array_search($from, $route, true);
        $toIdx = array_search($to, $route, true);
        if ($fromIdx === false || $toIdx === false) {
            return $to < $from;
        }
        return (int) $toIdx < (int) $fromIdx;
    }
}
