<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Extension;

use Mak\AtomicAssetsBundle\NodeVisitor\CssScopeNodeVisitor;
use Mak\AtomicAssetsBundle\NodeVisitor\AtomicAssetNodeVisitor;
use Mak\AtomicAssetsBundle\TokenParser\ImportModuleTokenParser;
use RuntimeException;
use Twig\Extension\AbstractExtension;
use Twig\TokenParser\TokenParserInterface;
use Twig\TwigFunction;

final class AtomicAssetsExtension extends AbstractExtension
{
    private const BASE_ENCODE_TABLES = [
        26 => 'abcdefghijklmnopqrstuvwxyz',
        32 => '123456789abcdefghjkmnpqrstuvwxyz',
        36 => '0123456789abcdefghijklmnopqrstuvwxyz',
        49 => 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ',
        52 => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        58 => '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ',
        62 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        64 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_',
    ];

    private readonly string $hashName;

    private readonly string $hashFunction;

    private readonly string $hashDigest;

    private readonly int $hashDigestLength;

    public function __construct(
        private readonly string $projectDir,
        private string $localIdentName = '[hash:base64:5]',
        private readonly ?string $localIdentHashSalt = null,
        private readonly string $assetTargetPath = 'build/atomic_assets',
    ) {
        preg_match("/\[(?:([^:\]]+):)?(?:(hash|contenthash|fullhash))(?::([a-z][a-z0-9]*))?(?::(\d+))?\]/i", $this->localIdentName, $matches);

        if ([] === $matches) {
            throw new RuntimeException('Invalid localIdentName given');
        }

        $this->hashName = $matches[2];
        $this->hashFunction = $matches[1] === "" ? 'md4' : $matches[1];
        $this->hashDigest = $matches[3] ?? 'base64';
        $this->hashDigestLength = isset($matches[4]) ? intval($matches[4]) : 20;

        $this->localIdentName = preg_replace_callback(
            '/\[(?:([^:\]]+):)?(?:hash|contenthash|fullhash)(?::([a-z][a-z0-9]*))?(?::(\d+))?\]/i',
            fn ($matches): string => 'fullhash' === $this->hashName ? '[fullhash]' : '[contenthash]',
            $this->localIdentName
        );
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
            new CssScopeNodeVisitor($this->projectDir),
            new AtomicAssetNodeVisitor($this->assetTargetPath, $this->projectDir),
        ];
    }

    /** @param string $asset */
    public function atomicAsset(string $asset, ?string $target = null): never
    {
        throw new \LogicException('atomic_asset() must use string literals; it is resolved when Twig compiles the template.');
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

            $hashedScopes[] = $this->hash($modulePath, $scope);
        }

        return implode(' ', $hashedScopes);
    }

    private function hash(string $relativeModulePath, string $scope): string
    {
        $relativeModulePath = str_replace('\\', '/', $relativeModulePath);
        $localIdentHash = '';

        for ($tier = 0; strlen($localIdentHash) < $this->hashDigestLength; ++$tier) {
            $contentToHash = '';
            if (isset($this->localIdentHashSalt)) {
                $contentToHash .= $this->localIdentHashSalt;
            }

            $tierSalt = pack('V', $tier); // Assuming $tier is already defined

            $contentToHash .= $tierSalt;

            $contentToHash .= $relativeModulePath.chr(0).$scope;

            $localIdentHash .= $this->encodeHashDigest(hash($this->hashFunction, $contentToHash, true));

            // Remove all leading digits
            $localIdentHash = preg_replace("/^\d+/", '', $localIdentHash);
            // Replace all slashes with underscores (same as in base64url)
            $localIdentHash = str_replace('/', '_', $localIdentHash);
            // Remove everything that is not an alphanumeric or underscore
            $localIdentHash = preg_replace('/[^A-Za-z0-9_]+/', '', $localIdentHash);
            // Shorten to $hashDigestLength
            $localIdentHash = substr((string) $localIdentHash, 0, $this->hashDigestLength);
        }

        $hashPlaceholder = 'fullhash' === $this->hashName ? '[fullhash]' : '[contenthash]';

        return str_replace($hashPlaceholder, $localIdentHash, $this->localIdentName);
    }

    private function encodeHashDigest(string $hash): string
    {
        if ('hex' === $this->hashDigest) {
            return bin2hex($hash);
        }

        if ('base64' === $this->hashDigest) {
            return base64_encode($hash);
        }

        if ('base64safe' === $this->hashDigest) {
            return $this->encodeBufferToBase($hash, 64);
        }

        if (1 === preg_match('/^base(26|32|36|49|52|58|62)$/', $this->hashDigest, $matches)) {
            return $this->encodeBufferToBase($hash, intval($matches[1]));
        }

        throw new RuntimeException(sprintf('Unsupported hash digest "%s"', $this->hashDigest));
    }

    private function encodeBufferToBase(string $buffer, int $base): string
    {
        $table = self::BASE_ENCODE_TABLES[$base];
        $bufferLength = strlen($buffer);
        $words = [];

        for ($offset = 0; $offset < $bufferLength; $offset += 4) {
            $word = 0;
            for ($byte = 0; $byte < 4 && $offset + $byte < $bufferLength; ++$byte) {
                $word |= ord($buffer[$offset + $byte]) << (8 * $byte);
            }

            $words[] = $word;
        }

        $output = '';
        $length = (int) ceil(($bufferLength * 8) / log($base, 2));
        for ($index = 0; $index < $length; ++$index) {
            $carry = 0;
            for ($wordIndex = count($words) - 1; $wordIndex >= 0; --$wordIndex) {
                $value = $carry * 0x100000000 + $words[$wordIndex];
                $carry = $value % $base;
                $words[$wordIndex] = intdiv($value, $base);
            }

            $output = $table[$carry].$output;
        }

        return $output;
    }
}
