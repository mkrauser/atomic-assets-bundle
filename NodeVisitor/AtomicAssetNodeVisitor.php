<?php

/*
 * This file is part of the CssModuleBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\NodeVisitor;

use RuntimeException;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

final readonly class AtomicAssetNodeVisitor implements NodeVisitorInterface
{
    public function __construct(private string $projectDir)
    {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof FunctionExpression || 'atomic_asset' !== $node->getAttribute('name')) {
            return $node;
        }

        $arguments = $node->getNode('arguments');
        if (1 !== count($arguments)) {
            throw new RuntimeException('atomic_asset() requires one string literal path; target filenames are now managed by the bundler.');
        }

        $pathNode = $arguments->hasNode('0') ? $arguments->getNode('0') : ($arguments->hasNode('path') ? $arguments->getNode('path') : null);
        if (!$pathNode instanceof ConstantExpression || !is_string($pathNode->getAttribute('value'))) {
            throw new RuntimeException('The path passed to atomic_asset() must be a string literal.');
        }

        $templatePath = $node->getSourceContext()->getPath();
        if ('' === $templatePath) {
            throw new RuntimeException('atomic_asset() requires a template with a filesystem path.');
        }

        $baseDirectory = Path::getDirectory($templatePath);
        $projectDir = realpath($this->projectDir) ?: $this->projectDir;
        $assetPath = Path::makeAbsolute($pathNode->getAttribute('value'), $baseDirectory);
        if (!Path::isBasePath($baseDirectory, $assetPath) || !Path::isBasePath($projectDir, $assetPath)) {
            throw new RuntimeException('Colocated asset path escapes its allowed directory.');
        }

        $arguments->setNode($arguments->hasNode('0') ? '0' : 'path', new ConstantExpression(
            Path::makeRelative($assetPath, $projectDir),
            $node->getTemplateLine(),
        ));

        return $node;
    }

    public function getPriority(): int
    {
        return -10;
    }
}
