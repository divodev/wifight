<?php
class Plan {
    private $conn;
    private $table = 'plans';
    
    public $id;
    public $name;
    public $description;
    public $price;
    public $duration_hours;
    public $data_limit_mb;
    public $bandwidth_up;
    public $bandwidth_down;
    public $validity_days;
    public $status;
    public $location_id;
    public $created_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET name = :name,
                      description = :description,
                      price = :price,
                      duration_hours = :duration_hours,
                      data_limit_mb = :data_limit_mb,
                      bandwidth_up = :bandwidth_up,
                      bandwidth_down = :bandwidth_down,
                      validity_days = :validity_days,
                      status = :status,
                      location_id = :location_id,
                      created_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':duration_hours', $this->duration_hours);
        $stmt->bindParam(':data_limit_mb', $this->data_limit_mb);
        $stmt->bindParam(':bandwidth_up', $this->bandwidth_up);
        $stmt->bindParam(':bandwidth_down', $this->bandwidth_down);
        $stmt->bindParam(':validity_days', $this->validity_days);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':location_id', $this->location_id);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function getById($id) {
        $query = "SELECT p.*, l.name as location_name 
                  FROM " . $this->table . " p
                  LEFT JOIN locations l ON p.location_id = l.id
                  WHERE p.id = :id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAll($location_id = null, $status = 'active') {
        $query = "SELECT p.*, l.name as location_name,
                  (SELECT COUNT(*) FROM vouchers WHERE plan_id = p.id AND status = 'unused') as available_vouchers,
                  (SELECT COUNT(*) FROM sessions WHERE plan_id = p.id AND status = 'active') as active_sessions
                  FROM " . $this->table . " p
                  LEFT JOIN locations l ON p.location_id = l.id
                  WHERE 1=1";
        
        $params = [];
        
        if ($location_id) {
            $query .= " AND p.location_id = :location_id";
            $params[':location_id'] = $location_id;
        }
        
        if ($status) {
            $query .= " AND p.status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY p.price ASC";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
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
        
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = htmlspecialchars(strip_tags($data['description']));
        }
        
        if (isset($data['price'])) {
            $fields[] = "price = :price";
            $params[':price'] = $data['price'];
        }
        
        if (isset($data['duration_hours'])) {
            $fields[] = "duration_hours = :duration_hours";
            $params[':duration_hours'] = $data['duration_hours'];
        }
        
        if (isset($data['data_limit_mb'])) {
            $fields[] = "data_limit_mb = :data_limit_mb";
            $params[':data_limit_mb'] = $data['data_limit_mb'];
        }
        
        if (isset($data['bandwidth_up'])) {
            $fields[] = "bandwidth_up = :bandwidth_up";
            $params[':bandwidth_up'] = $data['bandwidth_up'];
        }
        
        if (isset($data['bandwidth_down'])) {
            $fields[] = "bandwidth_down = :bandwidth_down";
            $params[':bandwidth_down'] = $data['bandwidth_down'];
        }
        
        if (isset($data['validity_days'])) {
            $fields[] = "validity_days = :validity_days";
            $params[':validity_days'] = $data['validity_days'];
        }
        
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        // Check if plan has active vouchers or sessions
        $query = "SELECT 
                    (SELECT COUNT(*) FROM vouchers WHERE plan_id = :id AND status = 'unused') as unused_vouchers,
                    (SELECT COUNT(*) FROM sessions WHERE plan_id = :id AND status = 'active') as active_sessions";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($check['unused_vouchers'] > 0 || $check['active_sessions'] > 0) {
            return [
                'success' => false, 
                'message' => 'Cannot delete plan with active vouchers or sessions'
            ];
        }
        
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Plan deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete plan'];
    }
    
    public function getPopular($limit = 5) {
        $query = "SELECT p.*, COUNT(s.id) as usage_count
                  FROM " . $this->table . " p
                  LEFT JOIN sessions s ON p.id = s.plan_id
                  WHERE p.status = 'active'
                  GROUP BY p.id
                  ORDER BY usage_count DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>