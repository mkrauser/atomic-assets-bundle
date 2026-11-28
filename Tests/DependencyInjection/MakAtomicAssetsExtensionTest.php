<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Tests\DependencyInjection;

use Mak\AtomicAssetsBundle\DependencyInjection\MakAtomicAssetsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MakAtomicAssetsExtensionTest extends TestCase
{
    public function testTheAssetMapperIntegrationIsOffByDefault(): void
    {
        $container = $this->load([]);

        $this->assertFalse($container->hasDefinition('mak_atomic_assets.asset_mapper.css_module_compiler'));
        $this->assertFalse($container->hasDefinition('mak_atomic_assets.asset_mapper.module_stylesheet_compiler'));
        $this->assertSame([], $container->getExtensionConfig('framework'));
    }

    public function testTheCompilersAreRegisteredWhenEnabled(): void
    {
        $container = $this->load(['asset_mapper' => ['stylesheet' => '/app/assets/styles/atomic-assets.css']]);

        $this->assertSame(
            [[]],
            $container->getDefinition('mak_atomic_assets.asset_mapper.css_module_compiler')->getTag('asset_mapper.compiler'),
        );
        $stylesheet = $container->getDefinition('mak_atomic_assets.asset_mapper.module_stylesheet_compiler');
        $this->assertSame([['priority' => 10]], $stylesheet->getTag('asset_mapper.compiler'));
        $this->assertSame(['/app/assets/styles/atomic-assets.css', ['%kernel.project_dir%/templates']], $stylesheet->getArguments());
    }

    public function testTheStylesheetIsOptional(): void
    {
        $container = $this->load(['asset_mapper' => true]);

        $this->assertTrue($container->hasDefinition('mak_atomic_assets.asset_mapper.css_module_compiler'));
        $this->assertFalse($container->hasDefinition('mak_atomic_assets.asset_mapper.module_stylesheet_compiler'));
    }

    public function testTemplatesAreExcludedFromTheMappedAssets(): void
    {
        $container = $this->load(['asset_mapper' => true]);

        $this->assertSame([['asset_mapper' => ['excluded_patterns' => ['*.twig']]]], $container->getExtensionConfig('framework'));
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());
        $extension = new MakAtomicAssetsExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('mak_atomic_assets', $config);

        $extension->prepend($container);
        $extension->load($container->getExtensionConfig('mak_atomic_assets'), $container);

        return $container;
    }
}
