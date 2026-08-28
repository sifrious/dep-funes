<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

use InvalidArgumentException;

final readonly class TextDraft
{
    public function __construct(
        public string $kind,
        public string $contentType,
        public string $text,
        public ?string $language = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9+.-]*:[^\s:][^\s]*$/', $kind) !== 1) {
            throw new InvalidArgumentException('Text kinds must be namespaced opaque identifiers.');
        }

        if (trim($contentType) === '' || $text === '' || ($language !== null && trim($language) === '')) {
            throw new InvalidArgumentException('Historical text requires content type, content, and a valid optional language.');
        }
    }
}
