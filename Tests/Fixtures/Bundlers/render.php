<?php

require __DIR__.'/../../../vendor/autoload.php';

use Mak\AtomicAssetsBundle\Extension\AtomicAssetsExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$arguments = $_SERVER['argv'];
$assets = new Packages(new Package(new JsonManifestVersionStrategy($arguments[2].'/manifest.json', null, true)));
$twig = new Environment(new FilesystemLoader($arguments[1].'/templates'));
$twig->addExtension(new AtomicAssetsExtension($arguments[1], assets: $assets));

echo json_encode([$twig->render('header/view.twig'), $twig->render('footer/view.twig')], JSON_THROW_ON_ERROR);
