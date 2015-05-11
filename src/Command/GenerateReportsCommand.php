<?php
namespace RedmineReportsGenerator\Command;

use GuzzleHttp\Message\Response;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Pool;

class GenerateReportsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('generate')
            ->setDescription('Generates reports');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \RedmineReportsGenerator\Service\Client $client */
        $client = $this->getApplication()->getService('client');
        /** @var \RedmineReportsGenerator\Service\Report $report */
        $report = $this->getApplication()->getService('report');
        /** @var \GuzzleHttp\Client $httpClient */
        $httpClient = $this->getApplication()->getService('httpClient');

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

            $options = [
                'auth' => [
                    $this->getApplication()->getService('config')['redmine']['login'],
                    $this->getApplication()->getService('config')['redmine']['pass'],
                ],
            ];

            $requests = [];
            foreach ($issuesUrls as $id => $issueUrl) {
                $requests[$id] = $httpClient->createRequest('GET', $issueUrl['url'], $options);
            }

            $responseData = [];
            $results = Pool::batch($httpClient, $requests);
            foreach ($requests as $id => $request) {
                /** @var Response $result */
                $result = $results->getResult($request);
                $responseData[$id]['data'] = $result->getBody()->getContents();
            }
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
