<?php
include 'connection.php';
session_start();

// Get search query if exists
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Modify query to include search
$query = "
    SELECT r.*, GROUP_CONCAT(e.name) as equipment_list
    FROM rooms r
    LEFT JOIN room_equipment re ON r.id = re.room_id
    LEFT JOIN equipment e ON re.equipment_id = e.id
";

if ($search) {
    $query .= " WHERE r.name LIKE :search";
    $query .= " GROUP BY r.id";
    $stmt = $db->prepare($query);
    $stmt->execute(['search' => "%$search%"]);
} else {
    $query .= " GROUP BY r.id";
    $stmt = $db->query($query);
}

$rooms = $stmt->fetchAll();

// Assuming the user is logged in and we have the user_id from session
$user_id = $_SESSION['user_id'];

// Query to get the number of unread notifications (comments awaiting admin response)
$stmt = $db->prepare("
    SELECT COUNT(*) AS unread_notifications
    FROM comments c
    LEFT JOIN responses r ON c.id = r.comment_id
    WHERE c.user_id = ? AND r.response IS NULL
");
$stmt->execute([$user_id]);

// Fetch the unread notifications count
$unread_notifications = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Browsing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Force navbar background color */
        .navbar {
            background-color: #343131 !important;
        }
        
        /* Override any Bootstrap background classes */
        .bg-light {
            background-color: #343131 !important;
        }
        
        /* Ensure text is visible on dark background */
        .navbar-brand {
            color: #EAC696 !important;
        }
        
        .navbar-nav .nav-link {
            color: white !important;
        }

        body {
            font-family: "Courier New", Courier, monospace; /* Set font to Courier New */
            padding-top: 80px; /* To accommodate the fixed navbar */
            background-color: #f8f9fa;
            margin: 0;
            overflow-y: scroll; /* Ensure the body scrolls vertically */
        }

        /* Custom Scrollbar Styling */
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
        }

        /* Navbar Styling */
        .navbar {
            background-color: #524C42;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
            padding: 1rem 0;
        }

        .navbar .navbar-brand {
            color: #EAC696;
            font-weight: bold;
        }

        .navbar .navbar-nav .nav-link {
            color: #fff !important;
        }

        .navbar .navbar-nav .nav-link:hover {
            color: #ddd !important;
        }

        .navbar .btn-outline-info {
            color: #FF9800;
            border-color: #17a2b8;
        }

        .navbar .btn-outline-info:hover {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }

        /* Room Cards Styling */
        .room-card {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            background-color: #fff;
            transition: transform 0.2s ease;
            margin-bottom: 20px;
        }

        .room-card:hover {
            transform: translateY(-10px);
        }

        .room-card h3 {
            font-size: 1.5rem;
            font-weight: bold;
            color: #343a40;
        }

        .room-details p {
            font-size: 0.9rem;
        }

        .room-card .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .room-card .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        /* Spacing for the content */
        .content {
            margin-top: 90px; /* To avoid overlap with fixed navbar */
        }

        .rooms-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
            max-width: 1200px; /* Limit maximum width */
            margin: 0 auto;
            align-items: stretch; /* Make all items stretch to match height */
        }

        .room-card-wrapper {
            display: flex;
            width: 100%;
        }

        .room-card {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 20px;
            background-color: #fff;
            transition: transform 0.2s ease;
            margin: 0; /* Remove margin to ensure consistent sizing */
        }

        /* Ensure content within cards is properly spaced */
        .room-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .equipment-list {
            margin: 15px 0;
        }

        /* Ensure the button stays at the bottom */
        .room-card .btn {
            margin-top: auto;
        }

        /* Media queries for responsive design */
        @media (min-width: 768px) {
            .rooms-container {
                grid-template-columns: repeat(3, 1fr); /* Force 3 columns on larger screens */
            }
        }

        @media (max-width: 767px) {
            .rooms-container {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        /* Positioning of the notification badge */
        .position-relative {
            position: relative;
        }

        .position-absolute {
            position: fixed;
        }

        .top-0 {
            top: 0;
        }

        .start-100 {
            right: 0;
        }

        .translate-middle {
            transform: translate(50%, -50%);
        }

        .p-1 {
            padding: 0.25rem;
        }

        .bg-danger {
            background-color: #dc3545;
        }

        .rounded-circle {
            border-radius: 50%;
        }

        .badge {
            position: absolute;
        }

        /* Update the navbar styling */
        .navbar {
            background-color: #343131;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
            padding: 1rem 0;
        }

        /* Update the search form styling */
        .nav-item .form-control {
            background: transparent;
            border: 1px solid #fff;
            color: #fff;
        }

        .nav-item .form-control::placeholder {
            color: rgba(255,255,255,0.7);
        }

        /* Remove the background from search input */
        .nav-item .form-control {
            background: none;
        }

        /* Center the rooms container */
        .rooms-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            align-items: center; /* Change from flex-start to center */
            margin: 0 auto;
            padding: 20px;
            min-height: calc(100vh - 80px); /* Subtract navbar height */
        }

        /* Update room card wrapper */
        .room-card-wrapper {
            flex: 0 1 calc(33.333% - 20px);
            min-width: 300px;
            max-width: 350px;
            display: flex;
            align-items: center;
        }

        /* Update navbar layout */
        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Adjust spacing between navbar items */
        .nav-item {
            margin: 0 5px;
        }

        /* Make search bar more prominent */
        .nav-item .form-control {
            min-width: 200px;
        }

        /* Content spacing */
        .content {
            margin-top: 100px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 100px);
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
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="rooms.php"><h2>Room Booking</h2></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <form class="d-flex" action="rooms.php" method="GET">
                            <input class="form-control me-2" type="search" name="search" placeholder="Search rooms..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-light" type="submit">Search</button>
                        </form>
                    </li>
                    <li class="nav-item">
                        <a href="my_bookings.php" class="btn btn-outline-light ms-3">My Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="btn btn-light ms-3">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a href="notifications.php" class="btn btn-outline-info position-relative ms-3">
                            <i class="bi bi-bell-fill"></i> 
                            <?php if ($unread_notifications > 0): ?>
                                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle p-1 rounded-circle">
                                    <?php echo $unread_notifications; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-danger ms-3">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <div class="container content">
        <div class="rooms-container">
            <?php foreach ($rooms as $room): ?>
                <div class="room-card-wrapper">
                    <div class="room-card">
                        <h3><?php echo htmlspecialchars($room['name']); ?></h3>
                        <div class="room-details">
                            <p><strong>Capacity:</strong> <?php echo $room['capacity']; ?> people</p>
                            <p><?php echo htmlspecialchars($room['description']); ?></p>
                        </div>
                        <?php if ($room['equipment_list']): ?>
                            <ul class="equipment-list">
                                <?php foreach (explode(',', $room['equipment_list']) as $equipment): ?>
                                    <li><?php echo htmlspecialchars($equipment); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a href="room_details.php?id=<?php echo $room['id']; ?>" class="btn btn-primary">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
