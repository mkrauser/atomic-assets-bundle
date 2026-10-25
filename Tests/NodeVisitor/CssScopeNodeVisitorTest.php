<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Tests\NodeVisitor;

use Mak\AtomicAssetsBundle\Node\ImportModuleNode;
use Mak\AtomicAssetsBundle\NodeVisitor\CssScopeNodeVisitor;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Source;

final class CssScopeNodeVisitorTest extends TestCase
{
    public function testNormalizesWindowsProjectPathWhenResolvingModule(): void
    {
        $modulePath = new ConstantExpression('../Extension/CssModuleExtensionTest.php', 1);
        $modulePath->setSourceContext(new Source('', 'template.twig', __DIR__.'/template.twig'));
        $node = new ImportModuleNode($modulePath, 1);
        $projectDir = str_replace('/', '\\', dirname(__DIR__, 2));
        $visitor = new CssScopeNodeVisitor($projectDir);

        $visitor->enterNode($node, new Environment(new ArrayLoader()));

        $this->assertSame(
            'Tests/Extension/CssModuleExtensionTest.php',
            $node->getNode('modulePath')->getAttribute('value'),
        );
    }
}
