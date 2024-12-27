<?php
session_start();
include 'db_connect.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php
    if(isset($_SESSION['google_name'])==TRUE)
        {
        $ax=$_SESSION['google_name'];
        echo "Welcome" . " " . "$ax";
        echo '<nav><a href="logout.php"><button1 style="--clr:#FF3131"><span>Logout</span><i></i></button1></a></nav>';
        echo '<a href="submit_project.php">Project Request Form</a>'; 
        }
    else{
        echo "User Not Registered";
        echo '<nav><a href="google-oauth.php"><button1 style="--clr:#39FF14"><span>Login</span><i></i></button1></a></nav>';   
    }
    ?>
</body>  
</html>