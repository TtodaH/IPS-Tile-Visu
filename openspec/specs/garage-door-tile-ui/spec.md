## Purpose
Provide a responsive, high-performance HTML-SDK tile visualization for garage door status and control in IP-Symcon 7+.
## Requirements
### Requirement: HTML-SDK Tile Visualization
The system SHALL provide a responsive, theme-blended HTML-SDK tile displaying the garage door state, percentage, and one-touch control arranged in a compact side-by-side two-column layout.

#### Scenario: Rendering closed garage door in side-by-side layout
- **WHEN** the door state is CLOSED (0%)
- **THEN** the left column renders the closed garage door SVG with 0% badge, and the right column renders the green status badge "Geschlossen", action button "Öffnen" (with icon ▲), and preset buttons

#### Scenario: Rendering moving garage door
- **WHEN** the door state is OPENING or CLOSING
- **THEN** the tile displays an active motion indicator with direction arrows on the garage door SVG, animated slats, estimated percentage, and action button "Stopp" (with icon ⏹)

#### Scenario: User touches control button
- **WHEN** the user taps or clicks the control button in the right column
- **THEN** the frontend calls requestAction('triggerDoor', true) and applies an active ripple state

### Requirement: Live WebSocket Synchronization
The frontend SHALL parse live WebSocket payload pushes without DOM destruction or flickering.

#### Scenario: WebSocket pushes JSON payload string
- **WHEN** IP-Symcon sends an update string via UpdateVisualizationValue
- **THEN** handleMessage parses the JSON string and smoothly updates the status badges, door animation, and button state without redrawing the DOM

### Requirement: Preset Action Buttons in Tile UI
The tile UI SHALL display dedicated interactive preset action buttons for the ventilation position and the configurable custom preset within the control column beside the garage door graphic.

#### Scenario: Displaying preset buttons in resting state
- **WHEN** the tile is rendered in closed, open, or stopped state
- **THEN** the tile renders a preset row in the control column with an airflow icon + ventilation percentage (e.g. "15%") and (if enabled) custom preset slider icon + percentage (e.g. "50%")

#### Scenario: User clicks preset ventilation button
- **WHEN** user taps or clicks the ventilation preset button in the tile
- **THEN** the tile sends a RequestAction call for `triggerPresetVentilation` to the backend and reflects an active pending status

#### Scenario: User clicks custom preset button
- **WHEN** user taps or clicks the custom preset button in the tile
- **THEN** the tile sends a RequestAction call for `triggerCustomPreset` to the backend

