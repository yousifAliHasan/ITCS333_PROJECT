<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="style.css">
    
    <style>
        body {
            font-family: "Courier New", Courier, monospace;
        }
    </style>
</head>
<body>
    <form action="register.php" method="POST">
        <h2>Register</h2>
        <label for="id">ID</label>
        <input type="number" id="id" name="id" required>

        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Register</button>

        <!-- Add link to login page -->
        <p style="text-align: center; margin-top: 1rem;">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </form>

    <?php 
    include 'connection.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $errors = [];
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $id = trim($_POST['id']);
        $user_type = 'student';

        // Validate UoB email
        if (!preg_match('/^' . $id . '@stu\.uob\.edu\.bh$/', $email)) {
            $errors[] = "Please use a valid University of Bahrain email address (your ID followed by @stu.uob.edu.bh)";
        }

        // Password validation
        $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
        if (!preg_match($passwordPattern, $password)) {
            $errors[] = "Password must be at least 8 characters long and include at least one uppercase letter, lowercase letter, number, and special character.";
        }

        // Check if email or ID already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE email = :email OR id = :id");
        $stmt->execute([':email' => $email, ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email or ID already registered";
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            try {
                $query = 'INSERT INTO students(username, password, email, id, user_type) 
                         VALUES (:username, :password, :email, :id, :user_type)';
                $statement = $db->prepare($query);
                $statement->execute([
                    ':username' => $username,
                    ':password' => $hashedPassword,
                    ':email' => $email,
                    ':id' => $id,
                    ':user_type' => $user_type
                ]);
                
                echo '<div class="alert alert-success">Registration successful! Please <a href="login.php">login</a>.</div>';
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">Registration failed: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        }
    }
    ?>
</body>
</html>
