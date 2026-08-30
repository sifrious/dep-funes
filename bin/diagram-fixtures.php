<?php

declare(strict_types=1);

use function Sifrious\Funes\diagram;

require dirname(__DIR__).'/vendor/autoload.php';

$fixtures = require dirname(__DIR__).'/tests/Fixtures/diagram_sentences.php';

echo "sentence,total_ms,parse_ms,transform_ms,render_ms,warnings\n";

foreach ($fixtures as $sentence) {
    $result = diagram($sentence);
    $timings = $result['timings'];
    $warnings = implode('|', $result['warnings']);

    printf(
        "\"%s\",%.3f,%.3f,%.3f,%.3f,\"%s\"\n",
        str_replace('"', '""', $sentence),
        $timings['total_ms'],
        $timings['parse_ms'],
        $timings['transform_ms'],
        $timings['render_ms'],
        str_replace('"', '""', $warnings),
    );
}
