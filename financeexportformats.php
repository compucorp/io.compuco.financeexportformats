<?php

require_once 'financeexportformats.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function financeexportformats_civicrm_config(&$config) {
  _financeexportformats_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function financeexportformats_civicrm_install() {
  _financeexportformats_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function financeexportformats_civicrm_postInstall() {
  _financeexportformats_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function financeexportformats_civicrm_uninstall() {
  _financeexportformats_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function financeexportformats_civicrm_enable() {
  _financeexportformats_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function financeexportformats_civicrm_disable() {
  _financeexportformats_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function financeexportformats_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _financeexportformats_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function financeexportformats_civicrm_entityTypes(&$entityTypes) {
  _financeexportformats_civix_civicrm_entityTypes($entityTypes);
}

function financeexportformats_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Financial_Form_Export') {
    $hook = new CRM_Financeexportformats_Hook_BuildForm_FinancialBatchExport($form);
    $hook->run();
  }
}

function financeexportformats_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  if ($tplName == "CRM/Financial/Page/Batch.tpl") {
    $hook = new CRM_Financeexportformats_Hook_AlterContent_FinancialBatchPage($content);
    $hook->run();
  }

  if ($tplName == "CRM/Financial/Form/Export.tpl") {
    $hook = new CRM_Financeexportformats_Hook_AlterContent_FinancialExportForm($content);
    $hook->run();
  }
}
