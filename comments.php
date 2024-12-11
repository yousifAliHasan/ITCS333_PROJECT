<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'connection.php';

// Fetch all comments from the comments table
$stmt = $db->query("SELECT c.id, c.comment, c.created_at, r.name AS room_name, s.username AS student_username
                    FROM comments c 
                    JOIN rooms r ON c.room_id = r.id 
                    JOIN students s ON c.user_id = s.id
                    ORDER BY c.created_at DESC");

$comments = $stmt->fetchAll();

// Handle response to comment
if (isset($_POST['respond_to_comment'])) {
    $comment_id = $_POST['comment_id'];
    $admin_id = $_SESSION['user_id'];
    $response = $_POST['response'];

    // Insert the response into the responses table
    $stmt = $db->prepare("INSERT INTO responses (comment_id, admin_id, response) VALUES (?, ?, ?)");
    $stmt->execute([$comment_id, $admin_id, $response]);

    $success = 'Response submitted successfully!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Comments</title>
    
    <!-- Internal CSS -->
    <style>
    /* Base styles */
    body {
        font-family: 'Courier New', Courier, monospace; /* Apply Courier New font */
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

    /* Form response styling */
    .response-form {
        margin-bottom: 20px;
    }

    .response-form textarea {
        width: 100%;
        height: 120px;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid rgba(200, 200, 200, 0.6);
        font-size: 1rem;
        resize: none;
        background: rgba(255, 255, 255, 0.9);
    }

    .response-form button {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 30px;
        background: linear-gradient(90deg, rgba(255, 111, 97, 0.85), rgba(255, 154, 158, 0.85));
        color: white;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease-in-out;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .response-form button:hover {
        background: linear-gradient(90deg, rgba(255, 59, 47, 0.9), rgba(255, 111, 97, 0.9));
        box-shadow: 0 6px 12px rgba(255, 111, 97, 0.4), 0 0 20px rgba(255, 111, 97, 0.6);
        transform: scale(1.05);
    }

    .response-form .success-message {
        padding: 12px;
        margin-bottom: 20px;
        background-color: rgba(255, 111, 97, 0.1); /* Similar to table header */
        color: #ff6f61; /* Match button color */
        border-radius: 8px;
        border: 1px solid #ff6f61;
    }

    /* Custom scrollbar */
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
    /* Form response styling */
.response-form button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 30px;
    background: linear-gradient(90deg, rgba(255, 111, 97, 0.85), rgba(255, 154, 158, 0.85));
    color: white;
    font-family: 'Courier New', Courier, monospace; /* Change font to Courier New */
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease-in-out;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.response-form button:hover {
    background: linear-gradient(90deg, rgba(255, 59, 47, 0.9), rgba(255, 111, 97, 0.9));
    box-shadow: 0 6px 12px rgba(255, 111, 97, 0.4), 0 0 20px rgba(255, 111, 97, 0.6);
    transform: scale(1.05);
}

</style>

</head>
<body>

    <div class="container">
        <h2>Admin Dashboard - View Comments</h2>
        
        <!-- Success Message -->
        <?php if (isset($success)) echo '<div class="success-message">' . $success . '</div>'; ?>

        <h3>Comments Awaiting Response</h3>
        
        <!-- Comments Table -->
        <table>
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Student</th>
                    <th>Comment</th>
                    <th>Date Submitted</th>
                    <th>Respond</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $comment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($comment['room_name']); ?></td>
                    <td><?php echo htmlspecialchars($comment['student_username']); ?></td>
                    <td><?php echo htmlspecialchars($comment['comment']); ?></td>
                    <td><?php echo $comment['created_at']; ?></td>
                    <td>
                        <!-- Form for responding to the comment -->
                        <form method="POST" class="response-form">
                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                            <textarea name="response" rows="2" required placeholder="Write your response here..."></textarea><br>
                            <button type="submit" name="respond_to_comment" >Submit Response</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
