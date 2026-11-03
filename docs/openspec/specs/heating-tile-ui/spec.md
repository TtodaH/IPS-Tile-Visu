## Purpose
Provide a compact IP-Symcon HTML-SDK tile for one KNX floor heating circuit: room temperature, current setpoint, base setpoint and actuator value at a glance, with the setpoint adjustable on the outer ring and the operating state carried by the centre.

## Requirements

### Requirement: Floor heating tile visualization

Der Baustein SHALL eine HTML-SDK-Kachel bereitstellen, die einen Fussbodenheizungs-Heizkreis mit Raumtemperatur, aktuellem Sollwert, Basissollwert und Stellgroesse darstellt.

Der Ring SHALL das Bedienelement sein, die Mitte SHALL die Anzeige sein.

#### Scenario: Darstellung mit aktivem Waermebedarf
- **WHEN** die Kachel geladen wird, die Stellgroesse mindestens 5 % betraegt und die Raumtemperatur mehr als 0,3 K unter dem Sollwert liegt
- **THEN** zeigt die Kachel einen 270-Grad-Ring mit gefuelltem Bogen und Griff (Thumb) am Bogenende, in der Mitte die Raumtemperatur als groesste Zahl mit der Stellgroesse darunter, in der Ringluecke unten den Sollwert, und eine Zustands-Pille mit der Beschriftung "Heizt"

#### Scenario: Darstellung am Sollwert
- **WHEN** die Stellgroesse mindestens 5 % betraegt und die Raumtemperatur innerhalb von 0,3 K um den Sollwert liegt
- **THEN** ist die Zustands-Pille mit "Haelt" beschriftet und verwendet die Halten-Farbe

#### Scenario: Darstellung ohne Waermebedarf
- **WHEN** die Stellgroesse unter 5 % liegt
- **THEN** ist die Zustands-Pille mit "Aus" beschriftet und der Ringfuellstand entspricht der Stellgroesse

#### Scenario: Darstellung bei Uebertemperatur
- **WHEN** die Raumtemperatur mehr als 0,3 K ueber dem Sollwert liegt
- **THEN** ist die Zustands-Pille mit "Zu warm" beschriftet und verwendet die Uebertemperatur-Farbe

### Requirement: Configurable variable binding

Die Kachel SHALL ihre vier Werte aus explizit konfigurierten Variablen lesen, damit derselbe Baustein jeden Heizkreis ohne Codeaenderung bedient.

#### Scenario: Konfiguration der Kachel
- **WHEN** der Nutzer eine Basissollwert-, Raumtemperatur-, Stellgroessen- und Sollwertvariable in der Instanzkonfiguration auswaehlt
- **THEN** zeigt die Kachel die Live-Werte genau dieser Variablen

#### Scenario: Fehlende Variablenzuordnung
- **WHEN** eine oder mehrere der vier Variablen nicht konfiguriert sind
- **THEN** zeigt die Kachel einen sichtbaren Konfigurationshinweis mit der fehlenden Zuordnung, statt plausibel wirkende Nullwerte anzuzeigen

### Requirement: Setpoint control with write protection

Die Kachel SHALL den aktuellen Sollwert auf die KNX-Variable schreiben koennen, den Basissollwert jedoch niemals.

Geschrieben SHALL erst beim Loslassen des Rings oder bei einem Tastaturschritt werden, nicht bei jeder Zeigerbewegung.

#### Scenario: Ziehen am Ring
- **WHEN** der Nutzer den Ring drueckt, entlang des Bogens zieht und loslaesst
- **THEN** zeigt die Kachel den gezogenen Wert waehrend der Bewegung an und sendet `setSetpoint` genau einmal beim Loslassen

#### Scenario: Tastaturbedienung
- **WHEN** der Ring den Fokus hat und der Nutzer eine Pfeiltaste drueckt
- **THEN** aendert sich der Sollwert um `SetpointStep` und wird gesendet

#### Scenario: Winkelzuordnung
- **WHEN** der Nutzer 135 Grad (unten links) trifft
- **THEN** ist das der kleinste Sollwert; 270 Grad (oben) ist die Mitte und 45 Grad (unten rechts) der groesste Sollwert

#### Scenario: Nicht schreibbare Variable
- **WHEN** die konfigurierte Sollwertvariable nicht schreibbar ist
- **THEN** bleibt der Ring reine Anzeige und es wird kein Wert geschrieben

#### Scenario: Keine Rueckmeldung vom Bus
- **WHEN** innerhalb von 1,5 s kein Push vom Backend eintrifft
- **THEN** kehrt die Anzeige auf den zuletzt bestaetigten Wert zurueck, statt einen nie uebernommenen Wert vorzutaeuschen

### Requirement: Live updates without flicker

Das Frontend SHALL gepushte Nutzlasten ohne DOM-Neuaufbau anwenden.

#### Scenario: Variable aendert sich auf dem Bus
- **WHEN** eine der vier gebundenen Variablen einen neuen Wert erhaelt
- **THEN** pusht das Backend eine JSON-Nutzlast via UpdateVisualizationValue und die Kachel aktualisiert Zahlen, Ringfuellstand und Thumb an Ort und Stelle

### Requirement: Theme-aware rendering

