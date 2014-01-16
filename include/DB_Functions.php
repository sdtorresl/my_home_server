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

            // Verify that home ID and home password is correct
            $result = mysql_query("SELECT * FROM homes AS h WHERE h.id = $home_id");
            $no_of_rows = mysql_num_rows($result);
            if ($no_of_rows > 0) {
                $result = mysql_fetch_array($result);
                if ($home_password != $result['password']) {
                    $this->returnMessage("Home password or home ID is invalid");
                }
            }
            else {
                $this->returnMessage("Home is not registered, please verify home id");
            }

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
     * Return the state of a room
     * @param home_id
     * @param room_id
     * @return json
     */
    public function getRoomState($home_id, $room_id) {
        $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
        $no_of_rows = mysql_num_rows($result);
        if ($no_of_rows > 0) {
            # code...
            $result = mysql_fetch_assoc($result);
            $this->response["room"] = $result;
            $this->returnMessage("OK", SUCCESS);
        } else {
            $this->returnMessage("Room does not exist");
        }
    }

    /**
     * Return the state of a room
     * @param home_id
     * @param room_id
     * @return json
     */
    public function setRoomState($home_id, $room_id, $state) {
        // Validate the new state
        try {
            $state = (int) $state;
            if (!($state >= 0 && $state <= 100)) 
                $this->returnMessage("The state must be a integer less than 100 and greater than 0");
        } catch (Exception $e) {
            $this->returnMessage("State must be an integer");
        }

        // Update the new value
        $result = mysql_query("UPDATE rooms SET state = $state WHERE id = $room_id AND home_id = $home_id");
        $no_of_rows = mysql_affected_rows();
        if ($no_of_rows > 0) {
            // Status changed, return object and message
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            $this->response["room"] = $result;
            $room_name = $result["name"];
            $this->returnMessage("The state of the room '$room_name' is now $state", SUCCESS);
        } 
        else {
            // Status has the same value
            $result = mysql_query("SELECT * FROM rooms WHERE id = $room_id AND home_id = $home_id");
            $result = mysql_fetch_assoc($result);
            if($result['state'] == $state) {
                $room_name = $result["name"];
                $this->response["room"] = $result;
                $this->returnMessage("The state of the room '$room_name' is already in $state", SUCCESS);
            }
            else {
                // Room does not exist
                $this->returnMessage("Room does not exist");
            }
        }
    }

    /**
     * Give a json object with results
     * @param msg
     * @param success
     */
    public function returnMessage($msg, $success=ERROR) {
        $this->response["success"] = $success;
        $this->response["message"] = $msg;    
        echo json_encode($this->response);
        exit;
    }
}
?>