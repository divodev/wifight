<?php
class User
{
    // Database connection and table name
    private $conn;
    private $table_name = "users";

    // Object properties
    public $id;
    public $name; // This property will be filled by 'full_name'
    public $email;
    public $password;
    public $role;
    public $created_at;

    // Constructor with $db as database connection
    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Find user by email
    public function findByEmail($email)
    {
        // This query is now CORRECT and matches your database/schema.sql
        $query = "SELECT id, full_name, email, password, role 
                  FROM " . $this->table_name . "
                  WHERE email = :email
                  LIMIT 0,1";

        // Prepare query statement
        $stmt = $this->conn->prepare($query);

        // Sanitize
        $email = htmlspecialchars(strip_tags($email));

        // Bind value
        $stmt->bindParam(':email', $email);

        // Execute query
        $stmt->execute();

        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Set properties
            $this->id = $row['id'];
            // This now correctly assigns 'full_name' to the 'name' property
            $this->name = $row['full_name'];
            $this->email = $row['email'];
            $this->password = $row['password']; // This is the hashed password
            $this->role = $row['role'];
            return true;
        }

        return false;
    }
}
