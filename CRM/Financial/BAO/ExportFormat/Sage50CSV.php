<?php

/**
 * Formats batch rows according to Sage50 CSV format.
 *
 */
class CRM_Financial_BAO_ExportFormat_Sage50CSV extends CRM_Financial_BAO_ExportFormat {

  /**
   * @param array $exportParams
   */
  public function export($exportParams) {
    $export = parent::export($exportParams);

    // Save the file in the public directory.
    $fileName = $this->putFile($export);

    $this->output($fileName);
  }

  /**
   * @param $export
   *
   * @return string
   */
  public function putFile($export) {
    $config = CRM_Core_Config::singleton();
    $fileName = $config->uploadDir . 'Financial_Transactions_' . $this->_batchIds . '_' . date('YmdHis') . '.' . $this->getFileExtension();
    $this->_downloadFile[] = $config->customFileUploadDir . CRM_Utils_File::cleanFileName(basename($fileName));
    $out = fopen($fileName, 'w');
    if (!empty($export['headers'])) {
      fputcsv($out, $export['headers']);
    }
    unset($export['headers']);
    if (!empty($export)) {
      foreach ($export as $fields) {
        fputcsv($out, $fields);
      }
      fclose($out);
    }
    return $fileName;
  }

  /**
   * @return string
   */
  public function getFileExtension() {
    return 'csv';
  }

  /**
   * @param int $batchId
   *
   * @return Object
   */
  public function generateExportQuery($batchId) {
    return CRM_Financial_BAO_ExportFormat_DataProvider_Sage50CSVProvider::runExportQuery($batchId);
  }

  /**
   * Generates CSV array for export.
   *
   * @param array $export
   */
  public function makeExport($export) {
    foreach ($export as $batchId => $exportResultDao) {
      $financialItems = [];
      $this->_batchIds = $batchId;

      $dataProvider = new CRM_Financial_BAO_ExportFormat_DataProvider_Sage50CSVProvider();
      list($queryResults, $financialItems) = $dataProvider->formatDataRows($exportResultDao);

      CRM_Utils_Hook::batchItems($queryResults, $financialItems);

      $financialItems['headers'] = $this->formatHeaders($financialItems);
      $this->export($financialItems);
    }

    parent::initiateDownload();
  }

  /**
   * Formats table headers.
   *
   * @param array $values
   * @return array
   */
  public function formatHeaders($values) {
    $arrayKeys = array_keys($values);
    $headers = [];
    if (!empty($arrayKeys)) {
      foreach ($values[$arrayKeys[0]] as $title => $value) {
        $headers[] = $title;
      }
    }
    return $headers;
  }

}
