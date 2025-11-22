<?php

include 'includes/config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php?role=doctor");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Get doctor name
$doctorName = '';
$doctorStmt = $conn->prepare("SELECT name FROM doctors WHERE id = ?");
$doctorStmt->bind_param("i", $doctor_id);
$doctorStmt->execute();
$doctorResult = $doctorStmt->get_result();
if ($doctorRow = $doctorResult->fetch_assoc()) {
    $doctorName = $doctorRow['name'];
}

// Register patient
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pname = $_POST['pname'];
    $pemail = $_POST['pemail'];
    $ppass = password_hash($_POST['ppass'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO patients (doctor_id, name, email, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $doctor_id, $pname, $pemail, $ppass);
    $stmt->execute();

    // Redirect to avoid resubmission on refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Patient list
$patientListStmt = $conn->prepare("SELECT id, name FROM patients WHERE doctor_id = ?");
$patientListStmt->bind_param("i", $doctor_id);
$patientListStmt->execute();
$patientListResult = $patientListStmt->get_result();

$patientList = [];
while ($row = $patientListResult->fetch_assoc()) {
    $patientList[] = $row;
}

$firstPatientId = !empty($patientList) ? $patientList[0]['id'] : null;

// Get selected patient ID from GET parameter, fallback to first patient if none
$selectedPatientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : $firstPatientId;

// Logs for table filtered by patient if selected
if ($selectedPatientId) {
    $sql = "SELECT patients.name AS pname, emotion_logs.emotion, emotion_logs.recorded_at 
            FROM emotion_logs 
            JOIN patients ON emotion_logs.patient_id = patients.id 
            WHERE patients.doctor_id = ? AND patients.id = ?
            ORDER BY emotion_logs.recorded_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $doctor_id, $selectedPatientId);
} else {
    $sql = "SELECT patients.name AS pname, emotion_logs.emotion, emotion_logs.recorded_at 
            FROM emotion_logs 
            JOIN patients ON emotion_logs.patient_id = patients.id 
            WHERE patients.doctor_id = ?
            ORDER BY emotion_logs.recorded_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
}
$stmt->execute();
$logs = $stmt->get_result();

// Emotion data grouped (for all patients but we'll use it by patient in JS)
$emotionDataStmt = $conn->prepare("
    SELECT patients.id AS patient_id, emotion_logs.emotion, COUNT(*) as count
    FROM emotion_logs
    JOIN patients ON emotion_logs.patient_id = patients.id
    WHERE patients.doctor_id = ?
    GROUP BY patients.id, emotion_logs.emotion
");
$emotionDataStmt->bind_param("i", $doctor_id);
$emotionDataStmt->execute();
$emotionDataResult = $emotionDataStmt->get_result();

$emotionChartData = [];
while ($row = $emotionDataResult->fetch_assoc()) {
    $pid = $row['patient_id'];
    if (!isset($emotionChartData[$pid])) {
        $emotionChartData[$pid] = [];
    }
    $emotionChartData[$pid][$row['emotion']] = (int)$row['count'];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jsPDF & html2canvas for PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f4f6f9;
        }

        h2, h3 {
            color: #333;
        }

        .doctor-name {
            color: #007bff;
            font-weight: bold;
            text-shadow: 0 0 4px rgba(0, 123, 255, 0.6);
        }

        form, .chart-box, .table-box {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        input, select, button {
            padding: 8px 12px;
            margin: 5px 0;
            width: 100%;
            max-width: 400px;
        }

        button {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #e9f5ff;
        }

        .dashboard-layout {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .left-column, .right-column {
            flex: 1;
            min-width: 300px;
        }

        canvas {
            max-width: 100%;
        }

        /* Download PDF button alignment */
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<h2>Welcome <span class="doctor-name">Dr. <?= htmlspecialchars($doctorName) ?></span></h2>

<div class="dashboard-layout">

    <div class="left-column">

        <div class="table-box">
            <h3>Register New Patient</h3>
            <form method="POST">
                <input type="text" name="pname" placeholder="Patient Name" required><br>
                <input type="email" name="pemail" placeholder="Email" required><br>
                <input type="password" name="ppass" placeholder="Password" required><br>
                <button type="submit">Register Patient</button>
            </form>
        </div>

        <div class="table-box">
            <h3>List of Patients</h3>
            <ul>
                <?php foreach ($patientList as $p): ?>
                    <li><?= htmlspecialchars($p['name']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="table-box">
            <div class="table-header">
                <h3>Emotion Records of Your Patients</h3>
                <button class="btn btn-outline-primary btn-sm" onclick="downloadPDF()">Download PDF</button>
            </div>

            <table border="1" cellpadding="5" id="logsTable">
                <tr>
                    <th>Patient Name</th>
                    <th>Emotion</th>
                    <th>Time</th>
                </tr>
                <?php while ($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['pname']) ?></td>
                        <td><?= htmlspecialchars($row['emotion']) ?></td>
                        <td><?= htmlspecialchars($row['recorded_at']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>

    </div>

    <div class="right-column">

        <div class="chart-box">
            <h3>Emotion Distribution</h3>
            <form method="GET" id="filterForm">
                <label for="patientSelect">Filter by Patient:</label>
                <select name="patient_id" id="patientSelect" onchange="document.getElementById('filterForm').submit()">
                    <option value="">-- All Patients --</option>
                    <?php foreach ($patientList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $selectedPatientId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <canvas id="emotionPieChart" width="400" height="400"></canvas>
        </div>

    </div>

</div>

<script>
const emotionData = <?= json_encode($emotionChartData) ?>;
const ctx = document.getElementById('emotionPieChart').getContext('2d');
let pieChart;

function updatePieChart(pid) {
    if (!pid || !emotionData[pid]) {
        if (pieChart) pieChart.destroy();
        return;
    }

    const data = emotionData[pid];
    const labels = Object.keys(data);
    const counts = Object.values(data);

    if (pieChart) pieChart.destroy();

    pieChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: [
                    '#f94144', '#f3722c', '#f9c74f', '#90be6d',
                    '#43aa8b', '#577590', '#577590'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                title: {
                    display: true,
                    text: 'Emotion Distribution'
                }
            }
        }
    });
}

// Download PDF function using jsPDF + html2canvas
async function downloadPDF() {
    const { jsPDF } = window.jspdf;
    const element = document.getElementById("logsTable");

    try {
        const canvas = await html2canvas(element, { scale: 2 });
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF();

        const imgProps = pdf.getImageProperties(imgData);
        const pdfWidth = pdf.internal.pageSize.getWidth() - 20;
        const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

        pdf.text("Emotion Records of Your Patients", 10, 10);
        pdf.addImage(imgData, 'PNG', 10, 15, pdfWidth, pdfHeight);
        pdf.save("doctor_emotion_logs.pdf");
    } catch (error) {
        alert("Error generating PDF: " + error.message);
    }
}

// Initial render
window.onload = function () {
    const selectedPatientId = "<?= $selectedPatientId ?>";
    if (selectedPatientId) {
        updatePieChart(selectedPatientId);
    } else {
        if (pieChart) pieChart.destroy();
    }

    document.getElementById('patientSelect').addEventListener('change', function () {
        updatePieChart(this.value);
    });
};
</script>

</body>
</html>
