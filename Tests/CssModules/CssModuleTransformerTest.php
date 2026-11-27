<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Tests\CssModules;

use Mak\AtomicAssetsBundle\CssModules\CssModuleTransformer;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CssModuleTransformerTest extends TestCase
{
    #[TestWith(['.a { color: red }', '.x-a { color: red }'])]
    #[TestWith(['.a.b, .c > .d:hover {}', '.x-a.x-b, .x-c > .x-d:hover {}'])]
    #[TestWith(['div.a::before, a[href] .b {}', 'div.x-a::before, a[href] .x-b {}'])]
    #[TestWith([':is(.a, .b):not(.c) :has(> .d) {}', ':is(.x-a, .x-b):not(.x-c) :has(> .x-d) {}'])]
    #[TestWith(['.-a, ._b, .--c, .é {}', '.x--a, .x-_b, .x---c, .x-é {}'])]
    public function testClassSelectorsAreRenamed(string $css, string $expected): void
    {
        $this->assertSame($expected, $this->transform($css));
    }

    #[TestWith([':global(.a) .b {}', '.a .x-b {}'])]
    #[TestWith(['.a :global(.b, .c) {}', '.x-a .b, .c {}'])]
    #[TestWith(['.a :global .b .c, .d {}', '.x-a .b .c, .x-d {}'])]
    #[TestWith([':global .a :local(.b) {}', '.a .x-b {}'])]
    #[TestWith([':global .a :local .b {}', '.a .x-b {}'])]
    public function testGlobalSelectorsAreLeftAlone(string $css, string $expected): void
    {
        $this->assertSame($expected, $this->transform($css));
    }

    public function testNestedRulesAreRenamedAndDeclarationsAreNot(): void
    {
        $css = <<<'CSS'
            .a {
                padding: .5em 1.5em;
                font: 12px/1.5 "x.y", serif;
                & .b { margin: 0 }
                &.c:hover { color: red; }
            }
            CSS;

        $this->assertSame(<<<'CSS'
            .x-a {
                padding: .5em 1.5em;
                font: 12px/1.5 "x.y", serif;
                & .x-b { margin: 0 }
                &.x-c:hover { color: red; }
            }
            CSS, $this->transform($css));
    }

    public function testAtRulePreludesAreLeftAloneButTheirRulesAreRenamed(): void
    {
        $css = '@media (min-width: 40.5em) { .a { x: y } } @supports selector(.b) { .c {} } @keyframes k { 12.5% { x: y } } @import "x.css"; @layer base;';

        $this->assertSame(
            '@media (min-width: 40.5em) { .x-a { x: y } } @supports selector(.b) { .x-c {} } @keyframes k { 12.5% { x: y } } @import "x.css"; @layer base;',
            $this->transform($css),
        );
    }

    public function testCommentsStringsAndUrlsAreLeftAlone(): void
    {
        $css = <<<'CSS'
            /* .a { } */
            .b /* .c */ { background: url(data:image/png;base64,iVBOR.x{}) }
            .d[data-x=".e"]::after { content: ".f;}{" }
            CSS;

        $this->assertSame(<<<'CSS'
            /* .a { } */
            .x-b /* .c */ { background: url(data:image/png;base64,iVBOR.x{}) }
            .x-d[data-x=".e"]::after { content: ".f;}{" }
            CSS, $this->transform($css));
    }

    public function testEscapedClassNamesAreRenamedByTheirValue(): void
    {
        $renamed = [];
        $output = (new CssModuleTransformer())->transform('.sm\:flex, .\31 0 {}', function (string $class) use (&$renamed): string {
            $renamed[] = $class;

            return 'x-'.$class;
        });

        $this->assertSame(['sm:flex', '10'], $renamed);
        $this->assertSame('.x-sm\:flex, .x-10 {}', $output);
    }

    private function transform(string $css): string
    {
        return (new CssModuleTransformer())->transform($css, static fn (string $class): string => 'x-'.$class);
    }
}
