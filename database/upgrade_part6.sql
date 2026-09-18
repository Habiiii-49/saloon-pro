-- ============================================================
-- Elegance Salon  |  PART 6 - Payments, Invoices, Receipts,
--                  Revenue management.
-- Idempotent upgrade (safe to re-run). MariaDB 10.4+.
-- Reuses existing tables: invoices, payments, commissions,
-- site_settings, notifications, appointments, clients,
-- services, staff, users.
-- ============================================================

-- ------------------------------------------------------------
-- 1. invoices: financial tracking columns
--    (status = lifecycle; payment_status = money state)
-- ------------------------------------------------------------
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS stylist_id     INT(10) UNSIGNED      NULL                      AFTER appointment_id,
  ADD COLUMN IF NOT EXISTS paid_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00             AFTER total,
  ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid','partially_paid','paid','refunded','cancelled') NOT NULL DEFAULT 'unpaid' AFTER paid_amount,
  ADD COLUMN IF NOT EXISTS notes          TEXT                                          NULL AFTER due_at,
  ADD COLUMN IF NOT EXISTS created_by     INT(10) UNSIGNED      NULL                      AFTER notes;

CREATE INDEX IF NOT EXISTS idx_invoice_stylist        ON invoices (stylist_id);
CREATE INDEX IF NOT EXISTS idx_invoice_payment_status ON invoices (payment_status);

-- ------------------------------------------------------------
-- 2. payments: make appointment optional, tie to invoices
-- ------------------------------------------------------------
ALTER TABLE payments
  DROP FOREIGN KEY IF EXISTS fk_payments_appointment;

ALTER TABLE payments
  MODIFY appointment_id INT(10) UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS invoice_id     INT(10) UNSIGNED      NULL AFTER payment_id,
  ADD COLUMN IF NOT EXISTS client_id      INT(10) UNSIGNED      NULL AFTER invoice_id,
  ADD COLUMN IF NOT EXISTS received_by    INT(10) UNSIGNED      NULL AFTER client_id,
  ADD COLUMN IF NOT EXISTS refunded_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount,
  ADD COLUMN IF NOT EXISTS notes          TEXT                                          NULL,
  ADD COLUMN IF NOT EXISTS request_token  VARCHAR(64)          NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uk_payment_request_token ON payments (request_token);
CREATE INDEX IF NOT EXISTS idx_payment_invoice  ON payments (invoice_id);
CREATE INDEX IF NOT EXISTS idx_payment_client   ON payments (client_id);
CREATE INDEX IF NOT EXISTS idx_payment_date     ON payments (paid_at);
CREATE INDEX IF NOT EXISTS idx_payment_method   ON payments (payment_method);

-- ------------------------------------------------------------
-- 3. invoice_items - snapshot-based line items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_items (
  invoice_item_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id      INT(10) UNSIGNED NOT NULL,
  service_id      INT(10) UNSIGNED NULL,
  service_name    VARCHAR(255) NOT NULL,
  quantity        INT NOT NULL DEFAULT 1,
  unit_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  line_total      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (invoice_item_id),
  KEY idx_ii_invoice (invoice_id),
  CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (invoice_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. receipts - printable proof per payment (unique number)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS receipts (
  receipt_id     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id     INT(10) UNSIGNED NOT NULL,
  receipt_number VARCHAR(50) NOT NULL,
  generated_by   INT(10) UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (receipt_id),
  UNIQUE KEY uk_receipt_number (receipt_number),
  KEY idx_receipt_payment (payment_id),
  CONSTRAINT fk_receipt_payment FOREIGN KEY (payment_id) REFERENCES payments (payment_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. refunds - audit-trail refunds (original payment kept)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS refunds (
  refund_id        INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id       INT(10) UNSIGNED NOT NULL,
  invoice_id       INT(10) UNSIGNED NOT NULL,
  amount           DECIMAL(10,2) NOT NULL,
  refund_reason    TEXT NULL,
  refund_reference VARCHAR(255) NULL,
  status           ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
  processed_by     INT(10) UNSIGNED NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (refund_id),
  KEY idx_refund_payment  (payment_id),
  KEY idx_refund_invoice  (invoice_id),
  KEY idx_refund_status   (status),
  CONSTRAINT fk_refund_payment FOREIGN KEY (payment_id) REFERENCES payments (payment_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_refund_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (invoice_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. financial_audit_logs - who did what, when (no deletes)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS financial_audit_logs (
  audit_id     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT(10) UNSIGNED NULL,
  entity_type  VARCHAR(50) NOT NULL,
  entity_id    INT NOT NULL DEFAULT 0,
  action       VARCHAR(100) NOT NULL,
  old_value    TEXT NULL,
  new_value    TEXT NULL,
  reason       VARCHAR(255) NULL,
  ip_address   VARCHAR(45) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_log_entity (entity_type, entity_id),
  KEY idx_log_action (action),
  KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. settings seeds (do not overwrite existing values)
-- ------------------------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('financial_currency',     'PKR'),
  ('financial_tax_rate',     '0'),
  ('invoice_prefix',         'INV'),
  ('receipt_prefix',         'RCT'),
  ('financial_invoice_terms','Payment is due within 14 days of the issue date.'),
  ('financial_invoice_footer','Thank you for choosing '
     'Elegance Salon. We appreciate your business.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ------------------------------------------------------------
-- 8. Backfill existing (real) data so Part 6 financials are coherent.
--    No invented amounts are inserted: figures come from the
--    appointments / payments already present in the database.
-- ------------------------------------------------------------
UPDATE payments p
JOIN invoices i ON i.appointment_id = p.appointment_id
SET p.invoice_id = i.invoice_id, p.client_id = i.client_id
WHERE p.invoice_id IS NULL;

UPDATE payments p
JOIN appointments a ON a.appointment_id = p.appointment_id
SET p.client_id = a.client_id
WHERE p.client_id IS NULL AND p.appointment_id IS NOT NULL;

-- Recompute invoices.paid_amount + payment_status from valid payments.
UPDATE invoices i
SET i.paid_amount    = COALESCE((
      SELECT SUM(p.amount) FROM payments p
      WHERE p.invoice_id = i.invoice_id
        AND p.payment_status IN ('paid','completed')
    ), 0.00),
    i.created_by     = COALESCE(i.created_by, 1);

UPDATE invoices i
SET i.payment_status = CASE
      WHEN i.status = 'cancelled' THEN 'cancelled'
      WHEN i.paid_amount >= i.total AND i.total > 0 THEN 'paid'
      WHEN i.paid_amount > 0 THEN 'partially_paid'
      ELSE 'unpaid'
    END;

-- Itemize legacy invoices from their appointment's service (real data).
INSERT INTO invoice_items (invoice_id, service_id, service_name, quantity, unit_price, discount, tax, line_total)
SELECT i.invoice_id, a.service_id, s.service_name, 1, i.total, i.discount, i.tax_amount, i.total
FROM invoices i
JOIN appointments a ON a.appointment_id = i.appointment_id
LEFT JOIN services s ON s.service_id = a.service_id
WHERE i.status <> 'cancelled'
  AND NOT EXISTS (SELECT 1 FROM invoice_items ii WHERE ii.invoice_id = i.invoice_id);