<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'connection.php';

// Validate input function
function validateInput($input) {
    return htmlspecialchars(trim($input));
}

// Handle booking insertion
if (isset($_POST['book_room'])) {
    $roomId = validateInput($_POST['room_id']);
    $studentId = $_SESSION['user_id'];
    $timeslotId = validateInput($_POST['timeslot_id']);
    $bookingDate = validateInput($_POST['booking_date']);
    $bookingStartTime = validateInput($_POST['booking_start_time']);
    $bookingEndTime = validateInput($_POST['booking_end_time']);

    if ($roomId && $timeslotId && $bookingDate && $bookingStartTime && $bookingEndTime) {
        try {
            $db->beginTransaction();

            // Insert a new booking record
            $stmt = $db->prepare("
                INSERT INTO bookings (room_id, student_id, timeslot_id, booking_date, status, created_at, booking_start_time, booking_end_time) 
                VALUES (?, ?, ?, ?, 'pending', NOW(), ?, ?)
            ");
            $stmt->execute([$roomId, $studentId, $timeslotId, $bookingDate, $bookingStartTime, $bookingEndTime]);

            // Mark the timeslot as not available
            $stmt = $db->prepare("UPDATE timeslots SET is_available = 0 WHERE id = ?");
            $stmt->execute([$timeslotId]);

            $db->commit();
            $success = 'Booking created successfully!';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error creating booking: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid booking details provided.';
    }
}

// Handle booking cancellation
if (isset($_POST['cancel_booking'])) {
    $bookingId = validateInput($_POST['booking_id']);
    if ($bookingId) {
        try {
            $db->beginTransaction();

            // Get timeslot_id before cancelling
            $stmt = $db->prepare("SELECT timeslot_id FROM bookings WHERE id = ? AND student_id = ?");
            $stmt->execute([$bookingId, $_SESSION['user_id']]);
            $timeslotId = $stmt->fetchColumn();

            if ($timeslotId) {
                // Update booking status
                $stmt = $db->prepare("
                    UPDATE bookings 
                    SET status = 'cancelled' 
                    WHERE id = ? AND student_id = ? AND status = 'pending'
                ");
                $stmt->execute([$bookingId, $_SESSION['user_id']]);

                // Make timeslot available again
                $stmt = $db->prepare("UPDATE timeslots SET is_available = 1 WHERE id = ?");
                $stmt->execute([$timeslotId]);

                $db->commit();
                $success = 'Booking cancelled successfully!';
            } else {
                $error = 'Booking not found or already cancelled.';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error cancelling booking: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid booking ID.';
    }
}

// Fetch user's bookings with divided timeslots
$stmt = $db->prepare("
    SELECT b.*, r.name as room_name, 
           t.start_time, t.end_time,
           b.booking_start_time, b.booking_end_time
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN timeslots t ON b.timeslot_id = t.id
    WHERE b.student_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            padding-top: 70px;
            height: auto;
            min-height: 100vh;
            overflow-y: auto;
            font-family: "Courier New", Courier, monospace;
        }

        /* Navbar Styling */
        .navbar {
            background-color: #343131 !important;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
            padding: 1rem 0;
        }

        .navbar .navbar-brand {
            color: #EAC696;
            font-weight: bold;
            font-family: "Courier New", Courier, monospace;
        }

        .navbar .navbar-nav .nav-link {
            color: #fff !important;
        }

        .navbar .navbar-nav .nav-link:hover {
            color: #ddd !important;
        }

        .navbar .btn-outline-info {
            color: #FF9800;
            border-color: #17a2b8;
        }

        .navbar .btn-outline-info:hover {
            background-color: #17a2b8;
            border-color: #17a2b8;
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

        table th, table td {
            font-family: "Courier New", Courier, monospace;
        }
    </style>
</head>
<nav class="navbar" style="background-color: #343131 !important;">
    <div class="container">
        <a class="navbar-brand" href="rooms.php" style="color: #EAC696;"><h2>Room Booking</h2></a>
        <div class="d-flex">
            <a href="rooms.php" class="btn me-2">Home</a>
            <a href="profile.php" class="btn me-2">Profile</a>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>
</nav>
<body>
    <div class="container mt-4">
        <h2>My Bookings</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($booking['booking_start_time'] ?? $booking['start_time'])); ?></td>
                        <td>
                            <?php 
                            if (isset($booking['booking_start_time']) && isset($booking['booking_end_time'])) {
                                echo date('h:i A', strtotime($booking['booking_start_time'])) . ' - ' . 
                                     date('h:i A', strtotime($booking['booking_end_time']));
                            } else {
                                echo date('h:i A', strtotime($booking['start_time'])) . ' - ' . 
                                     date('h:i A', strtotime($booking['end_time']));
                            }
                            ?>
                        </td>
                        <td><?php echo ucfirst($booking['status']); ?></td>
                        <td>
                            <?php if ($booking['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                <button type="submit" name="cancel_booking" 
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to cancel this booking?')">
                                    Cancel
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
           

            </table>
        </div>

        <a href="rooms.php" class="btn btn-secondary">Back to Rooms</a>
    </div>
</body>
</html>
