<?php
// ===== Impostazioni del word cloud =====

// La domanda mostrata sullo schermo e sui telefoni
const DOMANDA = 'In una parola, come ti senti un attimo prima di entrare?';

// PIN richiesto per il pulsante "Azzera". CAMBIALO prima di usare il sito:
// finché resta 'cambiami' l'azzeramento è bloccato.
const PIN_DOCENTE = 'cambiami';

// Lunghezza massima di una parola e numero massimo di risposte per sessione
const MAX_LEN = 25;
const MAX_RISPOSTE = 1000;

// Cartella dei dati: se esiste /var/www/wordcloud-dati (fuori dalla cartella pubblica,
// come la moodledata di Moodle) usa quella, altrimenti la sottocartella "dati" accanto a questo file.
define('DATA_DIR', is_dir('/var/www/wordcloud-dati') ? '/var/www/wordcloud-dati' : __DIR__ . '/dati');
