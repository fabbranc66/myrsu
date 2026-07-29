ALTER TABLE emails
  ADD COLUMN bcc_emails TEXT NULL AFTER cc_emails;
