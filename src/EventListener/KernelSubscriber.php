<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\EventListener;

use Beem\Beem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Auto-traces HTTP requests as Beem transactions.
 *
 * - {@code kernel.request}: start a transaction named after the matched route.
 * - {@code kernel.terminate}: finish the transaction, set HTTP status, flush.
 * - {@code kernel.exception}: capture the throwable, leave the transaction
 *   open (terminate will finish it with the error status).
 *
 * The transaction op is {@code http.server}. The HTTP method, status code, and
 * route are recorded on the transaction. Sampling, environment, and tags are
 * handled by the core client.
 */
final class KernelSubscriber implements EventSubscriberInterface
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
            KernelEvents::REQUEST => ['onRequest', 1],          // after router (priority 0)
            KernelEvents::TERMINATE => ['onTerminate', -128],   // late, after response sent
            KernelEvents::EXCEPTION => ['onException', -128],   // late, after other listeners
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route', $request->getPathInfo());
        $method = $request->getMethod();

        $tx = Beem::startTransaction("{$method} {$route}", 'http.server');
        $tx->environment = $this->environment;
        $tx->release = $this->release;
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $tx = Beem::getCurrentTransaction();
        $context = [
            'environment' => $this->environment,
            'release' => $this->release,
            'request' => [
                'method' => $event->getRequest()->getMethod(),
                'url' => $event->getRequest()->getUri(),
            ],
        ];

        if ($tx !== null) {
            $tx->setStatus('error');
        }

        Beem::captureException($exception, $context);
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $response = $event->getResponse();
        $status = $response->getStatusCode();

        Beem::finishTransaction($status);

        // fastcgi_finish_request was already called by Symfony's response sender;
        // flush now sends buffered events to the ingest endpoint.
        Beem::flush();
    }
}
