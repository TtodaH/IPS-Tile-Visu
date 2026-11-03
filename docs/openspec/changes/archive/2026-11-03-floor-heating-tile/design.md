## Context

Each heating circuit is a `Dummy Module` instance that groups four KNX DPT instances. The tile module is configured with four variable IDs by the user, so the same module works for every room without hardcoding group addresses.

## Goals / Non-Goals

**Goals**
- One glance answers: is this room heating right now, and how close is it to its setpoint?
- Legible in dark and light visualization themes.
- Read-only; no bus writes.
- Works for every room without code changes.

**Non-Goals**
- Editing setpoints from the tile (KNX write path, mode switching, schedules).
- Aggregating all six rooms into one tile.

## Decisions

**Four explicit variable properties instead of reading children of a parent instance.**
The user may group variables differently per room; explicit `SelectVariable` properties keep the configuration obvious and avoid hidden coupling to the object tree. Defaults in the form hint at the Elena circuit so the first setup is fast.

**Actuator rendered as radial gauge, temperatures as the primary numbers.**
The actuator value (0-100%) is the only bounded value and benefits most from a gauge. Room temperature is the number a user actually looks for, so it is the largest element; setpoint sits next to it for direct comparison.

**State derived from the temperature delta plus actuator value.**

| Condition | State |
|---|---|
| actuator >= 5% and room < setpoint - 0.3 K | `heating` |
| actuator >= 5% and room within +/- 0.3 K | `holding` |
| actuator < 5% | `idle` |
| room > setpoint + 0.3 K | `overshoot` |

Purely deriving the state from the actuator would hide undershoot; deriving it only from the delta would hide a closed valve. Combining both is what the user sees in reality.

**Theme detection reused from `tile-theme-support`.**
Identical luminance bootstrap on `cardcolor`/`textcolor` so the tile matches the existing modules and never flickers.

## Risks

- KNX DPT 9 values are floats with one decimal; rounding must not hide a 0.1 K difference. The tile formats to one decimal and thresholds at 0.3 K.
- A missing or zero variable ID would render `0.0 °C`. The module shows a configuration hint instead of plausible-looking zeros.

## Addendum: Bedienung und Skalierung (nach Live-Test)

**Sollwert ist schreibbar.**
Alle vier KNX-Variablen von Elena melden `CapabilityWrite=true`. Die Standard-Kachel
zeigt fuer den Basissollwert bereits einen Schieberegler, also wird die Kachel
bedienbar. Das widerspricht der urspruenglichen Non-Goal-Definition und ersetzt sie.

**Geschrieben wird nur auf den Sollwert, nicht auf den Basiswert.**
Der aktuelle Sollwert ist der Wert, den ein Nutzer im Alltag verstellt. Der
Basissollwert ist die Konfiguration des Regelkreises; ihn versehentlich zu
verschieben veraendert das Heizverhalten dauerhaft. Er bleibt in dieser
Ausbaustufe schreibgeschuetzt und wird nur angezeigt.

**Optimistisches Update mit Rueckfall.**
Der Slider springt sofort auf den neuen Wert, damit die Bedienung nicht traege
wirkt. Kommt innerhalb von 1,5 s kein Push vom Backend, wird der zuletzt
gemeldete Wert wiederhergestellt. So bleibt die Anzeige ehrlich, auch wenn KNX
den Wert ablehnt.

**Skalierung ueber gemessene Kachelgroesse.**
Feste `clamp()`-Maxima liessen die Kachel auf grossen Fliesen leer wirken. Die
Schriftgroessen werden jetzt aus der tatsaechlichen Kachelhoehe abgeleitet und
bei `resize` neu gesetzt.
