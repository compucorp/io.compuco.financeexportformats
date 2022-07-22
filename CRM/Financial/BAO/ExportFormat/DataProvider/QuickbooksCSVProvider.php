<?php

/**
 * Generates batch data formatted in Quickbooks CSV
 * format.
 *
 *
 */
class CRM_Financial_BAO_ExportFormat_DataProvider_QuickbooksCSVProvider {

  /**
   * generates and run the query that
   * generates the batch rows data.
   * @param int $batchId
   *
   * @return CRM_Core_DAO
   */
  public static function runExportQuery($batchId) {
    $sql = "SELECT
      ft.id as financial_trxn_id,
      DATE_FORMAT(ft.trxn_date, '%d/%m/%Y') as trxn_date,
      fa_to.name AS to_account_name,
      fa_to.contact_id AS to_account_contact_id,
      c.source AS source,
      c.id AS contribution_id,
      c.contact_id AS contact_id,
      c.financial_type_id AS financial_type_id,
      ft.currency AS currency,
      CASE
        WHEN efti.entity_id IS NOT NULL
        THEN efti.amount
        ELSE eftc.amount
      END AS amount,
      fa_from.accounting_code AS credit_account,
      fa_from.name AS credit_account_name,
      fa_from.contact_id AS credit_account_contact_id,
      fac.name AS from_credit_account_name,
      fac.contact_id AS from_credit_account_contact_id,
      fi.description AS item_description
      FROM civicrm_entity_batch eb
      LEFT JOIN civicrm_financial_trxn ft ON (eb.entity_id = ft.id AND eb.entity_table = 'civicrm_financial_trxn')
      LEFT JOIN civicrm_financial_account fa_to ON fa_to.id = ft.to_financial_account_id
      LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
      LEFT JOIN civicrm_entity_financial_trxn eftc ON (eftc.financial_trxn_id  = ft.id AND eftc.entity_table = 'civicrm_contribution')
      LEFT JOIN civicrm_contribution c ON c.id = eftc.entity_id
      LEFT JOIN civicrm_entity_financial_trxn efti ON (efti.financial_trxn_id  = ft.id AND efti.entity_table = 'civicrm_financial_item')
      LEFT JOIN civicrm_financial_item fi ON fi.id = efti.entity_id
      LEFT JOIN civicrm_financial_account fac ON fac.id = fi.financial_account_id
      WHERE eb.batch_id = ( %1 )";

    CRM_Utils_Hook::batchQuery($sql);

    $params = [1 => [$batchId, 'String']];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    return $dao;
  }

  /**
   * Loops through the exported data rows
   * and formats them according to Quickbooks
   * format which divides each row into one debit
   * row and another credit row, both rows are
   * the same except for few fields.
   *
   * @param $exportResultDao
   *
   * @return array
   *   Returns two arrays, one contains only the query results without
   *   formatting, and another with the formatted data. This is needed
   *   CRM_Utils_Hook::batchItems hook.
   */
  public static function formatDataRows($exportResultDao) {
    $prefixValue = Civi::settings()->get('contribution_invoice_settings');
    $financialAccountsOwners = self::getAllFinancialAccountsOwnersIdNameMapping();

    $financialItems = $queryResults = [];

    while ($exportResultDao->fetch()) {
      if ($exportResultDao->credit_account) {
        $creditAccountName = $exportResultDao->credit_account_name;
        $creditAccountContactId = $exportResultDao->credit_account_contact_id;
      }
      else {
        $creditAccountName = $exportResultDao->from_credit_account_name;
        $creditAccountContactId = $exportResultDao->from_credit_account_contact_id;
      }

      $invoiceNo = CRM_Utils_Array::value('invoice_prefix', $prefixValue) . "" . $exportResultDao->contribution_id;

      $financialTypeName = CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $exportResultDao->financial_type_id);

      $debitRow = [
        '*JournalNo' => $exportResultDao->financial_trxn_id,
        '*JournalDate' => $exportResultDao->trxn_date,
        '*Currency' => $exportResultDao->currency,
        'Memo' => $invoiceNo,
        '*AccountName' => $exportResultDao->to_account_name,
        'Debits' => $exportResultDao->amount,
        'Credits' => '',
        'Description' => $exportResultDao->item_description,
        'Name' => $exportResultDao->contact_id,
        'Location' => $financialAccountsOwners[$exportResultDao->to_account_contact_id],
        'Class' => $financialTypeName,
      ];
      $financialItems[] = $debitRow;

      $creditRow = [
        '*JournalNo' => $exportResultDao->financial_trxn_id,
        '*JournalDate' => $exportResultDao->trxn_date,
        '*Currency' => $exportResultDao->currency,
        'Memo' => $invoiceNo,
        '*AccountName' => $creditAccountName,
        'Debits' => '',
        'Credits' => $exportResultDao->amount,
        'Description' => $exportResultDao->item_description,
        'Name' => $exportResultDao->contact_id,
        'Location' => $financialAccountsOwners[$creditAccountContactId],
        'Class' => $financialTypeName,
      ];
      $financialItems[] = $creditRow;

      end($financialItems);
      $queryResults[] = get_object_vars($exportResultDao);
    }

    return [$queryResults, $financialItems];
  }

  /**
   * Gets all the financial account owners id to name
   * mapping, getting the list here is better than the
   * alternative which is to do join on the whole contact table twice
   * in runExportQuery() method above, the reason is we use it to
   * get the account owner name for the "Location" column,
   * which might differ between debit and credit rows.
   *
   * @return array
   */
  private static function getAllFinancialAccountsOwnersIdNameMapping() {
    $sql = "SELECT cc.id as contact_id, cc.display_name, cc.sort_name
      FROM civicrm_financial_account fa
      INNER JOIN civicrm_contact cc ON fa.contact_id = cc.id";
    $dao = CRM_Core_DAO::executeQuery($sql);

    $contactIdNameMapping = [];
    while ($dao->fetch()) {
      if (!empty($dao->display_name)) {
        $contactIdNameMapping[$dao->contact_id] = $dao->display_name;
      }
      else {
        $contactIdNameMapping[$dao->contact_id] = $dao->sort_name;
      }
    }

    return $contactIdNameMapping;
  }

}
