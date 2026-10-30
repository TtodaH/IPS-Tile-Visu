## ADDED Requirements

### Requirement: HTML-SDK Tile Visualization
The system SHALL provide a responsive, theme-blended HTML-SDK tile displaying the garage door state, percentage, and one-touch control.

#### Scenario: Rendering closed garage door
- **WHEN** the door state is CLOSED (0%)
- **THEN** the tile displays a green status badge "Geschlossen", 0% position, and button label "Tor öffnen"

#### Scenario: Rendering moving garage door
- **WHEN** the door state is OPENING or CLOSING
- **THEN** the tile displays an active motion indicator with direction arrows, moving progress bar or animated slats, estimated percentage, and button label "Tor stoppen"

#### Scenario: User touches control button
- **WHEN** the user taps or clicks the control button in the tile
- **THEN** the frontend calls requestAction('triggerDoor', true) and applies an active ripple state

### Requirement: Live WebSocket Synchronization
The frontend SHALL parse live WebSocket payload pushes without DOM destruction or flickering.

#### Scenario: WebSocket pushes JSON payload string
- **WHEN** IP-Symcon sends an update string via UpdateVisualizationValue
- **THEN** handleMessage parses the JSON string and smoothly updates the status badges, door animation, and button state without redrawing the DOM