Die Kachel SHALL im dunklen und im hellen IP-Symcon-Visualisierungsdesign lesbar sein.

#### Scenario: Helles Visualisierungsdesign
- **WHEN** die Kachel mit einer `cardcolor` geoeffnet wird, deren relative Luminanz groesser als 0,45 ist
- **THEN** traegt das Dokument `data-theme="light"` vor dem ersten Paint und alle Texte, die Ring-Spur und die Zustandsfarben verwenden kontrastreiche Werte des hellen Designs

#### Scenario: Dunkles Visualisierungsdesign
- **WHEN** die Kachel mit einer dunklen `cardcolor` oder ohne Theme-Parameter unter einer dunklen Betriebssystem-Einstellung geoeffnet wird
- **THEN** traegt das Dokument `data-theme="dark"` und die Kachel verwendet die dunkle Palette

### Requirement: Collision-free scaling

Die Kachel SHALL bei jeder Groesse ohne Ueberlappung und ohne abgeschnittenen Inhalt darstellen.

Der Ring SHALL nur die Flaeche erhalten, die nach Abzug des Symcon-Titelplatzes und der Sollwert-Pille verbleibt.

#### Scenario: Sehr flache Kachel
- **WHEN** die Kachelhoehe unter 115 px liegt
- **THEN** entfaellt der eigene Kopf, die Sollwert-Pille bleibt jedoch sichtbar

#### Scenario: Symcon zeichnet den Instanznamen
- **WHEN** IP-Symcon den Instanznamen ueber die Kachel zeichnet
- **THEN** reserviert die Kachel dafuer einen Platzhalter von mindestens 18 px Hoehe, sodass kein Text ueberlappt

### Requirement: Temperature gradient ring

Der Ring SHALL die Temperaturskala als Farbverlauf von kalt nach warm darstellen, nicht die Zustandsfarbe.

Der Verlauf SHALL entlang des Bogens verlaufen und in der Mitte neutrale Farben zeigen, damit keine falsche Zwischenfarbe wie Violett entsteht.

#### Scenario: Verlauf entlang des Bogens
- **WHEN** die Kachel geladen wird
- **THEN** traegt jedes Bogensegment die Farbe seiner eigenen Skalenposition, sodass der gefuellte Bogen von Blau ueber neutrale Zwischentoene nach Rot verlaeuft

#### Scenario: Kein Violett in der Mitte
- **WHEN** der Sollwert in der Mitte des Wertebereichs liegt
- **THEN** zeigt das mittlere Segment einen neutralen Grauton, nicht die direkte Mischung aus Blau und Rot

#### Scenario: Ungefuellter Teil des Rings
- **WHEN** der Sollwert unter dem Hoechstwert liegt
- **THEN** bleibt der nicht gefuellte Teil einfarbig blass, ohne Streifen an den Segmentnahtstellen

#### Scenario: Farben folgen dem Visualisierungsdesign
- **WHEN** das Visualisierungsdesign wechselt
- **THEN** liest die Kachel die fuenf Stuetzfarben der Skala aus den CSS-Variablen und verwendet im hellen Design kraeftigere Werte als im dunklen

#### Scenario: Keine Nahtlinien zwischen den Segmenten
- **WHEN** der gefuellte Bogen aus mehreren Segmenten zusammengesetzt ist
- **THEN** ueberlappen sich benachbarte Segmente, sodass an den Nahtstellen keine Haarlinien sichtbar sind

#### Scenario: Buendiger Bogenanfang
- **WHEN** mindestens ein Segment gefuellt ist
- **THEN** sitzt am Bogenanfang eine runde Kappe in der Farbe des kaltesten Stuetzpunkts, sodass der Verlauf unten links buendig beginnt statt abgeschnitten zu wirken

#### Scenario: Warme Farbe frueh erreicht
- **WHEN** der Sollwert 23 °C betraegt
- **THEN** zeigt der Griff bereits einen roten Ton, weil die Stuetzpunkte der Skala ungleichmaessig auf dem Bogen verteilt sind (`--grad-pos`) und nicht dem Reglerbereich 5..35 °C folgen

### Requirement: State colour in the centre

Die Zustandsfarbe SHALL im Mittelkreis liegen, nicht auf dem Ring, damit Zustand und Temperatur gleichzeitig ablesbar sind.

Sie SHALL gedaempft dargestellt werden, damit sie die grosse Temperaturzahl nicht ueberstrahlt.

#### Scenario: Gedämpfte Zustandsfarbe
- **WHEN** die Kachel einen der vier Zustaende anzeigt
- **THEN** mischt der Mittelkreis die Zustandsfarbe mit etwa 9 % in den Hintergrund und mit etwa 22 % in den Rand, waehrend der Ring weiterhin den Temperaturverlauf zeigt

### Requirement: Breathing room in the centre

Die Ventilstellung SHALL deutlichen Abstand zur Temperaturzahl halten.

#### Scenario: Abstand unter der Temperatur
- **WHEN** die Kachel die Raumtemperatur und die Stellgroesse in der Mitte anzeigt
- **THEN** liegt zwischen beiden ein ueber `--sub-gap` steuerbarer Abstand, sodass der Prozentwert nicht an der grossen Zahl klebt
