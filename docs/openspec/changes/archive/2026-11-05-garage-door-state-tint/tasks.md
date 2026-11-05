## 1. Frontend

- [x] 1.1 `--door-state` als einzige Zustandsfarbe setzen, abgeleitet aus `data.stateColor`
- [x] 1.2 Rahmen (`--frame-edge`, `--frame-mid`) mit der Zustandsfarbe mischen, staerkerer Anteil
- [x] 1.3 Lamellen (`--slat-1` bis `--slat-4`) mit der Zustandsfarbe mischen, schwaecherer Anteil
- [x] 1.4 Neutrale Zustaende (`stopped`, `offline`) unveraendert lassen
- [x] 1.5 Anteile je Theme getrennt einstellen (hell braucht mehr Mischung, nicht weniger)

## 2. Verifikation

- [x] 2.1 Alle Zustaende (closed, open, opening, closing, stopped, offline) in beiden Themes rendern
- [x] 2.2 Mischanteile messen: Rahmen und Lamellen unterscheiden sich vom Neutralzustand
- [x] 2.3 Kontrast des Prozent-Badges und der Scharniere pruefen
- [x] 2.4 README um die Zustandsfaerbung ergaenzen
