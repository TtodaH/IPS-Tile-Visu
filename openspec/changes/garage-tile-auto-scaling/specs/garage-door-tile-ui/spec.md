## MODIFIED Requirements

### Requirement: HTML-SDK Tile Visualization
The system SHALL provide a responsive, theme-blended HTML-SDK tile displaying the garage door state, percentage, and one-touch control arranged in a compact side-by-side two-column layout with automatic proportional scaling of the garage door visual across all viewport dimensions.

#### Scenario: Rendering closed garage door in side-by-side layout
- **WHEN** the door state is CLOSED (0%)
- **THEN** the left column renders the closed garage door SVG scaling automatically to the available space while preserving its 3:2 proportion, and the right column renders the green status badge "Geschlossen", action button "Öffnen" (with icon ▲), and preset buttons

#### Scenario: Rendering moving garage door
- **WHEN** the door state is OPENING or CLOSING
- **THEN** the tile displays an active motion indicator with direction arrows on the garage door SVG, animated slats, estimated percentage, and action button "Stopp" (with icon ⏹)

#### Scenario: User touches control button
- **WHEN** the user taps or clicks the control button in the right column
- **THEN** the frontend calls requestAction('triggerDoor', true) and applies an active ripple state

#### Scenario: Resizing or expanding tile to desktop or modal dimensions
- **WHEN** the tile container is enlarged beyond smartphone dimensions (e.g. tablet, desktop 2x2, or fullscreen modal)
- **THEN** the sectional garage door SVG automatically scales up to utilize the maximum available width and height while maintaining its 3:2 aspect ratio, without compressing or overflowing the control buttons
