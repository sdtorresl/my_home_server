<?
class DB_Functions {
 
    private $db, $response;
 
    // Constructor
    function __construct() {
        require_once 'DB_Connect.php';
        require_once 'config.php';

        // connecting to database
        $this->db = new DB_Connect();
        $this->db->connect();
        $this->response = array();
    }
 
    // Destructor
    function __destruct() {
         
    }
 
    /** 
     * Verify that home ID and home password is correct
     * @param $home_id
     * @param $home_password
     * @return boolean
     */
    public function verifyHomePass($home_id, $home_password) {
        $passOK = false;

        $result = mysql_query("SELECT * FROM homes AS h WHERE h.id = $home_id");
        $no_of_rows = mysql_num_rows($result);
        if ($no_of_rows > 0) {
            $result = mysql_fetch_array($result);
            if ($home_password != $result['password']) {
                $this->returnMessage("Home password or home ID is invalid");
            } else {
                $passOK = true;
            }
        }
        else {
            $this->returnMessage("Home is not registered, please verify home id");
        }

        return $passOK;
    }

    /**
     * Storing new user
     * @param full_name
     * @param username
     * @param email
     * @param password
     * @param home_id
     * @param home_password
     * @return user details
     */
    public function storeUser($name, $username, $email, $password, $home_id, $home_password) {
        // Check that user doesn't exist
        if (!$this->isUserExisted($email) && !$this->isUserExisted($username)) {

            $this->verifyHomePass($home_id, $home_password);

            // Create user
            $unique_id = uniqid('', true);
            $hash = $this->hashSSHA($password);
            $salt = $hash["salt"]; // salt
            $encrypted_password = $hash["encrypted"];

            $result = mysql_query("INSERT INTO users(unique_id, name, username, email, password, home_id, salt, created_at) VALUES ('$unique_id', '$name', '$username', '$email', '$encrypted_password', '$home_id', '$salt', NOW())");
            
            if ($result) {  
                // Get user details
                $id = mysql_insert_id(); // last inserted id
                $result = mysql_query("SELECT * FROM users WHERE id = $id");
                // Return user details
                $this->response['user'] = mysql_fetch_assoc($result);
                $this->returnMessage("The user '$username' was created successfully", SUCCESS);
            } else {
                $this->returnMessage("Error inserting the user '$username', please contact with your system administrator. " . mysql_error());
            }
        }
        else {
            $this->returnMessage("User email or username already exist");
        }
    }
 
    /**
     * Check user is existed or not
     * @param login
     * @return boolean
     */
    public function isUserExisted($login) {
        $result = mysql_query("SELECT * FROM users WHERE email = '$login' OR username = '$login'");
        $no_of_rows = mysql_num_rows($result);
        if ($no_of_rows > 0) {
            // User existed
            return true;
        } 
        else {
            // User not existed
            return false;
        }
    }
 
    /**
     * Encrypting password
     * @param password
     * @return salt and encrypted password
     */
    public function hashSSHA($password) {
        $salt = sha1(rand());
        $salt = substr($salt, 0, 10);
        $encrypted = base64_encode(sha1($password . $salt, true) . $salt);
        $hash = array("salt" => $salt, "encrypted" => $encrypted);
        return $hash;
    }
 
    /**
     * Decrypting password
     * @param salt
     * @param password
     * @return hash string
     */
    public function checkHashSSHA($salt, $password) {
        $hash = base64_encode(sha1($password . $salt, true) . $salt);
        return $hash;
    }

    public function validateString($str) { 
        $str = str_replace ("ñ", "&ntilde", $str); 
        $str = str_replace ("á", "&aacute", $str); 
        $str = str_replace ("é", "&eacute", $str); 
        $str = str_replace ("í", "&iacute", $str); 
        $str = str_replace ("ó", "&oacute", $str); 
        $str = str_replace ("ú", "&uacute", $str); 
    
        return $str; 
    }
 
