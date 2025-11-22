<?php
// upload_frame.php
session_start(); // ✅ Step 0: Start session

header('Content-Type: application/json');

// Step 1: Ensure the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$patientId = $_SESSION['user_id'];

// Step 2: Get base64 image from request
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['image'])) {
    echo json_encode(['error' => 'No image data received']);
    exit();
}

// Step 3: Decode the base64 image
$image = $data['image'];
$image = str_replace('data:image/jpeg;base64,', '', $image);
$image = str_replace(' ', '+', $image);
$imageData = base64_decode($image);

// Step 4: Save the image
$imagePath = __DIR__ . '/python/temp.jpg';
if (!file_put_contents($imagePath, $imageData)) {
    echo json_encode(['error' => 'Failed to save image']);
    exit();
}

// Step 5: Run the detector Python script
$pythonPath = 'python';  // or 'C:\\Python310\\python.exe'
$scriptPath = __DIR__ . '/python/detector.py';

$command = escapeshellcmd("$pythonPath $scriptPath") . " 2>&1";
exec($command, $output, $status);

// Optional: Save debug output to file
file_put_contents(__DIR__ . '/debug_output.txt', print_r(['status' => $status, 'output' => $output], true));

// Step 6: Get the detected emotion
$emotion = null;
for ($i = count($output) - 1; $i >= 0; $i--) {
    $line = trim($output[$i]);
    if ($line !== '') {
        $emotion = $line;
        break;
    }
}

// Step 7: If emotion was detected, insert into DB
if ($status === 0 && $emotion !== null) {
    $conn = new mysqli('localhost', 'root', '', 'emotion_detection'); // Modify DB credentials if needed

    if ($conn->connect_error) {
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO emotion_logs (patient_id, emotion, recorded_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $patientId, $emotion);

    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Failed to log emotion']);
        $stmt->close();
        $conn->close();
        exit();
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['emotion' => $emotion]); // ✅ Send result to JS
} else {
    echo json_encode([
        'error' => 'Emotion detection failed',
        'status' => $status,
        'details' => $output
    ]);
}
?>
