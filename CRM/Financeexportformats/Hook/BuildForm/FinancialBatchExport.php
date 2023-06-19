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
    $this->processQuickbooksFormatFromExportModalContext();
    $this->addExtraExportFormats();
  }

  /**
   * CiviCRM versions < 5.39.1 has two ways to export batches, one of them
   * shows the export formats in a separate form, while the other shows them
   * in a Modal window, this adds Quickbooks, Sage50 formats support to the Modal
   * window, and allow it to be processed from such context.
   */
  private function processQuickbooksFormatFromExportModalContext() {
    $selectedExportFormat = CRM_Utils_Request::retrieve('export_format', 'String');
    if (empty($selectedExportFormat)
      || !in_array($selectedExportFormat, ['IIF', 'CSV', 'QuickbooksCSV', 'Sage50CSV'])
    ) {
      return;
    }

    $reflection = new \ReflectionProperty(get_class($this->form), '_exportFormat');
    $reflection->setAccessible(TRUE);
    $reflection->setValue($this->form, $selectedExportFormat);
    $this->form->postProcess();
  }

  /**
   * Adds extra financial batch export formats to the existing core ones.
   */
  private function addExtraExportFormats() {
    // These options are the default export options provided
    // by CiviCRM core.
    $civiCoreOptions = [
      'IIF' => ts('Export to IIF'),
      'CSV' => ts('Export to CSV'),
    ];

    // These are the options we are adding/supporting
    // in this extension.
    $extensionAddedOptions = [
      'QuickbooksCSV' => ts('Quickbooks Online journal import CSV'),
      'Sage50CSV' => ts('Export to Invoice lines and Payments (Sage50) CSV'),
    ];

    $allOptions = array_merge($civiCoreOptions, $extensionAddedOptions);

    // Here we override the existing export format radio field options
    // with the all the available options (both CiviCRM core ones and the ones
    // added by this extension).
    $this->form->addRadio('export_format', NULL, $allOptions, NULL, '<br/>', TRUE);
  }

}
