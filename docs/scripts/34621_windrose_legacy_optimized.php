<?php

declare(strict_types=1);

/**
 * Optimiertes Übergangsskript für HTMLBox (ID 34621)
 *
 * HINWEIS:
 * Die sauberste und vollständig flackerfreie Lösung ist das neue native IP-Symcon Modul
 * "WeatherStationTile" (HTML-SDK).
 *
 * Wenn dieses Skript übergangsweise weiterverwendet werden soll, wurden folgende
 * Anti-Flacker-Maßnahmen integriert:
 * 1. String-Vergleich vor SetValueString: Schreibt nur bei echter Inhaltsänderung.
 * 2. WICHTIGER SCHRITT IN DER KONSOLE: Die Ereignisse 37511, 27605 und 50140 sollten
 *    von "Bei Aktualisierung" auf "Bei Änderung" umgestellt werden!
 */

$dirID       = 38465; // Aktuelle Windrichtung (Grad)
$speedID     = 21228; // Aktuelle Windgeschwindigkeit (m/s)
$gustID      = 58720; // Aktuelle Böe (m/s)
$tempWPID    = 13145; // Temperatur_TA_Aussenfuehler (Wärmepumpe)
$tempStallID = 31094; // Aktuelle Temperatur (Kaninchenstall)
$htmlID      = 31737; // Windrose (HTMLBox)

$degrees  = (float)@GetValue($dirID);
$speedMS  = (float)@GetValue($speedID);
$speedKMH = round($speedMS * 3.6, 1);
$gustMS   = (float)@GetValue($gustID);
$gustKMH  = round($gustMS * 3.6, 1);

$tempWP = @GetValue($tempWPID);
$tempWPFormatted = ($tempWP !== false && $tempWP !== null) ? number_format((float)$tempWP, 1, ',', '.') : '--,-';

$tempStall = @GetValue($tempStallID);
$tempStallFormatted = ($tempStall !== false && $tempStall !== null) ? number_format((float)$tempStall, 1, ',', '.') : '--,-';

// Himmelsrichtungen (16-teilig)
$cardinals = [
    'N'   => 'Nord',
    'NNO' => 'Nord-Nordost',
    'NO'  => 'Nordost',
    'ONO' => 'Ost-Nordost',
    'O'   => 'Ost',
    'OSO' => 'Ost-Südost',
    'SO'  => 'Südost',
    'SSO' => 'Süd-Südost',
    'S'   => 'Süd',
    'SSW' => 'Süd-Südwest',
    'SW'  => 'Südwest',
    'WSW' => 'West-Südwest',
    'W'   => 'West',
    'WNW' => 'West-Nordwest',
    'NW'  => 'Nordwest',
    'NNW' => 'Nord-Nordwest'
];
$keys = array_keys($cardinals);
$idx = (int)round(fmod(fmod($degrees, 360) + 360, 360) / 22.5) % 16;
$shortDir = $keys[$idx];
$longDir  = $cardinals[$shortDir];

// Beaufort-Skala
$beaufort = 0;
$bftDescriptions = [
    0  => 'Windstille',
    1  => 'Leiser Zug',
    2  => 'Leichte Brise',
    3  => 'Schwache Brise',
    4  => 'Mäßige Brise',
    5  => 'Frische Brise',
    6  => 'Starker Wind',
    7  => 'Steifer Wind',
    8  => 'Stürmischer Wind',
    9  => 'Sturm',
    10 => 'Schwerer Sturm',
    11 => 'Orkanartiger Sturm',
    12 => 'Orkan'
];
$thresholds = [0.2, 1.5, 3.3, 5.4, 7.9, 10.7, 13.8, 17.1, 20.7, 24.4, 28.4, 32.6];
foreach ($thresholds as $b => $th) {
    if ($speedMS > $th) {
        $beaufort = $b + 1;
    }
}
$bftName = $bftDescriptions[$beaufort] ?? '';

