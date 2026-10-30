## Context

The garage door drive operates with a single dry-contact impulse switch (Shelly 1) and a physical closed reed switch (`SensorClosedVariable`, Shelly Pro 4PM input). Currently, opening and closing travel times are statically defined in integer seconds (`TravelTimeOpenSec`, `TravelTimeCloseSec`). Physical variations due to temperature, mechanical friction, and motor age cause position drift and preset stopping errors unless regularly calibrated.

## Goals / Non-Goals

**Goals:**
- Measure the actual physical travel time with high precision whenever the door completes an uninterrupted closing run from 100% (fully open) to 0% (closed sensor triggered).
- Maintain persistent calibration data in module attributes and a visible child variable (`CalibratedTravelTime`).
- Automatically update the instance configuration and runtime travel time if `AutoCalibrateTravelTime` is enabled.
- Protect against spurious calibrations from partial travels or manual interruptions.

**Non-Goals:**
- Measuring opening time from 0% to 100% via sensor, because no top end-limit sensor is physically installed. Opening time continues to use the calibrated duration or user setting.
- Adding complex multi-point travel profiling (e.g. acceleration ramps).

## Decisions

### 1. Cycle Qualification via State Machine Flag
- **Decision:** Track whether the current movement is a qualified full run using an attribute `IsFullCloseRun` (boolean).
- **Rationale:** A cycle only qualifies for calibration if the door began closing from `STATE_OPEN` (`CurrentPosition == 100.0`) and reached the closed contact without any intermediate manual stops or direction reversals.
- **Alternatives considered:** Checking `elapsed > 10s` upon sensor trip, but this could erroneously calibrate if the door started from 80% and moved slowly.

### 2. Sanity Validation Range
- **Decision:** Validate that the measured travel duration falls within $5.0\,\text{s} \le t \le 90.0\,\text{s}$.
- **Rationale:** Prevents sensor glitching or debounce spikes from saving invalid travel times (e.g. 0.2s or 500s).

### 3. Attribute & Variable Representation
- **Decision:**
  - Attribute `MeasuredCloseTimeSec` (Float, persistent).
  - Child variable `CalibratedTravelTime` (Ident: `CalibratedTravelTime`, Type: Float, Suffix: `s`, formatted with 1 decimal place).
  - When `AutoCalibrateTravelTime` is true, automatically update `TravelTimeCloseSec` property and in-memory runtime travel calculation.
- **Rationale:** Provides full transparency in both the WebFront/Tile detail view and the instance configuration without requiring manual user intervention.

## Risks / Trade-offs

- **[Risk]** Spurious sensor bounce causes early termination.  
  → **Mitigation:** Sanity check rejects any measurement under 5 seconds. The door also transitions to CLOSED only when the sensor confirms stable closed state.
- **[Risk]** Writing properties frequently might cause excessive disk I/O.  
  → **Mitigation:** Property `TravelTimeCloseSec` is only updated if the rounded integer value differs from the currently configured value.
