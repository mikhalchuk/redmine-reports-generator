<?php
namespace RedmineReportsGenerator\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        if (count($responseData) <= 0) {
            return false;
        }

        $handles = array();

        $options = [
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD =>
                $this->getApplication()->getService('config')['redmine']['login'] . ':' .
                $this->getApplication()->getService('config')['redmine']['pass'],
            CURLOPT_USERAGENT => "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)",
            CURLOPT_TIMEOUT => 6000,
            CURLOPT_CAINFO => '/etc/ssl/certs/ca-certificates.crt',
        ];

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
        /** @var \RedmineReportsGenerator\Service\Client $client */
        $client = $this->getApplication()->getService('client');
        /** @var \RedmineReportsGenerator\Service\Report $report */
        $report = $this->getApplication()->getService('report');

        $offset = 0;
        $issuesInfo = [];

        $output->writeln('<info>start getting issues...</info>');
        while (true) {
            if (!$client->requestEntries($offset)) {
                break;
            }

            $issuesUrls = $client->collectIssuesInfo();

            $offset += 100;
            $output->writeln('<info>' . $offset . ' entries processed' . '</info>');

            if (empty($issuesUrls) || !is_array($issuesUrls)) {
                $output->writeln('<info>oops: there are no issues urls</info>');
                continue;
            }

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
                $client->prepareReportsData($issuesInfo);
            }
        }
        $output->writeln('<info>end getting issues</info>');

        $reports = $client->getReportsData();
        if (!empty($reports) && is_array($reports)) {
            $output->writeln('<info>start create reports...</info>');
            if ($report->generate($reports)) {
                $output->writeln('<info>reports created</info>');
            }
        }
    }
}
