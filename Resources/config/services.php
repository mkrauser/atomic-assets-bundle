<?php

namespace Mak\AtomicAssetsBundle\Component\DependencyInjection\Loader\Configurator;

use Mak\AtomicAssetsBundle\Extension\AtomicAssetsExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('twig.extension.mak_atomic_assets', AtomicAssetsExtension::class)
        ->arg(0, param('mak_atomic_assets.localIdentContext'))
        ->arg(1, param('mak_atomic_assets.localIdentName'))
        ->arg(2, param('mak_atomic_assets.localIdentHashSalt'))
        ->arg(3, param('mak_atomic_assets.assetTargetPath'))
        ->tag('twig.extension');
};