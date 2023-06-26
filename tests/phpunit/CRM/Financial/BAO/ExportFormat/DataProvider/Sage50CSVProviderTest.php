<?php

use CRM_Financial_BAO_ExportFormat_DataProvider_Sage50CSVProvider as Sage50CSVProvider;

/**
 * @group headless
 */
class CRM_Financial_BAO_ExportFormat_DataProvider_Sage50CSVProviderTest extends BaseHeadlessTest {

  public function setup(): void {
    $this->setContributionSettings();
  }

  public function testFinancialIemLinesWithoutConfigureVAT() {
    $contributionData = $this->mockContributionInBatch(100);
    $exportResultDao = Sage50CSVProvider::runExportQuery($contributionData['batch_id']);
    $provider = new Sage50CSVProvider();

    list($queryResults, $rows) = $provider->formatDataRows($exportResultDao);
    $row = $rows[0];

    $contribution = $this->getContributionByID($contributionData['contribution_id']);
    $lineItem = $this->getLineItemByContributionID($contribution['id']);

    $this->assertEquals('SI', $row[Sage50CSVProvider::TYPE_LABEL]);
    $this->assertEquals('CiviCRM', $row[Sage50CSVProvider::ACCOUNT_REFERENCE_LABEL]);
    $this->assertEquals(4200, $row[Sage50CSVProvider::NOMINAL_AC_REF_LABEL]);
    $this->assertEquals(NULL, $row[Sage50CSVProvider::DEPARTMENT_CODE_LABEL]);
    $this->assertEquals('01/06/2023', $row[Sage50CSVProvider::DATE_LABEL]);
    $this->assertEquals($contribution['invoice_number'], $row[Sage50CSVProvider::REFERENCE_LABEL]);
    $this->assertEquals($contribution['display_name'] . ' - ' . $lineItem['label'], $row[Sage50CSVProvider::DETAILS_LABEL]);
    $this->assertEquals(100, $row[Sage50CSVProvider::NET_AMOUNT_LABEL]);
    $this->assertEquals('T2', $row[Sage50CSVProvider::TAX_CODE_LABEL]);
    $this->assertEquals(0, $row[Sage50CSVProvider::TAX_AMOUNT_LABEL]);
  }

  public function testFinancialItemLinesWithZeroRatedVAT() {
    $this->mockSalesTaxFinancialAccount(0);
    $contributionData = $this->mockContributionInBatch(100);
    $exportResultDao = Sage50CSVProvider::runExportQuery($contributionData['batch_id']);
    $provider = new Sage50CSVProvider();

    list($queryResults, $rows) = $provider->formatDataRows($exportResultDao);
    $row = $rows[0];
    $this->assertEquals(0, $row[Sage50CSVProvider::TAX_AMOUNT_LABEL]);
  }

  public function testFinancialIemLinesWithVAT() {
    $this->mockSalesTaxFinancialAccount();
    $contributionData = $this->mockContributionInBatch(120);

    $exportResultDao = Sage50CSVProvider::runExportQuery($contributionData['batch_id']);
    $provider = new Sage50CSVProvider();

    list($queryResults, $rows) = $provider->formatDataRows($exportResultDao);
    $row = $rows[0];
    $this->assertEquals(20, $row[Sage50CSVProvider::TAX_AMOUNT_LABEL]);
  }

  public function testFinancialIemLinesNegativeChangeOnAContribution() {
    $contributionData = $this->mockContributionInBatch(100);
    $this->mockNonPaymentNegativeBalanceTxrn($contributionData['contribution_id'], $contributionData['batch_id'], 50);

    $exportResultDao = Sage50CSVProvider::runExportQuery($contributionData['batch_id']);
    $provider = new Sage50CSVProvider();
    list($queryResults, $rows) = $provider->formatDataRows($exportResultDao);

    $row = $rows[0];
    $this->assertEquals('SI', $row[Sage50CSVProvider::TYPE_LABEL]);

    $row = $rows[1];
    $this->assertEquals('SC', $row[Sage50CSVProvider::TYPE_LABEL]);
  }

  /**
   * Sets CiviContribute settings.
   */
  private function setContributionSettings() {
    Civi::settings()->set('always_post_to_accounts_receivable', TRUE);
    Civi::settings()->set('invoicing', TRUE);
    Civi::settings()->set('invoice_prefix', "INV_");
    Civi::settings()->set('tax_term', "Sales Tax");
  }

