## Purpose
Provide automatic, reliable detection of the active IP-Symcon visualization theme for HTML-SDK tiles and theme-aware rendering of all tile content, so tiles remain readable in both the dark and the light visualization design.

## Requirements

### Requirement: Theme detection from tile frame parameters
The tile modules SHALL determine the active IP-Symcon visualization theme from the URL parameters provided by the tile frame, not from the operating system preference.

#### Scenario: Light visualization theme
- **WHEN** the tile is loaded with a `cardcolor` whose relative luminance is greater than 0.45
- **THEN** the document element carries `data-theme="light"` before the first paint

#### Scenario: Dark visualization theme
- **WHEN** the tile is loaded with a `cardcolor` whose relative luminance is at most 0.45
- **THEN** the document element carries `data-theme="dark"` before the first paint

#### Scenario: Missing theme parameters
- **WHEN** no `cardcolor` or `textcolor` parameter is present
- **THEN** the tile falls back to the `prefers-color-scheme` media query result

### Requirement: Theme-aware tile rendering
All tile content SHALL remain readable in both themes, including text, badges, buttons, dividers and embedded SVG artwork.

#### Scenario: Text and badge contrast in the light theme
- **WHEN** the tile is rendered with `data-theme="light"`
- **THEN** primary text, secondary text, badges, buttons and dividers use dark-on-light colors with sufficient contrast

#### Scenario: SVG artwork in the light theme
- **WHEN** the tile is rendered with `data-theme="light"`
- **THEN** SVG artwork (garage wall, slats, wind rose dial, ticks, letters, hub) uses light-appropriate fills and strokes instead of the dark defaults

#### Scenario: Runtime status colors in the light theme
- **WHEN** the PHP backend pushes a status or accent color and the tile renders it in the light theme
- **THEN** the color is darkened before being applied so the contrast on a light surface is preserved

#### Scenario: Dark theme unchanged
- **WHEN** the tile is rendered with `data-theme="dark"`
- **THEN** the visual appearance matches the previous dark-only implementation
