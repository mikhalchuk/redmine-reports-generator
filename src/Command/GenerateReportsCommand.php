<?php

namespace RedmineReportsGenerator\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Redmine\Client;

class GenerateReportsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('generate')
            ->setDescription('Generates reports');
    }

    private function multiCurl($responseData)
    {
        /**
         * @var \RedmineReportsGenerator\Application $application
         */
        $application = $this->getApplication();

        if (count($responseData) <= 0) {
            return false;
        }

        $handles = array();

        $options = array(
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD =>
                $application->getConfig()['redmine']['login'] . ':' . $application->getConfig()['redmine']['pass'],
            CURLOPT_USERAGENT => "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)",
            CURLOPT_TIMEOUT => 6000,
        );

        foreach ($responseData as $k => $row) {
            $ch{$k} = curl_init();
            $options[CURLOPT_URL] = $row['url'];
            curl_setopt_array($ch{$k}, $options);
            $handles[$k] = $ch{$k};
        }

        $mh = curl_multi_init();

        foreach ($handles as $k => $handle) {
            curl_multi_add_handle($mh, $handle);
        }

        $running_handles = null;
        do {
            $status_cme = curl_multi_exec($mh, $running_handles);
        } while ($running_handles > 0);

        while ($running_handles && $status_cme == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $status_cme = curl_multi_exec($mh, $running_handles);
                } while ($status_cme == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach ($responseData as $k => $row) {
            $responseData[$k]['error'] = curl_error($handles[$k]);
            if (!empty($responseData[$k]['error'])) {
                $responseData[$k]['data'] = '';
            } else {
                $responseData[$k]['data']  = curl_multi_getcontent($handles[$k]);
            }
            curl_multi_remove_handle($mh, $handles[$k]);
        }
        curl_multi_close($mh);
        return $responseData;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var \RedmineReportsGenerator\Application $application
         */
        $application = $this->getApplication();
        $client = new Client(
            $application->getConfig()['redmine']['host'],
            $application->getConfig()['redmine']['login'],
            $application->getConfig()['redmine']['pass']
        );

        $timeEntryParams = [
            'limit' => 100
        ] + $application->getDateRange();

        /**
         * @var \Redmine\Api\TimeEntry $timeEntry
         */
        $timeEntry = $client->api('time_entry');

        /**
         * @var \Redmine\Api\User $user
         */
        $user = $client->api('user');

        $offset = 0;
        $counter = 0;

        $reports = [];
        $logins = [];
        $allUsers = [];
        $allIssues = [];
        $issuesInfo = [];

        $output->writeln('<info>Start getting issues...</info>');
        while (true) {
            $timeEntryParams['offset'] = $offset;
            $entries = $timeEntry->all($timeEntryParams);
            if (empty($entries) || !is_array($entries)) {
                break;
            }

            $entries = array_values($entries)[0];
            if (empty($entries) || !is_array($entries)) {
                break;
            }

            $issuesUrls = [];
            foreach ($entries as $entry) {
                if (!empty($entry)) {
                    $issueId = $entry['issue']['id'];
                    $userName = explode(' ', strtolower((string)$entry['user']['name']))[0];
                    if (!in_array($userName, $logins)) {
                        $usersIds = $application->getUsers();
                        if (!empty($usersIds) && is_array($usersIds)) {
                            if (!in_array($entry['user']['id'], $usersIds)) {
                                continue;
                            }
                        }

                        $userInfo = $user->show(
                            $entry['user']['id']
                        );

                        $userData = [];
                        $customFields = $userInfo['user']['custom_fields'];
                        if (!empty($customFields) && is_array($customFields)) {
                            foreach ($customFields as $field) {
                                $userData[$field['name']] = $field['value'];
                            }
                        }

                        $allUsers[$userName] = [
                            'lastname' => $userInfo['user']['lastname'],
                            'firstname' => $userInfo['user']['firstname'],
                            'position' => isset($userData['Position']) ? $userData['Position'] : '',
                            'level' => isset($userData['Level']) ? $userData['Level'] : '',
                            'trial_start' => isset($userData['Trial start']) ? $userData['Trial start'] : '',
                            'trial_end' => isset($userData['Trial end']) ? $userData['Trial end'] : '',
                        ];
                    }

                    $logins[] = $userName;

                    $hours = $entry['hours'];
                    if (isset($allIssues[$userName][$issueId])) {
                        $hours += $allIssues[$userName][$issueId]['hours'];
                    }

                    $allIssues[$userName][$issueId] =[
                        'id' => $issueId,
                        'hours' => $hours,
                        'title' => '',
                        'link' => '',
                    ];
                    $issuesUrls[$issueId] = ['url' => $application->getIssuesUrl() . "/issues/{$issueId}.json"];
                } else {
                    $output->writeln('<error>oops: time entry is empty</error>');
                    die();
                }
                $output->writeln('<info>' . $counter++ . "\r" . '</info>');
            }

            $offset += 100;
            $output->writeln('<info>' . $offset . ' entries processed' . '</info>');

            $responseData = $this->multiCurl($issuesUrls);
            if (empty($responseData) || !is_array($responseData)) {
                $output->writeln('<error>oops: empty multicurl response</error>');
            } elseif (count($issuesUrls) !== count($responseData)) {
                $output->writeln(
                    "<error>oops: count of issuesUrls({$issuesUrls}) " .
                    "and responses({$responseData}) doesn`t match</error>"
                );
            } else {
                $issuesInfo += $responseData;

                $logins = array_unique($logins);
                foreach ($logins as $login) {
                    $reports[$login] = [
                        'info' => $allUsers[$login],
                        'issues' => $allIssues[$login]
                    ];

                    foreach ($reports[$login]['issues'] as $issueId => $obj) {
                         $ss = json_decode($issuesInfo[$issueId]['data'], true);

                        $deliveryTime = 0;
                        if (!empty($ss['issue']['custom_fields']) && is_array($ss['issue']['custom_fields'])) {
                            foreach ($ss['issue']['custom_fields'] as $customField) {
                                if ($customField['id'] == '87') { // Delivery time
                                    if (!empty($custom_field['value'])) {
                                        $deliveryTime = $customField['value'];
                                    }
                                }
                            }
                        }

                        $reports[$login]['issues'][$issueId]['delivery_time'] = $deliveryTime;
                        $reports[$login]['issues'][$issueId]['title'] = "#{$issueId} {$ss['issue']['subject']}";
                        $reports[$login]['issues'][$issueId]['link'] =
                            $application->getIssuesUrl().  "/issues/{$issueId}";
                    }
                }
            }
        }
        $output->writeln('<info>end getting issues</info>');

        if (!empty($reports) && is_array($reports)) {
            $output->writeln('<info>start create reports...</info>');
            $counter = 0;
            foreach ($reports as $login => $report) {
                $objPHPExcel = \PHPExcel_IOFactory::load($application->getTemplatePath());
                $objPHPExcel->setActiveSheetIndex(0);
                $aSheet = $objPHPExcel->getActiveSheet();

                $aSheet->setCellValue('B4', $report['info']['lastname'] . ' ' . $report['info']['firstname']);

                $aSheet->setCellValue('B5', $report['info']['position']);
                $aSheet->setCellValue('B6', $report['info']['level']);

                if (!empty($report['info']['trial_end'])) {

                    $trialEndMonth = date('m', strtotime($report['info']['trial_end']));
                    $currentMonth = date('m');

                    if ($trialEndMonth >= $currentMonth) {
                        $aSheet->setCellValue('K4', $report['info']['trial_start']);
                        $aSheet->setCellValue('K5', $report['info']['trial_end']);
                    }
                }

                $startIndex = 14;
                foreach ($report['issues'] as $id => $issue) {

                    $aSheet->setCellValue('B' . $startIndex, $issue['title']);
                    $objPHPExcel->getActiveSheet()->getCell('B' . $startIndex)->getHyperlink()->setUrl($issue['link']);

                    $aSheet->setCellValue('C' . $startIndex, $issue['hours']);
                    $aSheet->setCellValue('D' . $startIndex, $issue['delivery_time']);
                    $aSheet->setCellValue('A' . $startIndex, '+');

                    $objPHPExcel->getActiveSheet()->insertNewRowBefore($startIndex + 1, 1);

                    $startIndex++;
                }

                $objWriter = new \PHPExcel_Writer_Excel5($objPHPExcel);

                $objWriter->save($application->getOutputPath() . $login . '.xls');
                $output->writeln('<info>' . $counter++ . "\r" . '</info>');
            }
            $output->writeln('<info>reports created</info>');
        }
    }
}
