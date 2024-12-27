<?php
session_start();
require 'vendor/autoload.php';

// Database connection configuration
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'proj';

// Connect to the database
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Google OAuth configuration
$google_oauth_client_id = '';
$google_oauth_client_secret = '';
$google_oauth_redirect_uri = 'http://localhost/proj/google-oauth.php';

// Create the Google Client object
$client = new Google_Client();
$client->setClientId($google_oauth_client_id);
$client->setClientSecret($google_oauth_client_secret);
$client->setRedirectUri($google_oauth_redirect_uri);
$client->addScope("https://www.googleapis.com/auth/userinfo.email");
$client->addScope("https://www.googleapis.com/auth/userinfo.profile");

// Process login if authorization code is received
if (isset($_GET['code']) && !empty($_GET['code'])) {
    $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($accessToken);

    if (isset($accessToken['access_token']) && !empty($accessToken['access_token'])) {
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        if (isset($google_account_info->email)) {
            $email = $conn->real_escape_string($google_account_info->email);
            $name = $conn->real_escape_string($google_account_info->name);
            $picture = $conn->real_escape_string($google_account_info->picture);

            // Check if user exists in the database
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                session_regenerate_id(true);
                $_SESSION['google_loggedin'] = true;
                $_SESSION['google_email'] = $user_data['email'];
                $_SESSION['google_name'] = $user_data['name'];
                $_SESSION['google_picture'] = $user_data['picture'];

                // Update user's info if changed on Google
                if ($user_data['name'] !== $name || $user_data['picture'] !== $picture) {
                    $update_stmt = $conn->prepare("UPDATE users SET name = ?, picture = ? WHERE email = ?");
                    $update_stmt->bind_param("sss", $name, $picture, $email);
                    $update_stmt->execute();
                }
                // Welcome message
                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => "Welcome back, {$name}! You have successfully logged in with Google."
                ];

            } else {
                // Insert new user if they don't exist
                $insert_stmt = $conn->prepare("
                    INSERT INTO users (email, name, picture)
                    VALUES (?, ?, ?)
                ");
                $insert_stmt->bind_param("sss", $email, $name, $picture);
                $insert_stmt->execute();

                $_SESSION['google_loggedin'] = true;
                $_SESSION['google_email'] = $email;
                $_SESSION['google_name'] = $name;
                $_SESSION['google_picture'] = $picture;

                // Success message for new users
                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => "Welcome, {$name}! You have successfully signed up with Google."
                ];
            }

            header("Location: index.php");
        } else {
            $_SESSION['notification'] = [
                'type' => 'danger',
                'message' => "Failed to retrieve Google account information."
            ];
            header("Location: login-register.php");
        }
    } else {
        $_SESSION['notification'] = [
            'type' => 'danger',
            'message' => "Invalid access token. Please try again."
        ];
        header("Location: google-oauth.php");
    }
} else {
    // Redirect to Google's OAuth 2.0 login page
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}

$conn->close();
?>
