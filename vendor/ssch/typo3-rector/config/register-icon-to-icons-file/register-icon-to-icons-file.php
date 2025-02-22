<?php

declare (strict_types=1);
namespace RectorPrefix20220410;

use Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector;
use Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector\AddIconsToReturnRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
return static function (\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator) : void {
    $containerConfigurator->import(__DIR__ . '/../config.php');
    $services = $containerConfigurator->services();
    $services->set(\Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector\AddIconsToReturnRector::class);
    $services->set(\Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector::class);
};
