## Context

The garage door is operated via a Shelly 1 relay (`ID 44039`), which bridges the push-button terminal of the door motor. The current closed state is monitored by a magnetic reed switch connected to a Shelly Pro 4PM input (`ID 39824`). Previous automations relied on legacy script `38346` and workflows (`54874`, `49291`) that suffered from missing hardware `RequestAction()` execution, manual switch toggle requirements, and lack of door position visibility.

## Goals / Non-Goals

**Goals:**
* Provide an automated, non-blocking Shelly 1 impulse generator (`SetTimerInterval`) with configurable duration (e.g. 800ms) that turns off the relay automatically.
* Implement a state machine tracking `CLOSED`, `OPENING`, `OPEN`, `CLOSING`, and `STOPPED`.
* Calculate time-based door position estimation (0–100%) calibrated by the physical closed-sensor.
* Deliver an HTML-SDK tile with seamless `#333438` theme blending, animated door visualization, and one-touch action button.
* Create native child status variables (`MaintainVariable`) for IP-Symcon detail/fullscreen views.

**Non-Goals:**
* Implementing physical obstruction detection (the garage door drive's internal safety mechanics handle physical obstacle reversal).
* Modifying Shelly 1 or Shelly Pro 4PM device firmware.

## Decisions

### 1. Module Non-Blocking Timers vs. PHP `sleep()`
* **Decision**: Use IP-Symcon's native `$this->SetTimerInterval('ImpulseTimer', $ms)` and `$this->SetTimerInterval('TravelTimer', 1000)` rather than blocking `sleep()` or `usleep()`.
* **Rationale**: Blocking PHP sleep holds worker threads in IP-Symcon, causing thread pool starvation and freezing UI updates. Module timers run asynchronously in the core event loop.
* **Alternatives considered**: Shelly 1 onboard auto-off timer. While possible in the Shelly web interface, it is vulnerable to lost settings upon device resets and cannot report exact pulse completion back to IP-Symcon.

### 2. Time-Based Opening Estimation with Reed Switch Calibration
* **Decision**: Estimate intermediate position (0–100%) during motion based on user-calibrated travel times (`TravelTimeOpenSec`, `TravelTimeCloseSec`). Instantly snap to 0% when the bottom reed switch closes.
* **Rationale**: The garage door only has a single physical contact at the closed position. Time interpolation gives users realistic progress indicators while the physical switch guarantees zero drift when fully closed.

### 3. Bidirectional Action Handling via HTML-SDK
* **Decision**: Use `requestAction('triggerDoor', true)` in the tile frontend, routed to `RequestAction($Ident, $Value)` in PHP.
* **Rationale**: Conforms to the standard IP-Symcon 7+/9+ HTML-SDK architecture without requiring custom webhooks or intermediary dummy variables.

## Risks / Trade-offs

* **[Risk]** Out-of-sync state if the garage door is operated via an external radio remote control.
  - *Mitigation*: The bottom reed switch (`39824`) is continuously monitored via `VM_UPDATE`. As soon as the door opens via remote, the sensor triggers and the state machine transitions out of `CLOSED`. When it closes again, the sensor resets the state to `CLOSED` (0%).
* **[Risk]** Intermediate stop (user halts door mid-travel).
  - *Mitigation*: The single-button impulse cycle detects an impulse during active motion (`OPENING` or `CLOSING`), transitions state to `STOPPED`, and freezes estimated position until the next impulse.

## Migration Plan

1. Install `GarageDoorTile` in IP-Symcon under the desired category.
2. Configure variables: Switch `44039`, Sensor `39824`, Reachable `30959`.
3. Test impulse operation and calibrate travel times.
4. Replace WebFront link `20927` with the new tile.
5. Deactivate legacy workflows `54874` and `49291` and script `38346`.
