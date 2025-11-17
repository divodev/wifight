<?php
class Location {
    private $conn;
    private $table = 'locations';
    
    public $id;
    public $name;
    public $address;
    public $city;
    public $country;
    public $contact_email;
    public $contact_phone;
    public $timezone;
    public $currency;
    public $status;
    public $created_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET name = :name,
                      address = :address,
                      city = :city,
                      country = :country,
                      contact_email = :contact_email,
                      contact_phone = :contact_phone,
                      timezone = :timezone,
                      currency = :currency,
                      status = :status,
                      created_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':contact_email', $this->contact_email);
        $stmt->bindParam(':contact_phone', $this->contact_phone);
        $stmt->bindParam(':timezone', $this->timezone);
        $stmt->bindParam(':currency', $this->currency);
        $stmt->bindParam(':status', $this->status);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function getAll($status = 'active') {
        $query = "SELECT * FROM " . $this->table;
        
        if ($status) {
            $query .= " WHERE status = :status";
        }
        
        $query .= " ORDER BY name ASC";
        
        $stmt = $this->conn->prepare($query);
        
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>