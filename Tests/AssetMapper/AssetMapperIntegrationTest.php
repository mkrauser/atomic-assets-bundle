<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Tests\AssetMapper;

use Mak\AtomicAssetsBundle\AssetMapper\CssModuleCompiler;
use Mak\AtomicAssetsBundle\AssetMapper\ModuleStylesheetCompiler;
use Mak\AtomicAssetsBundle\Extension\AtomicAssetsExtension;
use Mak\AtomicAssetsBundle\Hashing\LocalIdentHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactory;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolver;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Serves the fixture project through a real AssetMapper, with the compilers in the order their tag priorities give them.
 */
class AssetMapperIntegrationTest extends TestCase
{
    private string $projectDir;

    private AssetMapper $assetMapper;

    protected function setUp(): void
    {
        $this->projectDir = (string) realpath(__DIR__.'/../Fixtures/AssetMapper');

        $compiler = new AssetMapperCompiler(
            [
                new ModuleStylesheetCompiler($this->projectDir.'/assets/styles/atomic-assets.css', [$this->projectDir.'/templates']),
                new CssModuleCompiler(new LocalIdentHasher(), $this->projectDir),
                new CssAssetUrlCompiler(AssetCompilerInterface::MISSING_IMPORT_STRICT),
            ],
            fn (): AssetMapper => $this->assetMapper,
        );

        $this->assetMapper = new AssetMapper(
            new AssetMapperRepository(
                [$this->projectDir.'/assets' => '', $this->projectDir.'/templates/components' => 'templates/components'],
                $this->projectDir,
                ['#\.twig$#'],
            ),
            new MappedAssetFactory(new PublicAssetsPathResolver('/assets/'), $compiler, $this->projectDir.'/vendor'),
            new CompiledAssetMapperConfigReader(sys_get_temp_dir().'/atomic-assets-no-compiled-config'),
        );
    }

    public function testTheStylesheetImportsTheVersionedModules(): void
    {
        $stylesheet = $this->assetMapper->getAsset('styles/atomic-assets.css');
        $button = $this->assetMapper->getAsset('templates/components/button/button.module.css');
        $card = $this->assetMapper->getAsset('templates/components/card/card.module.css');

        $this->assertNotNull($stylesheet);
        $this->assertNotNull($button);
        $this->assertNotNull($card);
        // Relative to /assets/styles/, pointing at each module's versioned file
        $this->assertSame([
            '@import "../templates/components/button/'.basename($button->publicPath).'";',
            '@import "../templates/components/card/'.basename($card->publicPath).'";',
            '/* Filled with an @import of every CSS module by the AtomicAssetsBundle */',
        ], array_slice(explode("\n", (string) $stylesheet->content), 0, 3));
        $this->assertStringContainsString('-', basename($button->publicPath, '.css'));
        $this->assertContains($this->projectDir.'/templates', $stylesheet->getFileDependencies());
    }

    public function testTheModuleClassesMatchTheTemplate(): void
    {
        $twig = new Environment(new FilesystemLoader($this->projectDir.'/templates'), ['cache' => false]);
        $twig->addExtension(new AtomicAssetsExtension($this->projectDir));

        $html = $twig->render('components/button/button.html.twig', ['label' => 'Go']);
        preg_match_all('/class="([^"]+)"/', $html, $matches);
        $classes = explode(' ', implode(' ', $matches[1]));

        $css = (string) $this->assetMapper->getAsset('templates/components/button/button.module.css')?->content;

        $this->assertCount(3, $classes);
        foreach ($classes as $class) {
            $this->assertStringContainsString('.'.$class, $css);
        }
        [$button, $primary, $icon] = $classes;
        $this->assertStringContainsString("&.$primary:hover", $css);
        $this->assertStringContainsString(":global(.legacy)", file_get_contents($this->projectDir.'/templates/components/button/button.module.css') ?: '');
        $this->assertStringContainsString(".legacy .$button > .$icon", $css);
        $this->assertStringContainsString('padding: .5em 1em;', $css);
    }

    public function testTemplatesAreNotMapped(): void
    {
        $this->assertNull($this->assetMapper->getAsset('templates/components/button/button.html.twig'));
    }
}
