#!/usr/bin/env php
<?php

chdir(realpath(__DIR__));
require_once '../vendor/autoload.php';

use RedmineReportsGenerator\Application;
use RedmineReportsGenerator\Command\GenerateReportsCommand;

$application = new Application("Redmine Reports Generator", '1.0alpha');
$application->add(new GenerateReportsCommand());
$application->run();
