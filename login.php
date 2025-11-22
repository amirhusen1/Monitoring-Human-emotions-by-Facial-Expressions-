<?php
session_start();
include 'includes/config.php';

$role = $_GET['role'] ?? 'patient';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $table = ($role === 'doctor') ? 'doctors' : 'patients';
    $sql = "SELECT * FROM $table WHERE email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $role;
        header("Location: " . ($role === 'doctor' ? 'doctor_dashboard.php' : 'patient_dashboard.php'));
        exit();
    } else {
        $error = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= ucfirst($role) ?> Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<h2><?= ucfirst($role) ?> Login</h2>
<?php if ($error): ?><p style="color:red"><?= $error ?></p><?php endif; ?>
<form method="POST">
    <input type="email" name="email" placeholder="Email" required /><br>
    <input type="password" name="password" placeholder="Password" required /><br>
    <button type="submit">Login</button>
</form>
<a href="index.php">Back</a>
</body>
</html>
