#!/usr/bin/env php
<?php

chdir(realpath(__DIR__));
require_once '../vendor/autoload.php';

use Cilex\Provider\Console\ContainerAwareApplication;
use RedmineReportsGenerator\Command\GenerateReportsCommand;

$container = require_once '../config/container.php';

$application = new ContainerAwareApplication("Redmine Reports Generator", '1.5');
$application->setContainer($container);
$application->add(new GenerateReportsCommand());
$application->run();
