<?php

class CRM_Financeexportformats_Hook_AlterContent_FinancialBatchPage {

  private $pageContent;

  public function __construct(&$content) {
    $this->pageContent = &$content;
  }

  public function run() {
    if (!$this->shouldRun()) {
      return;
    }

    $this->addInlineQuickbooksExportSelectOption();
  }

  private function addInlineQuickbooksExportSelectOption() {
    $this->pageContent = str_replace(
      "<option value=\"CSV\">CSV</option>",
      "<option value=\"CSV\">CSV</option><option value=\"QuickbooksCSV\">Quickbooks CSV</option>",
      $this->pageContent
    );
  }

  private function shouldRun() {
    if (version_compare(CRM_Utils_System::version(), '5.39.1', '>')) {
      return FALSE;
    }
    return TRUE;
  }

}
