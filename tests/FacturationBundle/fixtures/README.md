# CAMT fixtures

- `postfinance_camt054_qr_v2019.xml`: PostFinance's public sample "camt.054 detailed notification
  of QR-bill virtual account" (V2019, camt.054.001.08), unmodified. Source:
  https://www.postfinance.ch/en/support/documents/sample-files-for-business-customers.html
- `camt054_qr_notification.xml` + `camt054_tx_*.xml`: the same structure as the PostFinance sample,
  with `{{PLACEHOLDERS}}` that `CamtImportTest` fills in. One entry is a batch credit (Sammelgutschrift)
  holding one or more transactions:
  - `Ntfctn/Acct/Id/IBAN` is the main (non-QR) account
  - `Ntry/NtryRef` and `TxDtls/RltdPties/CdtrAcct` carry the QR-IBAN that was credited
  - QR payments carry the 27-digit QR reference in `RmtInf/Strd/CdtrRefInf` with `Prtry` = `QRR`
