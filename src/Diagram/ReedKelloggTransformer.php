<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

final class ReedKelloggTransformer implements GrammarTransformer
{
    public function name(): string
    {
        return 'reed-kellogg-ish-transform';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function transform(ParsedSentence $sentence): array
    {
        $root = $sentence->tokens[0]['id'] ?? 0;

        foreach ($sentence->dependencies as $dependency) {
            if ($dependency['label'] === 'root') {
                $root = $dependency['from'];
                break;
            }
        }

        return [
            'nodes' => $sentence->tokens,
            'edges' => $sentence->dependencies,
            'root' => $root,
        ];
    }
}
