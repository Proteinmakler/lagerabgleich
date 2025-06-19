<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(60);

// Secrets lesen
$apiKey = getenv("BILLBEE_API_KEY");
$user = getenv("BILLBEE_USER");
$pass = getenv("BILLBEE_PASS");
$csvUrl = getenv("CSV_URL");

// Debug-Ausgabe
print_r($_ENV); // Zeigt alle verfügbaren Umgebungsvariablen

echo "\n\n🧪 CSV_URL geladen als: '" . $csvUrl . "'\n";
if (!$csvUrl) {
    echo "❌ CSV_URL ist leer oder wurde nicht geladen!\n";
    exit(1);
}

// Falls bis hierher alles klappt, Skript abbrechen – nur Test
exit(0);
