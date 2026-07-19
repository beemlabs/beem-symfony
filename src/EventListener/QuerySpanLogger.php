<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\EventListener;

use Beem\Beem;

/**
 * Records Doctrine DBAL queries as {@code db.query} spans on the current transaction.
 *
 * Implements {@see \Doctrine\DBAL\Logging\SQLLogger} (DBAL 3.x). For DBAL 4.x
 * where SQLLogger was removed, use {@see QuerySpanMiddleware} instead, wired
 * via the bundle's compiler pass.
 *
 * Each executed SQL statement is recorded as a span with the SQL fragment
 * as the description. If no transaction is active, the query is ignored
 * (no span is created) — instrumentation is a no-op outside traced contexts.
 */
final class QuerySpanLogger
{
    /** @var array<string, float> */
    private array $startTimes = [];

    /**
     * Called by Doctrine before a query is executed.
     *
     * @param string|null $sql    The SQL statement
     * @param array<int, mixed>|null $params Bound parameters
     * @param array<int, int>|null $types  Parameter types
     */
    public function startQuery(?string $sql, ?array $params = null, ?array $types = null): void
    {
        $tx = Beem::getCurrentTransaction();
        if ($tx === null) {
            return;
        }

        $span = $tx->startSpan('db.query', $sql ?? 'unknown');
        $this->startTimes[$span->spanId] = microtime(true) * 1000.0;
    }

    /**
     * Called by Doctrine after a query finishes.
     */
    public function stopQuery(): void
    {
        $tx = Beem::getCurrentTransaction();
        if ($tx === null) {
            return;
        }

        // The last span started is the one we need to finish.
        $spans = $tx->spans;
        if ($spans === []) {
            return;
        }
        $span = $spans[count($spans) - 1];
        $startedAt = $this->startTimes[$span->spanId] ?? microtime(true) * 1000.0;
        $span->finish((microtime(true) * 1000.0) - $startedAt);
        unset($this->startTimes[$span->spanId]);
    }
}
