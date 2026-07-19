<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\DependencyInjection;

use Beem\Client;
use Beem\SymfonyBundle\BeemClientFactory;
use Beem\SymfonyBundle\EventListener\ConsoleSubscriber;
use Beem\SymfonyBundle\EventListener\KernelSubscriber;
use Beem\SymfonyBundle\EventListener\MessengerSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * DI extension — wires the Beem client and event subscribers into the container.
 *
 * The client service is built lazily at runtime by {@see BeemClientFactory}
 * (env placeholders in the DSN are only resolved then) and instantiated at
 * kernel boot by the bundle, so early errors are still captured. Subscribers
 * are registered with the framework's event subscriber tag.
 */
final class BeemExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $environment = $config['environment']
            ?? ($container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : 'production');
        $environment = is_string($environment) ? $environment : 'production';

        // Register the client as a lazily-built service. The DSN is passed as a
        // raw argument so an env placeholder ("%env(BEEM_DSN)%") stays unresolved
        // at compile time — the factory parses it at runtime, when Symfony has
        // resolved env vars. Eager boot happens in BeemSymfonyBundle::boot().
        $clientDef = new Definition(Client::class);
        $clientDef->setPublic(true);
        $clientDef->setFactory([BeemClientFactory::class, 'create']);
        $clientDef->setArguments([
            $config['dsn'],
            [
                'sample_rate' => $config['sample_rate'],
                'max_batch_size' => $config['max_batch_size'],
                'flush_timeout_ms' => $config['flush_timeout_ms'],
                'environment' => $environment,
                'release' => $config['release'],
                'default_tags' => $config['default_tags'],
            ],
        ]);
        $container->setDefinition('beem.client', $clientDef);
        $container->setAlias('beem', 'beem.client')->setPublic(true);

        // Kernel subscriber (request/terminate/exception).
        $container->register(KernelSubscriber::class)
            ->addArgument($environment)
            ->addArgument($config['release'])
            ->addTag('kernel.event_subscriber')
            ->setPublic(true);

        // Console subscriber (command/terminate/error) — optional.
        if ($config['instrument_console']) {
            $container->register(ConsoleSubscriber::class)
                ->addArgument($environment)
                ->addArgument($config['release'])
                ->addTag('kernel.event_subscriber')
                ->setPublic(true);
        }

        // Doctrine query span instrumentation — wired by RegisterDoctrineLoggerPass
        // (compiler pass), only if DBAL 3.x SQLLogger interface exists.
        if ($config['instrument_doctrine'] && class_exists(\Doctrine\DBAL\Connection::class)) {
            // No subscriber needed — compiler pass hooks the QuerySpanLogger into connections.
        }

        // Messenger subscriber — only if Messenger is present.
        if ($config['instrument_messenger'] && interface_exists(\Symfony\Component\Messenger\MessageBusInterface::class)) {
            $container->register(MessengerSubscriber::class)
                ->addArgument($environment)
                ->addArgument($config['release'])
                ->addTag('kernel.event_subscriber')
                ->setPublic(true);
        }
    }

    public function getAlias(): string
    {
        return 'beem';
    }
}
