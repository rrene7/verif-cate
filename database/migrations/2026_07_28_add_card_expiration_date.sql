USE verif_cate;

ALTER TABLE requests
  ADD COLUMN card_expiration_date DATE NULL AFTER exact_work_location;

CREATE INDEX idx_card_expiration_date
  ON requests (card_expiration_date);

-- Después de completar cualquier registro previo, puede volver el campo obligatorio:
-- ALTER TABLE requests MODIFY card_expiration_date DATE NOT NULL;
