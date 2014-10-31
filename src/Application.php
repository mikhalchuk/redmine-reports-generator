<?php

namespace RedmineReportsGenerator;

use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    const OUTPUT_FOLDER = '/monthly_reports/';
    const CONFIG_PATH = '/config/config.php';

    private $config;

    public function getConfig()
    {
        if (empty($this->config)) {
            $this->config = require_once(dirname(dirname(__FILE__)) . static::CONFIG_PATH);
        }
        return $this->config;
    }

    public function getDateRange()
    {
        return [
            'from' => date($this->getConfig()['date']['from']),
            'to' => date($this->getConfig()['date']['to']),
        ];
    }

    public function getUsers()
    {
        return $this->getConfig()['users'];
    }

    public function getIssuesUrl()
    {
        return $this->getConfig()['redmine']['host'];
    }

    public function getTemplatePath()
    {
        return dirname(dirname(__FILE__)) . '/config/template.xls';
    }

    public function getOutputPath()
    {
        return dirname(dirname(__FILE__)) . static::OUTPUT_FOLDER;
    }
}
