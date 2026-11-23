<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Tests\Extension;

use Mak\AtomicAssetsBundle\Extension\AtomicAssetsExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

class CssModuleExtensionTest extends TestCase
{
    public function testStaticScopesCompileToConstantsAndDynamicScopesRunAtRenderTime(): void
    {
        $extension = new AtomicAssetsExtension(dirname(__DIR__, 2));
        $twig = new Environment(new ArrayLoader(['scopes' => '']));
        $twig->addExtension($extension);
        $source = new Source("{% importModule 'Tests/Extension/CssModuleExtensionTest.php' %}{{ scope('button') }} {{ scope(['button', 'icon']) }} {{ scope(className) }}", 'scopes', dirname(__DIR__, 2).'/template.twig');
        $compiled = $twig->compileSource($source);
        $button = $extension->cssScope('button', 'Tests/Extension/CssModuleExtensionTest.php');
        $both = $extension->cssScope(['button', 'icon'], 'Tests/Extension/CssModuleExtensionTest.php');

        $this->assertStringContainsString($button, $compiled);
        $this->assertStringContainsString($both, $compiled);
        $this->assertSame(1, substr_count($compiled, '->cssScope('));
        $runtimeButton = $extension->cssScope('button', '');
        $runtimeBoth = $extension->cssScope(['button', 'icon'], '');
        $this->assertSame($runtimeButton.' '.$runtimeBoth.' '.$runtimeButton, $twig->createTemplate($source->getCode(), 'scopes')->render(['className' => 'button']));
    }

    public function testImportModuleTag(): void
    {
        $extension = new AtomicAssetsExtension(__DIR__);
        $twig = new Environment(new ArrayLoader(['template' => 'A{% importModule "button.module.scss" %}B']), [
            'cache' => false,
            'optimizations' => 0,
        ]);
        $twig->addExtension($extension);

        $this->assertSame('AB', $twig->render('template'));
    }

    /** @param string|string[] $css */
    #[TestWith(['button', 'GS4zN'])]
    #[TestWith([['button', 'icon'], 'GS4zN mwX9W'])]
    public function testCssScope(string|array $css, string $expectedHashes): void
    {
        $extension = new AtomicAssetsExtension(__DIR__);

        $this->assertSame($expectedHashes, $extension->cssScope($css, 'styles/button.module.css'));
    }

    public function testCssScopeAppendsSeparatelyEncodedHashTiers(): void
    {
        $extension = new AtomicAssetsExtension(__DIR__, '[hash:base64:40]');

        $this->assertSame(
            'GS4zNsAPADq7eD6HjCRWLQryl6yuvAymcs_T5opn',
            $extension->cssScope('button', 'styles/button.module.css'),
        );
    }

    #[TestWith(['[hash:base64safe:10]', 'JlyicxPVUK'])]
    #[TestWith(['[hash:base32:10]', 'ebsk9t2tyg'])]
    #[TestWith(['[hash:hex:10]', 'e3336c00f0'])]
    public function testCssScopeUsesConfiguredHashDigest(string $localIdentName, string $expectedHash): void
    {
        $extension = new AtomicAssetsExtension(__DIR__, $localIdentName);

        $this->assertSame($expectedHash, $extension->cssScope('button', 'styles/button.module.css'));
    }

    public function testCssScopeNormalizesWindowsPathSeparators(): void
    {
        $extension = new AtomicAssetsExtension(__DIR__);

        $this->assertSame('GS4zN', $extension->cssScope('button', 'styles\\button.module.css'));
    }

    public function testCssScopeReplacesFullHashPlaceholder(): void
    {
        $extension = new AtomicAssetsExtension(__DIR__, '[fullhash:base64:5]');

        $this->assertSame('GS4zN', $extension->cssScope('button', 'styles/button.module.css'));
    }

    public function testCssScopeDefaultsHashLengthToTwenty(): void
    {
        $extension = new AtomicAssetsExtension(__DIR__, '[hash:base64]');

        $this->assertSame('GS4zNsAPADq7eD6HjCRW', $extension->cssScope('button', 'styles/button.module.css'));
    }

    /** @param array{name: string, localIdentName: string, modulePath: string, localName: string, hashSalt?: string, expected: string} $fixture */
    #[DataProvider('provideCssLoaderFixtures')]
    public function testCssScopeMatchesCssLoader(array $fixture): void
    {
        $extension = new AtomicAssetsExtension(
            '/virtual/project',
            $fixture['localIdentName'],
            $fixture['hashSalt'] ?? null,
        );

        $this->assertSame(
            $fixture['expected'],
            $extension->cssScope($fixture['localName'], $fixture['modulePath']),
            $fixture['name'],
        );
    }

    /** @return iterable<string, array{array{name: string, localIdentName: string, modulePath: string, localName: string, hashSalt?: string, expected: string}}> */
    public static function provideCssLoaderFixtures(): iterable
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/CssLoader/hashes.json');
        if (false === $contents) {
            throw new \RuntimeException('Unable to read css-loader hash fixtures');
        }

        /** @var array{fixtures: list<array{name: string, localIdentName: string, modulePath: string, localName: string, hashSalt?: string, expected: string}>} $data */
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        foreach ($data['fixtures'] as $fixture) {
            yield $fixture['name'] => [$fixture];
        }
    }
}
