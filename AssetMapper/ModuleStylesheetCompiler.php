<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\AssetMapper;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Path;

/**
 * Fills the configured stylesheet with an @import of every *.module.css file under the module directories, the way a
 * bundler's glob import of the templates pulls every module into the app's CSS.
 *
 * It runs before AssetMapper's own CSS compiler, which then points each @import at the module's versioned file.
 */
final class ModuleStylesheetCompiler implements AssetCompilerInterface
{
    /**
     * @param string       $stylesheet        The stylesheet to fill: an (empty) file in a mapped asset path
     * @param list<string> $moduleDirectories Where to look for *.module.css files, all of them in mapped asset paths
     */
    public function __construct(
        private readonly string $stylesheet,
        private readonly array $moduleDirectories,
    ) {
    }

    public function supports(MappedAsset $asset): bool
    {
        $stylesheet = realpath($this->stylesheet);

        return false !== $stylesheet && (realpath($asset->sourcePath) ?: $asset->sourcePath) === $stylesheet;
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        $stylesheetDirectory = \dirname(realpath($asset->sourcePath) ?: $asset->sourcePath);
        $imports = [];

        foreach ($this->moduleDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            // So adding or removing a module in development rebuilds the stylesheet
            $asset->addFileDependency($directory);

            foreach ($this->findModules($directory) as $module) {
                $imports[$module] = \sprintf('@import "%s";', Path::makeRelative($module, $stylesheetDirectory));
            }
        }

        ksort($imports);

        // @import has to come before every other rule
        return ([] === $imports ? '' : implode("\n", $imports)."\n").$content;
    }

    /** @return iterable<string> */
    private function findModules(string $directory): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.module.css')) {
                yield realpath($file->getPathname()) ?: $file->getPathname();
            }
        }
    }
}
