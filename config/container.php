<?php

$dc = new Pimple\Container();

$dc['config'] = function ($dc) {
    return require_once(__DIR__ . '/config.php');
};

$dc['redmineClient'] = function ($dc) {
    return new Redmine\Client(
        $dc['config']['redmine']['host'],
        $dc['config']['redmine']['login'],
        $dc['config']['redmine']['pass']
    );
};

$dc['client'] = function ($dc) {
    $timeEntryParams = [
        'offset' => 0,
        'limit' => 100,
        'from' => date($dc['config']['date']['from']),
        'to' => date($dc['config']['date']['to']),
    ];

    return new RedmineReportsGenerator\Service\Client(
        $dc['redmineClient'],
        $timeEntryParams,
        $dc['config']['users'],
        $dc['config']['redmine']['host']
    );
};

$dc['report'] = function ($dc) {
    return new RedmineReportsGenerator\Service\Report(
        \PHPExcel_IOFactory::load(__DIR__ . '/template.xls'),
        dirname(dirname(__FILE__)) . '/monthly_reports/'
    );
};

return $dc;
