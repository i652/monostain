<?php
declare(strict_types=1);

namespace Stain\Http;

use Stain\Exceptions\MediaInUseException;
use Throwable;

/**
 * Единый JSON для ошибок API: { "error": string, "code": string, ... }
 */
final class ApiErrorMapper
{
    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    public static function fromThrowable(Throwable $e): array
    {
        if ($e instanceof MediaInUseException) {
            return [
                409,
                [
                    'ok' => false,
                    'error' => $e->getMessage(),
                    'code' => 'MEDIA_IN_USE',
                    'references' => $e->references,
                ],
            ];
        }

        $msg = $e->getMessage();

        if ($msg === 'Unauthorized') {
            return [401, ['error' => 'Требуется авторизация', 'code' => 'UNAUTHORIZED']];
        }

        if ($msg === 'Forbidden') {
            return [403, ['error' => 'Недостаточно прав для выполнения действия', 'code' => 'FORBIDDEN']];
        }

        if ($e instanceof \InvalidArgumentException) {
            return [422, ['error' => $msg, 'code' => 'VALIDATION_ERROR']];
        }

        if (in_array($msg, ['Post not found', 'Page not found', 'Media not found', 'Game not found', 'Invite not found'], true)) {
            return [404, ['error' => 'Запрошенный ресурс не найден', 'code' => 'NOT_FOUND']];
        }

        return [400, ['error' => $msg, 'code' => 'BAD_REQUEST']];
    }
}
