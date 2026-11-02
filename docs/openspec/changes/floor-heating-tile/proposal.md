## Why

The user runs six KNX floor heating circuits (Bad, Elena, Abstellraum, Anna, Sarah, WC). Each circuit exposes four KNX group addresses: base setpoint, room temperature, heating actuator value (0-100%) and current setpoint. Today these are only visible as raw KNX variables in the object tree, so there is no compact visual overview of whether a room is actually heating, how far the room temperature is from its setpoint, and how hard the actuator is working.

## What Changes

- New `HeatingTile` module (HTML-SDK tile) that visualizes one heating circuit: room temperature, current setpoint, base setpoint and actuator value.
- The actuator value is rendered as a radial gauge so the current heating demand is readable at a glance.
- The delta between room temperature and setpoint drives a state indicator (heating / holding / cooling / off).
- Readability in both the dark and the light IP-Symcon visualization design, reusing the theme detection introduced with `tile-theme-support`.

## Capabilities

### New Capabilities
- `heating-tile-ui`: layout, gauges and state logic of the floor heating tile.

## Impact

- New module folder `HeatingTile/` with `module.json`, `module.php`, `module.html`, `form.json` and `locale.json`.
- No changes to existing tile modules.
- Read-only on KNX: the tile only reads variables, it never writes to the bus.
