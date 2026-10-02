-- Registra i tipi di documento richiesti dal flusso di iscrizione.
-- Sostituire/collegare gli URL ai testi ufficiali prima della messa in produzione.
ALTER TABLE token_verifica
  MODIFY COLUMN tipo ENUM('email_verifica','reset_password','invito_giocatore','consenso_genitore') NOT NULL;

INSERT INTO tipi_documento (codice, nome)
SELECT 'privacy', 'Informativa privacy' WHERE NOT EXISTS (SELECT 1 FROM tipi_documento WHERE codice = 'privacy');
INSERT INTO tipi_documento (codice, nome)
SELECT 'regolamento', 'Regolamento del torneo' WHERE NOT EXISTS (SELECT 1 FROM tipi_documento WHERE codice = 'regolamento');
INSERT INTO tipi_documento (codice, nome)
SELECT 'liberatoria_foto', 'Liberatoria foto e video' WHERE NOT EXISTS (SELECT 1 FROM tipi_documento WHERE codice = 'liberatoria_foto');

INSERT INTO documenti (id_tipo, versione, titolo, url_file, data_pubblicazione)
SELECT t.id, 1, t.nome, NULL, CURRENT_DATE FROM tipi_documento t
WHERE t.codice IN ('privacy', 'regolamento', 'liberatoria_foto')
AND NOT EXISTS (SELECT 1 FROM documenti d WHERE d.id_tipo = t.id);
