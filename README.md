# CiviCRM Finance export formats

This extension adds additional finance export formats, at the moment it adds support for the following formats:

### Quickbooks Online journal (CSV Import):
More details about this format can be found here:
   https://quickbooks.intuit.com/learn-support/en-us/help-article/bank-transactions/format-csv-files-excel-get-bank-transactions/L4BjLWckq_US_en_US
 , where it does the following "Quickbooks => CiviCRM" mapping:
- *JournalNo => Financial Trxn ID/Internal ID.
- *JournalDate => Transaction Date (Format as dd/mm/yyyy)
- *Currency => Currency (Format as iso short currency i.e. “GBP” for UK£)
- Memo => Invoice No
- *AccountName => "Debit Account Name" for debit row, and "Credit Account Name" for credit row
- Debits => "Amount" for debit row, "Null" for credit row
- Credits => "Amount" for credit row, "Null" for debit row
- Description => Item description
- Name => Contact ID
- Location => Financial Account Owner (which vary based on debit or credit row account)
- Class => Financial type
