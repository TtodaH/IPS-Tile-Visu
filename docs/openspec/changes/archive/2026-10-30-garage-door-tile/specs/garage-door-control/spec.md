## ADDED Requirements

### Requirement: Automated Shelly 1 Impulse Generation
The system SHALL simulate a momentary push-button impulse on the Shelly 1 relay switch variable without requiring manual toggling.

#### Scenario: User triggers garage door action
- **WHEN** user commands a door impulse from the visualization or PHP API
- **THEN** the system sets the Shelly 1 relay variable to true via RequestAction, activates a non-blocking module timer with the configured duration (default 800ms), and automatically sets the variable back to false when the timer elapses

#### Scenario: Shelly 1 is offline
- **WHEN** the user triggers a door impulse while the reachability variable is false
- **THEN** the system aborts the impulse and logs a warning in IP-Symcon without hanging or deadlocking

### Requirement: Garage Door State Tracking
The system SHALL maintain a state machine reflecting the physical garage door state (CLOSED, OPENING, OPEN, CLOSING, STOPPED).

#### Scenario: Door begins opening from closed state
- **WHEN** an impulse is sent while the door is CLOSED and the bottom magnetic contact reports false
- **THEN** the system transitions to the OPENING state, marks the start timestamp, and begins runtime position estimation

#### Scenario: Bottom reed switch triggers closed state
- **WHEN** the bottom magnetic contact variable switches to false (closed)
- **THEN** the system immediately transitions to CLOSED, hard-resets the estimated position to 0%, and halts active travel timers

### Requirement: Runtime-Based Position Estimation
The system SHALL estimate door opening percentage (0% to 100%) during motion based on configured opening and closing travel times.

#### Scenario: Door opens during travel
- **WHEN** the door is in the OPENING state and time t has elapsed since impulse
- **THEN** estimated position is calculated as min(100, (elapsedTime / TravelTimeOpenSec) * 100)

#### Scenario: Door reaches full open duration
- **WHEN** elapsed opening time reaches TravelTimeOpenSec
- **THEN** the system sets the position to 100% and transitions to OPEN state

### Requirement: Door Open Duration Tracking
The system SHALL track how long the garage door has been continuously open.

#### Scenario: Door remains open past warning threshold
- **WHEN** the door is in an open or stopped state longer than WarnOpenMinutes
- **THEN** the system flags a duration warning flag in the module payload and detail variables
