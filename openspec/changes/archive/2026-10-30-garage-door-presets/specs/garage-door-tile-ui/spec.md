## ADDED Requirements

### Requirement: Preset Action Buttons in Tile UI
The tile UI SHALL display dedicated interactive preset action buttons for the ventilation position and the configurable custom preset.

#### Scenario: Displaying preset buttons in resting state
- **WHEN** the tile is rendered in closed, open, or stopped state
- **THEN** the tile renders a secondary button row below the main action button displaying a "Lüften" button with the configured percentage and (if enabled) the custom preset button with its configured label and percentage

#### Scenario: User clicks preset ventilation button
- **WHEN** user taps or clicks the "Lüften" button on the tile
- **THEN** the tile sends a RequestAction call for `triggerPresetVentilation` to the backend and reflects an active pending status

#### Scenario: User clicks custom preset button
- **WHEN** user taps or clicks the custom preset button on the tile
- **THEN** the tile sends a RequestAction call for `triggerCustomPreset` to the backend
