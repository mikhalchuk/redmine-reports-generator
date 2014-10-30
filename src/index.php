#!/usr/bin/env php
<?php

chdir(realpath(__DIR__));
require_once '../vendor/autoload.php';

use \RedmineReportsGenerator\Application;

$application = new Application("Redmine Reports Generator", '1.0alpha');
$application->run();
