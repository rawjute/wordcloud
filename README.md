# Word cloud

Nuvola di parole in stile Mentimeter, con una sola domanda fissa:
**"In una parola, come ti senti un attimo prima di entrare?"**

- Tab **Home**: la domanda e un QR code.
- Gli studenti inquadrano il QR e rispondono dal telefono (senza login, massimo 20 caratteri).
- Tab **Risultati**: la nuvola di parole, aggiornata in tempo reale.

Due pagine HTML e un file PHP (`api.php`) che salva le risposte in un file JSON.
Niente database, niente processi da tenere accesi. Requisiti: Apache o Nginx con PHP 7.4+
(se sul server c'è Moodle, PHP è già pronto).

## Installazione sul server

Nei comandi, `utente@server` è l'accesso SSH al server.

### 1. Scarica il sito in `/var/www/html/wordcloud`
Sul server:
```bash
sudo git clone https://github.com/rawjute/wordcloud.git /var/www/html/wordcloud
```
Senza git sul server, scarica lo ZIP da GitHub (Code → Download ZIP), estrailo sul PC e copialo:
```powershell
scp -r wordcloud utente@server:/tmp/
```
```bash
sudo mv /tmp/wordcloud /var/www/html/
```

### 2. Crea la cartella dei dati, fuori dalla parte pubblica
```bash
sudo mkdir /var/www/wordcloud-dati
sudo chown www-data:www-data /var/www/wordcloud-dati
sudo chmod 750 /var/www/wordcloud-dati
```
`config.php` la trova da solo. È l'unica cartella in cui il server web scrive, e dal browser non
è raggiungibile (come la `moodledata` di Moodle). Su CentOS/Rocky l'utente è `apache` invece di
`www-data`; per scoprirlo: `ps -o user= -C apache2,httpd,php-fpm,nginx | sort -u`.

### 3. Imposta il PIN per "Azzera"
```bash
echo "<?php define('PIN_DOCENTE', 'il-tuo-pin');" | sudo tee /var/www/html/wordcloud/config.locale.php
```
`config.locale.php` è escluso da git, quindi i `git pull` non lo toccano.
Finché il PIN non è impostato, il pulsante Azzera non funziona.

### 4. Prova
- Apri `https://tuo-sito.it/wordcloud/api.php?azione=info`: deve comparire un testo con la domanda e
  `"sessione"`. Se compare `"errore"`, il messaggio dice quale cartella non è scrivibile e da quale utente.
- Schermo docente: `https://tuo-sito.it/wordcloud/`. Se il server ha un problema, sotto la domanda
  compare un avviso rosso, prima ancora che gli studenti provino a rispondere.

### Aggiornare
```bash
cd /var/www/html/wordcloud && sudo git pull
```

## Uso in aula
- `←` / `→` (o `1` / `2`) per cambiare tab, `F` per lo schermo intero.
- **Azzera** chiede il PIN, cancella le risposte e archivia quelle vecchie nella cartella dei dati.
- Maiuscole e minuscole non contano; ogni telefono può rispondere una volta (si riattiva dopo Azzera).
- Domanda, limite di caratteri e numero massimo di risposte si cambiano in `config.php`
  (la domanda è scritta anche in `index.html` e `rispondi.html`, che la mostrano subito).

QR code e font Inter vengono caricati da internet; senza connessione al posto del QR compare
l'indirizzo da digitare.

## Sicurezza
- Il server web scrive solo nella cartella dei dati, e solo file JSON con nomi fissi:
  nessun file caricato dagli utenti, nessun codice eseguibile.
- Le risposte sono mostrate come testo: non si può inserire HTML o JavaScript nella nuvola.
- Azzera chiede il PIN; ogni PIN sbagliato fa aspettare 1 secondo. Il PIN sta in
  `config.locale.php`, che non finisce nel repository.
- Al massimo 1000 risposte per sessione, così nessuno può riempire il disco.
- Il limite di una risposta per telefono vale solo nel browser: chi vuole può aggirarlo.
