<?php 
include 'connection.php'; // Ensure this file correctly connects to the database

session_start(); // Start the session

// Check if the user is logged in, otherwise redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the user ID from the session
$user_id = $_SESSION['user_id'];

// Handle the "Clear All" request
if (isset($_POST['clear_all'])) {
    // Delete all comments and their responses for the current user
    try {
        // Disable foreign key checks temporarily to allow deletion
        $db->exec("SET foreign_key_checks = 0;");

        // Delete responses first to avoid foreign key constraint errors
        $stmt = $db->prepare("DELETE FROM responses WHERE comment_id IN (SELECT id FROM comments WHERE user_id = ?)");
        $stmt->execute([$user_id]);

        // Now delete the comments
        $stmt = $db->prepare("DELETE FROM comments WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Enable foreign key checks again
        $db->exec("SET foreign_key_checks = 1;");

        // Redirect to avoid resubmission of form
        header("Location: notifications.php");
        exit();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Fetch all comments with admin responses for the current user
$stmt = $db->prepare("
    SELECT comments.comment, responses.response, comments.created_at AS comment_created_at, responses.created_at AS response_created_at
    FROM comments
    LEFT JOIN responses ON comments.id = responses.comment_id
    WHERE comments.user_id = ? AND responses.response IS NOT NULL
    ORDER BY comments.created_at DESC
");

$stmt->execute([$user_id]);
$responses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>Your Notifications</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f8f9fa;
            padding-top: 50px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .notification {
            background-color: #ffffff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .notification p {
            margin: 5px 0;
        }

        .btn-danger {
            background-color: #e74c3c;
            border: none;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-danger:focus {
            outline: none;
        }
    </style>
</head>
<body>
    <div class="container text-center">
        <h2>Your Notifications</h2>

        <!-- Form for clearing all notifications, centered horizontally -->
        <div class="d-flex justify-content-center mb-4">
            <form method="POST" action="notifications.php">
                <button type="submit" name="clear_all" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear all notifications?')">Clear All Notifications</button>
            </form>
        </div>

        <?php if (count($responses) > 0): ?>
            <?php foreach ($responses as $response): ?>
                <div class="notification">
                    <p><strong>Your Comment:</strong> <?php echo htmlspecialchars($response['comment']); ?></p>
                    <p><strong>Admin Response:</strong> <?php echo htmlspecialchars($response['response']); ?></p>
                    <p><em>Comment received on: <?php echo $response['comment_created_at']; ?></em></p>
                    <p><em>Response received on: <?php echo $response['response_created_at']; ?></em></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No notifications available.</p>
        <?php endif; ?>
    </div>
</body>
</html>
