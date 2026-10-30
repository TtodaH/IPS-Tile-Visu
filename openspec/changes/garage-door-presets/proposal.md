## Why

Users frequently need to open their garage door slightly for ventilation or to a specific intermediate height (e.g., partial opening for pet access or parcel drop-off) without having to manually watch the door movement and time the stop impulse by hand. Furthermore, users want quick-access preset buttons on the tile UI and the ability to configure target percentages directly from the detail view.

## What Changes

- Add a **Ventilation Position Preset ("Lüftungsstellung")**: A dedicated control action that moves the door to a user-defined ventilation position (e.g. 15%) and automatically stops it via a timed impulse.
- Add an editable **Ventilation Position Variable / Control in Detail View**: Allows users to view and adjust the ventilation target percentage directly in the WebFront detail view and module configuration.
- Add a **Freely Configurable Preset Button ("Frei konfigurierbarer Preset-Knopf")**: Adds an optional second preset button with customizable label and target position percentage.
- Add **Preset Button Controls to Tile UI**: Renders sleek preset buttons on the garage door tile below or beside the primary action button to trigger ventilation or custom positions with one touch.
- Implement **Target-Seeking Movement Logic**: Handles time-based positioning from current state (Closed, Opening, Open, Closing) to target percentage using the Shelly 1 single-button pulse sequence.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `garage-door-control`: Add target position calculation, timed stop impulse scheduling (`MoveToPosition`), configurable ventilation position variable, and custom preset target configuration.
- `garage-door-tile-ui`: Add preset action buttons (Lüften and custom preset) to the tile UI, handling click events and visualizing preset activation.

## Impact

- `GarageDoorTile/form.json`: New properties for default ventilation position, custom preset enablement, custom preset label, and custom preset target.
- `GarageDoorTile/module.php`: New public actions `MoveToPosition($targetPercent)`, `TriggerVentilation()`, `TriggerCustomPreset()`, variable registration for `VentilationPosition`, and `TargetStop` timer handling.
- `GarageDoorTile/module.html`: Preset button row with SVG icons, localized tooltips/labels, and JS action triggers.
