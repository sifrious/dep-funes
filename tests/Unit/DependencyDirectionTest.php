<?php

declare(strict_types=1);

it('does not import host or foreign package models', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));
    $source = '';

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $source .= file_get_contents($file->getPathname());
        }
    }

    expect($source)->not->toContain('Sifrious\\Aleph')
        ->and($source)->not->toContain('Sifrious\\Kilgore')
        ->and($source)->not->toContain('App\\Models');
});