    /**
     * Validate a user login
     * @param login
     * @param password
     */
    public function userLogin($login, $password) {
        if (!$this->isUserExisted($login)) {
            $this->returnMessage("The email or username is not registered yet.");
        }
        else {
            $result = mysql_query("SELECT * FROM users WHERE email = '$login' OR username = '$login'");
            $no_of_rows = mysql_num_rows($result);

            if ($no_of_rows > 0) {
                $result = mysql_fetch_assoc($result);
                $salt = $result['salt'];
                $encrypted_password = $result['password'];

                $hash = $this->checkHashSSHA($salt, $password);

                // check for password equality
                if ($encrypted_password == $hash) {
                //if ($encrypted_password == $password) {
                    $this->response['user'] = $result;
                    $this->returnMessage("User authentication is done", SUCCESS);
                } else {
                    $this->returnMessage("Incorrect password");
                }
            }
        }
    }

    /**
     * Return the level of all rooms in a home
     * @param home_id
     * @param room_id
     * @return json
     */
    public function getHomeLevel($home_id, $home_password) {
        
        $this->verifyHomePass($home_id, $home_password);
        
        $result = mysql_query("SELECT h.id AS 'id', h.name AS 'name', r.id AS 'room_id', r.home_id AS 'home_id', r.name AS 'room_name', r.level AS 'level', r.control AS 'control', r.automatic AS 'automatic' FROM rooms AS r INNER JOIN homes AS h ON r.home_id = h.id WHERE h.id = $home_id");
        
        $no_of_rows = mysql_num_rows($result);

        if ($no_of_rows > 0) {
            $id = 0;
            while ($row = mysql_fetch_array($result)) {
                $this->response["home"]["id"] = $row["id"];
                $this->response["home"]["name"] = $row["name"];
                $this->response["home"]["nodes"] = $no_of_rows;
                $this->response["home"]["room_".$id]["id"] = $row["room_id"];
                $this->response["home"]["room_".$id]["DL"] = $id+1;
                $this->response["home"]["room_".$id]["name"] = $this->validateString(utf8_encode($row["room_name"]));
                $this->response["home"]["room_".$id]["level"] = $row["level"];
                $this->response["home"]["room_".$id]["control"] = $row["control"];
                $this->response["home"]["room_".$id]["automatic"] = $row["automatic"];

                $id++;
            }

            $this->returnMessage("OK", SUCCESS);
        } else {
            $this->returnMessage("Room does not exist");
        }
    }

    /**
     * Return the level of a room
     * @param home_id
     * @param room_id
     * @return json
     */
    public function getRoomState($home_id, $home_password, $room_id) {

        $this->verifyHomePass($home_id, $home_password);

        $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
        $no_of_rows = mysql_num_rows($result);
        if ($no_of_rows > 0) {
            $result = mysql_fetch_assoc($result);
            $this->response["room"] = $result;
            $this->returnMessage("OK", SUCCESS);
        } else {
            $this->returnMessage("Room does not exist");
        }
    }

    /**
     * Set the level of a room
     * @param home_id
     * @param homePassword
     * @param room_id
     * @param level
     * @return json
     */
    public function setRoomLevel($home_id, $home_password, $room_id, $level) {

        $this->verifyHomePass($home_id, $home_password);

        // Validate the new level
        try {
            $level = (int) $level;
            if (!($level >= 0 && $level <= 100)) 
                $this->returnMessage("The level must be a integer less than 100 and greater than 0");
        } catch (Exception $e) {
            $this->returnMessage("level must be an integer");
        }

        // Update the new value
        $result = mysql_query("UPDATE rooms SET level = $level WHERE id = $room_id AND home_id = $home_id");
        $no_of_rows = mysql_affected_rows();
        if ($no_of_rows > 0) {
            // Status changed, return object and message
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            $this->response["room"] =  $result;
            $room_name = $this->validateString(utf8_encode($result["name"]));
            $this->response["room"]["name"] =  $room_name;
            $this->returnMessage("The level of the room '$room_name' is now $level", SUCCESS);
        } 
        else {
            // Status has the same value
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            if($result['level'] == $level) {
                $this->response["room"] =  $result;
                $room_name = $this->validateString(utf8_encode($result["name"]));
                $this->response["room"]["name"] =  $room_name;
                $this->returnMessage("The level of the room '$room_name' is already in $level", SUCCESS);
            }
            else {
                // Room does not exist
                $this->returnMessage("Room does not exist");
            }
        }
    }

