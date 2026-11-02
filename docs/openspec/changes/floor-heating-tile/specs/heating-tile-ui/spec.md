## ADDED Requirements

### Requirement: Floor heating tile visualization
The system SHALL provide an HTML-SDK tile that visualizes a single floor heating circuit with its room temperature, current setpoint, base setpoint and heating actuator value.

#### Scenario: Rendering a circuit with active heating demand
- **WHEN** the tile is loaded and the actuator value is at least 5% while the room temperature is more than 0.3 K below the setpoint
- **THEN** the tile shows the room temperature as the largest number, the setpoint and base setpoint beside it, a radial gauge filled proportionally to the actuator value, and a state pill labelled "Heizt"

#### Scenario: Rendering a circuit at setpoint
- **WHEN** the actuator value is at least 5% and the room temperature is within 0.3 K of the setpoint
- **THEN** the state pill is labelled "Hält" and uses the holding colour

#### Scenario: Rendering a circuit without heating demand
- **WHEN** the actuator value is below 5%
- **THEN** the state pill is labelled "Aus" and the gauge is visually empty

#### Scenario: Rendering an overheated room
- **WHEN** the room temperature is more than 0.3 K above the setpoint
- **THEN** the state pill is labelled "Über Temperatur" and uses the overshoot colour

### Requirement: Configurable variable binding
The tile SHALL read its four values from explicitly configured variables so the same module serves every heating circuit without code changes.

#### Scenario: Configuring the tile
- **WHEN** the user selects a base setpoint, room temperature, actuator and current setpoint variable in the instance configuration
- **THEN** the tile renders the live values of exactly these variables

#### Scenario: Missing variable binding
- **WHEN** one or more of the four variables are not configured
- **THEN** the tile renders a visible configuration hint naming the missing binding instead of plausible-looking zero values

### Requirement: Read-only bus behaviour
The tile SHALL never write to the KNX bus.

#### Scenario: User interacts with the tile
- **WHEN** the user taps or clicks anywhere in the tile
- **THEN** no value is written and no KNX group address is addressed; the tile only displays state

### Requirement: Live updates without flicker
The frontend SHALL apply pushed payloads without rebuilding the DOM.

#### Scenario: Variable changes on the bus
- **WHEN** any of the four bound variables receives a new value
- **THEN** the backend pushes a JSON payload via UpdateVisualizationValue and the tile updates numbers and gauge in place

### Requirement: Theme-aware rendering
The tile SHALL be legible in both the dark and the light IP-Symcon visualization design.

#### Scenario: Light visualization theme
- **WHEN** the tile is opened with a `cardcolor` whose relative luminance is greater than 0.45
- **THEN** the document carries `data-theme="light"` before the first paint and all text, the gauge track and the state colours use light-theme values with sufficient contrast

#### Scenario: Dark visualization theme
- **WHEN** the tile is opened with a dark `cardcolor` or without theme parameters under a dark OS preference
- **THEN** the document carries `data-theme="dark"` and the tile uses the dark palette
