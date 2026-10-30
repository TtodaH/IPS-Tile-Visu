<?php

declare(strict_types=1);

// Ensure IP-Symcon variable type constants are defined
if (!defined('VARIABLETYPE_BOOLEAN')) {
    define('VARIABLETYPE_BOOLEAN', 0);
}
if (!defined('VARIABLETYPE_INTEGER')) {
    define('VARIABLETYPE_INTEGER', 1);
}
if (!defined('VARIABLETYPE_FLOAT')) {
    define('VARIABLETYPE_FLOAT', 2);
}
if (!defined('VARIABLETYPE_STRING')) {
    define('VARIABLETYPE_STRING', 3);
}

// IP-Symcon message constant for variable updates
if (!defined('VM_UPDATE')) {
    define('VM_UPDATE', 10603);
}

/**
 * WeatherStationTile
 *
 * Flackerfreie, moderne Kachel-Visualisierung (HTML-SDK) für Wetterstationen.
 * Spezialisiert auf TFA / WeatherHub / Netatmo / Davis Stationen.
 *
 * @author Thomas Hellstern
 */
class WeatherStationTile extends IPSModule
{
    /**
     * 16-teilige Windrose (Grad -> Kürzel & Langbezeichnung)
     */
    private const CARDINALS = [
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

    /**
     * Beaufort-Skala Grenzwerte in m/s und deutsche Bezeichnungen
     */
    private const BEAUFORT_THRESHOLDS = [0.2, 1.5, 3.3, 5.4, 7.9, 10.7, 13.8, 17.1, 20.7, 24.4, 28.4, 32.6];
    private const BEAUFORT_NAMES = [
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

    public function Create(): void
    {
        parent::Create();

        // Aktiviert das IP-Symcon HTML-SDK für Kachelvisualisierung (1 = INSTANCE_VISUALIZATION_TYPE_HTML)
        $this->SetVisualizationType(1);

        // Sensoren & Variablen (Standardmäßig mit 0 registriert)
        $this->RegisterPropertyInteger('WindDirectionVariable', 0);     // z.B. 38465 (Grad)
        $this->RegisterPropertyInteger('WindSpeedVariable', 0);         // z.B. 21228 (m/s)
        $this->RegisterPropertyInteger('GustVariable', 0);              // z.B. 58720 (m/s)
        $this->RegisterPropertyInteger('PreviousDirectionVariable', 0); // z.B. 44797 (Grad)
        $this->RegisterPropertyInteger('OutdoorTempVariable', 0);       // z.B. 13145 (°C)
        $this->RegisterPropertyInteger('SecondaryTempVariable', 0);     // z.B. 31094 (°C)
        $this->RegisterPropertyInteger('RainVariable', 0);              // z.B. 35022 (Boolean)
        $this->RegisterPropertyInteger('BatteryVariable', 0);           // z.B. 15523 (Boolean)

        // Anzeige-Optionen
        $this->RegisterPropertyString('TileTitle', 'Wetterstation');
        $this->RegisterPropertyString('WindUnit', 'kmh'); // kmh, ms, kn
        $this->RegisterPropertyBoolean('ShowBeaufort', true);
        $this->RegisterPropertyBoolean('ShowSecondaryTemp', true);
        $this->RegisterPropertyString('SecondaryTempLabel', 'Stall');
        $this->RegisterPropertyBoolean('ShowRainStatus', true);
        $this->RegisterPropertyBoolean('ShowBatteryStatus', false);
        $this->RegisterPropertyBoolean('ShowPreviousDirection', true);

        // Farbakzente
        $this->RegisterPropertyInteger('NeedleColor', 0x00CDAB);      // Türkis/Cyan
        $this->RegisterPropertyInteger('NorthMarkerColor', 0xF43F5E); // Rosarot
        $this->RegisterPropertyInteger('GustColor', 0xF59E0B);        // Bernstein/Orange
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Stellt sicher, dass Kachel-Visualisierung (Typ 1) aktiv ist
        $this->SetVisualizationType(1);

        // 1. Alle bisherigen Nachrichten deregistrieren
        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        // 2. Alle bisherigen Referenzen deregistrieren
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }

        // 3. Konfigurierte Variablen registrieren & Child-Statusvariablen für Vollbild/Detailansicht pflegen
        $dirID     = $this->ReadPropertyInteger('WindDirectionVariable');
        $speedID   = $this->ReadPropertyInteger('WindSpeedVariable');
        $gustID    = $this->ReadPropertyInteger('GustVariable');
        $prevDirID = $this->ReadPropertyInteger('PreviousDirectionVariable');
        $tempOutID = $this->ReadPropertyInteger('OutdoorTempVariable');
        $tempSecID = $this->ReadPropertyInteger('SecondaryTempVariable');
        $rainID    = $this->ReadPropertyInteger('RainVariable');
        $batteryID = $this->ReadPropertyInteger('BatteryVariable');

        $this->MaintainVariable('OutdoorTemperature', $this->Translate('Außentemperatur'), 2, '~Temperature', 1, $tempOutID > 0);
        $this->MaintainVariable('WindSpeed', $this->Translate('Windgeschwindigkeit'), 2, '~WindSpeed.ms', 2, $speedID > 0);
        $this->MaintainVariable('Gust', $this->Translate('Böe'), 2, '~WindSpeed.ms', 3, $gustID > 0);
        $this->MaintainVariable('WindDirection', $this->Translate('Windrichtung'), 2, '~WindDirection.Text', 4, $dirID > 0);
        $secLabel = $this->ReadPropertyString('SecondaryTempLabel');
        $this->MaintainVariable('SecondaryTemperature', $secLabel ? $secLabel : $this->Translate('Sekundärtemperatur'), 2, '~Temperature', 5, $tempSecID > 0);
        $this->MaintainVariable('Rain', $this->Translate('Regen'), 0, '~Raining', 6, $rainID > 0);
        $this->MaintainVariable('Battery', $this->Translate('Batterie'), 0, '~Battery.Reversed', 7, $batteryID > 0);

        $sensorVars = [
            $dirID, $speedID, $gustID, $prevDirID, $tempOutID, $tempSecID, $rainID, $batteryID
        ];

        foreach ($sensorVars as $varID) {
            if ($varID > 0 && @IPS_VariableExists($varID)) {
                $this->RegisterReference($varID);
                $this->RegisterMessage($varID, VM_UPDATE);
            }
        }

        // 4. Initialen Zustand berechnen und senden
        $this->SendTileUpdate();
    }

    /**
     * Liefert den persistenten HTML-Frame für die Visualisierung
     */
    public function GetVisualizationTile(): string
    {
        $htmlFile = __DIR__ . '/module.html';
        if (!file_exists($htmlFile)) {
            return '<div style="color:red;padding:10px;">module.html nicht gefunden!</div>';
        }

        // 1. WebSocket-Zustand in IP-Symcon sofort synchronisieren
        $payload = $this->GetWeatherPayload();
        $this->UpdateVisualizationValue(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $html = file_get_contents($htmlFile);

        // 2. HTML-Platzhalter direkt vorab mit echten Werten befüllen (Zero-Delay Rendering auf Frame 0)
        if ($payload['outdoorTempFormatted'] !== '--,-') {
            $html = str_replace('>--,-<', '>' . $payload['outdoorTempFormatted'] . '<', $html);
        }

        if ($payload['secondaryTempFormatted'] !== '--,-' && ($payload['options']['showSecondaryTemp'] ?? true)) {
            $html = str_replace('>--,- °C<', '>' . $payload['secondaryTempFormatted'] . ' °C<', $html);
            if (!empty($payload['secondaryTempLabel'])) {
                $html = str_replace('>Stall<', '>' . htmlspecialchars($payload['secondaryTempLabel']) . '<', $html);
            }
        } elseif (!($payload['options']['showSecondaryTemp'] ?? true) || $payload['secondaryTempFormatted'] === '--,-') {
            $html = str_replace('id="secondaryTempWrap"', 'id="secondaryTempWrap" style="display:none;"', $html);
        }

        $html = str_replace('>0,0 km/h<', '>' . $payload['windSpeedFormatted'] . ' ' . $payload['windSpeedUnit'] . '<', $html);

        if ($payload['gust'] > 0) {
            $html = str_replace('id="gustWrap" style="display:none;"', 'id="gustWrap" style="display:inline;"', $html);
            $html = str_replace('>Böe 0,0<', '>Böe ' . $payload['gustFormatted'] . ' ' . $payload['gustUnit'] . '<', $html);
        }

        if ($payload['options']['showBeaufort'] ?? true) {
            $bftText = $payload['bft'] . ' Bft (' . $payload['bftText'] . ')';
            $html = str_replace('>0 Bft (Windstille)<', '>' . $bftText . '<', $html);
        } else {
            $html = str_replace('id="bftVal"', 'id="bftVal" style="display:none;"', $html);
        }

        // Himmelsrichtung-Badge im Footer vorbefüllen (das statische "N" im Ziffernblatt bleibt unberührt!)
        $html = str_replace('id="shortDir">N</span>', 'id="shortDir">' . htmlspecialchars($payload['cardinal']) . '</span>', $html);
        $cardSub = '(' . round($payload['windDirection']) . '°) · ' . htmlspecialchars($payload['cardinalText']);
        $html = str_replace('id="cardinalSub">(0°) · Nord</span>', 'id="cardinalSub">' . $cardSub . '</span>', $html);

        $needleRot = round($payload['windDirection'], 1);
        $html = str_replace('id="needleGroup" class="needle-transition"', 'id="needleGroup" class="needle-transition" style="transform: rotate(' . $needleRot . 'deg);"', $html);

        // 3. Initiales Datenpaket sofort beim ersten Laden der Kachel übergeben
        $initialData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $script = '<script>window.__INITIAL_DATA__ = ' . $initialData . '; if (typeof handleMessage === "function") { handleMessage(window.__INITIAL_DATA__); }</script>';

        return str_replace('</body>', $script . '</body>', $html);
    }

    /**
     * Event-Handler bei Variablenaktualisierungen
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === VM_UPDATE) {
            $this->SendTileUpdate();
        }
    }

    /**
     * Interaktion / Aktionen aus dem Frontend
     */
    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'refresh':
                $this->SendTileUpdate();
                break;

            default:
                $this->SendDebug('RequestAction', "Unbekannter Ident: $Ident", 0);
                break;
        }
    }

