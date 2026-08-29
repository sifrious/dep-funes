<?php
declare(strict_types=1);
namespace Sifrious\Funes\Graph;
/** Append-only boundary; producers may deliver this asynchronously through an outbox. */
interface HistoricalAppender
{
    public function append(HistoricalAppend $append): void;
}
