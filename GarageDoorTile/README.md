# Garage Door Tile (Garagentor Kachel)

Smarte, reaktionsschnelle HTML-SDK Kachelvisualisierung für Garagentore in **IP-Symcon 7+**.

Entwickelt für Garagentorantriebe (z.B. Hörmann, Sommer, Marantec, Novoferm, Chamberlain), die über einen potentialfreien Tasterkontakt angesteuert werden (z.B. mittels **Shelly 1** oder **Shelly Plus 1**) und optional über einen Endlagensensor (z.B. Magnetschalter/Reed-Kontakt an einem **Shelly Pro 4PM** Input oder Shelly Door/Window) überwacht werden.

---

## ✨ Features

- **Taster-Impuls-Simulation**: Kein manuelles Ein-/Ausschalten mehr im WebFront nötig. Ein Klick sendet einen exakt getakteten Impuls (Standard 800 ms, frei konfigurierbar 200–3000 ms) und setzt das Relais über einen non-blocking Modul-Timer automatisch zurück.
- **Flackerfreie HTML-SDK Kachel**: Nutzt Symcons `SetVisualizationType(1)` und WebSocket-Push (`UpdateVisualizationValue`) ohne Neuladen des DOMs.
- **Sectional-Tor Animation (SVG)**:
  - Dynamische Sektionaltor-Grafik mit Lamellen, die sich proportional zum Öffnungsgrad (0–100%) heben und senken.
  - Einblick in das beleuchtete Garageninnere bei geöffnetem Tor.
  - Sanfte Bewegungs-Animation mit aufsteigenden bzw. absteigenden Pfeilen während der Torfahrt.
- **1-Taster State-Machine**:
  - `Geschlossen` (Grün) $\rightarrow$ Klick startet `Wird geöffnet...` (Blau)
  - `Wird geöffnet...` $\rightarrow$ Klick stoppt Tor (`Gestoppt`, Grau)
  - `Gestoppt` $\rightarrow$ Klick fährt Tor in die Gegenrichtung
  - `Offen` (Rot) $\rightarrow$ Klick startet `Wird geschlossen...` (Gelb)
  - `Wird geschlossen...` $\rightarrow$ Klick stoppt Tor (`Gestoppt`, Grau)
- **Sensor-Kalibrierung**:
  - Sobald der physische Endlagensensor `Geschlossen` meldet, wird die Position sofort auf `0 %` kalibriert und eventueller Drift eliminiert.
  - Optionale Sensor-Invertierung für Schließer/Öffner-Kontakte.
- **Offen-Dauer-Überwachung**:
  - Zählt die Zeit seit dem Öffnen mit.
  - Warnhinweis bei Überschreiten einer frei definierbaren Zeit (z.B. Warnung ab 30 Minuten offen).
- **Online-Status**:
  - Erkennt, ob der Shelly-Aktor online/erreichbar ist (z.B. über die Symcon-Variable `Erreichbar`), und blendet bei Verbindungsverlust eine Offline-Warnung ein.

---

## 📋 Konfigurationsparameter

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `SwitchVariable` | Variable (Boolean) | `0` | Schaltvariable des Torantriebs (z.B. ID `44039` Shelly 1 Relais) |
| `SensorClosedVariable` | Variable (Boolean) | `0` | Endlagensensor geschlossen (z.B. ID `39824` Shelly Pro 4PM Input) |
| `SensorInverted` | Checkbox | `false` | Invertiert die Sensorlogik (`false`: `false` = Geschlossen, `true` = Offen) |
| `ReachableVariable` | Variable (Boolean) | `0` | Online-Status des Aktors (z.B. ID `30959` Shelly `Erreichbar`) |
| `ImpulseDurationMs` | NumberSpinner | `800` | Dauer des Taster-Impulses in Millisekunden (200 bis 3000 ms) |
| `TravelTimeOpenSec` | NumberSpinner | `16` | Gemessene Fahrzeit für vollständiges Öffnen in Sekunden |
| `TravelTimeCloseSec` | NumberSpinner | `16` | Gemessene Fahrzeit für vollständiges Schließen in Sekunden |
| `WarnOpenMinutes` | NumberSpinner | `30` | Warnung in der Kachel, wenn Tor länger als X Minuten offen steht (0 = deaktiviert) |
| `TileTitle` | Text | `Garagentor` | Angezeigter Titel in der Kachel |
| `ShowPercentage` | Checkbox | `true` | Öffnungsgrad in Prozent (0–100%) einblenden |
| `ShowOpenDuration` | Checkbox | `true` | Dauer seit Öffnung im Footer einblenden |
| `ShowReachableBadge` | Checkbox | `true` | "Offline"-Hinweis anzeigen, falls Aktor nicht erreichbar ist |

---

## 🏗️ Statusvariablen (Child-Objekte)

Das Modul legt unterhalb der Instanz automatisch folgende Statusvariablen an:

1. **Torzustand (`State`)**:
   - `0`: Geschlossen (Grün)
   - `1`: Wird geöffnet... (Blau)
   - `2`: Offen (Rot)
   - `3`: Wird geschlossen... (Gelb)
   - `4`: Gestoppt (Grau)
2. **Position (`Position`)**: Öffnungsgrad in `0.0 %` bis `100.0 %` (Profil `~ShutterMoveStep`).
3. **Offen seit (`OpenDuration`)**: Zeit in Minuten seit dem Öffnen.
4. **Taster-Impuls (`ImpulseActive`)**: Zeigt den Zustand des simulierten Tastendrucks an.

---

## 🚀 PHP-Befehlsreferenz

```php
// Tor bedienen (entspricht einem Tastendruck)
GARDOOR_TriggerDoor(12345);

// Kacheldaten neu generieren und per WebSocket an WebFront senden
GARDOOR_SendTileUpdate(12345);
```
