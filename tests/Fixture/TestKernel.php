<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\Tests\Fixture;

use Beem\SymfonyBundle\BeemSymfonyBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal Symfony kernel for testing the bundle in isolation.
 *
 * Wires only the bundles and routes needed to boot the container and
 * verify that event subscribers + compiler pass are registered.
 */
final class TestKernel extends Kernel
{
    private string $beemConfig;

    /**
     * @param array<string, mixed> $beemConfig The raw `beem` extension config.
     */
    public function __construct(array $beemConfig = [])
    {
        // Use a unique cache dir per config so the container re-compiles.
        $hash = substr(md5(serialize($beemConfig)), 0, 8);
        $this->cacheDir = sys_get_temp_dir() . "/beem-symfony-tests/{$hash}/cache";
        $this->logDir = sys_get_temp_dir() . "/beem-symfony-tests/{$hash}/logs";
        parent::__construct('test', false);
        $this->beemConfig = serialize($beemConfig);
    }

    public function registerBundles(): iterable
    {
        yield new \Symfony\Bundle\FrameworkBundle\FrameworkBundle();
        yield new BeemSymfonyBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $config = unserialize($this->beemConfig);
            $container->loadFromExtension('beem', $config);
        });

        // Minimal framework config so the kernel boots.
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secrets' => false,
                'error_controller' => null,
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getLogDir(): string
    {
        return $this->logDir;
    }
}
