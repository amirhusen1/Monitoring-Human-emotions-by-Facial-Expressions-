<?php
include 'includes/header.php';
include 'includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php?role=patient");
    exit();
}

$patient_id = $_SESSION['user_id'] ?? null;
if (!$patient_id) {
    die("❌ Session patient ID not found. Please login again.");
}

// Fetch patient name
$patientName = '';
$stmt = $conn->prepare("SELECT name FROM patients WHERE id = ?");
if (!$stmt) {
    die("❌ Prepare failed (patient name): " . $conn->error);
}
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $patientName = $row['name'];
} else {
    die("❌ Patient not found with ID = $patient_id");
}

// Fetch emotion logs
$emotionLogs = [];
$query = "SELECT emotion, recorded_at FROM emotion_logs WHERE patient_id = ? ORDER BY recorded_at DESC";
$stmtLogs = $conn->prepare($query);
if (!$stmtLogs) {
    die("❌ Prepare failed (logs): " . $conn->error . "<br>Query: " . $query);
}
$stmtLogs->bind_param("i", $patient_id);
$stmtLogs->execute();
$resultLogs = $stmtLogs->get_result();
while ($log = $resultLogs->fetch_assoc()) {
    $emotionLogs[] = $log;
}

// Emotion pie chart data
$emotionData = [];
$chartQuery = "SELECT emotion, COUNT(*) AS count FROM emotion_logs WHERE patient_id = ? GROUP BY emotion";
$chartStmt = $conn->prepare($chartQuery);
if (!$chartStmt) {
    die("❌ Prepare failed (chart): " . $conn->error);
}
$chartStmt->bind_param("i", $patient_id);
$chartStmt->execute();
$chartResult = $chartStmt->get_result();
while ($row = $chartResult->fetch_assoc()) {
    $emotionData[$row['emotion']] = (int)$row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        body { background-color: #f8f9fa; }
        .header-title { text-align: center; margin: 30px 0 20px; font-size: 28px; font-weight: bold; color: #007bff; }
        .dashboard-wrapper { display: flex; flex-wrap: wrap; gap: 20px; }
        .left-section, .right-section { flex: 1; min-width: 300px; }
        #webcam { width: 100%; border-radius: 12px; border: 3px solid #007BFF; }
        #emotionResult { font-size: 18px; margin-top: 10px; color: #007BFF; }
        .logs-table { margin-top: 10px; }
        .chart-container { margin-top: 40px; }
        canvas { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }

        .emotion-indicator {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 12px;
            background-color: #fff;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 10px;
        }

        #indicatorEmoji {
            font-size: 50px;
        }

        #indicatorText {
            font-size: 18px;
            font-weight: 500;
            color: #333;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-title">
        Welcome, <?= htmlspecialchars($patientName) ?>
    </div>

    <div class="dashboard-wrapper">
        <!-- Left Section: Webcam and Detection -->
        <div class="left-section">
            <!-- Emotion Indicator -->
            <div class="emotion-indicator">
                <div id="indicatorEmoji">🙂</div>
                <div id="indicatorText">Waiting for detection...</div>
            </div>

            <video id="webcam" autoplay muted playsinline></video>
            <button id="detectButton" class="btn btn-primary mt-3 w-100" onclick="toggleDetection()">Start Detection</button>
            <p id="emotionResult">Detection stopped.</p>
        </div>

        <!-- Right Section: Logs Table -->
        <div class="right-section">
            <div class="table-header">
                <h5>Your Emotion Logs</h5>
                <button class="btn btn-outline-primary btn-sm" onclick="downloadPDF()">Download PDF</button>
            </div>

            <?php if (!empty($emotionLogs)): ?>
                <table class="table table-bordered table-striped logs-table" id="logsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Emotion</th>
                            <th>Detected At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emotionLogs as $index => $log): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($log['emotion']) ?></td>
                                <td><?= htmlspecialchars($log['recorded_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No logs yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pie Chart Section -->
    <div class="chart-container">
        <h5 class="text-center">Your Emotion Distribution</h5>
        <canvas id="emotionPieChart" height="300"></canvas>
    </div>
</div>

<script>
    let isDetecting = false;

    function downloadPDF() {
        const { jsPDF } = window.jspdf;
        const element = document.getElementById("logsTable");

        html2canvas(element, { scale: 2 }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF();
            const imgProps = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

            pdf.text("Patient Emotion Logs", 14, 15);
            pdf.addImage(imgData, 'PNG', 10, 20, pdfWidth - 20, pdfHeight);
            pdf.save("emotion_logs.pdf");
        }).catch(err => {
            alert("Error generating PDF: " + err.message);
        });
    }

    function toggleDetection() {
        const button = document.getElementById("detectButton");
        isDetecting = !isDetecting;

        if (isDetecting) {
            button.innerText = "Stop Detection";
            captureAndSend(); // Start loop
        } else {
            button.innerText = "Start Detection";
            document.getElementById("emotionResult").innerText = "Detection stopped.";
        }
    }

    function captureAndSend() {
        if (!isDetecting) return;

        const video = document.getElementById('webcam');

        if (!video.videoWidth || !video.videoHeight) {
            setTimeout(captureAndSend, 1000); // Retry if video not ready
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = canvas.toDataURL('image/jpeg');

        document.getElementById("emotionResult").innerText = "Analyzing...";

        fetch('upload_frame.php', {
            method: 'POST',
            body: JSON.stringify({ image: imageData }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.emotion) {
                const emotion = data.emotion.toLowerCase();
                document.getElementById("emotionResult").innerText = "Detected Emotion: " + data.emotion;

                // Update real-time indicator
                const emojiMap = {
                    happy: "😄",
                    sad: "😢",
                    angry: "😠",
                    surprised: "😲",
                    neutral: "😐",
                    disgust: "🤢",
                    fear: "😱"
                };

                const emoji = emojiMap[emotion] || "🙂";
                document.getElementById("indicatorEmoji").innerText = emoji;
                document.getElementById("indicatorText").innerText = data.emotion;
            } else {
                document.getElementById("emotionResult").innerText = "Error: " + (data.error || "Unknown error");
            }

            if (isDetecting) {
                setTimeout(captureAndSend, 5000); // Repeat every 5 sec
            }
        })
        .catch(err => {
            document.getElementById("emotionResult").innerText = "Error: " + err.message;
            if (isDetecting) {
                setTimeout(captureAndSend, 5000);
            }
        });
    }

    function startWebcam() {
        const video = document.getElementById('webcam');
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    video.play();
                };
            })
            .catch(err => {
                alert("Webcam access denied or failed: " + err.message);
            });
    }

    window.onload = function () {
        startWebcam();

        const chartData = <?= json_encode($emotionData) ?>;
        const ctx = document.getElementById('emotionPieChart').getContext('2d');
        const labels = Object.keys(chartData);
        const values = Object.values(chartData);

        if (labels.length > 0) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#f94144', '#f3722c', '#f9c74f', '#90be6d', '#43aa8b']
                    }]
                },
                options: {
                    plugins: {
                        title: { display: false },
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    };
</script>

</body>
</html>