  /**
   * Mocks Negative Balance Transaction for non payment transaction.
   *
   * Create a negative on balance on contribution and financial transaction.
   * This method only provide the records that batch export could handle
   * The sage50 type e.g. SI or SC. It does not provide completed entities in
   * CiviCRM financial transactions.
   */
  private function mockNonPaymentNegativeBalanceTxrn($contributionID, $batchID, $amount) {
    $contribution = $this->getContributionByID($contributionID);
    $balanceAmount = $contribution['total_amount'] - $amount;
    civicrm_api3('contribution', 'create', [
      'id' => $contribution['contribution_id'],
      'total_amount' => $balanceAmount,
    ]);

    $toFinancialAccount = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($contribution['financial_type_id'], 'Accounts Receivable Account is');
    $adjustedTrxnValues = array(
      'from_financial_account_id' => NULL,
      'to_financial_account_id' => $toFinancialAccount,
      'total_amount' => -$balanceAmount,
      'net_amount' => -$balanceAmount,
      'payment_instrument_id' => $contribution['payment_instrument_id'],
      'contribution_id' => $contribution['id'],
      'trxn_date' => date('YmdHis'),
      'currency' => $contribution['currency'],
      'is_payment' => FALSE,
    );
    $adjustedTrxn = CRM_Core_BAO_FinancialTrxn::create($adjustedTrxnValues);

    civicrm_api3('EntityBatch', 'create', [
      'entity_id' => $adjustedTrxn->id,
      'batch_id' => $batchID,
      'entity_table' => 'civicrm_financial_trxn',
    ]);
  }

  /**
   * Creates financial transaction including payments and
   * add transactions to entity batch.
   *
   * @return array
   */
  private function mockContributionInBatch($amount) {
    $contact = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'John',
      'last_name' => 'Smith',
    ]);
    $contactId = $contact['id'];

    $order = civicrm_api3('Order', 'create', [
      'contact_id' => $contactId,
      'financial_type_id' => "Donation",
      'total_amount' => $amount,
      'receive_date' => '2023-06-01',
    // This creates a non payment financial transaction.
      'is_pay_later' => TRUE,
    ]);

    civicrm_api3('Payment', 'create', [
      'contribution_id' => $order['id'],
      'total_amount' => $amount,
      'trxn_date' => '2023-06-01',
      'payment_instrument_id' => 'Credit Card',
    //Test Payment Processor
      'payment_processor' => 1,
    ]);

    $financialTrxnIds = civicrm_api3('FinancialTrxn', 'get', [
      'sequential' => 1,
      'options' => ['sort' => 'id desc'],
    ])['values'];

    $randomName = 'test_' . substr(md5(mt_rand()), 0, 5);
    $batchId = civicrm_api3('Batch', 'create', [
      'title' => $randomName,
      'name' => $randomName,
      'status_id' => 'Open',
    ])['id'];

    foreach ($financialTrxnIds as $fTrxn) {
      civicrm_api3('EntityBatch', 'create', [
        'entity_id' => $fTrxn['id'],
        'batch_id' => $batchId,
        'entity_table' => 'civicrm_financial_trxn',
      ]);
    }

    return [
      'contact_id' => $contactId,
      'contact_name' => $contact['values'][$contactId]['display_name'],
      'contribution_id' => $order['id'],
      'batch_id' => $batchId,
    ];
  }

  /**
   * This function helps to mock Sale Tax financial account
   * for simulating the financial tax for given financial type.
   *
   * @param int $taxRate
   * @param string $financialTypeName
   *
   * @throws CiviCRM_API3_Exception
   */
  private function mockSalesTaxFinancialAccount($taxRate = 20, $financialTypeName = 'Donation') {
    $existingRecordResponse = civicrm_api3('FinancialAccount', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 1],
      'name' => 'Sales Tax',
    ]);

    if (empty($existingRecordResponse['id'])) {
      $financialAccount = civicrm_api3('FinancialAccount', 'create', [
        'name' => 'Sales Tax',
        'contact_id' => 1,
        'financial_account_type_id' => 'Liability',
        'accounting_code' => 5500,
        'is_header_account' => 0,
        'is_deductible' => TRUE,
        'is_tax' => TRUE,
        'tax_rate' => $taxRate,
        'is_active' => TRUE,
        'is_default' => 0,
      ]);

      civicrm_api3('EntityFinancialAccount', 'create', [
        'entity_table' => 'civicrm_financial_type',
        'account_relationship' => 'Sales Tax Account is',
        'financial_account_id' => $financialAccount['id'],
        'entity_id' => $this->getFinancialTypeID($financialTypeName),
      ]);
    }
  }

  /**
   * Gets financial type ID from given financial type name.
   *
   * @param $financialTypeName
   *
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  protected function getFinancialTypeID($financialTypeName) {
    $financialType = civicrm_api3('FinancialType', 'get', [
      'name' => $financialTypeName,
      'sequential' => 1,
    ]);

    if ($financialType['count'] == 0) {
      return CRM_MembershipExtras_Test_Fabricator_FinancialType::fabricate([
        'sequential' => 1,
        'name' => $financialTypeName,
      ])['id'];
    }

    return $financialType['values'][0]['id'];
  }

  private function getContributionByID($id) {
    return civicrm_api3('Contribution', 'getsingle', [
      'id' => $id,
    ]);
  }

  private function getLineItemByContributionID($id) {
    return civicrm_api3('LineItem', 'getsingle', [
      'entity_table' => "civicrm_contribution",
      'contribution_id' => $id,
    ]);
  }

}
