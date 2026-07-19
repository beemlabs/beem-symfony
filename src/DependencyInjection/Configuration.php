<?php

declare(strict_types=1);

namespace Beem\SymfonyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Semantic configuration tree for the Beem bundle.
 *
 *   beem:
 *     dsn: "%env(BEEM_DSN)%"
 *     sample_rate: 1.0
 *     environment: "%kernel.environment%"
 *     release: null
 *     default_tags: { service: "my-svc" }
 *     instrument_doctrine: true
 *     instrument_console: true
 *     instrument_messenger: true
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('beem');
        $root = $tb->getRootNode();

        $root
            ->children()
                ->scalarNode('dsn')
                    ->info('Beem ingest DSN: {scheme}://{public_key}@{host}/{project_id}')
                    ->isRequired()
                ->end()
                ->floatNode('sample_rate')->defaultValue(1.0)->min(0.0)->max(1.0)->end()
                ->integerNode('max_batch_size')->defaultValue(500)->min(1)->end()
                ->integerNode('flush_timeout_ms')->defaultValue(2000)->min(100)->end()
                ->scalarNode('environment')->defaultNull()->end()
                ->scalarNode('release')->defaultNull()->end()
                ->arrayNode('default_tags')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('instrument_doctrine')->defaultTrue()->end()
                ->booleanNode('instrument_console')->defaultTrue()->end()
                ->booleanNode('instrument_messenger')->defaultTrue()->end()
            ->end();

        return $tb;
    }
}
