<?php

declare(strict_types=1);

namespace Sifrious\Funes\Diagram;

final class SimpleSvgRenderer implements SvgRenderer
{
    public function name(): string
    {
        return 'simple-svg-renderer';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function render(array $graph): string
    {
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];
        $root = $graph['root'];
        $spacing = 120;
        $originX = 80;
        $baselineY = 130;
        $width = max(320, (count($nodes) * $spacing) + 100);
        $height = 260;

        $positions = [];
        foreach ($nodes as $index => $node) {
            $positions[$node['id']] = [
                'x' => $originX + ($index * $spacing),
                'y' => $baselineY,
            ];
        }

        $parts = [
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">', $width, $height, $width, $height),
            '<rect x="0" y="0" width="100%" height="100%" fill="white"/>',
            sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#222" stroke-width="2"/>', $originX - 40, $baselineY, $width - 40, $baselineY),
        ];

        foreach ($nodes as $node) {
            $x = $positions[$node['id']]['x'];
            $text = htmlspecialchars($node['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parts[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#222" stroke-width="1.5"/>', $x, $baselineY, $x, $baselineY + 22);
            $parts[] = sprintf('<text x="%d" y="%d" text-anchor="middle" font-family="Arial, sans-serif" font-size="15">%s</text>', $x, $baselineY + 44, $text);
        }

        foreach ($edges as $edge) {
            if ($edge['to'] === 0 || ! isset($positions[$edge['from']], $positions[$edge['to']])) {
                continue;
            }

            $from = $positions[$edge['from']];
            $to = $positions[$edge['to']];
            $midX = (int) round(($from['x'] + $to['x']) / 2);
            $topY = 35 + (abs($from['x'] - $to['x']) > 120 ? 0 : 20);
            $parts[] = sprintf(
                '<path d="M %d %d Q %d %d %d %d" fill="none" stroke="#4a5568" stroke-width="1.5"/>',
                $from['x'],
                $baselineY - 6,
                $midX,
                $topY,
                $to['x'],
                $baselineY - 6,
            );
            $parts[] = sprintf(
                '<text x="%d" y="%d" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#2d3748">%s</text>',
                $midX,
                $topY - 6,
                htmlspecialchars($edge['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        if (isset($positions[$root])) {
            $x = $positions[$root]['x'];
            $parts[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#2b6cb0" stroke-width="2"/>', $x, 20, $x, $baselineY - 8);
            $parts[] = sprintf('<text x="%d" y="%d" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" fill="#2b6cb0">root</text>', $x, 16);
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }
}
