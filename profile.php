<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'connection.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];

    // Start building the SQL query
    $updates = ["username = :username", "email = :email"];
    $params = [
        ':id' => $_SESSION['user_id'],
        ':username' => $username,
        ':email' => $email
    ];

    // Handle password update if provided
    if (!empty($_POST['new_password'])) {
        $updates[] = "password = :password";
        $params[':password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    }

    // Handle profile picture upload if provided
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $profilePicture = $_FILES['profile_picture'];

        $uploadDirectory = 'uploads/';
        $fileName = basename($profilePicture['name']);
        $targetFile = $uploadDirectory . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Validate image
        $check = getimagesize($profilePicture['tmp_name']);
        if ($check === false) {
            echo "Error: Invalid image file.";
            exit();
        }

        if ($profilePicture['size'] > 5 * 1024 * 1024) {
            echo "Error: File size exceeds the limit.";
            exit();
        }

        $allowedFormats = array('jpg', 'jpeg', 'png', 'gif');
        if (!in_array($imageFileType, $allowedFormats)) {
            echo "Error: Only JPG, JPEG, PNG, and GIF files are allowed.";
            exit();
        }

        // Move the uploaded file
        if (!move_uploaded_file($profilePicture['tmp_name'], $targetFile)) {
            echo "Error: Failed to upload image.";
            exit();
        }

        // Delete old profile picture if one exists
        $stmt = $db->prepare("SELECT profile_picture FROM students WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!empty($user['profile_picture'])) {
            $oldFile = __DIR__ . '/' . $user['profile_picture'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        // Update the profile picture field
        $updates[] = "profile_picture = :profile_picture";
        $params[':profile_picture'] = $targetFile; // Save the relative path to the database
    }

    // Update the database
    $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $db->prepare($sql);

    try {
        $stmt->execute($params);
        $_SESSION['message'] = "Profile updated successfully!";
        header('Location: profile.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
    }
}

// Fetch user data
$stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <form action="profile.php" method="POST" enctype="multipart/form-data">
            <h2 class="font">Profile</h2>

            <div class="profile-image">
                <?php 
                $profilePic = (!empty($user['profile_picture']) && file_exists(__DIR__ . '/' . $user['profile_picture']))
                    ? htmlspecialchars($user['profile_picture'])
                    : '/uploads/default-avatar.png';
                ?>
                <img src="<?php echo $profilePic . '?v=' . time(); ?>" alt="Profile Picture" class="img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                <input type="file" name="profile_picture" accept="image/*">
            </div>

            <label for="username" class="font">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

            <label for="email" class="font">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

            <label for="new_password" class="font">New Password (leave blank to keep current)</label>
            <input type="password" id="new_password" name="new_password">

            <button type="submit" class="font">Update Profile</button>
        </form>

        <form action="logout.php" method="POST" style="margin-top: 20px;">
            <button type="submit" class="btn btn-danger font">Logout</button>
        </form>
    </div>
</body>
</html>
