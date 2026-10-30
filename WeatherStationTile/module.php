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
        $this->RegisterPropertyBoolean('ShowBatteryStatus', true);
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

        // 3. Konfigurierte Variablen registrieren
        $sensorVars = [
            $this->ReadPropertyInteger('WindDirectionVariable'),
            $this->ReadPropertyInteger('WindSpeedVariable'),
            $this->ReadPropertyInteger('GustVariable'),
            $this->ReadPropertyInteger('PreviousDirectionVariable'),
            $this->ReadPropertyInteger('OutdoorTempVariable'),
            $this->ReadPropertyInteger('SecondaryTempVariable'),
            $this->ReadPropertyInteger('RainVariable'),
            $this->ReadPropertyInteger('BatteryVariable')
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

        $html = file_get_contents($htmlFile);

        // Initiales Datenpaket sofort beim ersten Laden der Kachel übergeben
        $initialData = json_encode($this->GetWeatherPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        // Regen-/Trocken-Status
        $rainStatus = null; // 'dry', 'rain', null
        if ($rainID > 0 && @IPS_VariableExists($rainID)) {
            $rainVal = GetValueBoolean($rainID);
            // TFA Sensor "Aktuell trocken": True = trocken, False = nass/regen
            $rainStatus = $rainVal ? 'dry' : 'rain';
        }

        // Batteriestatus
        $batteryOk = true;
        if ($batteryID > 0 && @IPS_VariableExists($batteryID)) {
            $batteryVal = GetValueBoolean($batteryID);
            // True = Batterie in Ordnung
            $batteryOk = $batteryVal;
        }

        return [
            // Metadaten & Konfiguration
            'title'               => $this->ReadPropertyString('TileTitle'),
            'windUnit'            => $this->ReadPropertyString('WindUnit'),
            'showBeaufort'        => $this->ReadPropertyBoolean('ShowBeaufort'),
            'showSecondaryTemp'   => $this->ReadPropertyBoolean('ShowSecondaryTemp'),
            'secondaryTempLabel'  => $this->ReadPropertyString('SecondaryTempLabel'),
            'showRainStatus'      => $this->ReadPropertyBoolean('ShowRainStatus'),
            'showBatteryStatus'   => $this->ReadPropertyBoolean('ShowBatteryStatus'),
            'showPreviousDirection' => $this->ReadPropertyBoolean('ShowPreviousDirection'),

            // Farben (Hex)
            'needleColor'         => sprintf('#%06X', $this->ReadPropertyInteger('NeedleColor')),
            'northColor'          => sprintf('#%06X', $this->ReadPropertyInteger('NorthMarkerColor')),
            'gustColor'           => sprintf('#%06X', $this->ReadPropertyInteger('GustColor')),

            // Winddaten
            'degrees'             => round($degrees, 1),
            'prevDegrees'         => $prevDegrees !== null ? round($prevDegrees, 1) : null,
            'cardinalShort'       => $shortDir,
            'cardinalLong'        => $longDir,
            'speedMS'             => number_format($speedMS, 1, ',', '.'),
            'speedKMH'            => number_format($speedKMH, 1, ',', '.'),
            'speedKN'             => number_format($speedKN, 1, ',', '.'),
            'gustMS'              => number_format($gustMS, 1, ',', '.'),
            'gustKMH'             => number_format($gustKMH, 1, ',', '.'),
            'gustActive'          => ($gustKMH > 0),
            'beaufort'            => $beaufort,
            'beaufortName'        => $beaufortName,

            // Temperaturdaten
            'outdoorTemp'         => $outdoorTempFormatted,
            'secondaryTemp'       => $secTempFormatted,

            // Status-Indikatoren
            'rainStatus'          => $rainStatus,
            'batteryOk'           => $batteryOk
        ];
    }
}
