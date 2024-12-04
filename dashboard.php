<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'connection.php';

// Fetch summary statistics
$roomCount = $db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$studentCount = $db->query("SELECT COUNT(*) FROM students WHERE user_type != 'admin'")->fetchColumn();
$equipmentCount = $db->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
$timeslotCount = $db->query("SELECT COUNT(*) FROM timeslots")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="container mt-4">
        <h2 class="font">Admin Dashboard</h2>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title font">Total Rooms</h5>
                        <p class="card-text"><?php echo $roomCount; ?></p>
                        <a href="manage_rooms.php" class="btn btn-primary font">Manage Rooms</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title font">Total Students</h5>
                        <p class="card-text"><?php echo $studentCount; ?></p>
                        <a href="manage_students.php" class="btn btn-primary font">Manage Students</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title font">Total Equipment</h5>
                        <p class="card-text"><?php echo $equipmentCount; ?></p>
                        <a href="manage_equipment.php" class="btn btn-primary font">Manage Equipment</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title font">Total Timeslots</h5>
                        <p class="card-text"><?php echo $timeslotCount; ?></p>
                        <a href="manage_timeslots.php" class="btn btn-primary font">Manage Timeslots</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="logout.php" class="btn btn-danger font">Logout</a>
        </div>
    </div>
</body>
</html>