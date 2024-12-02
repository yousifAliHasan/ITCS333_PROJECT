<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h2>Login</h2>
        <form action="login.php" method="POST">
            <label for="id">ID</label>
            <input type="number" id="id" name="id" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>
</body>
</html>
<?php 

    include 'connection.php'; // Ensure this connects to the database
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];
        $password = $_POST['password'];
    
        try {
            // Retrieve user by ID
            $stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch();
    
            if ($user && password_verify($password, $user['password'])) {
                echo "Login successful! Welcome, " . htmlspecialchars($user['username']) . ".";
            } else {
                echo "Invalid ID or password.";
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

?>
