<?php
session_start();
session_unset();
session_destroy();
header("Location: login.html?msg=Logged out successfully");
exit();
?>