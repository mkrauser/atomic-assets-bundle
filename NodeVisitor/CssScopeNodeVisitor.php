<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\NodeVisitor;

use Mak\AtomicAssetsBundle\Hashing\ModulePath;
use Mak\AtomicAssetsBundle\Node\ImportModuleNode;
use RuntimeException;
use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

class CssScopeNodeVisitor implements NodeVisitorInterface
{
    private Scope $scope;

    public function __construct(
        private readonly string $projectDir,
        private readonly ?\Closure $hashScope = null
    ) {
        $this->scope = new Scope();
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof BlockNode || $node instanceof ModuleNode) {
            $this->scope = $this->scope->enter();
        }

        if ($node instanceof ImportModuleNode) {
            $this->scope->set('modulePath', $node->getNode('modulePath'));
            if (!$node->getNode('modulePath') instanceof ConstantExpression) {
                throw new RuntimeException('The module path must be a constant expression');
            }

            $node->getNode('modulePath')->setAttribute('value', $this->getPathFromNode($node->getNode('modulePath')));
            return $node;
        }

        if ($node instanceof FunctionExpression
        && 'scope' === $node->getAttribute('name')) {
            $arguments = $node->getNode('arguments');
            if ($this->isNamedArguments($arguments)) {
                if (!$arguments->hasNode('module') && !$arguments->hasNode('0')) {
                    $arguments->setNode('module', $this->scope->get('modulePath'));
                } elseif (!$arguments->hasNode('1')) {
                    $arguments->setNode('1', $this->scope->get('modulePath'));
                }
            } elseif (!$arguments->hasNode('1')) {
                $arguments->setNode('1', $this->scope->get('modulePath'));
            } elseif($arguments->hasNode('1')) {
                $relativePath = $arguments->getNode('1')->getAttribute('value');

                $templateFile = $node->getSourceContext()->getPath();
                $templateDir = dirname($templateFile);

                $modulePath = realpath($templateDir.DIRECTORY_SEPARATOR.$relativePath);

                if(false == $modulePath) {
                    throw new RuntimeException(sprintf('The css module "%s" does not exist.', $node->getAttribute('value')));
                }
                
                $arguments->getNode('1')->setAttribute('value', $this->getRelativeModulePath($modulePath));
            }

            $class = $arguments->hasNode('0') ? $arguments->getNode('0') : ($arguments->hasNode('scopes') ? $arguments->getNode('scopes') : null);
            $module = $arguments->hasNode('1') ? $arguments->getNode('1') : ($arguments->hasNode('modulePath') ? $arguments->getNode('modulePath') : null);
            if (null !== $this->hashScope && $module instanceof ConstantExpression && is_string($module->getAttribute('value'))) {
                $classes = null;
                if ($class instanceof ConstantExpression && is_string($class->getAttribute('value'))) {
                    $classes = $class->getAttribute('value');
                } elseif ($class instanceof ArrayExpression) {
                    $classes = [];
                    foreach ($class->getKeyValuePairs() as $pair) {
                        if (!$pair['key'] instanceof ConstantExpression || !$pair['value'] instanceof ConstantExpression || !is_string($pair['value']->getAttribute('value'))) {
                            $classes = null;
                            break;
                        }
                        $classes[$pair['key']->getAttribute('value')] = $pair['value']->getAttribute('value');
                    }
                }
                if (null !== $classes) {
                    return new ConstantExpression(($this->hashScope)($classes, $module->getAttribute('value')), $node->getTemplateLine());
                }
            }
        }

        return $node;
    }

    private function getPathFromNode(ConstantExpression $node): string
    {
        $templateDirectory = dirname($node->getSourceContext()->getPath());

        $moduleFile = realpath($templateDirectory.DIRECTORY_SEPARATOR.$node->getAttribute('value'));

        if (false === $moduleFile && '' !== $templateDirectory) {
            // when running twig:lint, $templateDirectory is a empty string. We ignore that for now
            throw new RuntimeException(sprintf('The css module "%s" does not exist.', $node->getAttribute('value')));
        }

        return $this->getRelativeModulePath((string) $moduleFile);
    }

    private function getRelativeModulePath(string $modulePath): string
    {
        return ModulePath::relativeTo($modulePath, $this->projectDir);
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if ($node instanceof ImportModuleNode) {
            return null;
        }

        if ($node instanceof BlockNode || $node instanceof ModuleNode) {
            $this->scope = $this->scope->leave();
        }

        return $node;
    }

    private function isNamedArguments(Node $arguments): bool
    {
        foreach ($arguments as $name => $node) {
            /* @phpstan-ignore-next-line */
            if (!\is_int($name)) {
                return true;
            }
        }

        return false;
    }

    public function getPriority(): int
    {
        return -10;
    }
}