// Böen Text
$gustHtml = '';
if ($gustKMH > 0) {
    $gustHtml = sprintf(' · Böen: %s km/h', number_format($gustKMH, 1, ',', '.'));
}

$speedKMHFormatted = number_format($speedKMH, 1, ',', '.');
$speedMSFormatted  = number_format($speedMS, 1, ',', '.');
$degFormatted      = round($degrees);

$html = <<<HTML
<style>
  .wr-vertical {
    container-type: size;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    box-sizing: border-box;
    padding: 4px 14px 12px 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    user-select: none;
    overflow: hidden;
  }
  .wr-compass-hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1 1 auto;
    min-height: 0;
    width: 100%;
  }
  .wr-svg {
    width: auto;
    height: auto;
    max-width: min(calc(100cqw - 20px), calc(100cqh - 74px));
    max-height: min(calc(100cqw - 20px), calc(100cqh - 74px));
    aspect-ratio: 1 / 1;
    overflow: visible;
  }
  .wr-badge {
    font-size: clamp(11.5px, min(3.8cqw, 4.2cqh), 18px);
    font-weight: 700;
    color: #F8FAFC;
    text-align: center;
    letter-spacing: 0.5px;
    line-height: 1.1;
    margin-top: 3px;
    white-space: nowrap;
  }
  .wr-divider {
    height: 1px;
    background: rgba(255,255,255,0.08);
    width: 100%;
    flex-shrink: 0;
    margin: clamp(4px, 1.2cqh, 8px) 0;
  }
  .wr-footer {
    display: flex;
    flex-direction: column;
    width: 100%;
    flex-shrink: 0;
    gap: clamp(3px, 1cqh, 6px);
  }
  .wr-row1 {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    width: 100%;
  }
  .wr-temp-val {
    font-size: clamp(18px, min(6.5cqw, 7cqh), 30px);
    font-weight: 800;
    color: #F8FAFC;
    letter-spacing: -0.5px;
    line-height: 1;
  }
  .wr-temp-unit {
    font-size: clamp(12px, min(4cqw, 4.5cqh), 18px);
    font-weight: 600;
    color: #94A3B8;
    margin-left: 2px;
  }
  .wr-temp-tag {
    font-size: clamp(9px, min(2.8cqw, 3.2cqh), 12px);
    font-weight: 600;
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 4px;
  }
  .wr-stall {
    font-size: clamp(10.5px, min(3.4cqw, 3.8cqh), 15px);
    color: #64748B;
    font-weight: 500;
    white-space: nowrap;
  }
  .wr-row2 {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    width: 100%;
    font-size: clamp(11px, min(3.5cqw, 3.8cqh), 15.5px);
    line-height: 1.2;
    white-space: nowrap;
  }
  .wr-wind {
    font-weight: 700;
    color: #38BDF8;
  }
  .wr-gust {
    font-weight: 600;
    color: #F59E0B;
    margin-left: 6px;
  }
  .wr-bft {
    color: #94A3B8;
    font-size: 0.92em;
  }
</style>