    /**
     * Set the automatic state of a room
     * @param home_id
     * @param homePassword
     * @param room_id
     * @param automatic
     * @return json
     */
    public function setRoomAutomatic($home_id, $home_password, $room_id, $automatic) {

        $this->verifyHomePass($home_id, $home_password);

        // Validate the new value of automatic
        if ($automatic) {
            $automatic = 1;
        }
        else {
            $automatic = 0;
        }

        // Update the new value
        $result = mysql_query("UPDATE rooms SET automatic = $automatic WHERE id = $room_id AND home_id = $home_id");
        $no_of_rows = mysql_affected_rows();
        if ($no_of_rows > 0) {
            // Status changed, return object and message
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            $this->response["room"] = $result;
            $room_name = $result["name"];
            $this->returnMessage("The automatic state of the room '$room_name' is now $automatic", SUCCESS);
        } 
        else {
            // Status has the same value
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            if($result['automatic'] == $automatic) {
                $room_name = $result["name"];
                $this->response["room"] = $result;
                $this->returnMessage("The automatic state of the room '$room_name' is already in $automatic", SUCCESS);
            }
            else {
                // Room does not exist
                $this->returnMessage("Room does not exist");
            }
        }
    }

    /**
     * Set the control state of a room
     * @param home_id
     * @param homePassword
     * @param room_id
     * @param control
     * @return json
     */
    public function setRoomControl($home_id, $home_password, $room_id, $control) {

        $this->verifyHomePass($home_id, $home_password);

        // Validate the new value of control
        if ($control) {
            $control = 1;
        }
        else {
            $control = 0;
        }

        // Update the new value
        $result = mysql_query("UPDATE rooms SET control = $control WHERE id = $room_id AND home_id = $home_id");
        $no_of_rows = mysql_affected_rows();
        if ($no_of_rows > 0) {
            // Status changed, return object and message
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            $this->response["room"] = $result;
            $room_name = $result["name"];
            $this->returnMessage("The control state of the room '$room_name' is now $control", SUCCESS);
        } 
        else {
            // Status has the same value
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            if($result['control'] == $control) {
                $room_name = $result["name"];
                $this->response["room"] = $result;
                $this->returnMessage("The control state of the room '$room_name' is already in $control", SUCCESS);
            }
            else {
                // Room does not exist
                $this->returnMessage("Room does not exist");
            }
        }
    }

    /**
     * Turn off all lights of the house
     * @param home_id
     * @param homePassword
     * @return json
     */
    public function setLightsOff($home_id, $home_password) {

        $this->verifyHomePass($home_id, $home_password);

        // Update the new value
        $result = mysql_query("UPDATE rooms SET level = 100 WHERE home_id = $home_id");
        $no_of_rows = mysql_affected_rows();
        if ($no_of_rows > 0) {
            // Status changed, return object and message
            $this->returnMessage("$no_of_rows lights were turned off", SUCCESS);
        } 
        else {
            // Any light was turned off
            $this->returnMessage("No light was turned off");
        }
    }
    /**
     * Give a json object with results
     * @param msg
     * @param success
     */
    public function returnMessage($msg, $success=ERROR) {
        if ($success) 
            $success = true;
        else
            $success = false;

        $this->response["success"] = $success;
        $this->response["message"] = $msg;    
        echo json_encode($this->response);
        exit;
    }
}
?>