<?php
// Find autoload.php and derive vendor directory from it
$autoloaderViaComposer = realpath(__DIR__ . '/../../../autoload.php');
if (file_exists($autoloaderViaComposer)) {
    $vendorRootPath = realpath(__DIR__ . '/../../..');
} else {
    $vendorRootPath = realpath(__DIR__ . '/../vendor');
}
require_once $vendorRootPath . '/autoload.php';

use Symfony\Component\Console\Application;

$pischi = new Application();
$pischi->setName('Pischi');
$pischi->setVersion('0.0.1');

$pischi->add(new \Glowpointzero\Pischi\Command\InfoCommand());
$pischi->add(new \Glowpointzero\Pischi\Command\GenerateHashesCommand());
$pischi->add(new \Glowpointzero\Pischi\Command\FuseFileStructureCommand());

$pischi->setDefaultCommand(\Glowpointzero\Pischi\Command\InfoCommand::COMMAND_NAME);
$pischi->run();
