<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Extension;

use Mak\AtomicAssetsBundle\Hashing\LocalIdentHasher;
use Mak\AtomicAssetsBundle\NodeVisitor\CssScopeNodeVisitor;
use Mak\AtomicAssetsBundle\NodeVisitor\AtomicAssetNodeVisitor;
use Mak\AtomicAssetsBundle\TokenParser\ImportModuleTokenParser;
use RuntimeException;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TokenParser\TokenParserInterface;
use Twig\TwigFunction;

final class AtomicAssetsExtension extends AbstractExtension
{
    private readonly LocalIdentHasher $hasher;

    public function __construct(
        private readonly string $projectDir,
        string $localIdentName = '[hash:base64:5]',
        ?string $localIdentHashSalt = null,
        private readonly ?Packages $assets = null,
        private readonly string $assetPathPrefix = 'build/',
    ) {
        $this->hasher = new LocalIdentHasher($localIdentName, $localIdentHashSalt);
    }

    /** @return TwigFunction[] */
    public function getFunctions()
    {
        return [
            new TwigFunction('scope', $this->cssScope(...)),
            new TwigFunction('atomic_asset', $this->atomicAsset(...)),
        ];
    }

    /**
     * @return TokenParserInterface[]
     */
    public function getTokenParsers(): array
    {
        return [
            /*
             * {% importModule "css-file" %}
             */
            new ImportModuleTokenParser(),
        ];
    }

    public function getNodeVisitors(): array
    {
        return [
            new CssScopeNodeVisitor($this->projectDir, $this->cssScope(...)),
            new AtomicAssetNodeVisitor($this->projectDir),
        ];
    }

    public function atomicAsset(string $path): string
    {
        if (null === $this->assets) {
            throw new RuntimeException('Configure framework.assets to resolve atomic_asset() URLs.');
        }

        return $this->assets->getUrl($this->assetPathPrefix.$path);
    }

    /** @param string|string[] $scopes */
    public function cssScope(array|string $scopes, string $modulePath): string
    {
        if (!is_array($scopes)) {
            $scopes = [$scopes];
        }

        $hashedScopes = [];
        foreach ($scopes as $scope) {
            // @phpstan-ignore-next-line
            if (!is_string($scope)) {
                throw new RuntimeException('Scope must be a string');
            }

            $hashedScopes[] = $this->hasher->hash($modulePath, $scope);
        }

        return implode(' ', $hashedScopes);
    }
}
