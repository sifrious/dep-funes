<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

interface GrammarTransformer
{
    public function name(): string;

    public function version(): string;

    /**
     * @return array{
     *     nodes:list<array{id:int,text:string,pos:string}>,
     *     edges:list<array{from:int,to:int,label:string}>,
     *     root:int
     * }
     */
    public function transform(ParsedSentence $sentence): array;
}
