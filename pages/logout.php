<?php
session_start();

// Clear all session data
session_unset();
session_destroy();

// Redirect to the homepage
header("Location: ../index.php");
exit;
