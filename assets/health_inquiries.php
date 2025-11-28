<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Access-Control-Allow-Origin: *");

// -------------------
// 1. Database Connection
// -------------------
$conn = new mysqli("localhost", "root", "", "insurance");
if ($conn->connect_error) {
    die("DB Connection Failed: " . $conn->connect_error);
}

// -------------------
// 2. Fetch & Sanitize Form Data
// -------------------
$name           = $_POST['name'] ?? '';
$mobile         = $_POST['mobile'] ?? '';
$date_of_birth  = $_POST['date_of_birth'] ?? '';   // UPDATED
$postal_code    = $_POST['postal_code'] ?? '';     // UPDATED
$income         = $_POST['income'] ?? '';

$name        = mysqli_real_escape_string($conn, $name);
$mobile      = preg_replace('/\D/', '', $mobile);
$postal_code = mysqli_real_escape_string($conn, $postal_code);
$income      = mysqli_real_escape_string($conn, $income);

// -------------------
// 3. Validations
// -------------------

// Mobile validation
if (strlen($mobile) != 10 || !preg_match('/^[6-9]/', $mobile)) {
    die("Invalid mobile number. Must be 10 digits starting with 6-9.");
}
$mobile_with_code = "+91" . $mobile;

// Age validation
$age = (new DateTime())->diff(new DateTime($date_of_birth))->y;
if ($age < 18) {
    die("You must be 18 or older.");
}

// Postal code validation
if (!preg_match('/^\d{6}$/', $postal_code)) {
    die("Invalid postal code. Must be 6 digits.");
}

// -------------------
// 4. Save to Database
// -------------------
$sql = "INSERT INTO health_inquiries (name, mobile, date_of_birth, postal_code, income)
        VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $name, $mobile_with_code, $date_of_birth, $postal_code, $income);

if ($stmt->execute()) {
    // TeleCRM API
   $api_url = "https://next-api.telecrm.in/v2/enterprise/67f4c4b5acc3c5434969330f/autoupdatelead";
$access_token = "5c9ca84e-8ced-43d4-8398-1e983b90a2af1748583256060:99861e39-480c-46f5-a203-03b49372f2aa";

$payload = [
    "fields" => [
        "name"          => $name,
        "phone"         => $mobile_with_code,
        "date_of_birth" => $date_of_birth,
        "postal_code"   => $postal_code,
        "income"        => $income
    ]
];


    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $access_token"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Log API response
    $logData = "==== API LOG (" . date("Y-m-d H:i:s") . ") ====\n";
    $logData .= "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    $logData .= "HTTP Code: $http_code\n";
    $logData .= "Response: $response\n";
    $logData .= "cURL Error: $curl_error\n\n";
    file_put_contents("api_log.txt", $logData, FILE_APPEND);

    if ($http_code == 200 || $http_code == 201) {
    // ✅ Data saved in Database and pushed to TeleCRM successfully
    echo "<!DOCTYPE html>
<html>
<head>
        <h4>✔️ Form Submitted Successfully</h4>
        <p>You will be redirected shortly...</p>
    <title>Success</title>
</head>
<body>
    
    <script>
        // Redirect after 3 seconds
        setTimeout(function() {
            window.location.href = 'thankyou.html'; // Change to your target page
        }, 1000);
    </script>
</body>
</html>";
    exit();  // Stop PHP execution
} else {
    // ⚠️ Data saved in Database, but API push failed
    echo "⚠️ Data saved in Database, but API push failed.<br>";
    echo "HTTP Code: $http_code<br>";
    echo "Response: $response<br>";
}

} else {
    echo "❌ Database Insert Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
ob_end_flush();  // Send the buffered output
?>
