# 07 — Decisioni aperte

Queste decisioni non sono determinate dal codice o dal prompt; non sono prerequisiti per la conclusione dell’audit, ma lo sono prima di finalizzare schema/testo legale.

1. **Necessità e base giuridica CF/indirizzo:** titolare/DPO/consulente conferma se CF e indirizzo di giocatore e genitore sono necessari, finalità, accessi e retention. Il requisito tecnico li chiede, ma la minimizzazione richiede validazione di necessità.
2. **Policy email:** confronto lowercase/trim deciso dal sistema, ma occorre approvare gestione di local-part case-sensitive e alias. Non collassare alias Gmail o plus-addressing come identità.
3. **Definizione unicità identità:** CF normalizzato come chiave di conflitto per edizione, procedura per CF mancante/duplicato preesistente/correzione manuale; il CF non è attestazione di identità.
4. **Configurazione per torneo:** limiti team 8–10 e deadline hanno requisito chiaro; restano valori iniziali e politica di riapertura (quali ruoli, durata/motivazione e impatto su sostituzioni) da impostare.
5. **Capitano account/persona:** una persona può essere capitano in più edizioni? Il requisito vieta player in più squadre nella stessa edizione, non disciplina account e riuso cross-edizione; definire modello che preservi storico.
6. **Carta e firma:** accettabilità di firma cartacea scansionata/originale e ubicazione/conservazione dell’originale. Non conservare ID; QR/ref deve essere opaco.
7. **Policy retention:** durate per iscrizione, consensi, evidenze, token, log email/IP/UA e dati minori, con cancellazione/anonimizzazione e legal hold, previa approvazione privacy.
8. **Versione deploy reale:** versione PHP/MySQL/MariaDB e limitazioni DDL/trigger/SMTP di AlterVista e schema attualmente installato non sono verificabili dal repository. Inventariare con autorizzazione in staging prima di migrazioni.
9. **Testo documenti:** privacy e responsabilità sono provvisorie. Qualsiasi testo nuovo è **BOZZA — soggetta ad approvazione legale/privacy**; titolare approva anche formulazioni separate di regolamento e foto/video.
