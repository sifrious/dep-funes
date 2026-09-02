<?php

declare(strict_types=1);

namespace Sifrious\Funes\Text;

use Illuminate\Database\ConnectionInterface;
use Sifrious\Funes\Time\StoredTimestamp;
use Sifrious\Funes\Value\TextAssertion;
use stdClass;

final readonly class SqlTextProjection implements TextProjection
{
    public function __construct(private ConnectionInterface $connection) {}

    public function rebuild(): int
    {
        return $this->connection->transaction(function (): int {
            $this->connection->table('funes_text_projection')->delete();

            foreach ($this->connection->table('funes_observation_text')->orderBy('id')->get() as $text) {
                $this->connection->table('funes_text_projection')->insert([
                    'document_id' => $text->id,
                    'observation_id' => $text->observation_id,
                    'provenance_id' => $text->provenance_id,
                    'kind' => $text->kind,
                    'content_type' => $text->content_type,
                    'language' => $text->language,
                    'text' => $text->text,
                    'text_hash' => $text->text_hash,
                    // Already a stored value; normalizing keeps the projection in one
                    // format whatever fidelity the source row was written with.
                    'recorded_at' => StoredTimestamp::normalize($text->recorded_at),
                ]);
            }

            $sourcePayloads = $this->connection->table('funes_observations as observations')
                ->join('funes_payloads as payloads', 'payloads.hash', '=', 'observations.payload_hash')
                ->where('observations.content_type', 'like', 'text/%')
                ->orderBy('observations.id')
                ->get([
                    'observations.id',
                    'observations.content_type',
                    'observations.payload_hash',
                    'observations.ingested_at',
                    'payloads.contents',
                ]);

            foreach ($sourcePayloads as $payload) {
                if ((string) $payload->contents === '') {
                    continue;
                }

                $provenanceId = $this->connection->table('funes_observation_provenance')
                    ->where('observation_id', $payload->id)
                    ->orderBy('recorded_at')
                    ->orderBy('id')
                    ->value('id');

                $this->connection->table('funes_text_projection')->insert([
                    'document_id' => 'funes:source-payload/'.$payload->id,
                    'observation_id' => $payload->id,
                    'provenance_id' => $provenanceId,
                    'kind' => 'funes:source-payload',
                    'content_type' => $payload->content_type,
                    'language' => null,
                    'text' => $payload->contents,
                    'text_hash' => $payload->payload_hash,
                    'recorded_at' => StoredTimestamp::normalize($payload->ingested_at),
                ]);
            }

            return $this->connection->table('funes_text_projection')->count();
        });
    }

    public function documents(): array
    {
        return array_values($this->connection->table('funes_text_projection')
            ->orderBy('document_id')
            ->get()
            ->map(fn (stdClass $item): TextAssertion => new TextAssertion(
                (string) $item->document_id,
                (string) $item->observation_id,
                $item->provenance_id === null ? null : (string) $item->provenance_id,
                (string) $item->kind,
                (string) $item->content_type,
                (string) $item->text,
                (string) $item->text_hash,
                $item->language === null ? null : (string) $item->language,
                StoredTimestamp::require($item->recorded_at),
            ))
            ->all());
    }
}
