<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\DependencyInjection;

use LogicException;
use Mak\AtomicAssetsBundle\AssetMapper\CssModuleCompiler;
use Mak\AtomicAssetsBundle\AssetMapper\ModuleStylesheetCompiler;
use Mak\AtomicAssetsBundle\Hashing\LocalIdentHasher;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

class MakAtomicAssetsExtension extends Extension implements PrependExtensionInterface
{
    /**
     * The AssetMapper integration maps template directories, so keep the templates themselves out of the public assets.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));

        if ($config['asset_mapper']['enabled'] && $container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => ['excluded_patterns' => ['*.twig']],
            ]);
        }
    }

    /** @inheritDoc */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('mak_atomic_assets.localIdentContext', $config['localIdentContext']);
        $container->setParameter('mak_atomic_assets.localIdentName', $config['localIdentName']);
        $container->setParameter('mak_atomic_assets.localIdentHashSalt', $config['localIdentHashSalt']);
        $container->setParameter('mak_atomic_assets.assetPathPrefix', $config['assetPathPrefix']);

        if ($config['asset_mapper']['enabled']) {
            $this->registerAssetMapperCompilers($config['asset_mapper'], $container);
        }
    }

    /** @param array{stylesheet: ?string, module_paths: list<string>} $config */
    private function registerAssetMapperCompilers(array $config, ContainerBuilder $container): void
    {
        if (!interface_exists(AssetCompilerInterface::class)) {
            throw new LogicException('"mak_atomic_assets.asset_mapper" needs AssetMapper: run "composer require symfony/asset-mapper".');
        }

        $container->register('mak_atomic_assets.local_ident_hasher', LocalIdentHasher::class)
            ->setArguments([
                new Parameter('mak_atomic_assets.localIdentName'),
                new Parameter('mak_atomic_assets.localIdentHashSalt'),
            ]);

        $container->register('mak_atomic_assets.asset_mapper.css_module_compiler', CssModuleCompiler::class)
            ->setArguments([
                new Reference('mak_atomic_assets.local_ident_hasher'),
                new Parameter('mak_atomic_assets.localIdentContext'),
            ])
            ->addTag('asset_mapper.compiler');

        if (null !== $config['stylesheet']) {
            $container->register('mak_atomic_assets.asset_mapper.module_stylesheet_compiler', ModuleStylesheetCompiler::class)
                ->setArguments([$config['stylesheet'], $config['module_paths']])
                // Before AssetMapper's CSS compiler, which versions the @import paths this one adds
                ->addTag('asset_mapper.compiler', ['priority' => 10]);
        }
    }

    public function getAlias(): string
    {
        return "mak_atomic_assets";
    }
}
