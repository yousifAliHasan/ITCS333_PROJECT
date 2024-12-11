<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'connection.php';

$error = '';
$success = '';

// Handle time slot addition or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $room_id = intval($_POST['room_id']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if (empty($room_id) || empty($start_time) || empty($end_time) || strtotime($start_time) >= strtotime($end_time)) {
        $error = 'Please provide valid inputs. Ensure start time is earlier than end time.';
    } else {
        try {
            $overlapCheck = $db->prepare("SELECT COUNT(*) FROM timeslots WHERE room_id = :room_id AND ((start_time <= :start_time AND end_time > :start_time) OR (start_time < :end_time AND end_time >= :end_time) OR (start_time >= :start_time AND end_time <= :end_time)) AND (:id IS NULL OR id != :id)");
            $overlapCheck->execute([':room_id' => $room_id, ':start_time' => $start_time, ':end_time' => $end_time, ':id' => $id]);

            if ($overlapCheck->fetchColumn() > 0) {
                $error = 'A time slot already exists for this time period. Please choose a different time.';
            } else {
                $current_time = strtotime($start_time);
                $end_time_timestamp = strtotime($end_time);

                $db->beginTransaction();

                while ($current_time < $end_time_timestamp) {
                    $next_time = strtotime('+1 hour', $current_time);
                    if ($next_time > $end_time_timestamp) {
                        $next_time = $end_time_timestamp;
                    }

                    if ($id) {
                        $updateQuery = "UPDATE timeslots SET room_id = :room_id, start_time = :start_time, end_time = :end_time, is_available = :is_available WHERE id = :id";
                        $statement = $db->prepare($updateQuery);
                        $statement->execute([
                            ':room_id' => $room_id,
                            ':start_time' => date('Y-m-d H:i:s', $current_time),
                            ':end_time' => date('Y-m-d H:i:s', $next_time),
                            ':is_available' => $is_available,
                            ':id' => $id
                        ]);
                    } else {
                        $insertQuery = "INSERT INTO timeslots (room_id, start_time, end_time, is_available) VALUES (:room_id, :start_time, :end_time, :is_available)";
                        $statement = $db->prepare($insertQuery);
                        $statement->execute([
                            ':room_id' => $room_id,
                            ':start_time' => date('Y-m-d H:i:s', $current_time),
                            ':end_time' => date('Y-m-d H:i:s', $next_time),
                            ':is_available' => $is_available
                        ]);
                    }

                    $current_time = $next_time;
                }

                $db->commit();
                $success = $id ? 'Time slot updated successfully!' : 'Time slot added successfully!';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error saving time slot: ' . $e->getMessage();
        }
    }
}

// Delete time slot logic
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $deleteQuery = "DELETE FROM timeslots WHERE id = :id";
        $statement = $db->prepare($deleteQuery);
        $statement->execute([':id' => $delete_id]);
        $success = 'Time slot deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting time slot: ' . $e->getMessage();
    }
}

// Fetch time slots and rooms
$timeSlots = $db->query("SELECT ts.*, r.name AS room_name FROM timeslots ts JOIN rooms r ON ts.room_id = r.id ORDER BY ts.start_time")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $db->query("SELECT * FROM rooms")->fetchAll(PDO::FETCH_ASSOC);

$editItem = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $statement = $db->prepare("SELECT * FROM timeslots WHERE id = :id");
    $statement->execute([':id' => $edit_id]);
    $editItem = $statement->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Time Slots</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: "Courier New", Courier, monospace;
            padding-top: 20px;
            height: 100vh;
            overflow-y: auto;
        }
        .container {
            max-height: 100%;
            padding-bottom: 20px;
        }
        .table-responsive {
            max-height: calc(100vh - 400px);
            overflow-y: auto;
        }
        body::-webkit-scrollbar {
            width: 12px;
        }

        body::-webkit-scrollbar-thumb {
            background-color: rgba(255, 111, 97, 0.7);
            border-radius: 10px;
            border: 3px solid rgba(255, 255, 255, 0.5);
        }

        body::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 111, 97, 1);
        }

        body::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
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
    <div class="container mt-4">
        <h2>Manage Time Slots</h2>
        <a href="dashboard.php" class="btn btn-secondary mb-3">Back to Dashboard</a>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Time Slot Form -->
        <form method="POST" class="mb-4">
            <?php if ($editItem && isset($editItem['id'])): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editItem['id']); ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="room_id" class="form-label">Room</label>
                <select class="form-control" id="room_id" name="room_id" required>
                    <option value="">Select Room</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?php echo $room['id']; ?>" <?php echo ($editItem && $editItem['room_id'] == $room['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($room['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="start_time" class="form-label">Start Time</label>
                <input type="datetime-local" class="form-control" id="start_time" name="start_time" 
                    value="<?php echo $editItem ? htmlspecialchars($editItem['start_time']) : ''; ?>" required>
            </div>

            <div class="mb-3">
                <label for="end_time" class="form-label">End Time</label>
                <input type="datetime-local" class="form-control" id="end_time" name="end_time" 
                    value="<?php echo $editItem ? htmlspecialchars($editItem['end_time']) : ''; ?>" required>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="is_available" name="is_available" value="1" 
                    <?php echo ($editItem && $editItem['is_available'] == 0) ? '' : 'checked'; ?>>
                <label class="form-check-label" for="is_available">Available</label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo $editItem ? 'Update Time Slot' : 'Add Time Slot'; ?></button>
                <a href="?" class="btn btn-secondary">Add New Time Slot</a>
            </div>
        </form>

        <!-- Time Slots Table -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeSlots as $slot): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($slot['room_name']); ?></td>
                            <td><?php echo htmlspecialchars($slot['start_time']); ?></td>
                            <td><?php echo htmlspecialchars($slot['end_time']); ?></td>
                            <td><?php echo $slot['is_available'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="?edit_id=<?php echo $slot['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="?delete_id=<?php echo $slot['id']; ?>" class="btn btn-danger btn-sm" 
                                    onclick="return confirm('Are you sure you want to delete this time slot?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