<div class="wr-vertical">

  <!-- Top: Compass Hero Section -->
  <div class="wr-compass-hero">
    <svg class="wr-svg" viewBox="0 0 120 120">
      <defs>
        <filter id="wr-shadow" x="-30%" y="-30%" width="160%" height="160%">
          <feDropShadow dx="0" dy="1.5" stdDeviation="2.5" flood-color="#000000" flood-opacity="0.6"/>
        </filter>
      </defs>

      <!-- Outer Rings -->
      <circle cx="60" cy="60" r="54" fill="rgba(255,255,255,0.02)" stroke="rgba(255,255,255,0.14)" stroke-width="1.2" />
      <circle cx="60" cy="60" r="44" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="0.8" stroke-dasharray="2,3" />

      <!-- Major Ticks -->
      <line x1="60" y1="8" x2="60" y2="15" stroke="rgba(255,255,255,0.45)" stroke-width="1.5" stroke-linecap="round" />
      <line x1="112" y1="60" x2="105" y2="60" stroke="rgba(255,255,255,0.45)" stroke-width="1.5" stroke-linecap="round" />
      <line x1="60" y1="112" x2="60" y2="105" stroke="rgba(255,255,255,0.45)" stroke-width="1.5" stroke-linecap="round" />
      <line x1="8" y1="60" x2="15" y2="60" stroke="rgba(255,255,255,0.45)" stroke-width="1.5" stroke-linecap="round" />

      <!-- Minor Ticks -->
      <line x1="60" y1="10" x2="60" y2="14" transform="rotate(45 60 60)" stroke="rgba(255,255,255,0.25)" stroke-width="1" stroke-linecap="round" />
      <line x1="60" y1="10" x2="60" y2="14" transform="rotate(135 60 60)" stroke="rgba(255,255,255,0.25)" stroke-width="1" stroke-linecap="round" />
      <line x1="60" y1="10" x2="60" y2="14" transform="rotate(225 60 60)" stroke="rgba(255,255,255,0.25)" stroke-width="1" stroke-linecap="round" />
      <line x1="60" y1="10" x2="60" y2="14" transform="rotate(315 60 60)" stroke="rgba(255,255,255,0.25)" stroke-width="1" stroke-linecap="round" />

      <!-- Cardinal Letters -->
      <text x="60" y="24" fill="#F43F5E" font-size="12" font-weight="800" text-anchor="middle" dominant-baseline="central">N</text>
      <text x="96" y="60" fill="rgba(255,255,255,0.85)" font-size="11" font-weight="700" text-anchor="middle" dominant-baseline="central">O</text>
      <text x="60" y="96" fill="rgba(255,255,255,0.85)" font-size="11" font-weight="700" text-anchor="middle" dominant-baseline="central">S</text>
      <text x="24" y="60" fill="rgba(255,255,255,0.85)" font-size="11" font-weight="700" text-anchor="middle" dominant-baseline="central">W</text>

      <!-- Rotating Needle -->
      <g transform="rotate({$degrees} 60 60)" style="transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);" filter="url(#wr-shadow)">
        <polygon points="60,28 56,60 60,54" fill="#00CDAB" />
        <polygon points="60,28 64,60 60,54" fill="#14F1CD" />
        <polygon points="60,92 56,60 60,66" fill="#475569" />
        <polygon points="60,92 64,60 60,66" fill="#64748B" />
        <circle cx="60" cy="60" r="4.5" fill="#0F172A" stroke="#00CDAB" stroke-width="1.5" />
        <circle cx="60" cy="60" r="1.5" fill="#FFFFFF" />
      </g>
    </svg>
    <div class="wr-badge">
      {$shortDir} <span style="font-weight:400; color:#94A3B8; font-size:0.85em;">({$degFormatted}°) · {$longDir}</span>
    </div>
  </div>

  <div class="wr-divider"></div>

  <!-- Bottom: Infos Untereinander -->
  <div class="wr-footer">
    <!-- Row 1: Temperatures -->
    <div class="wr-row1">
      <div>
        <span class="wr-temp-val">{$tempWPFormatted}<span class="wr-temp-unit">°C</span></span>
        <span class="wr-temp-tag">Außen</span>
      </div>
      <div class="wr-stall">Stall: <span style="color:#94A3B8; font-weight:600;">{$tempStallFormatted} °C</span></div>
    </div>

    <!-- Row 2: Wind + Gusts + Beaufort -->
    <div class="wr-row2">
      <div>
        <span class="wr-wind">{$speedKMHFormatted} km/h <span style="font-weight:400; color:#64748B; font-size:0.85em;">({$speedMSFormatted} m/s)</span></span>
        <span class="wr-gust">{$gustHtml}</span>
      </div>
      <div class="wr-bft">
        {$beaufort} Bft ({$bftName})
      </div>
    </div>
  </div>

</div>
HTML;

// ANTI-FLACKER FIX: Nur schreiben, wenn sich der String tatsächlich geändert hat!
if (GetValueString($htmlID) !== $html) {
    SetValueString($htmlID, $html);
}
