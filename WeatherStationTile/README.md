# Weather Station Tile (Wetterstation Kachel)

Moderne, performante und **100% flackerfreie** Kachel-Visualisierung für IP-Symcon 7.1+ basierend auf dem nativen **HTML-SDK**.

---

## Warum flackert die bisherige Visualisierung?

In der bisherigen Umsetzung (Skript `34621.ips.php` und Variable `31737` als `~HTMLBox`) trat starkes Flackern bei jedem Sensor-Update auf:

1. **Kompletter DOM-Abbruch:** Jedes `SetValueString(31737, $html)` veranlasst das WebFront/TileVisu, das gesamte innerHTML der Kachel zu vernichten und neu aufzubauen.
2. **Keine CSS-Transitions möglich:** Die Windrosen-Nadel (`<g transform="rotate(...)">`) konnte sich nicht kontinuierlich drehen, weil das SVG jedes Mal gelöscht und mit neuem Winkel neu gerendert wurde.
3. **Ereignisse bei Aktualisierung statt Änderung:** Mehrere Trigger-Ereignisse standen auf *"Bei Aktualisierung"* (selbst wenn sich der Wert gar nicht geändert hat). Das Skript lief ununterbrochen und hat die Kachel ständig neu geladen.

---

## Die Lösung: IP-Symcon HTML-SDK

Dieses Modul nutzt die offizielle Schnittstelle von IP-Symcon 7.1+:
- Die HTML-Struktur und das SVG der Windrose werden **nur einmal** beim Laden der Kachel im Browser initialisiert.
- Bei Änderungen der Sensoren (`VM_UPDATE`) schickt IP-Symcon per `$this->UpdateVisualizationValue(json_encode(...))` schlanke JSON-Datenpakete.
- In JavaScript nimmt `handleMessage(data)` diese Daten entgegen und modifiziert **nur gezielt die Textknoten und die CSS-Transform-Rotation**.
- **Ergebnis:**
  - **Absolut kein Flackern / White-Flash**
  - **Butterweiche 60 FPS Drehung** der Windnadel mit automatischer Kürzester-Weg-Berechnung (kein 350°-Rückwärtsdreher beim Überschreiten von Norden!)
  - **Minimalste CPU- und Netzwerklast**

---

## Zuordnung der Variablen (laut Screenshot)

| Funktion im Modul | Typische ID (Screenshot) | Beschreibung |
|---|---|---|
| **Windrichtung** | `38465` | TFA Wetterstation (Grad 0–360) |
| **Windgeschwindigkeit** | `21228` | TFA Wetterstation (m/s) |
| **Windböe** | `58720` | TFA Wetterstation (m/s) |
| **Vorherige Windrichtung** | `44797` | TFA Wetterstation (Geisterzeiger) |
| **Außentemperatur** | `13145` | Wärmepumpe `Temperatur_TA_Aussenfuehler` |
| **Zusatz-Temperatur** | `31094` | Kaninchenstall `Aktuelle Temperatur` |
| **Regen-/Trockensensor** | `35022` | TFA `Aktuell trocken` (Boolean) |
| **Batteriestatus** | `15523` | TFA `Batterie` (Boolean) |

---

## Features

- **Dynamische Vektor-Windrose (SVG)**: Skalenstriche, Himmelsrichtungen N/O/S/W, Windrichtungsanzeige mit 16-teiliger Benennung (z.B. `SO (135°) · Südost`).
- **Geisterzeiger für vorherige Windrichtung**: Zeigt dezent an, aus welcher Richtung der Wind zuvor kam.
- **Einheiten-Konvertierung**: Frei umschaltbar zwischen km/h, m/s und Knoten.
- **Beaufort-Skala**: Automatische Berechnung von 0 (Windstille) bis 12 (Orkan) mit deutscher Klartext-Bezeichnung.
- **Temperatur-Kombination**: Große Hero-Außentemperatur und Nebenmessung (z.B. Kaninchenstall).
- **Status-Pills**: Automatische Warnanzeigen für Regen/Trockenheit und schwache Batterien.
- **Dark/Light-Mode**: Passt sich nahtlos dem Farbmodus von IP-Symcon an.
