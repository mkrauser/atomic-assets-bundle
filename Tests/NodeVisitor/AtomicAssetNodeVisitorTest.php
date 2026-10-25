<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Tests\NodeVisitor;

use Mak\AtomicAssetsBundle\Extension\AtomicAssetsExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

final class AtomicAssetNodeVisitorTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = Path::join(
            sys_get_temp_dir(),
            'atomic-assets-bundle-test-'.bin2hex(random_bytes(8)),
        );

        $this->filesystem->mkdir(Path::join($this->projectDir, 'templates'));
        $this->filesystem->mkdir(Path::join($this->projectDir, 'public'));
        $this->filesystem->mkdir(Path::join($this->projectDir, 'public/build'));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testAllowsUsingSameAssetMultipleTimesInOneTemplate(): void
    {
        $componentDirectory = Path::join($this->projectDir, 'templates/components/button');
        $this->filesystem->mkdir($componentDirectory);

        file_put_contents(Path::join($componentDirectory, 'icon.svg'), '<svg>icon</svg>');
        file_put_contents(
            Path::join($componentDirectory, 'button.html.twig'),
            "{{ atomic_asset('icon.svg') }}|{{ atomic_asset('icon.svg') }}",
        );
        $this->writeManifest(['build/templates/components/button/icon.svg' => '/build/atomic_assets/icon.123.svg']);

        $output = $this->createTwig()->render('components/button/button.html.twig');
        $targetPath = Path::join($this->projectDir, 'public/build/atomic_assets/icon.svg');

        $this->assertSame('/build/atomic_assets/icon.123.svg|/build/atomic_assets/icon.123.svg', $output);
        $this->assertFileDoesNotExist($targetPath);
    }

    public function testRejectsLegacyTargetFilename(): void
    {
        $componentDirectory = Path::join($this->projectDir, 'templates/components/card');
        $this->filesystem->mkdir($componentDirectory);

        file_put_contents(Path::join($componentDirectory, 'first.svg'), '<svg>first</svg>');
        file_put_contents(Path::join($componentDirectory, 'second.svg'), '<svg>second</svg>');
        file_put_contents(
            Path::join($componentDirectory, 'card.html.twig'),
            "{{ atomic_asset('first.svg', 'shared.svg') }}{{ atomic_asset('second.svg', 'shared.svg') }}",
        );

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('target filenames are now managed by the bundler');

        $this->createTwig()->render('components/card/card.html.twig');
    }

    public function testUsesUpdatedManifestWithoutRecompilingTemplate(): void
    {
        $componentDirectory = Path::join($this->projectDir, 'templates/components/header');
        $this->filesystem->mkdir($componentDirectory);

        file_put_contents(Path::join($componentDirectory, 'logo.svg'), '<svg>logo</svg>');
        file_put_contents(
            Path::join($componentDirectory, 'header.html.twig'),
            "{{ atomic_asset('logo.svg') }}",
        );

        $this->writeManifest(['build/templates/components/header/logo.svg' => '/build/atomic_assets/logo.123.svg']);
        $firstOutput = $this->createTwig()->render('components/header/header.html.twig');
        $this->writeManifest(['build/templates/components/header/logo.svg' => '/build/atomic_assets/logo.456.svg']);
        $secondOutput = $this->createTwig()->render('components/header/header.html.twig');

        $this->assertSame('/build/atomic_assets/logo.123.svg', $firstOutput);
        $this->assertSame('/build/atomic_assets/logo.456.svg', $secondOutput);
    }

    public function testDistinguishesSameFilenameInDifferentComponents(): void
    {
        foreach (['header', 'footer'] as $component) {
            $directory = Path::join($this->projectDir, 'templates', $component);
            $this->filesystem->mkdir($directory);
            file_put_contents($directory.'/view.twig', "{{ atomic_asset(path: 'logo.svg') }}");
        }

        $this->writeManifest([
            'build/templates/header/logo.svg' => '/build/atomic_assets/logo.header.svg',
            'build/templates/footer/logo.svg' => '/build/atomic_assets/logo.footer.svg',
        ]);
        $twig = $this->createTwig();
        $this->assertSame('/build/atomic_assets/logo.header.svg', $twig->render('header/view.twig'));
        $this->assertSame('/build/atomic_assets/logo.footer.svg', $twig->render('footer/view.twig'));
    }

    public function testReportsMissingManifest(): void
    {
        file_put_contents($this->projectDir.'/templates/view.twig', "{{ atomic_asset('logo.svg') }}");
        $this->expectException(\Twig\Error\RuntimeError::class);
        $this->expectExceptionMessage('does not exist');
        $this->createTwig()->render('view.twig');
    }

    public function testReportsAssetMissingFromManifest(): void
    {
        file_put_contents($this->projectDir.'/templates/view.twig', "{{ atomic_asset('logo.svg') }}");
        $this->writeManifest([]);
        $this->expectException(\Twig\Error\RuntimeError::class);
        $this->expectExceptionMessage('templates/logo.svg');
        $this->createTwig()->render('view.twig');
    }

    public function testRejectsPathsOutsideTemplateDirectory(): void
    {
        file_put_contents($this->projectDir.'/templates/view.twig', "{{ atomic_asset('../logo.svg') }}");
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('escapes its allowed directory');
        $this->createTwig()->render('view.twig');
    }

    public function testUsesSymfonyCdnConfiguration(): void
    {
        file_put_contents($this->projectDir.'/templates/view.twig', "{{ atomic_asset('logo.svg') }}");
        $this->writeManifest(['build/templates/logo.svg' => 'build/logo.123.svg']);
        $assets = new Packages(new UrlPackage('https://cdn.example.com', new JsonManifestVersionStrategy(
            $this->projectDir.'/public/build/manifest.json', null, true,
        )));

        $this->assertSame('https://cdn.example.com/build/logo.123.svg', $this->createTwig($assets)->render('view.twig'));
    }

    #[TestWith([''])]
    #[TestWith(['assets/'])]
    public function testSupportsCustomManifestKeyPrefix(string $prefix): void
    {
        file_put_contents($this->projectDir.'/templates/view.twig', "{{ atomic_asset('logo.svg') }}");
        $this->writeManifest([$prefix.'templates/logo.svg' => '/assets/logo.123.svg']);

        $this->assertSame('/assets/logo.123.svg', $this->createTwig(prefix: $prefix)->render('view.twig'));
    }

    /** @param array<string, string> $manifest */
    private function writeManifest(array $manifest): void
    {
        file_put_contents($this->projectDir.'/public/build/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    private function createTwig(?Packages $assets = null, string $prefix = 'build/'): Environment
    {
        $twig = new Environment(new FilesystemLoader(Path::join($this->projectDir, 'templates')), [
            'cache' => false,
            'optimizations' => 0,
        ]);
        $assets ??= new Packages(new Package(new JsonManifestVersionStrategy(
            $this->projectDir.'/public/build/manifest.json', null, true,
        )));
        $twig->addExtension(new AtomicAssetsExtension($this->projectDir, assets: $assets, assetPathPrefix: $prefix));

        return $twig;
    }
}
