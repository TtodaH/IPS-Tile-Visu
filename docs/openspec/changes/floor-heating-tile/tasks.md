## 1. Module scaffold

- [x] 1.1 Create `HeatingTile/module.json` with a fresh GUID, `type: 3`, prefix `HEATTILE`
- [x] 1.2 Create `form.json` with four `SelectVariable` properties and tile presentation options
- [x] 1.3 Create `locale.json` for the German captions

## 2. Backend

- [x] 2.1 `Create()` registers properties, `SetVisualizationType(1)` and a status variable
- [x] 2.2 `ApplyChanges()` validates the four variable IDs and registers message sinks
- [x] 2.3 `GetTilePayload()` builds room temperature, setpoint, base setpoint, actuator value and derived state
- [x] 2.4 `GetVisualizationTile()` injects the initial payload without a round trip
- [x] 2.5 `MessageSink()` pushes updates on variable change via `SendTileUpdate()`

## 3. Frontend

- [x] 3.1 Theme bootstrap identical to the existing tiles (`data-theme` before first paint)
- [x] 3.2 Layout: room name, large room temperature, setpoint, base setpoint, radial actuator gauge _(durch 7.1 ersetzt: Gauge und Slider-Leiste sind dem Bedienring gewichen)_
- [x] 3.3 State pill with colour and label per derived state
- [x] 3.4 `handleMessage()` updates values without DOM rebuild
- [x] 3.5 Light theme variables for text, panel, gauge track and state colours

## 4. Verification

- [x] 4.1 Render with real Elena values in dark and light theme and inspect visually
- [x] 4.2 Verify gauge at 0%, 5%, 50% and 100%
- [x] 4.3 Verify a configuration hint appears when a variable ID is missing
- [x] 4.4 Update README with the new module

## 5. Sollwert-Bedienung (Nachtrag nach Live-Test)

- [x] 5.1 Properties fuer Schrittweite, Min- und Max-Temperatur ergaenzen
- [x] 5.2 `RequestAction` schreibt Sollwert und Basissollwert per `@RequestAction` auf die KNX-Variable
- [x] 5.3 Schreibbarkeit pruefen (`CapabilityWrite`) und in der Payload melden
- [x] 5.4 Slider mit Minus/Plus-Tastern im Frontend, inkl. optimistischem Update
- [x] 5.5 Bei nicht-schreibbarer Variable die Bedienung ausblenden statt ins Leere schreiben

## 6. Skalierung (Nachtrag nach Live-Test)

- [x] 6.1 Schriftgroessen an die tatsaechliche Kachelgroesse koppeln statt fester Maxima
- [x] 6.2 Vertikale Flaeche fuellen: Kopf, Werte, Gauge, Bedienleiste
- [x] 6.3 Kompaktmodus fuer kleine Kacheln (Gauge-Label und Nebenwerte ausblenden)
- [x] 6.4 In drei Groessen und beiden Themes visuell pruefen

## 7. Bedienring statt Gauge (Nachtrag nach Design-Vorgabe)

- [x] 7.1 Aussenring als Bedienelement: 270-Grad-Bogen mit Luecke unten, ziehbarer Thumb am Bogenende
- [x] 7.2 Mitte als Anzeige: grosse Raumtemperatur, darunter die Stellgroesse in Prozent
- [x] 7.3 Sollwert-Pille in der Ringluecke unten, beim Einstellen live aktualisiert
- [x] 7.4 Platz fuer die Pille reservieren, damit der Ring sie nicht ueberdeckt
- [x] 7.5 Kopf bei sehr flachen Kacheln ausblenden (Symcon zeichnet den Instanznamen)
- [x] 7.6 Winkel-zu-Wert-Zuordnung der Luecke korrigiert (45 Grad = Maximum, 135 Grad = Minimum)
- [x] 7.7 Echte Maus-Drags pruefen: 5 -> 20 -> 35 Grad C, Schreiben erst beim Loslassen
- [x] 7.8 Tastaturbedienung als Ausweichweg (Pfeiltasten)

## 8. Skalierung der Mittelscheibe (Nachtrag nach Live-Test)

- [x] 8.1 Messschleife fuer die Mittelscheibe: Schrift setzen, Ueberlauf pruefen, verkleinern
- [x] 8.2 Temperatur und Stellgroesse getrennt einpassen
- [x] 8.3 Ring und Sollwert-Pille in den normalen Fluss setzen statt absolut positionieren
- [x] 8.4 Ringdeckel auf 240 px anheben, damit grosse Kacheln den Platz nutzen
- [x] 8.5 Schriftuntergrenze auf 6 px, damit auch 200x90 ohne Ueberlauf passt
- [x] 8.6 12 Varianten von 200x90 bis 360x360 in beiden Themes ohne Ueberlauf pruefen


