<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\EventListener;

use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Adapter that makes {@see QuerySpanLogger} conform to Doctrine's
 * {@see SQLLogger} interface (DBAL 3.x). When DBAL 4.x is in use
 * (SQLLogger removed), this class is skipped by the compiler pass.
 */
final class DoctrineSqlLoggerAdapter implements SQLLogger
{
    private QuerySpanLogger $inner;

    public function __construct(QuerySpanLogger $inner)
    {
        $this->inner = $inner;
    }

    /**
     * @param string|null $sql
     * @param array<int, mixed>|null $params
     * @param array<int, int>|null $types
     */
    public function startQuery(?string $sql, ?array $params = null, ?array $types = null): void
    {
        $this->inner->startQuery($sql, $params, $types);
    }

    public function stopQuery(): void
    {
        $this->inner->stopQuery();
    }
}
