## 1. Modul-Grundstruktur & Konfiguration

- [ ] 1.1 Modulordner `GarageDoorTile/` mit `module.json` (Prefix `GARDOOR`, UUIDv4) anlegen
- [ ] 1.2 `form.json` mit Variablenzuordnung (Shelly 1 Relais `44039`, Endlagensensor `39824`, Erreichbarkeit `30959`), Laufzeiten und Impulsdauer erstellen
- [ ] 1.3 `locale.json` für Mehrsprachigkeit (de/en) erstellen

## 2. Backend-Logik (module.php)

- [ ] 2.1 Modul-Klasse `GarageDoorTile` mit `SetVisualizationType(1)` und Property-Registrierungen implementieren
- [ ] 2.2 Taster-Impuls-Controller mit non-blocking Modul-Timer (`SetTimerInterval`) und automatischer Rückstellung des Shelly 1 Relais implementieren
- [ ] 2.3 State-Machine (`CLOSED`, `OPENING`, `OPEN`, `CLOSING`, `STOPPED`) und Zeitbasierte Positionsschätzung (0–100%) mit Sensor-Kalibrierung umsetzen
- [ ] 2.4 Offen-Dauer-Zähler und native Statusvariablen (`MaintainVariable`) für Detailansicht pflegen
- [ ] 2.5 Nachrichtenempfang (`VM_UPDATE`) und WebSocket-Push (`UpdateVisualizationValue`) implementieren

## 3. Frontend-Kachel & Animation (module.html)

- [ ] 3.1 Responsives Flexbox-Layout mit transparentem Standard-Kachelhintergrund (`#333438`) aufbauen
- [ ] 3.2 Animierte Sektionaltor-Grafik (SVG-Lamellen) und dynamische Zustandsfarben (Grün/Gelb/Rot) gestalten
- [ ] 3.3 One-Touch Taster-Bedienung mit `requestAction('triggerDoor', true)` und haptischem Feedback einbinden
- [ ] 3.4 Robuste `handleMessage(data)` mit JSON-String-Parsing für flackerfreie Live-Updates implementieren

## 4. Bereitstellung & Verifikation

- [ ] 4.1 Deployment auf `unserver` (`/mnt/user/ips/data/modules/IPS-Tile-Visu`) synchronisieren
- [ ] 4.2 Neue Modul-Instanz in IP-Symcon erzeugen und Variablen zuordnen
- [ ] 4.3 Kachel-Funktion im WebFront testen (Impuls, Animation, Laufzeit, Sensor-Kalibrierung)
- [ ] 4.4 Dokumentation in `README.md` und `GarageDoorTile/README.md` erstellen und committen
