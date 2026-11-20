<?php
// ===== Impostazioni del word cloud =====

// La domanda mostrata sullo schermo e sui telefoni
const DOMANDA = 'In una parola, come ti senti un attimo prima di entrare?';

// PIN richiesto per il pulsante "Azzera". Non scriverlo qui: crea accanto a questo file
// config.locale.php (escluso da git) con dentro:
//     <?php define('PIN_DOCENTE', 'il-tuo-pin');
// Finché il PIN resta 'cambiami' l'azzeramento è bloccato.
if (is_file(__DIR__ . '/config.locale.php')) {
    require __DIR__ . '/config.locale.php';
}
defined('PIN_DOCENTE') || define('PIN_DOCENTE', 'cambiami');

// Lunghezza massima di una risposta e numero massimo di risposte per sessione
const MAX_LEN = 20;
const MAX_RISPOSTE = 1000;

// Cartella dei dati: se esiste /var/www/wordcloud-dati (fuori dalla cartella pubblica,
// come la moodledata di Moodle) usa quella, altrimenti la sottocartella "dati" accanto a questo file.
define('DATA_DIR', is_dir('/var/www/wordcloud-dati') ? '/var/www/wordcloud-dati' : __DIR__ . '/dati');
