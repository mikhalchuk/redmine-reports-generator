<?php
namespace RedmineReportsGenerator\Tests\Service;

use RedmineReportsGenerator\Service\Report;

class ReportTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Report $report report object
     */
    protected $report;

    /**
     * @var string $projectRoot
     */
    protected static $projectRoot;

    protected function setUp()
    {
        $this->report = new Report(
            \PHPExcel_IOFactory::load(self::$projectRoot . '/config/template.xls'),
            self::$projectRoot . '/monthly_reports/'
        );
    }

    public static function setUpBeforeClass()
    {
        self::$projectRoot = dirname(dirname(dirname(__FILE__)));
        self::clearOutputReports();
    }

    public static function tearDownAfterClass()
    {
        self::clearOutputReports();
    }

    protected static function clearOutputReports()
    {
        $reports = [
            self::$projectRoot . '/monthly_reports/mykhalchuk_test.xls',
            self::$projectRoot . '/monthly_reports/user2_test.xls',
        ];
        foreach ($reports as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @dataProvider reportDataProvider
     */
    public function testSuccessGenerate(array $data)
    {
        $this->assertTrue($this->report->generate($data));
    }

    /**
     * @depends testSuccessGenerate
     * @dataProvider reportDataProvider
     */
    public function testXlsDocument(array $data)
    {
        $login = array_keys($data)[0];
        $excel = \PHPExcel_IOFactory::load(self::$projectRoot . "/monthly_reports/{$login}.xls");

        $this->assertEquals($data['info']['lastname'], $excel->getActiveSheet()->getCell('B4')->getValue());
        $this->assertEquals($data['info']['firstname'], $excel->getActiveSheet()->getCell('B5')->getValue());
        $this->assertEquals($data['info']['level'], $excel->getActiveSheet()->getCell('B6')->getValue());
        $this->assertEquals($data['info']['trial_start'], $excel->getActiveSheet()->getCell('K4')->getValue());
        $this->assertEquals($data['info']['trial_end'], $excel->getActiveSheet()->getCell('K5')->getValue());

        $startIndex = Report::INITIAL_NUMBER_FOR_ISSUES_LIST;
        foreach ($data['issues'] as $issue) {
            $this->assertEquals(
                $issue['title'],
                $excel->getActiveSheet()->getCell('B' . $startIndex)->getValue()
            );
            $this->assertEquals(
                $issue['link'],
                $excel->getActiveSheet()->getCell('B' . $startIndex)->getHyperlink()
            );
            $this->assertEquals(
                $issue['hours'],
                $excel->getActiveSheet()->getCell('C' . $startIndex)->getValue()
            );
            $this->assertEquals(
                $issue['delivery_time'],
                $excel->getActiveSheet()->getCell('D' . $startIndex)->getValue()
            );
            $startIndex++;
        }
    }

    public function reportDataProvider()
    {
        return [
            [
                [
                    'mykhalchuk_test' => [
                        'info' => [
                            'lastname' => 'Mykhalchuk',
                            'firstname' => 'Sergii',
                            'position' => 'PHP Developer, Team Lead',
                            'level' => 'Senior',
                            'trial_start' => null,
                            'trial_end' => null,
                        ],
                        'issues' => [
                            152170 => [
                                'id' => 152170,
                                'hours' => 15,
                                'title' => '#152170 Create new super feature',
                                'link' => 'https://redmine.domain.com/issues/152170',
                                'delivery_time' => 0,
                            ],
                            154949 => [
                                'id' => 154949,
                                'hours' => 5,
                                'title' => '#154949 Think about feature',
                                'link' => 'https://redmine.domain.com/issues/154949',
                                'delivery_time' => 3,
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'user2_test' => [
                        'info' => [
                            'lastname' => 'Second',
                            'firstname' => 'User',
                            'position' => 'Developer',
                            'level' => 'Junior',
                            'trial_start' => '2015-05-05-',
                            'trial_end' => '2016-09-09',
                        ],
                        'issues' => [
                            152170 => [
                                'id' => 152170,
                                'hours' => 10,
                                'title' => '#152170 Create new super feature',
                                'link' => 'https://redmine.domain.com/issues/152170',
                                'delivery_time' => 4,
                            ],
                            154949 => [
                                'id' => 154949,
                                'hours' => 2,
                                'title' => '#154949 Think about feature',
                                'link' => 'https://redmine.domain.com/issues/154949',
                                'delivery_time' => 2,
                            ],
                        ],
                    ],
                ],
            ]
        ];
    }
}
