<?php

declare (strict_types=1);
namespace RectorPrefix20220410;

use RectorPrefix20220410\Nette\Neon\Decoder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
return static function (\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator) : void {
    $services = $containerConfigurator->services();
    $services->defaults()->public()->autowire()->autoconfigure();
    $services->load('Rector\\Nette\\NeonParser\\', __DIR__ . '/../packages/NeonParser')->exclude([__DIR__ . '/../packages/NeonParser/NeonNodeTraverser.php', __DIR__ . '/../packages/NeonParser/Node']);
    $services->set(\RectorPrefix20220410\Nette\Neon\Decoder::class);
};
