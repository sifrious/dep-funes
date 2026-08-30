<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

final readonly class ParsedSentence
{
    /**
     * @param  list<array{id:int,text:string,pos:string}>  $tokens
     * @param  list<array{from:int,to:int,label:string}>  $dependencies
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $tokens,
        public array $dependencies,
        public array $warnings = [],
    ) {}
}
