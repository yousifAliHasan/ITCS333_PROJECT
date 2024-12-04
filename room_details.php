<?php
include 'connection.php';

$roomId = $_GET['id'];

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
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

            <h4>Available Timeslots</h4>
            <ul class="timeslots">
                <?php foreach ($timeslots as $timeslot): ?>
                    <li>
                        <?php echo date('Y-m-d H:i', strtotime($timeslot['start_time'])) . ' to ' . 
                                 date('H:i', strtotime($timeslot['end_time'])); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>