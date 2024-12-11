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

// Fetch all bookings with related details
$stmt = $db->prepare("
    SELECT b.id, b.room_id, b.student_id, b.timeslot_id, b.booking_date, b.status, 
           r.name AS room_name, t.start_time, t.end_time, s.username AS student_username
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN timeslots t ON b.timeslot_id = t.id
    JOIN students s ON b.student_id = s.id
    ORDER BY b.created_at DESC
");
$stmt->execute();
$bookings = $stmt->fetchAll();

// Handle multiple status updates for bookings
if (isset($_POST['update_multiple_status']) && isset($_POST['status']) && !empty($_POST['booking_ids'])) {
    $newStatus = $_POST['status'];
    $bookingIds = $_POST['booking_ids'];

    try {
        $db->beginTransaction();

        foreach ($bookingIds as $bookingId) {
            // Update the booking status
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $bookingId]);

            // Handle room availability changes based on the new status
            if ($newStatus === 'approved') {
                // Mark the timeslot and room as unavailable
                $stmt = $db->prepare("UPDATE timeslots SET is_available = 0 WHERE id = (SELECT timeslot_id FROM bookings WHERE id = ?)");
                $stmt->execute([$bookingId]);

                $stmt = $db->prepare("UPDATE rooms SET is_available = 0 WHERE id = (SELECT room_id FROM bookings WHERE id = ?)");
                $stmt->execute([$bookingId]);
            } elseif ($newStatus === 'cancelled') {
                // Mark the timeslot and room as available again
                $stmt = $db->prepare("UPDATE timeslots SET is_available = 1 WHERE id = (SELECT timeslot_id FROM bookings WHERE id = ?)");
                $stmt->execute([$bookingId]);

                $stmt = $db->prepare("UPDATE rooms SET is_available = 1 WHERE id = (SELECT room_id FROM bookings WHERE id = ?)");
                $stmt->execute([$bookingId]);
            }
        }

        $db->commit();
        $success = 'Booking statuses updated successfully!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error updating booking statuses: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title >Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
    /* Ensure body and container take full height and allow scrolling */
    body {
        padding-top: 20px;
        height: 100vh;
        overflow-y: auto;
    }
    .container {
        max-height: 100%;
        padding-bottom: 20px;
    }

    /* Make table responsive with scrollable overflow */
    .table-responsive {
        max-height: calc(100vh - 400px);
        overflow-y: auto;
    }

    /* Increase width of table and ensure proper spacing for columns */
    table {
        width: 100%;
    }

    /* Increase width of individual columns */
    th, td {
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
    }

    /* Style for status actions */
    .status-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Form-select width adjustment */
    .form-select {
        width: auto;
        display: inline-block;
        margin-bottom: 10px;
    }

    /* Ensure table content is scrollable */
    .table-wrapper {
        overflow-x: auto;
        width: 100%;
    }
</style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="font">Admin Dashboard</h2>
        
        <!-- Summary Cards -->
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
                        <h5 class="card-title font">Total Equipment</h5>
                        <p class="card-text"><?php echo $equipmentCount; ?></p>
                        <a href="equipment.php" class="btn btn-primary font">Manage Equipment</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title font">Total Timeslots</h5>
                        <p class="card-text"><?php echo $timeslotCount; ?></p>
                        <a href="timeslots.php" class="btn btn-primary font">Manage Timeslots</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title font">comments</h5>
                        
                        <a href="comments.php" class="btn btn-primary font">respond</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booked Room Status Section -->
        <div class="mt-5">
            <h3>Booked Room Status</h3>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Form to update multiple bookings' statuses -->
            <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <style>
        body {
            font-family: 'Courier New', Courier, monospace;;
            margin: 0;
            padding: 20px;
            background-color: #f4f7fc;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h2, h3 {
            color: #333;
            font-weight: bold;
            margin-bottom: 20px;
            font-family: 'Courier New', Courier, monospace;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-actions {
            margin-bottom: 20px;
        }

        .status-actions label {
            font-size: 16px;
            margin-right: 10px;
            font-family: 'Courier New', Courier, monospace;
        }

        .status-actions select {
            padding: 8px;
            font-size: 14px;
            margin-right: 10px;
            font-family: 'Courier New', Courier, monospace;
        }

        .status-actions button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            font-size: 14px;
            cursor: pointer;
            border-radius: 5px;
            font-family: 'Courier New', Courier, monospace;
        }

        .status-actions button:hover {
            background-color: #0056b3;
        }

        .table-wrapper {
            margin-top: 20px;
            overflow-x: auto;
            max-height: 500px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #007bff;
            color: white;
            font-size: 14px;
            font-family: 'Courier New', Courier, monospace;
        }

        table td {
            background-color: #f9f9f9;
        }

        table td input[type="checkbox"] {
            transform: scale(1.2);
        }

        .select-all-checkbox {
            margin-right: 10px;
        }

        .logout-button {
            padding: 10px 15px;
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-family: 'Courier New', Courier, monospace;
        }

        .logout-button:hover {
            background-color: #c82333;
        }
        .btn {
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 30px;
            background: linear-gradient(90deg, rgba(255, 111, 97, 0.85), rgba(255, 154, 158, 0.85));
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            min-width: 120px;
        }
    </style>
</head>
<body>
    <div >
        <h2>Admin Dashboard</h2>
        
        <!-- Status Update Form -->
        <form method="POST">
            <div class="status-actions">
                <label for="status">Select Status for Selected Bookings</label>
                <select name="status" id="status">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button type="submit" name="update_multiple_status">Update Status</button>
            </div>
            
            <!-- Table Wrapper -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="select_all" class="select-all-checkbox">
                            </th>
                            <th>Room</th>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><input type="checkbox" name="booking_ids[]" value="<?php echo $booking['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['student_username']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($booking['booking_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])); ?></td>
                            <td><?php echo ucfirst($booking['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Logout Button -->
        <div style="margin-top: 20px;">
            <a href="logout.php" class="logout-button">Logout</a>
        </div>
    </div>

    <script>
        // Select/Deselect all checkboxes
        document.getElementById('select_all').addEventListener('click', function() {
            let checkboxes = document.querySelectorAll('input[name="booking_ids[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = document.getElementById('select_all').checked;
            });
        });
    </script>
</body>
</html>