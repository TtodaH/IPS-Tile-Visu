## ADDED Requirements

### Requirement: State tint on the door graphic

Die Torgrafik SHALL den Betriebszustand farblich tragen, damit offen und geschlossen ohne Blick auf die Status-Pille erkennbar sind.

Die Faerbung SHALL eine Mischung mit der Zustandsfarbe sein, keine Ersetzung der Grautoene, damit Schattierung und Tiefe des Tors erhalten bleiben.

#### Scenario: Geschlossenes Tor

- **WHEN** der Zustand `closed` ist
- **THEN** sind Rahmen und Lamellen gruenlich getoent, wobei die Lamellenschattierung sichtbar bleibt

#### Scenario: Offenes Tor

- **WHEN** der Zustand `open` ist
- **THEN** sind Rahmen und Lamellen roetlich getoent

#### Scenario: Tor in Bewegung

- **WHEN** der Zustand `opening` oder `closing` ist
- **THEN** folgt die Toenung der jeweiligen Bewegungsfarbe

#### Scenario: Unbekannter Zustand

- **WHEN** der Zustand `stopped` oder `offline` ist
- **THEN** bleibt die Torgrafik neutral, weil keine Aussage ueber offen oder geschlossen moeglich ist

#### Scenario: Helles Visualisierungsdesign

- **WHEN** das helle Design aktiv ist
- **THEN** verwendet die Toenung einen eigenen, schwaechern Mischanteil, damit das Tor nicht ausbleicht
