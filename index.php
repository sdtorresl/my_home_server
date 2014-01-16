<?php

// include db handler
require_once 'include/DB_Functions.php';
require_once 'include/config.php';

$functions = new DB_Functions();

// Array for JSON response
$response = array();

// Check that function is specified
$function = "";

if (isset($_GET['function']) && $_GET['function'] != "") {
	$function = $_GET['function'];
}
else {
	$functions->returnMessage("No function was specified, nothing to do here");
}

// Get the task to do
switch ($function) {
 	case 'getState':
 		if (isset($_GET['home_id']) && isset($_GET['room_id'])) {
			$home_id = $_GET['home_id'];
			$room_id = $_GET['room_id'];

			$functions->getRoomState($home_id, $room_id);
		}
		else {
			$functions->returnMessage("Required field(s) is missing");
		}
 		break;

 	case 'setState':
 		if (isset($_GET['home_id']) && isset($_GET['room_id']) && isset($_GET['state'])) {
			$home_id = $_GET['home_id'];
			$room_id = $_GET['room_id'];
			$state = $_GET['state'];

			$functions->setRoomState($home_id, $room_id, $state);
		}
		else {
			$functions->returnMessage("Required field(s) is missing");
		}
  		break;

 	case 'newUser':
 		if (isset($_GET['full_name']) && isset($_GET['username']) && isset($_GET['password']) && isset($_GET['email']) && isset($_GET['home_id']) && isset($_GET['home_password'])) {
			$fullName = $_GET['full_name'];
			$username = $_GET['username'];
			$password = $_GET['password'];
			$email = $_GET['email'];
			$home_id = $_GET['home_id'];
			$home_password = $_GET['home_password'];

			// Validate values
			if ($fullName == "") $functions->returnMessage("fullName can not be empty");
			if ($email == "") $functions->returnMessage("Email can not be empty");
			if ($username == "") $functions->returnMessage("Username can not be empty");
			if ($password == "") $functions->returnMessage("Password can not be empty");
			if ($home_id == "") $functions->returnMessage("Home ID can not be empty");
			if ($home_password == "") $functions->returnMessage("Home password can not be empty");

			$functions->storeUser($fullName, $username, $email, $password, $home_id, $home_password);
		}
		else {
			$functions->returnMessage("Required field(s) is missing");
		}
 		break;

 	case 'login':
 		if (isset($_GET['login']) && isset($_GET['password'])) {
 			if ((($login = $_GET['login']) != "") && (($password = $_GET['password']) != "")) {
 				$functions->userLogin($login, $password);
 			}
 			else {
 				$functions->returnMessage("Some fields are empty");
 			}
 		}
 		else {
			$functions->returnMessage("Required field(s) is missing");
		}
 		break;

  	default:
 		$functions->returnMessage("Invalid function");
 		break;
}

// Echoing JSON response
$functions->returnMessage("The function $function was done successfully", SUCCESS);

// Close mysql connection
$functions->close();
?>