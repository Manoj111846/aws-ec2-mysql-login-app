<?php
$db_host = "123.11.1.49";
$db_name = "loginapp";
$db_user = "appuser";
$db_pass = "AppPass123!";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST["username"];
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "INSERT INTO users (username, password) VALUES (?, ?)"
    );

    $stmt->bind_param("ss", $username, $password);

    if ($stmt->execute()) {
        echo "<h2>Registration successful!</h2>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Login Application</title>
</head>

<body>

<h1>Login Application</h1>

<form method="POST">

    <label>Username:</label>
    <input type="text" name="username" required>

    <br><br>

    <label>Password:</label>
    <input type="password" name="password" required>

    <br><br>

    <button type="submit">Save</button>

</form>

</body>
</html>

<?php
$conn->close();
?>
