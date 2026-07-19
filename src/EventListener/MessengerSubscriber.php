<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\EventListener;

use Beem\Beem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Auto-traces Messenger messages as Beem transactions.
 *
 * All three events fire inside the worker process that actually handles the
 * message, so the transaction is started and finished in the same process:
 *
 * - {@code WorkerMessageReceived}: start a transaction per consumed message.
 * - {@code WorkerMessageHandled}: finish transaction (success), flush.
 * - {@code WorkerMessageFailed}: capture exception, finish transaction (error), flush.
 *
 * The transaction op is {@code queue}. Message class name is used as the
 * transaction name. Sync transport also emits these events, so sync dispatch
 * is traced identically.
 */
final class MessengerSubscriber implements EventSubscriberInterface
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
            WorkerMessageReceivedEvent::class => ['onReceived', 1],
            WorkerMessageHandledEvent::class => ['onHandled', -128],
            WorkerMessageFailedEvent::class => ['onFailed', -128],
        ];
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        $name = $message::class;

        $tx = Beem::startTransaction("queue:{$name}", 'queue');
        $tx->environment = $this->environment;
        $tx->release = $this->release;
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        Beem::finishTransaction();
        Beem::flush();
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        Beem::getCurrentTransaction()?->setStatus('error');

        Beem::captureException($event->getThrowable(), [
            'environment' => $this->environment,
            'release' => $this->release,
        ]);

        Beem::finishTransaction();
        Beem::flush();
    }
}
