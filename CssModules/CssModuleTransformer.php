<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\CssModules;

use Closure;

/**
 * Renames the class selectors of a CSS module, for when no bundler's css-loader is there to do it.
 *
 * It covers what scope() can refer to from a template:
 *  - every class in a style rule's selector is local, including in nested rules and inside :is(), :not(), :has() etc;
 *  - :global(.x) and :global .x (to the end of the selector) are left as they are, and :local(...) / :local is local.
 *
 * It doesn't support composes, and leaves @keyframes names, ids and at-rule preludes (@media, @supports, @scope, ...) global.
 */
final class CssModuleTransformer
{
    /**
     * @param Closure(string): string $rename Given a class name as written in the module, returns its scoped name
     */
    public function transform(string $css, Closure $rename): string
    {
        $output = '';
        // Everything since the last "{", "}" or ";": a selector when it ends with "{", a declaration otherwise
        $segment = '';
        $length = strlen($css);

        for ($i = 0; $i < $length;) {
            $char = $css[$i];

            if ('/' === $char && '*' === ($css[$i + 1] ?? '')) {
                $end = $this->commentEnd($css, $i);
            } elseif ('"' === $char || "'" === $char) {
                $end = $this->stringEnd($css, $i);
            } elseif ('(' === $char) {
                // a url(), a :is(...): its ";", "{" and "}" don't end the segment
                $end = $this->parenthesisEnd($css, $i);
            } elseif ('\\' === $char) {
                $end = min($i + 2, $length);
            } else {
                if ('{' === $char) {
                    $output .= $this->isAtRule($segment) ? $segment : $this->renameSelector($segment, $rename);
                    $output .= '{';
                    $segment = '';
                } elseif (';' === $char || '}' === $char) {
                    $output .= $segment.$char;
                    $segment = '';
                } else {
                    $segment .= $char;
                }
                ++$i;

                continue;
            }

            $segment .= substr($css, $i, $end - $i);
            $i = $end;
        }

        return $output.$segment;
    }

    /**
     * @param Closure(string): string $rename
     */
    private function renameSelector(string $selector, Closure $rename, bool $global = false): string
    {
        $output = '';
        $length = strlen($selector);
        // :global or :local without parentheses switches the mode until the next "," of the selector list
        $listGlobal = $global;
        $depth = 0;

        for ($i = 0; $i < $length;) {
            $char = $selector[$i];

            if ('/' === $char && '*' === ($selector[$i + 1] ?? '')) {
                $end = $this->commentEnd($selector, $i);
                $output .= substr($selector, $i, $end - $i);
                $i = $end;
            } elseif ('"' === $char || "'" === $char) {
                $end = $this->stringEnd($selector, $i);
                $output .= substr($selector, $i, $end - $i);
                $i = $end;
            } elseif (':' === $char && 1 === preg_match('/\G:(global|local)(\(|(?![-\w]))\s*/Ai', $selector, $match, 0, $i)) {
                $isGlobal = 'global' === strtolower($match[1]);
                if ('(' === $match[2]) {
                    $open = $i + strlen(rtrim($match[0])) - 1;
                    $close = $this->parenthesisEnd($selector, $open);
                    $inner = substr($selector, $open + 1, max($close - $open - 2, 0));
                    $output .= $this->renameSelector($inner, $rename, $isGlobal);
                    $i = $close;
                } else {
                    $listGlobal = $isGlobal;
                    $i += strlen($match[0]);
                }
            } elseif ('.' === $char && 1 === preg_match('/\G\.((?:-?(?:[a-zA-Z_\x80-\xff]|\\\\(?:[0-9a-fA-F]{1,6}\s?|.))|--)(?:[-\w\x80-\xff]|\\\\(?:[0-9a-fA-F]{1,6}\s?|.))*)/As', $selector, $match, 0, $i)) {
                $output .= '.'.($listGlobal ? $match[1] : $this->escape($rename($this->unescape($match[1]))));
                $i += strlen($match[0]);
            } else {
                if ('(' === $char) {
                    ++$depth;
                } elseif (')' === $char) {
                    --$depth;
                } elseif (',' === $char && 0 === $depth) {
                    $listGlobal = $global;
                }

                if ('\\' === $char) {
                    $output .= substr($selector, $i, 2);
                    $i += 2;
                } else {
                    $output .= $char;
                    ++$i;
                }
            }
        }

        return $output;
    }

    private function isAtRule(string $segment): bool
    {
        $segment = preg_replace('#/\*.*?\*/#s', '', $segment) ?? $segment;

        return str_starts_with(ltrim($segment), '@');
    }

    private function commentEnd(string $css, int $start): int
    {
        $end = strpos($css, '*/', $start + 2);

        return false === $end ? strlen($css) : $end + 2;
    }

    private function stringEnd(string $css, int $start): int
    {
        $quote = $css[$start];
        $length = strlen($css);

        for ($i = $start + 1; $i < $length; ++$i) {
            if ('\\' === $css[$i]) {
                ++$i;
            } elseif ($quote === $css[$i] || "\n" === $css[$i]) {
                return $i + 1;
            }
        }

        return $length;
    }

    /** Returns the offset just after the ")" matching the "(" at $start. */
    private function parenthesisEnd(string $css, int $start): int
    {
        $length = strlen($css);
        $depth = 0;

        for ($i = $start; $i < $length; ++$i) {
            $char = $css[$i];
            if ('/' === $char && '*' === ($css[$i + 1] ?? '')) {
                $i = $this->commentEnd($css, $i) - 1;
            } elseif ('"' === $char || "'" === $char) {
                $i = $this->stringEnd($css, $i) - 1;
            } elseif ('\\' === $char) {
                ++$i;
            } elseif ('(' === $char) {
                ++$depth;
            } elseif (')' === $char && 0 === --$depth) {
                return $i + 1;
            }
        }

        return $length;
    }

    /** The class name as scope() is given it: ".sm\:flex" is the class "sm:flex". */
    private function unescape(string $identifier): string
    {
        return (string) preg_replace_callback(
            '/\\\\(?:([0-9a-fA-F]{1,6})\s?|(.))/s',
            static fn (array $match): string => '' !== $match[1] ? mb_chr((int) hexdec($match[1]), 'UTF-8') : $match[2],
            $identifier,
        );
    }

    private function escape(string $identifier): string
    {
        return (string) preg_replace_callback(
            '/[^-\w\x80-\xff]/',
            static fn (array $match): string => '\\'.$match[0],
            $identifier,
        );
    }
}
