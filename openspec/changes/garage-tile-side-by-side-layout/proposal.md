## Why

On mobile smartphones and compact tile dashboard views, vertical space is strongly constrained (~140px to 170px height). In the previous layout, the garage door SVG graphic and the control buttons (main trigger button, preset buttons, status and meta rows) were stacked vertically, requiring ~240px and causing severe vertical clipping or requiring internal scrolling on smartphones. In addition, the status pill placed near the top clashed with IP-Symcon's tile header and expand icon.

Reorganizing the tile into a horizontal side-by-side layout (garage door graphic on the left, controls and presets on the right) optimizes space utilization for the standard 16:9 / 2:1 tile aspect ratio, fits cleanly without scrolling, and replaces excessive text with intuitive iconography.

## What Changes

- Reorganize tile body from vertically stacked into a side-by-side 2-column flex layout (`.tile-content-row`):
  - **Left column (`.garage-col`)**: Modern sectional door SVG graphic, floating motion indicator, and bottom-corner percentage badge (`0 %`).
  - **Right column (`.controls-col`)**: Vertical control stack containing status pill, main action button, preset buttons, and open duration / impulse metadata.
- Optimize button labels and iconography for compact widths:
  - Main button features clear directional icon (▲, ▼, ⏹) with concise action text ("Öffnen", "Schließen", "Stopp").
  - Preset buttons feature intuitive SVG icons (ventilation airflow, custom preset sliders) accompanied by percentage numbers (e.g. `15%`, `50%`) and full descriptive tooltip titles.
- Guarantee collision-free header spacing with Symcon's native tile title and expand button.
- Ensure seamless Frame-0 pre-rendering in `module.php` and smooth real-time WebSocket updates via `handleMessage(data)`.

## Capabilities

### New Capabilities
<!-- No new capabilities -->

### Modified Capabilities
- `garage-door-tile-ui`: Update UI layout requirement to specify side-by-side arrangement of door visual and control elements for smartphone / compact tile optimization.

## Impact

- `GarageDoorTile/module.html`: CSS and HTML structure adjusted to 2-column flex layout; button styles and icons updated.
- `GarageDoorTile/module.php`: Pre-rendering replacements in `GetVisualizationTile()` updated to match new HTML elements and compact button text.
- No database schema or breaking IPC changes.
