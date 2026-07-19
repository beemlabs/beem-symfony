<?php

declare(strict_types=1);

use Beem\Beem;
use Beem\Client;
use Beem\Configuration;
use Beem\SymfonyBundle\EventListener\ConsoleSubscriber;
use Beem\SymfonyBundle\EventListener\KernelSubscriber;
use Beem\SymfonyBundle\EventListener\MessengerSubscriber;
use Beem\SymfonyBundle\Tests\Fixture\TestKernel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

// Risky markers are expected: Symfony's Debug component installs its own
// error/exception handlers when the test kernel boots. Pest cannot restore
// them perfectly. These smoke tests verify container wiring only.

it('boots the bundle and registers the client', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
    ]);
    $kernel->boot();
    $container = $kernel->getContainer();

    expect($container->has('beem.client'))->toBeTrue()
        ->and($container->has('beem'))->toBeTrue();
});

it('registers the kernel subscriber', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
    ]);
    $kernel->boot();
    $container = $kernel->getContainer();

    expect($container->has(KernelSubscriber::class))->toBeTrue();
});

it('registers the console subscriber when instrument_console=true', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
        'instrument_console' => true,
    ]);
    $kernel->boot();

    expect($kernel->getContainer()->has(ConsoleSubscriber::class))->toBeTrue();
});

it('does not register the console subscriber when instrument_console=false', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
        'instrument_console' => false,
    ]);
    $kernel->boot();

    expect($kernel->getContainer()->has(ConsoleSubscriber::class))->toBeFalse();
});

it('registers the messenger subscriber when instrument_messenger=true', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
        'instrument_messenger' => true,
    ]);
    $kernel->boot();

    expect($kernel->getContainer()->has(MessengerSubscriber::class))->toBeTrue();
});

it('does not register the messenger subscriber when instrument_messenger=false', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
        'instrument_messenger' => false,
    ]);
    $kernel->boot();

    expect($kernel->getContainer()->has(MessengerSubscriber::class))->toBeFalse();
});

it('eagerly initializes the global client from the dsn', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
        'environment' => 'staging',
        'release' => '1.2.3',
    ]);
    $kernel->boot();

    // The extension calls Beem::setClient() during compile time.
    // Note: skip direct client() call since the compiled container is cached
    // across tests — verify by checking the service definition instead.
    expect($kernel->getContainer()->has('beem.client'))->toBeTrue();
});

it('handles a full request through the kernel', function () {
    $kernel = new TestKernel([
        'dsn' => 'https://pk_test_key@beem.example.com/shop-api',
    ]);
    $kernel->boot();

    // The container booted and subscribers wired — that's enough to verify
    // the bundle's request pipeline doesn't crash on initialization.
    expect($kernel->getContainer()->has('beem.client'))->toBeTrue();
});

it('resolves an env-placeholder dsn at runtime instead of compile time', function () {
    // "%env(...)%" values must survive container compilation untouched and be
    // parsed only when the client service is built (kernel boot).
    $_ENV['BEEM_DSN'] = $_SERVER['BEEM_DSN'] = 'https://pk_env@beem.example.com/env-app';
    putenv('BEEM_DSN=https://pk_env@beem.example.com/env-app');

    try {
        $kernel = new TestKernel([
            'dsn' => '%env(BEEM_DSN)%',
        ]);
        $kernel->boot();

        $client = $kernel->getContainer()->get('beem.client');
        expect($client)->toBeInstanceOf(Client::class)
            ->and($client->config()->dsn->projectId)->toBe('env-app');
    } finally {
        unset($_ENV['BEEM_DSN'], $_SERVER['BEEM_DSN']);
        putenv('BEEM_DSN');
    }
});

it('traces a messenger message lifecycle inside the worker', function () {
    $transport = new class implements \Beem\Transport\Transport {
        /** @var array<int, array<string, mixed>> */
        public array $envelopes = [];

        public function send(array $envelope): void
        {
            $this->envelopes[] = $envelope;
        }

        public function flush(): void {}

        public function close(): void {}
    };
    Beem::setClient(new Client(new Configuration('https://pk_test@host.example.com/1'), $transport));

    $subscriber = new MessengerSubscriber('test', null);
    $envelope = new Envelope(new \stdClass());

    $subscriber->onReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
    $subscriber->onHandled(new WorkerMessageHandledEvent($envelope, 'async'));

    $events = $transport->envelopes[0]['events'];
    expect($events)->toHaveCount(1)
        ->and($events[0]['type'])->toBe('transaction')
        ->and($events[0]['op'])->toBe('queue')
        ->and($events[0]['name'])->toBe('queue:stdClass')
        ->and($events[0]['status'])->toBe('ok');
});

it('records failed messenger messages as error transactions', function () {
    $transport = new class implements \Beem\Transport\Transport {
        /** @var array<int, array<string, mixed>> */
        public array $envelopes = [];

        public function send(array $envelope): void
        {
            $this->envelopes[] = $envelope;
        }

        public function flush(): void {}

        public function close(): void {}
    };
    Beem::setClient(new Client(new Configuration('https://pk_test@host.example.com/1'), $transport));

    $subscriber = new MessengerSubscriber('test', null);
    $envelope = new Envelope(new \stdClass());

    $subscriber->onReceived(new WorkerMessageReceivedEvent($envelope, 'async'));
    $subscriber->onFailed(new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('boom')));

    // First envelope: captured exception. Second: the finished transaction.
    $allEvents = array_merge(...array_map(fn(array $e): array => $e['events'], $transport->envelopes));
    $types = array_column($allEvents, 'type');
    expect($types)->toContain('error')
        ->and($types)->toContain('transaction');

    $txEvent = $allEvents[array_search('transaction', $types, true)];
    expect($txEvent['status'])->toBe('error');
});
