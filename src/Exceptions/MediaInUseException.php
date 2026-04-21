<?php
declare(strict_types=1);

namespace Stain\Exceptions;

/**
 * При удалении файла из медиатеки, пока на него ссылается HTML поста/страницы.
 *
 * @param list<array{id:int,title:string,slug:string,content_type:string}> $references
 */
final class MediaInUseException extends \RuntimeException
{
    /**
     * @param list<array{id:int,title:string,slug:string,content_type:string}> $references
     */
    public function __construct(
        string $message,
        public readonly array $references
    ) {
        parent::__construct($message);
    }
}
