<?php

/*
 * This file is part of the CssModuleBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\NodeVisitor;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final class AtomicAssetNodeVisitor implements NodeVisitorInterface
{
    /** @var array<string, string> */
    private array $publishedTargetSources = [];

    public function __construct(
        private readonly string $assetTargetPath,
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof FunctionExpression) {
            return $node;
        }

        if ('atomic_asset' !== $node->getAttribute('name')) {
            return $node;
        }

        $templatePath = $node->getSourceContext()->getPath();
        if ('' === $templatePath) {
            return new ConstantExpression('', $node->getTemplateLine());
        }

        $arguments = $node->getNode('arguments');
        $pathNode = $this->getArgumentNode($arguments, '0', 'path');
        if (null === $pathNode) {
            throw new RuntimeException('atomic_asset() requires a path argument.');
        }

        $relativeAsset = $this->getStringConstant($pathNode);
        if (null === $relativeAsset) {
            throw new RuntimeException('The path passed to atomic_asset() must be a string literal.');
        }

        $targetNode = $this->getArgumentNode($arguments, '1', 'target');
        $targetBasename = null;
        if (null !== $targetNode) {
            $targetBasename = $this->getStringConstant($targetNode);
            if (null === $targetBasename) {
                throw new RuntimeException('The target filename passed to atomic_asset() must be a string literal.');
            }
        }

        $absoluteAsset = $this->resolveColocatedPath($templatePath, $relativeAsset);
        $basename = null !== $targetBasename
            ? $this->validatedTargetBasename($targetBasename)
            : $this->sourceBasename($absoluteAsset);
        $url = $this->publishAsset($absoluteAsset, $basename);

        return new ConstantExpression($url, $node->getTemplateLine());
    }

    public function getPriority(): int
    {
        return -10;
    }

    private function getArgumentNode(Node $arguments, string $positionalKey, string $namedKey): ?Node
    {
        if ($arguments->hasNode($positionalKey)) {
            return $arguments->getNode($positionalKey);
        }

        if ($arguments->hasNode($namedKey)) {
            return $arguments->getNode($namedKey);
        }

        return null;
    }

    private function getStringConstant(Node $node): ?string
    {
        if (!$node instanceof ConstantExpression) {
            return null;
        }

        $value = $node->getAttribute('value');

        return \is_string($value) ? $value : null;
    }

    private function resolveColocatedPath(string $templatePath, string $relativeAsset): string
    {
        $templateDirectory = Path::getDirectory($templatePath);
        $baseDirectory = realpath($templateDirectory);
        if (false === $baseDirectory) {
            throw new RuntimeException(sprintf('Template directory "%s" could not be resolved.', $templateDirectory));
        }

        $candidate = Path::join($baseDirectory, $relativeAsset);
        $assetPath = realpath($candidate);
        if (false === $assetPath) {
            throw new RuntimeException(sprintf('Colocated asset "%s" does not exist.', $relativeAsset));
        }

        $this->assertPathInsideDirectory($assetPath, $baseDirectory);

        return $assetPath;
    }

    private function assertPathInsideDirectory(string $path, string $baseDirectory): void
    {
        $base = realpath($baseDirectory);
        if (false === $base) {
            throw new RuntimeException(sprintf('Base directory "%s" could not be resolved.', $baseDirectory));
        }

        if (!Path::isBasePath($base, $path)) {
            throw new RuntimeException('Colocated asset path escapes its allowed directory.');
        }
    }

    private function publishAsset(string $absoluteAssetPath, string $targetBasename): string
    {
        $targetDirectory = $this->resolveTargetDirectory();
        if (!$this->filesystem->exists($targetDirectory)) {
            $this->filesystem->mkdir($targetDirectory);
        } elseif (!is_dir($targetDirectory)) {
            throw new RuntimeException(sprintf(
                'Atomic assets target path "%s" exists but is not a directory.',
                $targetDirectory,
            ));
        }

        $targetPath = Path::join($targetDirectory, $targetBasename);
        if (isset($this->publishedTargetSources[$targetPath])) {
            if ($this->publishedTargetSources[$targetPath] !== $absoluteAssetPath) {
                throw new RuntimeException(sprintf(
                    'Atomic asset target "%s" would be overwritten by "%s".',
                    $targetPath,
                    $absoluteAssetPath,
                ));
            }

            return '/'.Path::join($this->normalizedTargetPath(), $targetBasename);
        }

        if ($this->filesystem->exists($targetPath)) {
            if ($this->existingTargetMatchesSource($absoluteAssetPath, $targetPath)) {
                $this->publishedTargetSources[$targetPath] = $absoluteAssetPath;

                return '/'.Path::join($this->normalizedTargetPath(), $targetBasename);
            }

            throw new RuntimeException(sprintf('Atomic asset target "%s" already exists.', $targetPath));
        }

        $this->filesystem->copy($absoluteAssetPath, $targetPath, false);
        $this->publishedTargetSources[$targetPath] = $absoluteAssetPath;

        return '/'.Path::join($this->normalizedTargetPath(), $targetBasename);
    }

    private function existingTargetMatchesSource(string $absoluteAssetPath, string $targetPath): bool
    {
        if (!is_file($targetPath)) {
            return false;
        }

        $sourceHash = sha1_file($absoluteAssetPath);
        $targetHash = sha1_file($targetPath);
        if (!is_string($sourceHash) || !is_string($targetHash)) {
            return false;
        }

        return hash_equals($sourceHash, $targetHash);
    }

    private function resolveTargetDirectory(): string
    {
        $publicDirectory = Path::canonicalize(Path::join($this->projectDir, 'public'));
        $targetDirectory = Path::canonicalize(Path::makeAbsolute($this->normalizedTargetPath(), $publicDirectory));

        if (!Path::isBasePath($publicDirectory, $targetDirectory)) {
            throw new RuntimeException('The configured atomic assets target path must stay inside public/.');
        }

        return $targetDirectory;
    }

    private function normalizedTargetPath(): string
    {
        $targetPath = trim($this->assetTargetPath);
        if ('' === $targetPath) {
            throw new RuntimeException('The configured atomic assets target path must not be empty.');
        }

        $targetPath = str_replace('\\', '/', $targetPath);
        $targetPath = trim($targetPath, '/');
        if ('' === $targetPath) {
            throw new RuntimeException('The configured atomic assets target path must not be empty.');
        }

        if (Path::isAbsolute($targetPath)) {
            throw new RuntimeException('The configured atomic assets target path must be relative to public/.');
        }

        return Path::canonicalize($targetPath);
    }

    private function sourceBasename(string $absoluteAssetPath): string
    {
        $basename = basename(str_replace('\\', '/', $absoluteAssetPath));
        if ('' === $basename || '.' === $basename || '..' === $basename) {
            throw new RuntimeException(sprintf('Could not determine target basename from "%s".', $absoluteAssetPath));
        }

        return $basename;
    }

    private function validatedTargetBasename(string $target): string
    {
        if ('' === $target) {
            throw new RuntimeException('atomic_asset() target filename must not be empty.');
        }

        if (str_contains($target, '/') || str_contains($target, '\\')) {
            throw new RuntimeException('atomic_asset() target filename must be basename only (no path segments).');
        }

        if (str_contains($target, "\0")) {
            throw new RuntimeException('atomic_asset() target filename is invalid.');
        }

        if ('..' === $target || str_starts_with($target, '..')) {
            throw new RuntimeException('atomic_asset() target filename is invalid.');
        }

        return $target;
    }
}
