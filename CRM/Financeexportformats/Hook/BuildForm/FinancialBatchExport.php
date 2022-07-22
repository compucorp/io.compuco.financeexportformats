<?php

class CRM_Financeexportformats_Hook_BuildForm_FinancialBatchExport {

  /**
   * @var CRM_Financial_Form_Export
   */
  private $form;

  /**
   * @param $form
   */
  public function __construct($form) {
    $this->form = $form;
  }

  public function run() {
    $this->addExtraExportFormats();
  }

  /**
   * Adds extra financial batch export formats
   * to the exisitng core ones.
   */
  private function addExtraExportFormats() {
    // These options are the default export options provided
    // by CiviCRM core.
    $civiCoreOptions = [
      'IIF' => ts('Export to IIF'),
      'CSV' => ts('Export to CSV'),
    ];

    // These are the options we are adding/supporting
    // in this extension
    $extensionAddedOptions = [
      'QuickbooksCSV' => ts('Quickbooks Online journal import CSV'),
    ];

    $allOptions = array_merge($civiCoreOptions, $extensionAddedOptions);

    // Here we override the existing export format radio field options
    // with the all the available options (both CiviCRM core ones and the ones
    // added by this extension).
    $this->form->addRadio('export_format', NULL, $allOptions, NULL, '<br/>', TRUE);
  }

}
