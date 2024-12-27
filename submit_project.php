<?php
session_start();
include 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];
    $project_description = $_POST["project_description"];
    $preferred_language = $_POST["preferred_language"];

    // Handle signature file upload
    $signature_file = $_FILES['signature']['tmp_name'];
    $signature_path = 'uploads/' . uniqid() . '_signature.png';
    if (move_uploaded_file($signature_file, $signature_path)) {
        $_SESSION['signature_path'] = $signature_path;
    } else {
        echo "Failed to upload signature.";
        exit;
    }

    // Database checks and inserts
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $user_stmt = $conn->prepare("INSERT INTO users (name, email, phone) VALUES (?, ?, ?)");
        $user_stmt->bind_param("sss", $name, $email, $phone);
        $user_stmt->execute();
        $user_id = $user_stmt->insert_id;
    } else {
        $user = $result->fetch_assoc();
        $user_id = $user["user_id"];
    }

    // Generate OTP and signature token
    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['email'] = $email;
    $_SESSION['preferred_language'] = $preferred_language;
    $otp_expires_at = date("Y-m-d H:i:s", strtotime("+15 minutes"));
    $signature_token = bin2hex(random_bytes(16));

    // Insert project
    $project_stmt = $conn->prepare("INSERT INTO projects (user_id, project_description, preferred_language, signature_token) VALUES (?, ?, ?, ?)");
    $project_stmt->bind_param("isss", $user_id, $project_description, $preferred_language, $signature_token);
    $project_stmt->execute();

    // Update OTP in users table
    $update_stmt = $conn->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE email = ?");
    $update_stmt->bind_param("iss", $otp, $otp_expires_at, $email);
    $update_stmt->execute();

    // Send OTP email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'vastrloecommerce@gmail.com';
        $mail->Password = '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('vastrloecommerce@gmail.com', 'Project Verification');
        $mail->addAddress($email, $name);
        
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Project Submission Verification';
        $mail->Body = "Hello $name,<br>Your OTP is <strong>$otp</strong>. It will expire in 15 minutes.";
        
        $mail->send();
        header('Location: verify_otp.php');
    } catch (Exception $e) {
        echo "Error: {$mail->ErrorInfo}";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Submission Form</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Submit Your Project</h1>
    <form action="submit_project.php" method="POST" enctype="multipart/form-data">
        <label for="name">Name:</label>
        <input type="text" name="name" required><br>

        <label for="email">Email:</label>
        <input type="email" name="email" required><br>

        <label for="phone">Phone:</label>
        <input type="text" name="phone" ><br>

        <label for="project_description">Project Description:</label>
        <textarea name="project_description" required></textarea><br>

        <label for="preferred_language">Preferred Language:</label>
        <select name="preferred_language" required>
            <option value="PHP">PHP</option>
            <option value="JAVA">Java</option>
            <option value="REACT">React</option>
        </select><br>

        <label for="terms">Terms and Conditions:</label>
        <div id="terms">
            <!-- Terms content would dynamically display based on JavaScript below -->
        </div><br>

        <label for="signature">Digital Signature:</label>
        <input type="file" name="signature" accept="image/*" required><br>

        <input type="submit" value="Submit Project">
    </form>

    <script>
        const terms = {
            PHP: "HERE ARE THE TERMS FOR PHP",
            JAVA: "ABHI AAP DEKH RAHE H JAVA K LIYA TERMAS AND CONDITIONS",
            REACT: "REACT KA LIYA YAHA SAMPARK KARE"
        };

        const languageSelect = document.querySelector("select[name='preferred_language']");
        const termsDiv = document.getElementById("terms");

        function updateTerms() {
            termsDiv.innerText = terms[languageSelect.value];
        }

        languageSelect.addEventListener("change", updateTerms);
        window.onload = updateTerms;
    </script>
</body>
</html>
