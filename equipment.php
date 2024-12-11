<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'connection.php';

$error = '';
$success = '';

// Handle delete action
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    try {
        // Begin transaction
        $db->beginTransaction();

        // First delete from room_equipment table
        $deleteRoomEquipment = "DELETE FROM room_equipment WHERE equipment_id = :id";
        $statement = $db->prepare($deleteRoomEquipment);
        $statement->execute([':id' => $deleteId]);

        // Then delete from equipment table
        $deleteQuery = "DELETE FROM equipment WHERE id = :id";
        $statement = $db->prepare($deleteQuery);
        $statement->execute([':id' => $deleteId]);

        $db->commit();
        $success = 'Equipment deleted successfully!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error deleting equipment: ' . $e->getMessage();
    }
}

// Handle edit action
if (isset($_POST['edit_id'])) {
    $editId = intval($_POST['edit_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $quantity = intval($_POST['quantity']);
    $roomId = intval($_POST['room_id']);

    if (empty($name) || empty($description)) {
        $error = 'Please provide valid inputs for all fields.';
    } else {
        try {
            $updateQuery = "UPDATE equipment SET name = :name, description = :description, quantity = :quantity WHERE id = :id";
            $statement = $db->prepare($updateQuery);
            $statement->execute([
                ':name' => $name,
                ':description' => $description,
                ':quantity' => $quantity,
                ':id' => $editId
            ]);

            // Update room_equipment table
            $updateRoomEquipment = "UPDATE room_equipment SET room_id = :room_id WHERE equipment_id = :equipment_id";
            $statement = $db->prepare($updateRoomEquipment);
            $statement->execute([
                ':room_id' => $roomId,
                ':equipment_id' => $editId
            ]);

            $success = 'Equipment updated successfully!';
            header("Location: equipment.php"); 
            exit();
        } catch (Exception $e) {
            $error = 'Error updating equipment: ' . $e->getMessage();
        }
    }
}

// Handle add new equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']); 
    $quantity = intval($_POST['quantity']);
    $roomId = intval($_POST['room_id']);

    if (empty($name) || empty($description)) {
        $error = 'Please provide valid inputs for all fields.';
    } else {
        try {
            // Begin transaction
            $db->beginTransaction();

            // Insert into equipment table
            $insertQuery = "INSERT INTO equipment (name, description, quantity) VALUES (:name, :description, :quantity)";
            $statement = $db->prepare($insertQuery);
            $result = $statement->execute([
                ':name' => $name,
                ':description' => $description,
                ':quantity' => $quantity
            ]);
            
            if ($result) {
                $equipmentId = $db->lastInsertId();

                // Insert into room_equipment table
                $insertRoomEquipment = "INSERT INTO room_equipment (room_id, equipment_id) VALUES (:room_id, :equipment_id)";
                $statement = $db->prepare($insertRoomEquipment);
                $statement->execute([
                    ':room_id' => $roomId,
                    ':equipment_id' => $equipmentId
                ]);

                $db->commit();
                $success = 'Equipment added successfully!';
                header("Location: equipment.php");
                exit();
            } else {
                $db->rollBack();
                $error = 'Failed to add equipment';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error adding equipment: ' . $e->getMessage();
        }
    }
}

// Fetch equipment data with room information
try {
    $equipment = $db->query("
        SELECT e.*, r.id as room_id 
        FROM equipment e 
        LEFT JOIN room_equipment re ON e.id = re.equipment_id 
        LEFT JOIN rooms r ON re.room_id = r.id 
        ORDER BY e.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Error fetching equipment: ' . $e->getMessage();
    $equipment = [];
}

// Fetch rooms for dropdown
try {
    $rooms = $db->query("SELECT id, name FROM rooms")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Error fetching rooms: ' . $e->getMessage();
    $rooms = [];
}

// Fetch a single equipment record for editing
$editItem = null;
if (isset($_GET['edit_id'])) {
    $editId = intval($_GET['edit_id']);
    try {
        $editQuery = "
            SELECT e.*, re.room_id 
            FROM equipment e 
            LEFT JOIN room_equipment re ON e.id = re.equipment_id 
            WHERE e.id = :id";
        $statement = $db->prepare($editQuery);
        $statement->execute([':id' => $editId]);
        $editItem = $statement->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error fetching equipment details: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
    
    body {
        padding-top: 20px;
        height: 100vh;
        overflow-y: auto; /* Enable scrolling for the entire page */
        font-family: 'Courier New', Courier, monospace; /* Apply the font change */
    }
    .container {
        max-height: 100%; /* Allow the container to take full height of the page */
        padding-bottom: 20px; /* Optional padding to avoid content being cut off */
    }
    .table-responsive {
        max-height: calc(100vh - 400px); /* Adjust the height of the table to fit within the page */
        overflow-y: auto; /* Allow the table to scroll if its height exceeds the viewport */
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
    

</style>

</head>
<body>
    <div class="container mt-4">
        <h2 class="font">Manage Equipment</h2>

        <!-- Back to Dashboard Button -->
        <a href="dashboard.php" class="btn btn-secondary mb-3">Back to Dashboard</a>

        <!-- Display Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Equipment Form -->
        <form method="POST" class="mb-4">
            <?php if ($editItem): ?>
                <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($editItem['id']); ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="name" class="form-label">Equipment Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($editItem['name'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" required><?php echo htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="<?php echo htmlspecialchars($editItem['quantity'] ?? '0'); ?>" required>
            </div>
            <div class="mb-3">
                <label for="room_id" class="form-label">Assign to Room</label>
                <select class="form-control" id="room_id" name="room_id" required>
                    <option value="">Select Room</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?php echo $room['id']; ?>" <?php echo ($editItem && $editItem['room_id'] == $room['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($room['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo $editItem ? 'Update Equipment' : 'Add Equipment'; ?></button>
                <a href="?" class="btn btn-secondary">Add New Equipment</a>
            </div>
        </form>

        <!-- Equipment Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Room</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipment as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['id']); ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                    <td>
                        <?php 
                        foreach ($rooms as $room) {
                            if ($room['id'] == $item['room_id']) {
                                echo htmlspecialchars($room['name']);
                                break;
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <a href="?edit_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="?delete_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
