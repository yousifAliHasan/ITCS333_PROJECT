<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
<form action="register.php" method="POST">
        <h2 class="font">Register</h2>
        <label for="id" class="font">ID</label>
        <input type="number" id="id" name="id" required>

        <label for="username" class="font">Username</label>
        <input type="text" id="username" name="username" required>

        <label for="email" class="font">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password" class="font">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="font">Register</button>
</body>
</html>
<?php 
include 'connection.php'; // Ensure this file establishes $db as the PDO instance

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $id = $_POST['id'];
    $user_type='student';
    // Password pattern validation
    $Pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    if (!preg_match($Pattern, $password)) {
        echo '<div style="font-size: 12px; class="font"; color: black; font-weight:; text-align: center;">Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.</div>';
        die(); // Stop further execution
    }
    

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Prepare SQL query
    $query = 'INSERT INTO students(username, password, email, id , user_type) VALUES (:username, :password, :email, :id , :user_type)';
    $statement = $db->prepare($query);

    try {
        // Execute query
        $statement->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':email' => $email,
            ':id' => $id,
            ':user_type' => $user_type
        ]);
        echo "Registration successful!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>