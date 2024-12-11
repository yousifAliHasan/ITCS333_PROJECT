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
    $selectedEquipment = isset($_POST['equipment']) ? $_POST['equipment'] : [];
    
    if (isset($_POST['room_id'])) {
        // Update existing room
        $stmt = $db->prepare("UPDATE rooms SET name = ?, capacity = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $capacity, $description, $_POST['room_id']]);
        
        // Update room_equipment
        $db->prepare("DELETE FROM room_equipment WHERE room_id = ?")->execute([$_POST['room_id']]);
        foreach ($selectedEquipment as $equipId) {
            $db->prepare("INSERT INTO room_equipment (room_id, equipment_id) VALUES (?, ?)")->execute([$_POST['room_id'], $equipId]);
        }
        header('Location: manage_rooms.php');
    } else {
        // Add new room
        $stmt = $db->prepare("INSERT INTO rooms (name, capacity, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $capacity, $description]);
        $newRoomId = $db->lastInsertId();
        
        // Insert into room_equipment
        foreach ($selectedEquipment as $equipId) {
            $db->prepare("INSERT INTO room_equipment (room_id, equipment_id) VALUES (?, ?)")->execute([$newRoomId, $equipId]);
        }
        header('Location: manage_rooms.php');
    }
    exit();
}

// Get room details for editing if edit_id is set
$editRoom = null;
$editRoomEquipment = [];
if (isset($_GET['edit_id'])) {
    $editId = $_GET['edit_id'];
    $editRoom = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $editRoom->execute([$editId]);
    $editRoom = $editRoom->fetch();
    
    // Get currently assigned equipment for this room
    $stmt = $db->prepare("SELECT equipment_id FROM room_equipment WHERE room_id = ?");
    $stmt->execute([$editId]);
    $editRoomEquipment = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch all rooms with their equipment
$rooms = $db->query("
    SELECT r.*, GROUP_CONCAT(e.name) as equipment_list,
           GROUP_CONCAT(e.id) as equipment_ids
    FROM rooms r 
    LEFT JOIN room_equipment re ON r.id = re.room_id 
    LEFT JOIN equipment e ON re.equipment_id = e.id 
    GROUP BY r.id
")->fetchAll();

// Fetch all equipment for the equipment selection
$equipment = $db->query("SELECT * FROM equipment")->fetchAll();

// Fetch equipment for each room from room_equipment table
$roomEquipment = [];
foreach ($rooms as $room) {
    $stmt = $db->prepare("
        SELECT e.name 
        FROM equipment e
        JOIN room_equipment re ON e.id = re.equipment_id
        WHERE re.room_id = ?
    ");
    $stmt->execute([$room['id']]);
    $roomEquipment[$room['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        /* Base styles */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, rgba(255, 154, 158, 0.8), rgba(250, 208, 196, 0.8), rgba(251, 194, 235, 0.8), rgba(161, 140, 209, 0.8));
            background-size: 400% 400%;
            animation: gradientBackground 10s ease infinite;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            height: 100vh;
            padding-top: 50px;
            margin: 0;
            overflow: auto; /* Allow scrolling */
        }

        @keyframes gradientBackground {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container {
            width: 100%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.75);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        h2, h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: rgba(255, 111, 97, 1); /* Color from your button gradient */
        }

        /* Button Container */
        .button-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }

        /* Small Action Button */
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

        .btn:hover {
            background: linear-gradient(90deg, rgba(255, 59, 47, 0.9), rgba(255, 111, 97, 0.9));
            box-shadow: 0 6px 12px rgba(255, 111, 97, 0.4), 0 0 20px rgba(255, 111, 97, 0.6);
            transform: scale(1.05);
        }

        .btn:active {
            transform: scale(0.98);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        th, td {
            padding: 1rem;
            text-align: left;
            vertical-align: middle;
            color: #333;
        }

        th {
            background: rgba(255, 111, 97, 0.1);
            font-weight: 600;
        }

        td {
            background-color: #fafafa;
        }

        tr:hover {
            background-color: #f1f1f1;
        }
        body {
    font-family: 'Courier New', Courier, monospace;
    background: linear-gradient(135deg, rgba(255, 154, 158, 0.8), rgba(250, 208, 196, 0.8), rgba(251, 194, 235, 0.8), rgba(161, 140, 209, 0.8));
    background-size: 400% 400%;
    animation: gradientBackground 10s ease infinite;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    height: 100vh;
    padding-top: 50px;
    margin: 0;
    overflow: auto; /* Allow scrolling */
}

    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="rooms-container">
            <div class="room-card">
                <h2 class="font mb-4">Manage Rooms</h2>
                
                <!-- Add/Edit Room Form -->
                <form method="POST" class="mb-4">
                    <?php if($editRoom): ?>
                        <input type="hidden" name="room_id" value="<?php echo $editRoom['id']; ?>">
                    <?php endif; ?>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <input type="text" name="name" class="form-control" placeholder="Room Name" 
                                value="<?php echo $editRoom ? htmlspecialchars($editRoom['name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="capacity" class="form-control" placeholder="Capacity" 
                                value="<?php echo $editRoom ? $editRoom['capacity'] : ''; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="description" class="form-control" placeholder="Description" 
                                value="<?php echo $editRoom ? htmlspecialchars($editRoom['description']) : ''; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <select name="equipment[]" multiple class="form-control select2" style="width: 100%;">
                                <?php foreach ($equipment as $equip): ?>
                                    <option value="<?php echo $equip['id']; ?>" 
                                        <?php echo (in_array($equip['id'], $editRoomEquipment)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($equip['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="button-container">
                                <button type="submit" name="save_room" class="btn btn-success font">
                                    <?php echo $editRoom ? 'Update Room' : 'Add Room'; ?>
                                </button>
                                <?php if($editRoom): ?>
                                    <a href="manage_rooms.php" class="btn btn-secondary font">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Rooms List -->
                <div class="table-responsive">
                    <table class="table table-striped">
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
                                <td>
                                    <ul class="equipment-list">
                                        <?php 
                                        if (!empty($roomEquipment[$room['id']])) {
                                            foreach ($roomEquipment[$room['id']] as $equipName) {
                                                echo "<li>" . htmlspecialchars($equipName) . "</li>";
                                            }
                                        } else {
                                            echo "<li>None</li>";
                                        }
                                        ?>
                                    </ul>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                        <button type="submit" name="delete_room" class="btn btn-danger font">Delete</button>
                                    </form>
                                    <a href="manage_rooms.php?edit_id=<?php echo $room['id']; ?>" class="btn btn-primary font">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <a href="dashboard.php" class="btn font">Dashboard</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: 'Select equipment...',
                allowClear: true
            });
        });
    </script>
</body>
</html>
