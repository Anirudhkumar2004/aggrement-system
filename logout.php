<?php
session_start();
session_unset();
echo '<script type="text/javascript">
alert("Log Out Successfully");
window.location = "index.php";
</script>';
?>
