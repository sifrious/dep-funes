<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

interface SvgRenderer
{
    public function name(): string;

    public function version(): string;

    /**
     * @param  array{
     *     nodes:list<array{id:int,text:string,pos:string}>,
     *     edges:list<array{from:int,to:int,label:string}>,
     *     root:int
     * }  $graph
     */
    public function render(array $graph): string;
}
