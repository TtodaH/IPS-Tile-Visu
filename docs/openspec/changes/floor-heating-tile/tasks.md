## 1. Module scaffold

- [x] 1.1 Create `HeatingTile/module.json` with a fresh GUID, `type: 3`, prefix `HEATTILE`
- [x] 1.2 Create `form.json` with four `SelectVariable` properties and tile presentation options
- [x] 1.3 Create `locale.json` for the German captions

## 2. Backend

- [x] 2.1 `Create()` registers properties, `SetVisualizationType(1)` and a status variable
- [x] 2.2 `ApplyChanges()` validates the four variable IDs and registers message sinks
- [x] 2.3 `GetTilePayload()` builds room temperature, setpoint, base setpoint, actuator value and derived state
- [x] 2.4 `GetVisualizationTile()` injects the initial payload without a round trip
- [x] 2.5 `MessageSink()` pushes updates on variable change via `SendTileUpdate()`

## 3. Frontend

- [x] 3.1 Theme bootstrap identical to the existing tiles (`data-theme` before first paint)
- [x] 3.2 Layout: room name, large room temperature, setpoint, base setpoint, radial actuator gauge
- [x] 3.3 State pill with colour and label per derived state
- [x] 3.4 `handleMessage()` updates values without DOM rebuild
- [x] 3.5 Light theme variables for text, panel, gauge track and state colours

## 4. Verification

- [x] 4.1 Render with real Elena values in dark and light theme and inspect visually
- [x] 4.2 Verify gauge at 0%, 5%, 50% and 100%
- [x] 4.3 Verify a configuration hint appears when a variable ID is missing
- [x] 4.4 Update README with the new module
