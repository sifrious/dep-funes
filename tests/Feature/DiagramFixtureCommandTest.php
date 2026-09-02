<?php

declare(strict_types=1);

it('prints timing rows for the diagram fixture command', function (): void {
    $output = [];
    $code = 1;
    exec('php '.escapeshellarg(dirname(__DIR__, 2).'/bin/diagram-fixtures.php'), $output, $code);

    expect($code)->toBe(0)
        ->and($output)->not->toBeEmpty()
        ->and($output[0])->toContain('sentence,total_ms,parse_ms,transform_ms,render_ms,warnings')
        ->and(implode("\n", $output))->toContain('Mary writes a clear note.');
});
