<?php
include 'connection.php';

// Fetch all rooms with their equipment
$stmt = $db->query("
    SELECT r.*, GROUP_CONCAT(e.name) as equipment_list
    FROM rooms r
    LEFT JOIN room_equipment re ON r.id = re.room_id
    LEFT JOIN equipment e ON re.equipment_id = e.id
    GROUP BY r.id
");
$rooms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Browsing</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="rooms-container">
        <?php foreach ($rooms as $room): ?>
            <div class="room-card">
                <h3><?php echo htmlspecialchars($room['name']); ?></h3>
                <div class="room-details">
                    <p><strong>Capacity:</strong> <?php echo $room['capacity']; ?> people</p>
                    <p><?php echo htmlspecialchars($room['description']); ?></p>
                </div>
                <?php if ($room['equipment_list']): ?>
                    <ul class="equipment-list">
                        <?php foreach (explode(',', $room['equipment_list']) as $equipment): ?>
                            <li><?php echo htmlspecialchars($equipment); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a href="room_details.php?id=<?php echo $room['id']; ?>" class="btn view-details-btn">View Details</a>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>