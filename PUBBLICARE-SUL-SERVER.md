# Pubblicare il word cloud sul server Linux (accanto a Moodle)

La cartella `wordcloud/` va copiata in `/var/www/html/wordcloud`, accanto a `/var/www/html/moodle`.
Contiene due pagine HTML, un file PHP (`api.php`) che salva le risposte in un file JSON,
e `config.php` con domanda e PIN. Niente database, niente processi da tenere accesi.
Dato che sul server gira Moodle, PHP è già installato e attivo.

Indirizzi finali (se Moodle risponde su https://tuo-sito.it/moodle):
- Schermo docente: **https://tuo-sito.it/wordcloud/**
- Pagina studenti (a cui porta il QR): https://tuo-sito.it/wordcloud/rispondi.html

Nei comandi sostituisci `utente` con il tuo utente SSH e `server` con l'indirizzo che usi per
collegarti in SSH (spesso l'IP interno del server, non per forza l'indirizzo pubblico del sito).

## 1. Copia la cartella (da Windows, PowerShell)
Dalla cartella che contiene `wordcloud`. Copia in `/tmp`, perché il tuo utente di solito non può
scrivere direttamente in `/var/www/html`:
```powershell
scp -r wordcloud utente@server:/tmp/
```

## 2. Spostala al suo posto (sul server, via SSH)
```bash
ssh utente@server
sudo mv /tmp/wordcloud /var/www/html/
sudo chown -R root:root /var/www/html/wordcloud
sudo chmod -R a+rX /var/www/html/wordcloud
```

## 3. Crea la cartella dei dati fuori dalla parte pubblica
```bash
sudo mkdir /var/www/wordcloud-dati
sudo chown www-data:www-data /var/www/wordcloud-dati
sudo chmod 750 /var/www/wordcloud-dati
```
`config.php` la trova da solo. È l'unica cartella in cui il server web può scrivere, e dal browser
non è raggiungibile (come la `moodledata` di Moodle).
Su CentOS/Rocky l'utente del server web è `apache` invece di `www-data`.
Per sapere qual è: `ps -o user= -C apache2,httpd,php-fpm,nginx | sort -u`.

## 4. Prova
Apri https://tuo-sito.it/wordcloud/api.php?azione=info
- Vedi un testo tipo `{"domanda":"In una parola…"}` → è tutto a posto.
- Vedi `La cartella dei dati non è scrivibile…` → ricontrolla il passo 3.

## 5. Imposta il PIN per "Azzera"
```bash
sudo nano /var/www/html/wordcloud/config.php
```
Cambia `PIN_DOCENTE`. Finché resta `cambiami`, il pulsante Azzera non funziona.

## Sicurezza: cosa è già coperto
- Il server web scrive solo in `/var/www/wordcloud-dati`, e solo file JSON con nomi fissi:
  nessun file caricato dagli utenti, nessun codice eseguibile. I file del sito appartengono a root
  e il server web non può modificarli.
- Le parole sono controllate sul server (solo lettere, una parola, massimo 25 caratteri) e mostrate
  come testo: non si può inserire HTML o JavaScript nella nuvola.
- Azzera chiede il PIN; ogni PIN sbagliato fa aspettare 1 secondo, per rallentare i tentativi.
- Al massimo 1000 risposte per sessione, così nessuno può riempire il disco.
- Il limite di una risposta per telefono vale solo nel browser: chi vuole può aggirarlo.
  Per un'attività in classe va bene.
- La sottocartella `wordcloud/dati` serve solo se salti il passo 3; in quel caso
  dovresti renderla scrivibile da `www-data`. Meglio seguire il passo 3.
