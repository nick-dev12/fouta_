-- Adresse et plafond BL cumulé HT par contact (0 = pas de limite)
ALTER TABLE contacts
    ADD COLUMN adresse TEXT NULL AFTER email;
ALTER TABLE contacts
    ADD COLUMN plafond_bl_cumul_ht DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER adresse;
