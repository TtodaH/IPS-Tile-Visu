## Context

The `GarageDoorTile` module controls a sectional garage door via a Shelly 1 relay (impulse trigger) and a bottom magnetic reed contact sensor (closed status). Position estimation is runtime-based (0% to 100%).
Users need a convenient way to move the door to a partial height for ventilation (e.g., 15%) or another intermediate height (e.g., 50% custom preset) without having to manually watch the door and press stop at the exact moment.
Furthermore, the ventilation percentage must be adjustable in the WebFront detail view, and custom preset options should be configurable in the module configuration.

## Goals / Non-Goals

**Goals:**
- Provide a target-seeking movement algorithm (`MoveToPosition($targetPercent)`) that initiates movement and automatically triggers a stop impulse when the calculated target runtime is reached.
- Provide a dedicated "Lüften" (Ventilation) preset button on the tile UI.
- Provide an editable `VentilationPosition` child variable with a percentage profile, allowing users to adjust the ventilation target directly in the WebFront detail view.
- Provide a freely configurable second preset button with customizable label and target percentage via `form.json`.
- Maintain clean, responsive tile layout fitting both mobile and desktop views without visual overflow.

**Non-Goals:**
- Closed-loop millimeter-accuracy encoder tracking (physical hardware only has a bottom magnetic switch, so intermediate positioning remains runtime-based).
- Multi-relay reversal logic (the Shelly 1 is a single-relay impulse control that drives a 1-button cycle).

## Decisions

### 1. Timed Stop Impulse for Intermediate Positioning
- **Decision**: When moving to an intermediate target $P_{target} \in (0, 100)$, the controller calculates the required motion duration from current position $P_{current}$, triggers the 1st impulse to begin travel in the required direction, and arms a non-blocking `TargetStop` timer. When the timer fires, the module automatically issues the 2nd impulse to stop the door.
- **Alternatives Considered**:
  - *Client-side JavaScript timer*: Rejected because closing the browser or network blips would cause the door to miss the stop signal and continue to full open/closed.
  - *PHP `sleep()`*: Rejected because blocking PHP threads freezes the entire IP-Symcon thread pool.
  - *Symcon Timer (`SetTimerInterval('TargetStop', $ms)`)': Chosen approach. Reliable, server-side, non-blocking.

### 2. Direction Determination & Cycle Logic
- **Decision**:
  - If $P_{current} == 0$ (Closed), an impulse always opens the door. If $P_{target} > 0$, movement direction is OPENING. Travel duration:
    $$T = \frac{P_{target} - 0}{100} \times TravelTimeOpenSec$$
  - If $P_{current} == 100$ (Fully Open), an impulse starts CLOSING. If $P_{target} < 100$, travel duration:
    $$T = \frac{100 - P_{target}}{100} \times TravelTimeCloseSec$$
  - If the door is already moving towards a target and the user presses any button (main button or preset), the active `TargetStop` timer is immediately canceled, allowing immediate manual stop/override.

### 3. Detail View Ventilation Percentage Variable
- **Decision**: Register a standard child variable `VentilationPosition` (Integer or Float, profile `~Valve` or `~ShutterMoveStep`, 0–100%).
- **Benefit**: Native IP-Symcon detail views automatically render a slider/input for this variable. Changes trigger `RequestAction('VentilationPosition', $value)`.

### 4. Tile UI Preset Buttons
- **Decision**: Render a secondary button row below the main action button with two compact pill buttons:
  - Preset 1: `[ 🪟 Lüften (15%) ]` (or user-configured %)
  - Preset 2: `[ ⚙️ <CustomLabel> (<CustomPercent>%) ]` (conditional on `CustomPresetEnabled`)
- **Benefit**: Compact footprint (approx. 32px height), clearly visually subordinate to the primary full-width action button.

## Risks / Trade-offs

- **[Risk] Direction Desynchronization if Door was Manually Stopped**: If the garage door was previously stopped mid-travel by an external remote or physical button, the next impulse might move in the opposite direction than expected by pure calculation.
  - *Mitigation*: The user can tap the main button or another preset at any time to halt or reverse. The bottom magnetic switch always re-synchronizes the 0% baseline whenever the door closes.
- **[Risk] Minimum Travel Time**: Very small percentage changes (e.g. 2%) might result in travel durations shorter than the Shelly impulse cycle (800ms).
  - *Mitigation*: Enforce a minimum travel duration threshold (e.g. minimum 1.5 seconds) or minimum 5% movement step.
