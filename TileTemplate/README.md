# Tile Template (IP-Symcon 7.1+ HTML-SDK)

Dieses Modul ist eine saubere, performante Vorlage für eigene Kachelvisualisierungen in IP-Symcon (ab Version 7.1). Es demonstriert die offizielle und flackerfreie Architektur des IP-Symcon HTML-SDKs im Vergleich zu herkömmlichen `~HTMLBox`-Skripten.

---

## Kernarchitektur: Warum HTML-SDK statt HTMLBox?

| Kriterium | Traditionelle HTMLBox (`SetValueString`) | IP-Symcon HTML-SDK (`UpdateVisualizationValue`) |
|---|---|---|
| **DOM-Handling** | Wird bei jedem Aufruf **komplett gelöscht & neu gebaut** | Bleibt persistent im Speicher erhalten |
| **Flackern (Flicker)** | **Massiv**, da Browser Layout & Styles neu rendert | **0% Flackern**, direkte DOM-Manipulation |
| **CSS-Animationen** | Unmöglich (Animation bricht bei Reset ab) | **Flüssig (60 FPS)**, CSS Transitions greifen sauber |
| **Bidirektionalität** | Aufwendige WebHooks oder Umwege | Direkt via JavaScript `requestAction(ident, val)` |
| **Performance** | Hohe CPU-Last bei schnellen Sensorintervallen | Minimaler Datentransfer via JSON |

---

## Moduldateien im Überblick

- **`module.json`**: Definiert GUID, Modultyp (`3` = Instanz), Aliase und Präfix.
- **`module.php`**: 
  - Aktiviert in `Create()` das HTML-SDK: `$this->SetVisualizationType(1);`
  - Registriert Variablen mit `$this->RegisterMessage($varID, VM_UPDATE);`
  - Sendet Updates über `$this->UpdateVisualizationValue(json_encode($data));`
  - Empfängt Frontend-Interaktionen über `RequestAction($Ident, $Value)`.
- **`module.html`**:
  - CSS mit Modern Features: Container Queries (`@container`), Dark/Light Mode, Glassmorphism.
  - Implementiert `handleMessage(data)` für gezieltes Aktualisieren von DOM-Nodes.
  - Sendet Klicks über `requestAction('actionButton', true)`.
- **`form.json`**: Konfigurationsoberfläche für Variablen, Farben, Icons und Texte in der Management Console.
- **`locale.json`**: Mehrsprachigkeit (Deutsch / Englisch).

---

## Verwendung als Vorlage

1. Ordner `TileTemplate` kopieren (z. B. nach `MyCustomTile`).
2. Eine neue UUIDv4 für `module.json` generieren (`uuidgen`).
3. Präfix in `module.json` und PHP-Klassenname anpassen.
4. Eigene HTML-/SVG-Elemente in `module.html` und Felder in `form.json` ergänzen.
