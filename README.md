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

### Export to Invoice lines and Payments (Sage50) CSV

Unlike the normal CiviCRM Journal CSV export, the Sage50 export format only generates three types of financial lines:

Financial item lines
Payment lines
Payment Processor fee lines
CiviCRM mapping:

Type => Hardcoded to SA, SP, SI, SC based on the amount and type of the financial line item.
Account Reference => Hardcoded to "CiviCRM".
Nominal A/C ref => Financial account based on the financial line item.
Department Code => Financial code in the contribution custom field.
Date => Transaction Date.
Reference => Contribution Invoice Number.
Details => Customized based on the type of financial line item.
Net Amount => Based on the type of financial line item.
Tax Code => Financial Account type code, otherwise T2.
Tax Amount => Financial item tax amount (only displayed in the financial line).
Exchange Rate => Null.
Extra Reference => CiviCRM entity financial transaction ID.
User Name => Null.
Project Refn => Financial Type.
Cost Code Refn = Null.
