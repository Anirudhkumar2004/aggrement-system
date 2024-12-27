<?php
session_start();
include 'db_connect.php';
require_once 'TCPDF/tcpdf.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entered_otp = $_POST["otp"];
    $email = $_SESSION["email"];
    $signature_path = $_SESSION['signature_path'];
    $preferred_language = $_SESSION["preferred_language"];

    // Check OTP and expiration
    $stmt = $conn->prepare("SELECT user_id, name, otp, otp_expires_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $user["otp"] == $entered_otp && strtotime($user["otp_expires_at"]) > time()) {
        $user_id = $user["user_id"];
        $user_name = $user["name"];

        // Retrieve project information
        $project_stmt = $conn->prepare("SELECT project_id, project_description FROM projects WHERE user_id = ? AND is_signed = FALSE LIMIT 1");
        $project_stmt->bind_param("i", $user_id);
        $project_stmt->execute();
        $project_result = $project_stmt->get_result();
        $project = $project_result->fetch_assoc();
        $project_id = $project["project_id"];
        $project_description = $project["project_description"];

        // Mark project as signed and update signature details in the projects table
        $update_project_stmt = $conn->prepare("UPDATE projects SET is_signed = TRUE, signature = ?, pdf_path = ? WHERE project_id = ?");
        $pdf_path = __DIR__ . '/pdfs/' . uniqid() . '_agreement.pdf';
        $update_project_stmt->bind_param("ssi", $signature_path, $pdf_path, $project_id);
        $update_project_stmt->execute();

        // Generate PDF with receipt details
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Project Agreement and Receipt', 0, 1, 'C');
        $pdf->Ln(10);

        // Receipt details
        
        $pdf->MultiCell(0, 10, "Customer Name: $user_name", 0, 'L');
        $pdf->MultiCell(0, 10, "Email: $email", 0, 'L');
        $pdf->MultiCell(0, 10, "Project Description: $project_description", 0, 'L');
        $pdf->MultiCell(0, 10, "Preferred Language: $preferred_language", 0, 'L');
        $pdf->Ln(10);

        // Terms and conditions per language
        $terms = [
            "PHP" => "Terms and Conditions: HERE ARE THE TERMS FOR PHP.",
            "JAVA" => "Terms and Conditions: ABHI AAP DEKH RAHE H JAVA K LIYA TERMAS AND CONDITIONS.",
            "REACT" => "Terms and Conditions : REACT KA LIYA YAHA SAMPARK KARE."
        ];
        $pdf->MultiCell(0, 10, $terms[$preferred_language], 0, 'L');
        $pdf->Ln(10);

        // Add signature if uploaded
        if (file_exists($signature_path)) {
            $pdf->MultiCell(0, 10, "Signature:", 0, 'L');
            $pdf->Ln(5);
            $pdf->Image($signature_path, 15, $pdf->GetY(), 50);
            $pdf->Ln(10);
        } else {
            $pdf->MultiCell(0, 10, "Signature: Not provided", 0, 'L');
        }

        $pdf->Output($pdf_path, 'F');

        // Send email with attached agreement PDF
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'vastrloecommerce@gmail.com';
            $mail->Password = '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('vastrloecommerce@gmail.com', 'Project Receipt');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your Signed Project Agreement';
            $mail->Body = "Hello $user_name,<br><br>Thank you for submitting your project. Please find attached a copy of your signed agreement and receipt.";
            $mail->addAttachment($pdf_path, 'Signed_Agreement_Receipt.pdf');

            $mail->send();
            echo "Project successfully signed, and a copy of the agreement has been sent to your email.";
        } catch (Exception $e) {
            echo "Error sending email: {$mail->ErrorInfo}";
        }
    } else {
        echo "Invalid OTP or OTP expired. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Verify OTP</h1>
    <form action="verify_otp.php" method="POST">
        <label for="otp">Enter OTP:</label>
        <input type="text" name="otp" required><br>
        <input type="submit" value="Verify OTP">
    </form>
</body>
</html>
