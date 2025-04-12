<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use App\Fixers\ArraySpacingFixer;

$finder = Finder::create()
    ->in( [
        __DIR__ . "/app",
        __DIR__ . "/config",
        __DIR__ . "/database",
        __DIR__ . "/resources",
        __DIR__ . "/routes",
        __DIR__ . "/tests",
    ] )
    ->name( "*.php" )
    ->notName( "*.blade.php" )
    ->notName( "ArraySpacingFixer.php" )
    ->ignoreDotFiles( true )
    ->ignoreVCS( true );

$config = new Config();

return $config->registerCustomFixers( [ new ArraySpacingFixer() ] )
              ->setRules( [ "Costa/array_spacing" => true ] )
              ->setFinder( $finder );
