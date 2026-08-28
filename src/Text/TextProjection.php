<?php

declare(strict_types=1);

namespace Sifrious\Funes\Text;

use Sifrious\Funes\Value\TextAssertion;

interface TextProjection
{
    public function rebuild(): int;

    /**
     * @return list<TextAssertion>
     */
    public function documents(): array;
}
