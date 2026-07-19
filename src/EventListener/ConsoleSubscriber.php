<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\EventListener;

use Beem\Beem;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Auto-traces console commands as Beem transactions.
 *
 * - {@code console.command}: start a transaction named after the command name.
 * - {@code console.error}: capture the throwable as an error event.
 * - {@code console.terminate}: finish the transaction, flush.
 *
 * The transaction op is {@code console}. Sampling, environment, and tags are
 * handled by the core client.
 */
final class ConsoleSubscriber implements EventSubscriberInterface
{
    private string $environment;
    private ?string $release;

    public function __construct(string $environment, ?string $release = null)
    {
        $this->environment = $environment;
        $this->release = $release;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', 1],
            ConsoleEvents::ERROR => ['onError', -128],
            ConsoleEvents::TERMINATE => ['onTerminate', -128],
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $name = $command?->getName() ?? 'unknown';

        $tx = Beem::startTransaction("console:{$name}", 'console');
        $tx->environment = $this->environment;
        $tx->release = $this->release;
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        Beem::captureException($event->getError(), [
            'environment' => $this->environment,
            'release' => $this->release,
        ]);

        $tx = Beem::getCurrentTransaction();
        if ($tx !== null) {
            $tx->setStatus('error');
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getExitCode() > 0) {
            Beem::getCurrentTransaction()?->setStatus('error');
        }
        Beem::finishTransaction();
        Beem::flush();
    }
}
