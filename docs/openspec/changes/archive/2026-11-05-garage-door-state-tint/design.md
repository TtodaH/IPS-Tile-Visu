## Context

The garage door is drawn as inline SVG. Frame and slats already take their colours from CSS custom properties (`--frame-edge`, `--frame-mid`, `--slat-1` … `--slat-4`), which the theme bootstrap overrides for the light design. The frontend already receives `data.state` and `data.stateColor` on every update.

## Goals / Non-Goals

**Goals**
- The door state is readable from the door graphic itself, at a glance.
- The door still looks like a garage door: shading, highlights and depth survive.
- Works in both themes without a second set of hardcoded colours.

**Non-Goals**
- No change to the state logic or the pill.
- No new module properties, no backend change.
- No animation of the tint.

## Decisions

**Tint by mixing, not replacing.** Each affected colour is mixed with the state colour at a low ratio (`color-mix`). Replacing the greys outright would flatten the door into a coloured rectangle and lose the slat shading that makes it readable as a door.

**Two intensities.** The frame takes a stronger mix than the slats. The frame is the outer boundary and reads first; the slats carry the shading, so they stay closer to neutral.

**Neutral states stay neutral.** `stopped` and `offline` carry no meaningful open/closed information. Tinting them would suggest a state that is not known.

**Driven by a single custom property.** The frontend sets `--door-state` once per update. The CSS rules derive every mix from it, so there is one place to change the intensity.

## Risks

- **Too much colour.** Mitigated by low mix ratios and by checking against the real screenshots in both themes.
- **Contrast loss.** The tint must not reduce the contrast of the percentage badge or the hinges. Verified by rendering all states.
- **Light theme.** A mix that looks right on dark can wash out on light. Both themes are rendered and measured separately.
