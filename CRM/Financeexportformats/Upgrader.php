<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Financeexportformats_Upgrader extends CRM_Financeexportformats_Upgrader_Base {

  const FINANCIAL_CODES = 'financial_codes';

  /**
   * Tasks to perform during installation.
   *
   * @throws CiviCRM_API3_Exception
   */
  public function uninstall(): void {
    $this->removeCustomGroups();
  }

  /**
   * Tasks to perform during enabling the extension.
   *
   * @throws CiviCRM_API3_Exception
   */
  public function enable(): void {
    $this->enableCustomGroups();
  }

  /**
   * Tasks to perform during disabling the extension.
   *
   * @throws CiviCRM_API3_Exception
   */
  public function disable(): void {
    $this->disableCustomGroups();
  }

  private function removeCustomGroups() {
    $customFields = [
      'financial_department_code',
    ];
    foreach ($customFields as $customFieldName) {
      civicrm_api3('CustomField', 'get', [
        'name' => $customFieldName,
        'custom_group_id' => self::FINANCIAL_CODES,
        'api.CustomField.delete' => ['id' => '$value.id'],
      ]);
    }

    civicrm_api3('CustomGroup', 'get', [
      'name' => self::FINANCIAL_CODES,
      'api.CustomGroup.delete' => ['id' => '$value.id'],
    ]);
  }

  private function disableCustomGroups() {
    civicrm_api3('CustomGroup', 'get', [
      'name' => self::FINANCIAL_CODES,
      'api.CustomGroup.create' => ['id' => '$value.id', 'is_active' => 0],
    ]);
  }

  private function enableCustomGroups() {
    civicrm_api3('CustomGroup', 'get', [
      'name' => self::FINANCIAL_CODES,
      'api.CustomGroup.create' => ['id' => '$value.id', 'is_active' => 1],
    ]);
  }

}
