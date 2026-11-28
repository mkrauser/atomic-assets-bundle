<?php

/*
 * This file is part of the AtomicAssetsBundle package.
 * Copyright (c) Matthias Krauser <matthias@krauser.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Mak\AtomicAssetsBundle\Hashing;

/**
 * The path of a CSS module as it is hashed: relative to the localIdentContext (the project directory), with forward slashes.
 */
final class ModulePath
{
    public static function relativeTo(string $modulePath, string $projectDir): string
    {
        $modulePath = str_replace('\\', '/', $modulePath);
        $projectDir = rtrim(str_replace('\\', '/', $projectDir), '/');

        if (str_starts_with($modulePath, $projectDir.'/')) {
            return substr($modulePath, strlen($projectDir) + 1);
        }

        return ltrim($modulePath, '/');
    }
}
