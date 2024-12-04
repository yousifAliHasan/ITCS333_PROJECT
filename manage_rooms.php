<?php
include 'connection.php';

// Handle room deletion
if (isset($_POST['delete_room'])) {
    $roomId = $_POST['room_id'];
    
    // First delete related records in room_equipment and timeslots
    $db->prepare("DELETE FROM room_equipment WHERE room_id = ?")->execute([$roomId]);
    $db->prepare("DELETE FROM timeslots WHERE room_id = ?")->execute([$roomId]);
    
    // Then delete the room
    $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    header('Location: manage_rooms.php');
    exit();
}

// Handle room addition/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_room'])) {
    $name = $_POST['name'];
    $capacity = $_POST['capacity'];
    $description = $_POST['description'];
    
    if (isset($_POST['room_id'])) {
        // Update existing room
        $stmt = $db->prepare("UPDATE rooms SET name = ?, capacity = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $capacity, $description, $_POST['room_id']]);
    } else {
        // Add new room
        $stmt = $db->prepare("INSERT INTO rooms (name, capacity, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $capacity, $description]);
    }
    
    header('Location: manage_rooms.php');
    exit();
}

// Fetch all rooms with their equipment
$rooms = $db->query("
    SELECT r.*, GROUP_CONCAT(e.name) as equipment_list 
    FROM rooms r 
    LEFT JOIN room_equipment re ON r.id = re.room_id 
    LEFT JOIN equipment e ON re.equipment_id = e.id 
    GROUP BY r.id
")->fetchAll();

// Fetch all equipment for the equipment selection
$equipment = $db->query("SELECT * FROM equipment")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="container mt-4">
        <h2 class="font">Manage Rooms</h2>
        
        <!-- Add Room Form -->
        <form method="POST" class="mb-4">
            <div class="row">
                <div class="col-md-3">
                    <input type="text" name="name" class="form-control" placeholder="Room Name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="capacity" class="form-control" placeholder="Capacity" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="description" class="form-control" placeholder="Description" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="save_room" class="btn btn-primary font">Add Room</button>
                </div>
            </div>
        </form>

        <!-- Rooms List -->
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Capacity</th>
                    <th>Description</th>
                    <th>Equipment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                <tr>
                    <td><?php echo htmlspecialchars($room['name']); ?></td>
                    <td><?php echo $room['capacity']; ?></td>
                    <td><?php echo htmlspecialchars($room['description']); ?></td>
                    <td><?php echo htmlspecialchars($room['equipment_list'] ?? 'None'); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                            <button type="submit" name="delete_room" class="btn btn-danger btn-sm font" 
                                    onclick="return confirm('Are you sure you want to delete this room?')">Delete</button>
                        </form>
                        <button class="btn btn-primary btn-sm font" 
                                onclick="editRoom(<?php echo htmlspecialchars(json_encode($room)); ?>)">Edit</button>
                        <a href="manage_room_equipment.php?room_id=<?php echo $room['id']; ?>" 
                           class="btn btn-info btn-sm font">Equipment</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-secondary font">Back to Dashboard</a>
        </div>
    </div>

    <script>
    function editRoom(room) {
        document.querySelector('input[name="name"]').value = room.name;
        document.querySelector('input[name="capacity"]').value = room.capacity;
        document.querySelector('input[name="description"]').value = room.description;
        
        // Remove existing hidden room_id if any
        const existingHidden = document.querySelector('input[name="room_id"]');
        if (existingHidden) {
            existingHidden.remove();
        }
        
        // Add hidden input for room ID
        let hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'room_id';
        hiddenInput.value = room.id;
        
        let form = document.querySelector('form');
        form.appendChild(hiddenInput);
        
        // Change button text to indicate editing
        document.querySelector('button[name="save_room"]').textContent = 'Update Room';
    }
    </script>
</body>
</html>