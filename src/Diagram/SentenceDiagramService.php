<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

use Illuminate\Database\ConnectionInterface;
use LogicException;
use Sifrious\Funes\Persistence\ObservationNotFound;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\ExtractionDraft;
use Sifrious\Funes\Value\ExtractionResult;
use Sifrious\Funes\Value\ProducerContext;

final class SentenceDiagramService
{
    public const EXTRACTOR = 'sentence-diagram';

    public function __construct(
        private readonly GrammarParser $parser,
        private readonly GrammarTransformer $transformer,
        private readonly SvgRenderer $renderer,
        private readonly ?ObservationStore $observationStore = null,
        private readonly ?ConnectionInterface $connection = null,
    ) {}

    public static function offline(): self
    {
        return new self(
            new LocalCompactEnglishParser,
            new ReedKelloggTransformer,
            new SimpleSvgRenderer,
        );
    }

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
    public function diagram(string $sentence): array
    {
        $source = trim($sentence);
        if ($source === '') {
            throw new LogicException('Sentence cannot be empty.');
        }

        $start = hrtime(true);
        $parseStart = hrtime(true);
        $parsed = $this->parser->parse($source);
        $parseMs = $this->elapsedMs($parseStart);

        $transformStart = hrtime(true);
        $graph = $this->transformer->transform($parsed);
        $transformMs = $this->elapsedMs($transformStart);

        $renderStart = hrtime(true);
        $svg = $this->renderer->render($graph);
        $renderMs = $this->elapsedMs($renderStart);

        return [
            'source' => $source,
            'grammar_graph' => $graph,
            'svg' => $svg,
            'timings' => [
                'parse_ms' => $parseMs,
                'transform_ms' => $transformMs,
                'render_ms' => $renderMs,
                'total_ms' => $this->elapsedMs($start),
            ],
            'warnings' => $parsed->warnings,
            'provenance' => [
                'mode' => 'offline',
                'llm_used' => false,
                'parser' => ['name' => $this->parser->name(), 'version' => $this->parser->version()],
                'transformer' => ['name' => $this->transformer->name(), 'version' => $this->transformer->version()],
                'renderer' => ['name' => $this->renderer->name(), 'version' => $this->renderer->version()],
            ],
        ];
    }

    public function diagramAndRecord(string $observationId, ProducerContext $producerContext): ExtractionResult
    {
        if ($this->observationStore === null || $this->connection === null) {
            throw new LogicException('Recording requires ObservationStore and database connection.');
        }

        $observation = $this->observationStore->get($observationId);
        if ($observation === null) {
            throw new ObservationNotFound("Observation [{$observationId}] does not exist.");
        }

        $result = $this->diagram($observation->payload);
        $version = $this->nextVersion($observationId);

        return $this->observationStore->recordExtraction(new ExtractionDraft(
            observationId: $observationId,
            extractor: self::EXTRACTOR,
            version: $version,
            producerContext: $producerContext,
            result: $result,
        ));
    }

    private function nextVersion(string $observationId): string
    {
        $versions = $this->connection
            ?->table('funes_extractions')
            ->where('observation_id', $observationId)
            ->where('extractor', self::EXTRACTOR)
            ->pluck('version')
            ->all() ?? [];

        $highest = 0;
        foreach ($versions as $version) {
            if (is_string($version) && ctype_digit($version)) {
                $highest = max($highest, (int) $version);
            }
        }

        return (string) ($highest + 1);
    }

    private function elapsedMs(int $startedAtNs): float
    {
        return round((hrtime(true) - $startedAtNs) / 1_000_000, 3);
    }
}
