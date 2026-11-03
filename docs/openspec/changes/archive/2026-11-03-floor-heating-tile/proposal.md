## Why

The user runs six KNX floor heating circuits (Bad, Elena, Abstellraum, Anna, Sarah, WC). Each circuit exposes four KNX group addresses: base setpoint, room temperature, heating actuator value (0-100%) and current setpoint. Today these are only visible as raw KNX variables in the object tree, so there is no compact visual overview of whether a room is actually heating, how far the room temperature is from its setpoint, and how hard the actuator is working.

## What Changes

- New `HeatingTile` module (HTML-SDK tile) that visualizes one heating circuit: room temperature, current setpoint, base setpoint and actuator value.
- The outer ring is both the control for the setpoint and the display of the temperature scale: it runs from blue (cold) through neutral shades to red (warm), and the thumb marks the setpoint position.
- The setpoint is adjustable by dragging the ring, by the arrow keys, or by the +/- buttons. Writing happens once on release, never on every pointer move.
- The centre shows the room temperature large, the actuator value below it, and carries the operating state as a muted tint.
- The delta between room temperature and setpoint combined with the actuator value drives a state indicator (heating / holding / off / too warm).
- Readability in both the dark and the light IP-Symcon visualization design, reusing the theme detection introduced with `tile-theme-support`.
- Collision-free scaling from 200x90 up to large tiles, including the space IP-Symcon reserves for the instance name.

## Capabilities

### New Capabilities
- `heating-tile-ui`: layout, ring, state logic and scaling of the floor heating tile.

## Impact

- New module folder `HeatingTile/` with `module.json`, `module.php`, `module.html`, `form.json` and `locale.json`.
- No changes to existing tile modules.
- Writes only the current setpoint. The base setpoint stays read-only on purpose: it configures the control loop, and moving it by accident would change the heating behaviour permanently.
