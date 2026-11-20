# Word cloud in classe

Nuvola di parole in stile Mentimeter con una sola domanda fissa:
**"In una parola, come ti senti un attimo prima di entrare?"**

- Tab **Domanda**: la domanda e un QR code.
- Gli studenti inquadrano il QR e rispondono con una parola dal telefono (senza login).
- Tab **Risultati**: la nuvola di parole, aggiornata in tempo reale.

Le risposte sono salvate in un file JSON: non serve un database.

## Due versioni

| Cartella | Per cosa | Requisiti |
|---|---|---|
| `wordcloud/` | **Server della scuola** (accanto a Moodle in `/var/www/html`) | Apache/Nginx con PHP 7.4+ |
| `server.js` + `public/` | Prova in locale sul PC | Node.js, nessun `npm install` |

### Versione PHP (server)
Vedi [PUBBLICARE-SUL-SERVER.md](PUBBLICARE-SUL-SERVER.md): copia con `scp`, cartella dei dati
fuori dalla parte pubblica, PIN per il pulsante Azzera in `wordcloud/config.php`.

### Versione Node (locale)
Doppio clic su `avvia.bat`, oppure:
```
node server.js
```
Si apre `http://localhost:3000`; i telefoni devono essere sulla stessa rete Wi-Fi del PC
(consenti Node.js nel firewall di Windows sulle reti private).

## Uso in aula
- `←` / `→` (o `1` / `2`) per cambiare tab, `F` per lo schermo intero.
- **Azzera** cancella le risposte e archivia quelle vecchie (nella versione PHP chiede il PIN).
- Maiuscole e minuscole non contano; ogni telefono può rispondere una volta (si riattiva dopo Azzera).
- La domanda si cambia in `wordcloud/config.php` (PHP) o in cima a `server.js` (Node).

QR code e font Inter vengono caricati da internet; senza connessione al posto del QR compare
l'indirizzo da digitare.
