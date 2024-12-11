<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: dashboard.php');
    } else {
        header('Location: rooms.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
        <h2 class="font">Login</h2>
            <label for="id" class="font">ID</label>
            <input type="number" id="id" name="id" required>

            <label for="password" class="font">Password</label>
            <input type="password" id="password" name="password" required>

        <button type="submit" class="font">Login</button>

        <!-- Add link to registration -->
        <p style="text-align: center; margin-top: 1rem;font-family: 'Courier New', Courier, monospace;">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </form>

    <?php 
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        include 'connection.php';
        
        $id = trim($_POST['id']);
        $password = $_POST['password'];
        
        try {
            // Retrieve user by ID
            $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect based on user type
                if ($user['user_type'] === 'admin') {
                    header('Location: dashboard.php');
                } else {
                    header('Location: rooms.php');
                }
                exit();
            } else {
                echo '<div class="alert alert-danger">Invalid ID or password.</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Login error: ' . $e->getMessage() . '</div>';
        }
    }
    ?>
</body>
</html>