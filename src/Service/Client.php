<?php
namespace RedmineReportsGenerator\Service;

use Redmine\Client as RedmineClient;

/**
 * Class Client
 * @package RedmineReportsGenerator\Service
 */
class Client
{
    const DELIVERY_TIME_FIELD_ID = '87';

    /**
     * @var RedmineClient $redmineClient client of Redmine API
     */
    protected $redmineClient;

    /**
     * @var array $timeEntryParams time entries params
     */
    protected $timeEntryParams;

    /**
     * @var array $usersIds users to collect time
     */
    protected $usersIds;

    /**
     * @var string $issuesUrl url for links to issues
     */
    protected $issuesUrl;

    /**
     * @var array $entries time entries collection
     */
    protected $entries;

    // list of temporary variables
    // TODO: refactor them
    protected $logins = [];
    protected $allUsers;
    protected $allIssues;
    protected $reportsData;

    /**
     * @param RedmineClient $redmineClient redmine client to make requests
     * @param array $timeEntryParams time params for report
     * @param array $usersIds collection of users to generate reports
     * @param string $issuesUrl url for links to issues
     */
    public function __construct(
        RedmineClient $redmineClient,
        array $timeEntryParams,
        array $usersIds,
        $issuesUrl
    ) {
        $this->redmineClient = $redmineClient;
        $this->timeEntryParams = $timeEntryParams;
        $this->usersIds = $usersIds;
        $this->issuesUrl = $issuesUrl;
    }

    /**
     * Request to redmine host and get time entries for configured params
     *
     * @param int $offset offset of time entries list
     * @return bool
     */
    public function requestEntries($offset = 0)
    {
        $this->timeEntryParams['offset'] = $offset;
        $entries = $this->redmineClient->api('time_entry')->all($this->timeEntryParams);
        if (!isset($entries['time_entries'])) {
            return false;
        }

        $entries = $entries['time_entries'];
        if (empty($entries) || !is_array($entries)) {
            return false;
        }

        $this->entries = $entries;

        return true;
    }

    /**
     * Collects issues and users info from entries
     *
     * @return array collection o issues urls
     */
    public function collectIssuesInfo()
    {
        $issuesUrls = [];
        foreach ($this->entries as $entry) {
            if (!empty($entry['issue'])) {
                $issueId = $entry['issue']['id'];
                $userName = explode(' ', strtolower((string)$entry['user']['name']))[0];
                if (!in_array($userName, $this->logins)) {
                    if (!empty($this->usersIds)) {
                        if (!in_array($entry['user']['id'], $this->usersIds)) {
                            continue;
                        }
                    }

                    $userInfo = $this->redmineClient->api('user')->show(
                        $entry['user']['id']
                    );

                    $userData = [];
                    $customFields = $userInfo['user']['custom_fields'];
                    if (!empty($customFields) && is_array($customFields)) {
                        foreach ($customFields as $field) {
                            $userData[$field['name']] = $field['value'];
                        }
                    }

                    $this->allUsers[$userName] = [
                        'lastname' => $userInfo['user']['lastname'],
                        'firstname' => $userInfo['user']['firstname'],
                        'position' => isset($userData['Position']) ? $userData['Position'] : '',
                        'level' => isset($userData['Level']) ? $userData['Level'] : '',
                        'trial_start' => isset($userData['Trial start']) ? $userData['Trial start'] : '',
                        'trial_end' => isset($userData['Trial end']) ? $userData['Trial end'] : '',
                    ];
                }

                $this->logins[] = $userName;

                $hours = $entry['hours'];
                if (isset($this->allIssues[$userName][$issueId])) {
                    $hours += $this->allIssues[$userName][$issueId]['hours'];
                }

                $this->allIssues[$userName][$issueId] =[
                    'id' => $issueId,
                    'hours' => $hours,
                    'title' => '',
                    'link' => '',
                ];
                $issuesUrls[$issueId] = ['url' => $this->issuesUrl . "/issues/{$issueId}.json"];
            }
        }
        return $issuesUrls;
    }

    /**
     * Converts raw issues data to reports format
     *
     * @param array $reportsData raw issues data
     */
    public function prepareReportsData(array $reportsData)
    {
        $logins = array_unique($this->logins);
        foreach ($logins as $login) {
            $this->reportsData[$login] = [
                'info' => $this->allUsers[$login],
                'issues' => $this->allIssues[$login]
            ];

            foreach ($this->reportsData[$login]['issues'] as $issueId => $obj) {
                $ss = json_decode($reportsData[$issueId]['data'], true);

                $deliveryTime = 0;
                if (!empty($ss['issue']['custom_fields']) && is_array($ss['issue']['custom_fields'])) {
                    foreach ($ss['issue']['custom_fields'] as $customField) {
                        if ($customField['id'] == self::DELIVERY_TIME_FIELD_ID) {
                            if (!empty($custom_field['value'])) {
                                $deliveryTime = $customField['value'];
                            }
                        }
                    }
                }

                $this->reportsData[$login]['issues'][$issueId]['delivery_time'] = $deliveryTime;
                $this->reportsData[$login]['issues'][$issueId]['title'] = "#{$issueId} {$ss['issue']['subject']}";
                $this->reportsData[$login]['issues'][$issueId]['link'] = $this->issuesUrl.  "/issues/{$issueId}";
            }
        }
    }

    /**
     * Returns previously generated data for reports
     *
     * @return array
     */
    public function getReportsData()
    {
        return $this->reportsData;
    }
}
