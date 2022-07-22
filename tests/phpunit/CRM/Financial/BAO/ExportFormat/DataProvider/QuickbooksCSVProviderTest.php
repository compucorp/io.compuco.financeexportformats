<?php

/**
 * Class CRM_Financial_BAO_ExportFormat_DataProvider_QuickbooksCSVProviderTest
 *
 * @group headless
 */
class CRM_Financial_BAO_ExportFormat_DataProvider_QuickbooksCSVProviderTest extends BaseHeadlessTest {

  public function testDebitAndCreditRowsAreGenerated() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    $this->assertCount(2, $data['batch_rows']);
    $this->assertEquals($data['batch_rows'][0]['Debits'], 50);
    $this->assertEquals($data['batch_rows'][0]['Credits'], '');
    $this->assertEquals($data['batch_rows'][1]['Debits'], '');
    $this->assertEquals($data['batch_rows'][1]['Credits'], 50);
  }

  public function testJournalNumberColumnEqualsFinancialTrxnId() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    $this->assertEquals($data['batch_rows'][0]['*JournalNo'], $data['contribution_data']['financial_trxn_id']);
    $this->assertEquals($data['batch_rows'][1]['*JournalNo'], $data['contribution_data']['financial_trxn_id']);
  }

  public function testJournalDateIsInDDMMYYFormat() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    $this->assertEquals($data['batch_rows'][0]['*JournalDate'], '01/07/2022');
    $this->assertEquals($data['batch_rows'][1]['*JournalDate'], '01/07/2022');
  }

  public function testCurrencyColumnIsInIsoFormat() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    $this->assertEquals($data['batch_rows'][0]['*Currency'], 'USD');
    $this->assertEquals($data['batch_rows'][1]['*Currency'], 'USD');
  }

  public function testMemoColumnEqualsInvoiceNumber() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    $prefixValue = Civi::settings()->get('contribution_invoice_settings');
    $invoiceNumber = CRM_Utils_Array::value('invoice_prefix', $prefixValue) . "" . $data['contribution_data']['contribution_id'];

    $this->assertEquals($data['batch_rows'][0]['Memo'], $invoiceNumber);
    $this->assertEquals($data['batch_rows'][1]['Memo'], $invoiceNumber);
  }

  public function testNameColumnEqualsContactId() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    $this->assertEquals($data['batch_rows'][0]['Name'], $data['contribution_data']['contact_id']);
    $this->assertEquals($data['batch_rows'][1]['Name'], $data['contribution_data']['contact_id']);
  }

  public function testLocationColumnEqualsFinancialAccountOwnerId() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    // All existing financial Account on Civi demo database are owned
    // by the "Default organization".
    $financialAccountOwnerId = 1;
    $this->assertEquals($data['batch_rows'][0]['Location'], $financialAccountOwnerId);
    $this->assertEquals($data['batch_rows'][1]['Location'], $financialAccountOwnerId);
  }

  public function testClassColumnEqualsFinancialTypeName() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    $this->assertEquals($data['batch_rows'][0]['Class'], 'Donation');
    $this->assertEquals($data['batch_rows'][1]['Class'], 'Donation');
  }

  public function testDebitRowAccountNamePointsToCorrectToAccount() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    // "Deposit Bank Account" is the is debit account for "Cash" payment method.
    $this->assertEquals($data['batch_rows'][0]['*AccountName'], 'Deposit Bank Account');
  }

  public function testCreditRowAccountNamePointsToCorrectFromAccount() {
    $data = $this->createTestContributionAndGenerateBatchRows();

    // "Donation" is the is credit account for "Donation" financial type.
    $this->assertEquals($data['batch_rows'][1]['*AccountName'], 'Donation');
  }

  public function testHavingMultipleContributionsInBatchGeneratesCorrectNumberOfRows() {
    $data = $this->generateContributionInBatch();
    $this->generateContributionInBatch($data['batch_id']);
    $this->generateContributionInBatch($data['batch_id']);

    $exportResultDao = CRM_Financial_BAO_ExportFormat_DataProvider_QuickbooksCSVProvider::runExportQuery($data['batch_id']);
    list($queryResults, $rows) = CRM_Financial_BAO_ExportFormat_DataProvider_QuickbooksCSVProvider::formatDataRows($exportResultDao);

    $this->assertCount(6, $rows);
  }

  /**
   * Creates test contribution in a batch and
   * generated the Quickbooks batch rows for it.
   *
   * @return array
   */
  private function createTestContributionAndGenerateBatchRows() {
    $batchData = $this->generateContributionInBatch();

    $exportResultDao = CRM_Financial_BAO_ExportFormat_DataProvider_QuickbooksCSVProvider::runExportQuery($batchData['batch_id']);
    list($queryResults, $rows) = CRM_Financial_BAO_ExportFormat_DataProvider_QuickbooksCSVProvider::formatDataRows($exportResultDao);

    return ['contribution_data' => $batchData, 'batch_rows' => $rows];
  }

  /**
   * Generates a contribution and a batch and adds
   * the contribution to that batch, or adds it to
   * existing batch if it is id is provided.
   *
   * @param int $existingBatchId
   *
   * @return array
   */
  private function generateContributionInBatch($existingBatchId = NULL) {
    $randomName = 'test_' . substr(md5(mt_rand()), 0, 5);

    $contactId = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => $randomName,
      'last_name' => 'cc',
    ])['id'];

    $contribution = civicrm_api3('Contribution', 'create', [
      'financial_type_id' => 'Donation',
      'receive_date' => '2022-07-01',
      'total_amount' => 50,
      'contact_id' => $contactId,
      'payment_instrument_id' => 'Cash',
    ]);

    $financialTrxnId = civicrm_api3('FinancialTrxn', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 1, 'sort' => 'id desc'],
    ])['id'];

    $batchId = $existingBatchId;
    if (empty($existingBatchId)) {
      $batchId = civicrm_api3('Batch', 'create', [
        'title' => $randomName,
        'name' => $randomName,
        'status_id' => 'Open',
      ])['id'];
    }

    civicrm_api3('EntityBatch', 'create', [
      'entity_id' => $financialTrxnId,
      'batch_id' => $batchId,
      'entity_table' => 'civicrm_financial_trxn',
    ]);

    return [
      'contact_id' => $contactId,
      'contribution_id' => $contribution['id'],
      'financial_trxn_id' => $financialTrxnId,
      'batch_id' => $batchId,
    ];
  }

}