    /**
     * Manuelle Aktualisierungsmethode (z.B. für Console-Aktionsbutton)
     */
    public function RefreshWeather(): void
    {
        $this->SendTileUpdate();
    }

    /**
     * Sendet das JSON-Datenpaket an das Frontend ohne DOM-Rebuild
     */
    public function SendTileUpdate(): void
    {
        $payload = $this->GetWeatherPayload();
        $this->UpdateVisualizationValue(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Child-Variablen für Symcon Vollbild- / Detailansicht synchronisieren
        $tempOutID = $this->ReadPropertyInteger('OutdoorTempVariable');
        if ($tempOutID > 0 && @IPS_VariableExists($tempOutID)) {
            $this->SetValue('OutdoorTemperature', (float)GetValue($tempOutID));
        }
        $speedID = $this->ReadPropertyInteger('WindSpeedVariable');
        if ($speedID > 0 && @IPS_VariableExists($speedID)) {
            $this->SetValue('WindSpeed', (float)GetValue($speedID));
        }
        $gustID = $this->ReadPropertyInteger('GustVariable');
        if ($gustID > 0 && @IPS_VariableExists($gustID)) {
            $this->SetValue('Gust', (float)GetValue($gustID));
        }
        $dirID = $this->ReadPropertyInteger('WindDirectionVariable');
        if ($dirID > 0 && @IPS_VariableExists($dirID)) {
            $this->SetValue('WindDirection', (float)GetValue($dirID));
        }
        $tempSecID = $this->ReadPropertyInteger('SecondaryTempVariable');
        if ($tempSecID > 0 && @IPS_VariableExists($tempSecID)) {
            $this->SetValue('SecondaryTemperature', (float)GetValue($tempSecID));
        }
        $rainID = $this->ReadPropertyInteger('RainVariable');
        if ($rainID > 0 && @IPS_VariableExists($rainID)) {
            $rawRain = GetValueBoolean($rainID);
            $varName = IPS_GetName($rainID);
            $isRaining = (stripos($varName, 'trocken') !== false) ? !$rawRain : $rawRain;
            $this->SetValue('Rain', $isRaining);
        }
        $batteryID = $this->ReadPropertyInteger('BatteryVariable');
        if ($batteryID > 0 && @IPS_VariableExists($batteryID)) {
            $this->SetValue('Battery', (bool)GetValue($batteryID));
        }
    }

    /**
     * Liest alle konfigurierten Sensoren aus und formatiert die Daten
     */
    protected function GetWeatherPayload(): array
    {
        $dirID       = $this->ReadPropertyInteger('WindDirectionVariable');
        $speedID     = $this->ReadPropertyInteger('WindSpeedVariable');
        $gustID      = $this->ReadPropertyInteger('GustVariable');
        $prevDirID   = $this->ReadPropertyInteger('PreviousDirectionVariable');
        $tempOutID   = $this->ReadPropertyInteger('OutdoorTempVariable');
        $tempSecID   = $this->ReadPropertyInteger('SecondaryTempVariable');
        $rainID      = $this->ReadPropertyInteger('RainVariable');
        $batteryID   = $this->ReadPropertyInteger('BatteryVariable');

        // Windrichtung
        $degrees = 0.0;
        if ($dirID > 0 && @IPS_VariableExists($dirID)) {
            $degrees = (float)GetValue($dirID);
        }

        // Vorherige Windrichtung
        $prevDegrees = null;
        if ($prevDirID > 0 && @IPS_VariableExists($prevDirID)) {
            $prevDegrees = (float)GetValue($prevDirID);
        }

        // 16-teilige Windrichtung berechnen
        $cardinalKeys = array_keys(self::CARDINALS);
        $normDeg = fmod(fmod($degrees, 360) + 360, 360);
        $idx = (int)round($normDeg / 22.5) % 16;
        $shortDir = $cardinalKeys[$idx] ?? 'N';
        $longDir  = self::CARDINALS[$shortDir] ?? 'Nord';

        // Windgeschwindigkeit
        $speedMS = 0.0;
        if ($speedID > 0 && @IPS_VariableExists($speedID)) {
            $speedMS = (float)GetValue($speedID);
        }
        $speedKMH = round($speedMS * 3.6, 1);
        $speedKN  = round($speedMS * 1.94384, 1);

        // Böen
        $gustMS = 0.0;
        if ($gustID > 0 && @IPS_VariableExists($gustID)) {
            $gustMS = (float)GetValue($gustID);
        }
        $gustKMH = round($gustMS * 3.6, 1);

        // Beaufort berechnen
        $beaufort = 0;
        foreach (self::BEAUFORT_THRESHOLDS as $b => $th) {
            if ($speedMS > $th) {
                $beaufort = $b + 1;
            }
        }
        $beaufortName = self::BEAUFORT_NAMES[$beaufort] ?? 'Windstille';

        // Außentemperatur
        $outdoorTempFormatted = '--,-';
        $outdoorTempVal = null;
        if ($tempOutID > 0 && @IPS_VariableExists($tempOutID)) {
            $val = GetValue($tempOutID);
            $outdoorTempVal = (float)$val;
            $outdoorTempFormatted = number_format($outdoorTempVal, 1, ',', '.');
        }

        // Sekundärtemperatur (z.B. Kaninchenstall)
        $secTempFormatted = '--,-';
        $secTempVal = null;
        if ($tempSecID > 0 && @IPS_VariableExists($tempSecID)) {
            $val2 = GetValue($tempSecID);
            $secTempVal = (float)$val2;
            $secTempFormatted = number_format($secTempVal, 1, ',', '.');
        }

        // Status-Indikatoren
        $rainStatus = null; // 'dry', 'rain', null
        if ($rainID > 0 && @IPS_VariableExists($rainID)) {
            $rawRain = GetValueBoolean($rainID);
            $varName = IPS_GetName($rainID);
            // TFA Sensor "Aktuell trocken": True = trocken (kein Regen), False = Regen
            $isRaining = (stripos($varName, 'trocken') !== false) ? !$rawRain : $rawRain;
            $rainStatus = $isRaining ? 'rain' : 'dry';
        }

        // Batteriestatus
        $batteryOk = true;
        if ($batteryID > 0 && @IPS_VariableExists($batteryID)) {
            $batteryOk = GetValueBoolean($batteryID);
        }

        $unit = $this->ReadPropertyString('WindUnit');
        $speedFormatted = match($unit) {
            'ms'    => number_format($speedMS, 1, ',', '.'),
            'kn'    => number_format($speedKN, 1, ',', '.'),
            default => number_format($speedKMH, 1, ',', '.')
        };
        $speedUnitText = match($unit) {
            'ms'    => 'm/s',
            'kn'    => 'kn',
            default => 'km/h'
        };

        $gustFormatted = match($unit) {
            'ms'    => number_format($gustMS, 1, ',', '.'),
            'kn'    => number_format($gustKN, 1, ',', '.'),
            default => number_format($gustKMH, 1, ',', '.')
        };

        $needleColorHex = sprintf('#%06X', $this->ReadPropertyInteger('NeedleColor'));
        $northColorHex  = sprintf('#%06X', $this->ReadPropertyInteger('NorthMarkerColor'));
        $gustColorHex   = sprintf('#%06X', $this->ReadPropertyInteger('GustColor'));

        return [
            // Wind
            'windDirection'       => round($degrees, 1),
            'prevDirection'       => $prevDegrees !== null ? round($prevDegrees, 1) : null,
            'cardinal'            => $shortDir,
            'cardinalText'        => $longDir,
            'windSpeed'           => $speedMS,
            'windSpeedFormatted'  => $speedFormatted,
            'windSpeedUnit'       => $speedUnitText,
            'gust'                => $gustMS,
            'gustFormatted'       => $gustFormatted,
            'gustUnit'            => $speedUnitText,
            'bft'                 => $beaufort,
            'bftText'             => $beaufortName,

            // Temperaturen
            'outdoorTemp'         => $outdoorTempVal,
            'outdoorTempFormatted'=> $outdoorTempFormatted,
            'secondaryTemp'       => $secTempVal,
            'secondaryTempFormatted' => $secTempFormatted,
            'secondaryTempLabel'  => $this->ReadPropertyString('SecondaryTempLabel'),

            // Status
            'rain'                => ($rainStatus === 'rain'),
            'rainText'            => ($rainStatus === 'rain') ? 'Regen' : 'Trocken',
            'battery'             => $batteryOk,
            'batteryText'         => $batteryOk ? 'Batterie OK' : 'Batterie schwach',

            // Farben & Optionen
            'colors' => [
                'needle'       => $needleColorHex,
                'needleBright' => '#14F1CD',
                'north'        => $northColorHex,
                'gust'         => $gustColorHex
            ],
            'options' => [
                'showBeaufort'          => $this->ReadPropertyBoolean('ShowBeaufort'),
                'showSecondaryTemp'     => $this->ReadPropertyBoolean('ShowSecondaryTemp'),
                'showRainStatus'        => $this->ReadPropertyBoolean('ShowRainStatus'),
                'showBatteryStatus'     => $this->ReadPropertyBoolean('ShowBatteryStatus'),
                'showPreviousDirection' => $this->ReadPropertyBoolean('ShowPreviousDirection')
            ],

            // Legacy keys für Rückwärtskompatibilität
            'degrees'             => round($degrees, 1),
            'prevDegrees'         => $prevDegrees !== null ? round($prevDegrees, 1) : null,
            'cardinalShort'       => $shortDir,
            'cardinalLong'        => $longDir,
            'speedKMH'            => number_format($speedKMH, 1, ',', '.'),
            'speedMS'             => number_format($speedMS, 1, ',', '.'),
            'gustKMH'             => number_format($gustKMH, 1, ',', '.'),
            'rainStatus'          => $rainStatus,
            'batteryOk'           => $batteryOk
        ];
    }
}
