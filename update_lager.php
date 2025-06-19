<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(60);

// Zugangsdaten aus GitHub Secrets
$apiKey = getenv("BILLBEE_API_KEY");
$user = getenv("BILLBEE_USER");
$pass = getenv("BILLBEE_PASS");
$csvUrl = getenv("CSV_URL");

// Debug-Ausgabe
echo "🧪 CSV_URL geladen als: '" . $csvUrl . "'\n";
if (!$csvUrl) {
    echo "❌ CSV_URL ist leer oder wurde nicht geladen!\n";
    exit(1);
}

$baseUrl = "https://app.billbee.io/api/v1/products";
$updateStockUrl = "https://app.billbee.io/api/v1/products/updatestock";
$standardLagerId = 400000000027869;

function billbeeProdukteLaden($apiKey, $user, $pass) {
    $headers = [
        "Accept: application/json",
        "X-Billbee-Api-Key: $apiKey",
        "Authorization: Basic " . base64_encode("$user:$pass")
    ];
    $alleProdukte = [];
    $page = 1;

    echo "📦 Lade Artikel aus Billbee ...\n";

    while (true) {
        $url = "$GLOBALS[baseUrl]?page=$page";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['Data']) || empty($data['Data'])) {
            break;
        }

        $alleProdukte = array_merge($alleProdukte, $data['Data']);
        $page++;
    }

    echo "✅ " . count($alleProdukte) . " Artikel erfolgreich geladen.\n";
    return $alleProdukte;
}

function csvLaden($csvUrl) {
    echo "📥 Lade CSV von Dropbox ...\n";
    $csv = file_get_contents($csvUrl);
    if (!$csv) {
        echo "❌ CSV konnte nicht geladen werden.\n";
        return [];
    }

    $lines = explode("\n", trim($csv));
    $header = str_getcsv(array_shift($lines), ";");

    $artikelIndex = array_search("Artikelnummer", $header);
    $verfuegbarIndex = array_search("Verfügbar", $header);

    $lieferdaten = [];

    foreach ($lines as $line) {
        $row = str_getcsv($line, ";");
        $sku = strtoupper(trim($row[$artikelIndex] ?? ''));
        $menge = isset($row[$verfuegbarIndex]) ? (int)floatval(str_replace(",", ".", $row[$verfuegbarIndex])) : 0;
        if ($sku !== '') {
            $lieferdaten[$sku] = $menge;
        }
    }

    echo "📊 " . count($lieferdaten) . " Artikel aus CSV geladen.\n";
    return $lieferdaten;
}

function aktualisiereBestand($produkte, $csv, $apiKey, $user, $pass, $lagerId) {
    $headers = [
        "Accept: application/json",
        "X-Billbee-Api-Key: $apiKey",
        "Authorization: Basic " . base64_encode("$user:$pass")
    ];

    echo "🔄 Aktualisiere Lagerbestände ...\n";
    $aktualisiert = 0;

    foreach ($produkte as $i => $p) {
        $sku = strtoupper(trim($p['SKU'] ?? ''));

        if ($i < 10) {
            echo "🔍 SKU aus Billbee: $sku\n";
            echo isset($csv[$sku]) ? "✅ In CSV gefunden\n" : "❌ Nicht in CSV\n";
        }

        if (!$sku || !isset($csv[$sku])) {
            continue;
        }

        $bestand = max(0, min(20, $csv[$sku] - 2));

        $payload = json_encode([
            "Sku" => $sku,
            "StockId" => $lagerId,
            "NewQuantity" => $bestand,
            "ForceSendStockToShops" => true,
            "AutosubtractReservedAmount" => true
        ]);

        $ch = curl_init($GLOBALS[updateStockUrl]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ["Content-Type: application/json"]));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_exec($ch);
        curl_close($ch);

        echo "✅ Bestand gesetzt für SKU $sku: $bestand\n";
        $aktualisiert++;
    }

    echo "\n🔁 Insgesamt $aktualisiert Artikel aktualisiert.\n";
}

$produkte = billbeeProdukteLaden($apiKey, $user, $pass);
$csv = csvLaden($csvUrl);
aktualisiereBestand($produkte, $csv, $apiKey, $user, $pass, $standardLagerId);
