<?php

class CRM_Financeexportformats_Hook_AlterContent_FinancialExportForm {

  private $pageContent;

  public function __construct(&$content) {
    $this->pageContent = &$content;
  }

  public function run() {
    $this->fixFormButtonsAlignment();
  }

  private function fixFormButtonsAlignment() {
    $this->pageContent = str_replace(
      "<div class=\"form-item\">",
      "<div class=\"form-item\" style=\"padding: 10px;height: 40px;\">",
      $this->pageContent
    );
  }

}
