## Why

All three tile modules (`TileTemplate`, `GarageDoorTile`, `WeatherStationTile`) were designed for the dark IP-Symcon tile theme. They hard-code light text colors (`#F8FAFC`, `#E2E8F0`), white-alpha surfaces and dark SVG artwork (garage wall/slats, dial background). In a light theme these values become unreadable: text nearly disappears on white cards, the wind rose dial and garage artwork lose contrast, and status colors (e.g. `#10B981`) fall below WCAG contrast requirements on white.

Additionally `TileTemplate` used `@media (prefers-color-scheme: light)`, which follows the **operating system** theme rather than the IP-Symcon visualization theme. A user with OS dark mode and a light Symcon theme therefore got the wrong colors.

## What Changes

- Add a theme bootstrap script to every `module.html` that reads the theme colors IP-Symcon appends to the tile frame URL (`cardcolor`, `textcolor`, `accentcolor`), computes the relative luminance of the card color and sets `data-theme="light|dark"` on `<html>` before first paint.
- Replace the `prefers-color-scheme` block in `TileTemplate` with a `:root[data-theme="light"]` variable set.
- Introduce light-theme variable overrides for all surfaces, text, buttons and dividers in all three tiles.
- Move SVG artwork colors (garage wall, frame, interior, slats, seal, hinges, threshold, dial background, ticks, letters, ghost needle, hub, needle tail, shadow) into CSS custom properties that are overridden in the light theme.
- Darken status colors in the light theme (`themeColor()` helper in JS and updated `--color-*` / `--accent-strong` / `--warn-strong` variables) so text keeps ≥ 4.5:1 contrast on white.
- Use the Symcon-provided card color as the tile background fallback where a tile previously painted its own opaque background.

## Capabilities

### New Capabilities
- `tile-theme-support`: Detection of the IP-Symcon visualization theme from the tile frame URL parameters and theme-aware rendering of all tile modules.

### Modified Capabilities
- `garage-door-tile-ui`: Tile must remain readable in light themes (text, status pill, buttons, garage artwork).

## Impact

- `TileTemplate/module.html`, `GarageDoorTile/module.html`, `WeatherStationTile/module.html`: theme bootstrap, light-theme CSS variable sets, SVG color variables, JS color darkening helper.
- No PHP, `form.json`, `module.json` or `locale.json` changes; no API or data contract changes.
- Backwards compatible: without theme parameters the bootstrap falls back to the OS preference, and the dark theme remains byte-for-byte equivalent in appearance.
- Repository layout: `openspec/` and `scripts/` moved under `docs/`. IP-Symcon treats any top-level folder without a `module.json` as an invalid module (only `libs/`, `docs/`, `imgs/`, `tests/`, `actions/` and dot-folders are exempt), so the previous layout made Module Control reject the whole library.
