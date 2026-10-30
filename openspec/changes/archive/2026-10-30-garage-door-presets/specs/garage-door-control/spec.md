## ADDED Requirements

### Requirement: Preset Target Movement Control
The system SHALL support moving the garage door to an arbitrary intermediate target percentage and automatically stopping it via a timed relay impulse.

#### Scenario: Initiating movement to ventilation position from closed state
- **WHEN** user activates the ventilation preset while the door is CLOSED (0%) and ventilation target is 15%
- **THEN** the system triggers the initial relay impulse to start OPENING, schedules a TargetStop timer for `(15 / 100) * TravelTimeOpenSec`, and triggers a second relay impulse when the timer fires to stop the door at 15%

#### Scenario: Cancelling target stop upon manual user override
- **WHEN** user manually triggers a door impulse while an automatic target stop timer is active
- **THEN** the system immediately cancels the TargetStop timer and executes the manual impulse without scheduling conflicting stops

### Requirement: Configurable Ventilation Position Variable
The system SHALL maintain a child variable for the ventilation position percentage that is editable from the WebFront detail view.

#### Scenario: User adjusts ventilation percentage in detail view
- **WHEN** user changes the VentilationPosition variable in the visualization detail view or via RequestAction
- **THEN** the system updates the variable, validates the range (0% to 100%), and pushes the updated preset configuration to connected visualization tiles

### Requirement: Freely Configurable Custom Preset
The system SHALL support an optional secondary preset with customizable label and target position percentage configured in module settings.

#### Scenario: Triggering custom preset
- **WHEN** user triggers the custom preset action while custom preset is enabled and configured to 50%
- **THEN** the system calculates the required runtime to reach 50% and schedules the stopping impulse accordingly
