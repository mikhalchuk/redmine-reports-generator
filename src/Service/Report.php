<?php
namespace RedmineReportsGenerator\Service;

/**
 * Class Report
 * @package RedmineReportsGenerator\Service
 */
class Report
{
    const INITIAL_NUMBER_FOR_ISSUES_LIST = 14;

    /**
     * @var \PHPExcel $excel
     */
    protected $excel;

    /**
     * @var string $outputPath output path for reports
     */
    protected $outputPath;

    /**
     * @param \PHPExcel $excel excel template for report
     * @param string $outputPath output path for reports
     */
    public function __construct(\PHPExcel $excel, $outputPath)
    {
        $this->excel = $excel;
        $this->outputPath = $outputPath;
    }

    /**
     * Generates reports from given data and saves them into configured output path
     *
     * @param array $data format is login => reportData, login it is a name of user to generate report
     * @return bool
     * @throws \PHPExcel_Exception
     */
    public function generate(array $data)
    {
        foreach ($data as $login => $report) {
            $objPHPExcel = clone $this->excel;
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

            $startIndex = self::INITIAL_NUMBER_FOR_ISSUES_LIST;
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

            $objWriter->save($this->outputPath . $login . '.xls');
        }

        return true;
    }
}
