<?php
// Backend del word cloud: salva le risposte in un file JSON (nessun database).
// Azioni:  GET api.php?azione=info | risultati      POST api.php?azione=risposta | azzera
// Compatibile con PHP 7.4 e successivi.

declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const DATA_FILE = DATA_DIR . '/risposte.json';

function esci(int $status, array $corpo): void
{
    http_response_code($status);
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}

function nuovaSessione(): array
{
    return ['sessione' => bin2hex(random_bytes(4)), 'risposte' => []];
}

// Restituisce un messaggio se la cartella dei dati non è utilizzabile, altrimenti null
function problemaCartella(): ?string
{
    $utente = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data') : 'www-data';
    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0750, true)) {
        return 'La cartella dei dati ' . DATA_DIR . ' non esiste e il server web non può crearla.';
    }
    if (!is_writable(DATA_DIR)) {
        return 'La cartella dei dati ' . DATA_DIR . ' non è scrivibile dall\'utente ' . $utente . '.';
    }
    return null;
}

// Esegue $fn con un lock esclusivo, così due invii contemporanei non si sovrascrivono
function conLock(callable $fn)
{
    $problema = problemaCartella();
    if ($problema !== null) {
        esci(500, ['errore' => $problema]);
    }
    $fp = @fopen(DATA_DIR . '/.lock', 'c');
    if ($fp === false) {
        esci(500, ['errore' => 'Impossibile creare il file di lock in ' . DATA_DIR . '.']);
    }
    flock($fp, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function leggiDati(): array
{
    if (is_file(DATA_FILE)) {
        $d = json_decode((string) file_get_contents(DATA_FILE), true);
        if (is_array($d) && isset($d['sessione'], $d['risposte']) && is_array($d['risposte'])) {
            return $d;
        }
    }
    $d = nuovaSessione();
    scriviDati($d);
    return $d;
}

function scriviDati(array $d): void
{
    $tmp = DATA_FILE . '.tmp';
    if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false
        || !rename($tmp, DATA_FILE)) {
        esci(500, ['errore' => 'Impossibile salvare le risposte.']);
    }
}

function lunghezza(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./su', $s);
}

function minuscolo(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function corpoJson(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 2000);
    $d = json_decode((string) $raw, true);
    return is_array($d) ? $d : [];
}

$azione = $_GET['azione'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($azione) {
    case 'info':
        // domanda e limite arrivano sempre, anche se la cartella dei dati ha problemi
        $base = ['domanda' => DOMANDA, 'maxLen' => MAX_LEN];
        $problema = problemaCartella();
        if ($problema !== null) esci(500, $base + ['errore' => $problema]);
        $d = conLock('leggiDati');
        esci(200, $base + ['sessione' => $d['sessione']]);

    case 'risultati':
        $d = conLock('leggiDati');
        $conta = [];
        foreach ($d['risposte'] as $r) {
            $conta[$r['parola']] = ($conta[$r['parola']] ?? 0) + 1;
        }
        $parole = [];
        foreach ($conta as $p => $n) {
            $parole[] = ['parola' => (string) $p, 'n' => $n];
        }
        usort($parole, function ($a, $b) {
            return $b['n'] <=> $a['n'] ?: strcmp($a['parola'], $b['parola']);
        });
        esci(200, ['sessione' => $d['sessione'], 'totale' => count($d['risposte']), 'parole' => $parole]);

    case 'risposta':
        if ($metodo !== 'POST') esci(405, ['errore' => 'Metodo non consentito.']);
        $testo = (string) (corpoJson()['parola'] ?? '');
        if (!preg_match('//u', $testo)) esci(400, ['errore' => 'Testo non valido.']);
        if (class_exists('Normalizer')) $testo = Normalizer::normalize($testo, Normalizer::FORM_C) ?: $testo;
        // toglie i caratteri di controllo, riduce gli spazi multipli a uno,
        // toglie spazi e punteggiatura ai bordi, poi minuscolo
        $testo = (string) preg_replace('/\p{Cc}+/u', ' ', $testo);
        $testo = (string) preg_replace('/\s+/u', ' ', $testo);
        $parola = minuscolo((string) preg_replace('/^[\s\p{P}]+|[\s\p{P}]+$/u', '', $testo));

        if ($parola === '') esci(400, ['errore' => 'Scrivi una risposta prima di inviare.']);
        if (lunghezza($parola) > MAX_LEN) esci(400, ['errore' => 'Massimo ' . MAX_LEN . ' caratteri.']);

        $sessione = conLock(function () use ($parola) {
            $d = leggiDati();
            if (count($d['risposte']) >= MAX_RISPOSTE) {
                esci(429, ['errore' => 'Sono già arrivate tutte le risposte possibili.']);
            }
            $d['risposte'][] = ['parola' => $parola, 'ora' => date('c')];
            scriviDati($d);
            return $d['sessione'];
        });
        esci(200, ['ok' => true, 'sessione' => $sessione, 'parola' => $parola]);

    case 'azzera':
        if ($metodo !== 'POST') esci(405, ['errore' => 'Metodo non consentito.']);
        if (PIN_DOCENTE === 'cambiami') {
            esci(403, ['errore' => 'Imposta prima un PIN in config.php (PIN_DOCENTE).']);
        }
        $pin = (string) (corpoJson()['pin'] ?? '');
        if (!hash_equals((string) PIN_DOCENTE, $pin)) {
            sleep(1); // rallenta i tentativi a caso
            esci(403, ['errore' => 'PIN errato.']);
        }
        $nuova = conLock(function () {
            $d = leggiDati();
            if ($d['risposte']) {
                // archivia le risposte precedenti (nome non indovinabile)
                $nome = DATA_DIR . '/archivio-' . date('Y-m-d_H-i-s') . '-' . bin2hex(random_bytes(3)) . '.json';
                file_put_contents($nome, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            $n = nuovaSessione();
            scriviDati($n);
            return $n['sessione'];
        });
        esci(200, ['ok' => true, 'sessione' => $nuova]);

    default:
        esci(404, ['errore' => 'Azione sconosciuta.']);
}
