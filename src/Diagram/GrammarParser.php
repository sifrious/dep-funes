<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

interface GrammarParser
{
    public function name(): string;

    public function version(): string;

    public function parse(string $sentence): ParsedSentence;
}
