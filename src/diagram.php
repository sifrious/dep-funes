<?php

declare(strict_types=1);

namespace Sifrious\Funes;

use Sifrious\Funes\Diagram\SentenceDiagramService;

/**
 * @return array{
 *     source:string,
 *     grammar_graph:array{
 *         nodes:list<array{id:int,text:string,pos:string}>,
 *         edges:list<array{from:int,to:int,label:string}>,
 *         root:int
 *     },
 *     svg:string,
 *     timings:array{parse_ms:float,transform_ms:float,render_ms:float,total_ms:float},
 *     warnings:list<string>,
 *     provenance:array{
 *         mode:string,
 *         llm_used:bool,
 *         parser:array{name:string,version:string},
 *         transformer:array{name:string,version:string},
 *         renderer:array{name:string,version:string}
 *     }
 * }
 */
function diagram(string $sentence): array
{
    return SentenceDiagramService::offline()->diagram($sentence);
}
