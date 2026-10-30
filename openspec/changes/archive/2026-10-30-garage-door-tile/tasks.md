## 1. Modul-Grundstruktur & Konfiguration

- [x] 1.1 Modulordner `GarageDoorTile/` mit `module.json` (Prefix `GARDOOR`, UUIDv4) anlegen
- [x] 1.2 `form.json` mit Variablenzuordnung (Shelly 1 Relais `44039`, Endlagensensor `39824`, Erreichbarkeit `30959`), Laufzeiten und Impulsdauer erstellen
- [x] 1.3 `locale.json` für Mehrsprachigkeit (de/en) erstellen

## 2. Backend-Logik (module.php)

- [x] 2.1 Modul-Klasse `GarageDoorTile` mit `SetVisualizationType(1)` und Property-Registrierungen implementieren
- [x] 2.2 Taster-Impuls-Controller mit non-blocking Modul-Timer (`SetTimerInterval`) und automatischer Rückstellung des Shelly 1 Relais implementieren
- [x] 2.3 State-Machine (`CLOSED`, `OPENING`, `OPEN`, `CLOSING`, `STOPPED`) und Zeitbasierte Positionsschätzung (0–100%) mit Sensor-Kalibrierung umsetzen
- [x] 2.4 Offen-Dauer-Zähler und native Statusvariablen (`MaintainVariable`) für Detailansicht pflegen
- [x] 2.5 Nachrichtenempfang (`VM_UPDATE`) und WebSocket-Push (`UpdateVisualizationValue`) implementieren

## 3. Frontend-Kachel & Animation (module.html)

- [x] 3.1 Responsives Flexbox-Layout mit transparentem Standard-Kachelhintergrund (`#333438`) aufbauen
- [x] 3.2 Animierte Sektionaltor-Grafik (SVG-Lamellen) und dynamische Zustandsfarben (Grün/Gelb/Rot) gestalten
- [x] 3.3 One-Touch Taster-Bedienung mit `requestAction('triggerDoor', true)` und haptischem Feedback einbinden
- [x] 3.4 Robuste `handleMessage(data)` mit JSON-String-Parsing für flackerfreie Live-Updates implementieren

## 4. Bereitstellung & Verifikation

- [x] 4.1 Deployment auf `unserver` (`/mnt/user/ips/data/modules/IPS-Tile-Visu`) synchronisieren
- [x] 4.2 Neue Modul-Instanz in IP-Symcon erzeugen und Variablen zuordnen
- [x] 4.3 Kachel-Funktion im WebFront testen (Impuls, Animation, Laufzeit, Sensor-Kalibrierung)
- [x] 4.4 Dokumentation in `README.md` und `GarageDoorTile/README.md` erstellen und committen
