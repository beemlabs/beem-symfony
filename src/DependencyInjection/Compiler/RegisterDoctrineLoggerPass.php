<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\DependencyInjection\Compiler;

use Beem\SymfonyBundle\EventListener\DoctrineSqlLoggerAdapter;
use Beem\SymfonyBundle\EventListener\QuerySpanLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass — installs the Beem {@see QuerySpanLogger} on all
 * Doctrine DBAL connections defined in the container.
 *
 * Works with DBAL 3.x (SQLLogger interface present) and silently skips
 * when DBAL 4.x is in use (SQLLogger removed — use the middleware variant
 * there). Existing SQL loggers are preserved by chaining them in a
 * {@see LoggerChain}.
 *
 * For each connection found, this pass:
 *   1. Registers a {@see QuerySpanLogger} service (shared, singleton).
 *   2. Registers a {@see DoctrineSqlLoggerAdapter} wrapping it.
 *   3. Adds a {@code setSQLLogger} call to the connection's DBAL
 *      {@see \Doctrine\DBAL\Configuration} service.
 *
 * Note: {@code setSQLLogger} exists on Doctrine\DBAL\Configuration, NOT on
 * Doctrine\DBAL\Connection — and DoctrineBundle does not tag connection
 * services. Connections are discovered via the {@code doctrine.connections}
 * parameter and each configuration service is named
 * {@code <connection service id>.configuration} by DoctrineBundle convention.
 */
final class RegisterDoctrineLoggerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(Connection::class) || !interface_exists(\Doctrine\DBAL\Logging\SQLLogger::class)) {
            return;
        }

        // DoctrineBundle registers all connection service ids under this parameter.
        if (!$container->hasParameter('doctrine.connections')) {
            return;
        }

        $container->setDefinition(
            'beem.doctrine.query_logger',
            new Definition(QuerySpanLogger::class)
        );

        $container->setDefinition(
            'beem.doctrine.sql_logger_adapter',
            new Definition(DoctrineSqlLoggerAdapter::class, [new Reference('beem.doctrine.query_logger')])
        );

        /** @var array<string, string> $connections */
        $connections = $container->getParameter('doctrine.connections');

        foreach ($connections as $connectionId) {
            $configId = $connectionId . '.configuration';
            if (!$container->hasDefinition($configId)) {
                continue;
            }
            $dbalConfigDef = $container->getDefinition($configId);

            // Wrap any logger already attached to this configuration in a chain.
            $existingLogger = null;
            foreach ($dbalConfigDef->getMethodCalls() as $call) {
                if ($call[0] === 'setSQLLogger' && isset($call[1][0])) {
                    $existingLogger = $call[1][0];
                    break;
                }
            }

            if ($existingLogger !== null) {
                // Chain our adapter with the existing logger.
                $chainId = "beem.doctrine.logger_chain.{$connectionId}";
                $chainDef = new Definition(\Doctrine\DBAL\Logging\LoggerChain::class, [
                    [new Reference('beem.doctrine.sql_logger_adapter'), $existingLogger],
                ]);
                $container->setDefinition($chainId, $chainDef);
                $dbalConfigDef->addMethodCall('setSQLLogger', [new Reference($chainId)]);
            } else {
                $dbalConfigDef->addMethodCall('setSQLLogger', [new Reference('beem.doctrine.sql_logger_adapter')]);
            }
        }
    }
}
