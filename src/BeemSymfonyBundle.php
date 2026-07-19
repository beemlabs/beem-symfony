<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle;

use Beem\SymfonyBundle\DependencyInjection\BeemExtension;
use Beem\SymfonyBundle\DependencyInjection\Compiler\RegisterDoctrineLoggerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Beem Symfony bundle — registers the core client and the
 * kernel/console/Doctrine/Messenger event subscribers that auto-trace the application.
 *
 * Register in {@code bundles.php} or {@code Kernel::registerBundles()}:
 *   {@code Beem\SymfonyBundle\BeemSymfonyBundle::class => ['all' => true]}
 *
 * Minimal config:
 *   {@code beem: { dsn: "%env(BEEM_DSN)%" }}
 */
final class BeemSymfonyBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterDoctrineLoggerPass());
    }

    /**
     * Instantiate the client at kernel boot. Env placeholders in the DSN are
     * resolved by now, and initializing here means early errors (before the
     * first request event) are still captured. The factory side effect also
     * configures the global {@see \Beem\Beem} facade.
     */
    public function boot(): void
    {
        $this->container->get('beem.client');
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new BeemExtension();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
