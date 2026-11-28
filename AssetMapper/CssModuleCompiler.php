<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\AssetMapper;

use Mak\AtomicAssetsBundle\CssModules\CssModuleTransformer;
use Mak\AtomicAssetsBundle\Hashing\LocalIdentHasher;
use Mak\AtomicAssetsBundle\Hashing\ModulePath;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Scopes the class names of every *.module.css file AssetMapper serves, with the hashes scope() puts in the templates.
 */
final class CssModuleCompiler implements AssetCompilerInterface
{
    public function __construct(
        private readonly LocalIdentHasher $hasher,
        private readonly string $localIdentContext,
        private readonly CssModuleTransformer $transformer = new CssModuleTransformer(),
    ) {
    }

    public function supports(MappedAsset $asset): bool
    {
        return str_ends_with($asset->sourcePath, '.module.css');
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        // The same path the importModule tag hashes with: the real path, relative to the localIdentContext
        $modulePath = ModulePath::relativeTo(realpath($asset->sourcePath) ?: $asset->sourcePath, $this->localIdentContext);

        return $this->transformer->transform($content, fn (string $class): string => $this->hasher->hash($modulePath, $class));
    }
}
