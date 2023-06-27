<?php

class CRM_Financial_BAO_ExportFormat_DataProvider_Sage50CSVProvider {

  const TYPE_LABEL = 'Type',
        ACCOUNT_REFERENCE_LABEL = 'Account Reference',
        NOMINAL_AC_REF_LABEL = 'Nominal A/C Ref',
        DEPARTMENT_CODE_LABEL = 'Department Code',
        DATE_LABEL = 'Date',
        REFERENCE_LABEL = 'Reference',
        DETAILS_LABEL = 'Details',
        NET_AMOUNT_LABEL = 'Net Amount',
        TAX_CODE_LABEL = 'Tax Code',
        TAX_AMOUNT_LABEL = 'Tax Amount',
        EXCHANGE_RATE_LABEL = 'Exchange Rate',
        EXTRA_REFERENCE = 'Extra Reference',
        USER_NAME_LABEL = 'User Name',
        PROJECT_REFN_LABEL = 'Project Refn',
        COST_CODE_REFN_LABEL = 'Cost Code Refn';

  private $financialTypeTaxCodeMap;
  private $invoicePrefixValue;

  public function __construct() {
    $this->invoicePrefixValue = Civi::settings()->get('contribution_invoice_settings');
    $this->financialTypeTaxCodeMap = [];
  }

  /**
   *
   * @param int $batchId
   *
   * @return CRM_Core_DAO
   */
  public static function runExportQuery($batchId) {
    $sql = "SELECT
      c.id as contribution_id,
      con.display_name as contact_display_name,
      c.invoice_number,
      ft.id as financial_trxn_id,
      ft.trxn_date,
      ft.is_payment,
      ft.total_amount AS debit_total_amount,
      ft.trxn_id AS trxn_id,
      CASE
        WHEN efti.entity_id IS NOT NULL
        THEN efti.amount
        ELSE eftc.amount
      END AS amount,
      fa_from.account_type_code AS credit_account_type_code,
      fa_from.accounting_code AS credit_account,
      fa_from.name AS credit_account_name,
      fac.account_type_code AS from_credit_account_type_code,
      fac.accounting_code AS from_credit_account,
      fac.name AS from_credit_account_name,
      fi.amount as net_amount,
      eftc.id as civicrm_entity_financial_trxn_id,
      li.label as item_description,
      li.tax_amount as line_item_tax_amount,
      li.financial_type_id as line_item_financial_type_id
    FROM civicrm_entity_batch eb
             LEFT JOIN civicrm_financial_trxn ft ON (eb.entity_id = ft.id AND eb.entity_table = 'civicrm_financial_trxn')
             LEFT JOIN civicrm_financial_account fa_from ON fa_from.id = ft.from_financial_account_id
             LEFT JOIN civicrm_entity_financial_trxn eftc ON (eftc.financial_trxn_id  = ft.id AND eftc.entity_table = 'civicrm_contribution')
             LEFT JOIN civicrm_contribution c ON c.id = eftc.entity_id
             LEFT JOIN civicrm_contact con on con.id = c.contact_id
             LEFT JOIN civicrm_entity_financial_trxn efti ON (efti.financial_trxn_id  = ft.id AND efti.entity_table = 'civicrm_financial_item')
             LEFT JOIN civicrm_financial_item fi ON fi.id = efti.entity_id
             LEFT JOIN civicrm_line_item li ON (li.id = fi.entity_id AND fi.entity_table = 'civicrm_line_item')
             LEFT JOIN civicrm_financial_account fac ON fac.id = fi.financial_account_id
    WHERE eb.batch_id = ( %1 )";

    CRM_Utils_Hook::batchQuery($sql);

    $params = [1 => [$batchId, 'Integer']];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    return $dao;
  }

  /**
   * @param $exportResultDao
   *
   * @return array
   */
  public function formatDataRows($exportResultDao) {
    $financialItems = $queryResults = [];
    while ($exportResultDao->fetch()) {
      $item = [
        self::TYPE_LABEL => NULL,
        self::ACCOUNT_REFERENCE_LABEL => 'CiviCRM',
        self::NOMINAL_AC_REF_LABEL => NULL,
        self::DEPARTMENT_CODE_LABEL => NULL,
        self::DATE_LABEL => CRM_Utils_Date::customFormat($exportResultDao->trxn_date, '%d/%m/%Y'),
        self::REFERENCE_LABEL => $exportResultDao->invoice_number,
        self::DETAILS_LABEL => NULL,
        self::NET_AMOUNT_LABEL => NULL,
        self::TAX_CODE_LABEL => NULL,
        self::TAX_AMOUNT_LABEL => $exportResultDao->line_item_tax_amount,
        self::EXCHANGE_RATE_LABEL => NULL,
        self::EXTRA_REFERENCE => NULL,
        self::USER_NAME_LABEL => NULL,
        self::PROJECT_REFN_LABEL => NULL,
        self::COST_CODE_REFN_LABEL => NULL,
      ];

      if ($exportResultDao->is_payment == 0) {
        $formattedItem = $this->formatFinancialItemLines($item, $exportResultDao);
        if (is_null($formattedItem)) {
          continue;
        }

        $financialItems[] = $formattedItem;
      }

      end($financialItems);
      $queryResults[] = get_object_vars($exportResultDao);
    }

    return [$queryResults, $financialItems];
  }

  private function formatFinancialItemLines(array $item, $exportResultDao) {
    $creditAccount = $exportResultDao->credit_account;
    if (!is_null($creditAccount)) {
      return NULL;
    }

    if ($exportResultDao->amount >= 0) {
      $item[self::TYPE_LABEL] = 'SI';
    }
    else {
      $item[self::TYPE_LABEL] = 'SC';
    }

    $item[self::NOMINAL_AC_REF_LABEL] = $exportResultDao->from_credit_account;
    $item[self::DETAILS_LABEL] = $exportResultDao->contact_display_name . ' - ' . $exportResultDao->item_description;
    $item[self::NET_AMOUNT_LABEL] = $exportResultDao->net_amount;
    $item[self::EXTRA_REFERENCE] = $exportResultDao->civicrm_entity_financial_trxn_id;
    if (!is_null($exportResultDao->line_item_tax_amount)) {
      $item[self::TAX_CODE_LABEL] = $this->getFinancialIemLinesTaxCodeByFinancialID($exportResultDao->line_item_financial_type_id);
    }
    return $item;
  }

  private function getFinancialIemLinesTaxCodeByFinancialID($financialTypeID) {
    if (isset($this->financialTypeTaxCodeMap[$financialTypeID])) {
      return $this->financialTypeTaxCodeMap[$financialTypeID];
    }

    $sql = "SELECT
        efa.id,
        fa.account_type_code
    FROM civicrm_entity_financial_account efa
        INNER JOIN civicrm_financial_account fa ON fa.id = efa.financial_account_id
    WHERE
        efa.entity_id = %1 AND efa.entity_table = 'civicrm_financial_type' AND fa.is_tax = 1";

    $params = [1 => [$financialTypeID, 'Integer']];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);

    $taxCode = 'T2';
    while ($dao->fetch()) {
      $accountTypeCode = $dao->account_type_code;
      if (!is_null($accountTypeCode)) {
        $taxCode = $accountTypeCode;
      }
    }

    // Cache tax code to the financial type.
    $this->financialTypeTaxCodeMap[$financialTypeID] = $taxCode;

    return $taxCode;
  }

}
