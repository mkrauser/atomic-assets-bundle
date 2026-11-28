<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('atomic_assets');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('localIdentName')
                ->defaultValue('[hash:base64:5]')
            ->end()
            ->scalarNode('localIdentContext')
                ->defaultValue('%kernel.project_dir%')
            ->end()
            ->scalarNode('localIdentHashSalt')
                ->defaultNull()
            ->end()
            ->scalarNode('assetPathPrefix')
                ->defaultValue('build/')
                ->info('Prefix prepended to project-relative asset paths for Symfony manifest lookup, including the trailing slash.')
            ->end()
            ->arrayNode('asset_mapper')
                ->info('Serve the CSS modules through AssetMapper instead of a bundler. Requires symfony/asset-mapper.')
                ->canBeEnabled()
                ->children()
                    ->scalarNode('stylesheet')
                        ->defaultNull()
                        ->info('An (empty) stylesheet in a mapped asset path, filled with an @import of every module. Link it from your layout, or import it from app.js.')
                        ->example('%kernel.project_dir%/assets/styles/atomic-assets.css')
                    ->end()
                    ->arrayNode('module_paths')
                        ->info('Where the stylesheet looks for *.module.css files. They must be in mapped asset paths too.')
                        ->scalarPrototype()->end()
                        ->defaultValue(['%kernel.project_dir%/templates'])
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
