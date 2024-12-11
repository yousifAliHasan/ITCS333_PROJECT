<?php
session_start();
include 'connection.php';

$roomId = $_GET['id'];
$error = '';
$success = '';

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_room'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    if (!isset($_POST['timeslot_id']) || empty($_POST['timeslot_id'])) {
        $error = 'Please select a timeslot.';
    } else {
        $timeslotId = $_POST['timeslot_id'];

        try {
            // Enable exception handling for PDO
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Start transaction
            $db->beginTransaction();

            // Insert booking into the bookings table
            $stmt = $db->prepare("
                INSERT INTO bookings 
                (room_id, student_id, timeslot_id, booking_date, status, created_at) 
                VALUES 
                (:room_id, :student_id, :timeslot_id, NOW(), :status, NOW())
            ");

            $bookingData = [
                ':room_id' => $roomId,
                ':student_id' => $_SESSION['user_id'],
                ':timeslot_id' => $timeslotId,
                ':status' => 'pending'
            ];

            if ($stmt->execute($bookingData)) {
                // Mark the timeslot as unavailable
                $updateStmt = $db->prepare("
                    UPDATE timeslots 
                    SET is_available = 0 
                    WHERE id = :timeslot_id
                ");
                $updateStmt->execute([':timeslot_id' => $timeslotId]);

                // Commit transaction
                $db->commit();
                $success = 'Booking request submitted successfully!';
            } else {
                throw new Exception("Failed to insert booking.");
            }

        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            $error = 'Error creating booking: ' . $e->getMessage();

            // Log detailed errors for debugging
            error_log("Booking Error: " . $e->getMessage());
        }
    }
}

// Fetch room details
$stmt = $db->prepare("SELECT * FROM rooms WHERE id = :id");
$stmt->execute([':id' => $roomId]);
$room = $stmt->fetch();

// Fetch equipment for the room
$equipmentStmt = $db->prepare("
    SELECT e.name FROM equipment e
    JOIN room_equipment re ON e.id = re.equipment_id
    WHERE re.room_id = :room_id
");
$equipmentStmt->execute([':room_id' => $roomId]);
$equipment = $equipmentStmt->fetchAll();

// Fetch available timeslots
$timeslotStmt = $db->prepare("
    SELECT * FROM timeslots
    WHERE room_id = :room_id AND is_available = 1
");
$timeslotStmt->execute([':room_id' => $roomId]);
$timeslots = $timeslotStmt->fetchAll();

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
    $room_id = $_POST['room_id'];
    $user_id = $_SESSION['user_id'];
    $comment = $_POST['comment'];

    // Insert comment into the database
    $stmt = $db->prepare("INSERT INTO comments (room_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$room_id, $user_id, $comment]);

    // Send a notification (optional, like an email or system message)
    $success = 'Your comment has been submitted and is awaiting approval!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: "Courier New", Courier, monospace;
            padding-top: 70px;
            height: auto;
            min-height: 100vh;
            overflow-y: auto;
        }

        /* Navbar Styling */
        .navbar {
            background-color: #524C42;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
            height: 100px;
        }

        .navbar .navbar-brand {
            color: #EAC696;
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
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

        /* Room details and booking form */
        .rooms-container {
            padding: 2rem;
        }

        .room-card {
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .room-card h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .room-details p {
            margin: 0.5rem 0;
        }

        .equipment-list {
            list-style-type: none;
            padding-left: 0;
        }

        .equipment-list li {
            background-color: #f8f9fa;
            margin: 0.5rem 0;
            padding: 0.5rem;
            border-radius: 4px;
        }

        .booking-form select, .booking-form button {
            width: 100%;
            padding: 0.8rem;
        }

        .comment-section {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .comment-section h4 {
            font-size: 1.6rem;
        }

        .form-control {
            margin-bottom: 1rem;
        }

        /* Alerts */
        .alert {
            margin-top: 1rem;
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
    <nav class="navbar navbar-expand-lg" style="background-color: #343131;">
        <div class="container">
            <a class="navbar-brand" href="rooms.php"><h2>Room Booking</h2></a>
            <div class="d-flex">
                <a href="rooms.php" class="btn btn-outline-primary me-2">Home</a>
                <a href="my_bookings.php" class="btn btn-outline-success me-2">My Bookings</a>
                <a href="profile.php" class="btn btn-primary">Profile</a>
                <a href="logout.php" class="btn btn-danger font">Logout</a>
            </div>
        </div>
    </nav>

    <div class="rooms-container">
        <div class="room-card">
            <h3><?php echo htmlspecialchars($room['name']); ?></h3>
            <div class="room-details">
                <p><strong>Capacity:</strong> <?php echo $room['capacity']; ?> people</p>
                <p><?php echo htmlspecialchars($room['description']); ?></p>
            </div>

            <h4>Equipment</h4>
            <ul class="equipment-list">
                <?php foreach ($equipment as $item): ?>
                    <li><?php echo htmlspecialchars($item['name']); ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="booking-section">
                <h4>Available Timeslots</h4>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" class="booking-form">
                    <select name="timeslot_id" class="form-control" required>
                        <option value="">Select a timeslot</option>
                        <?php foreach ($timeslots as $slot): ?>
                            <option value="<?php echo $slot['id']; ?>">
                                <?php echo date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="book_room" class="btn btn-primary mt-2">Book Room</button>
                </form>
            </div>

            <!-- Comment Section -->
            <div class="comment-section mt-4">
                <h4>Leave a Comment</h4>
                <?php if (isset($success)) echo '<div>' . $success . '</div>'; ?>

                <form method="POST">
                    <label for="comment">Your Comment:</label>
                    <textarea name="comment" id="comment" rows="4" class="form-control" required></textarea><br>
                    <input type="hidden" name="room_id" value="<?php echo $roomId; ?>"> <!-- Room ID -->
                    <button type="submit" name="submit_comment" class="btn btn-primary">Submit Comment</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
