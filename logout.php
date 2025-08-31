<?php
session_start();
session_unset(); 
session_destroy(); // destroy the session
header("Location: index.php"); 
exit();
