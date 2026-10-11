<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Tests\Extension;

use Mak\AtomicAssetsBundle\Extension\AtomicAssetsExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CssModuleExtensionTest extends TestCase
{

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
}
