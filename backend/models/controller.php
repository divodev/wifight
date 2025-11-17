<?php
class Controller {
    private $conn;
    private $table = 'controllers';
    
    public $id;
    public $name;
    public $ip_address;
    public $port;
    public $username;
    public $password;
    public $site_id;
    public $location_id;
    public $status;
    public $version;
    public $last_sync;
    public $created_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET name = :name,
                      ip_address = :ip_address,
                      port = :port,
                      username = :username,
                      password = :password,
                      site_id = :site_id,
                      location_id = :location_id,
                      status = :status,
                      created_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->ip_address = htmlspecialchars(strip_tags($this->ip_address));
        $this->username = htmlspecialchars(strip_tags($this->username));
        $encrypted_password = base64_encode($this->password);
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':ip_address', $this->ip_address);
        $stmt->bindParam(':port', $this->port);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':password', $encrypted_password);
        $stmt->bindParam(':site_id', $this->site_id);
        $stmt->bindParam(':location_id', $this->location_id);
        $stmt->bindParam(':status', $this->status);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function getById($id) {
        $query = "SELECT c.*, l.name as location_name 
                  FROM " . $this->table . " c
                  LEFT JOIN locations l ON c.location_id = l.id
                  WHERE c.id = :id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && isset($row['password'])) {
            $row['password'] = base64_decode($row['password']);
        }
        
        return $row;
    }
    
    public function getAll($location_id = null) {
        $query = "SELECT c.*, l.name as location_name,
                  (SELECT COUNT(*) FROM sessions WHERE controller_id = c.id AND status = 'active') as active_sessions
                  FROM " . $this->table . " c
                  LEFT JOIN locations l ON c.location_id = l.id
                  WHERE 1=1";
        
        if ($location_id) {
            $query .= " AND c.location_id = :location_id";
        }
        
        $query .= " ORDER BY c.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        
        if ($location_id) {
            $stmt->bindParam(':location_id', $location_id);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = htmlspecialchars(strip_tags($data['name']));
        }
        
        if (isset($data['ip_address'])) {
            $fields[] = "ip_address = :ip_address";
            $params[':ip_address'] = htmlspecialchars(strip_tags($data['ip_address']));
        }
        
        if (isset($data['port'])) {
            $fields[] = "port = :port";
            $params[':port'] = $data['port'];
        }
        
        if (isset($data['username'])) {
            $fields[] = "username = :username";
            $params[':username'] = htmlspecialchars(strip_tags($data['username']));
        }
        
        if (isset($data['password'])) {
            $fields[] = "password = :password";
            $params[':password'] = base64_encode($data['password']);
        }
        
        if (isset($data['site_id'])) {
            $fields[] = "site_id = :site_id";
            $params[':site_id'] = $data['site_id'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        
        if (isset($data['version'])) {
            $fields[] = "version = :version";
            $params[':version'] = $data['version'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function updateLastSync($id) {
        $query = "UPDATE " . $this->table . " SET last_sync = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function getActiveControllers() {
        $query = "SELECT * FROM " . $this->table . " WHERE status = 'active' ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>