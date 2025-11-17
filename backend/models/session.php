<?php
class Session {
    private $conn;
    private $table = 'sessions';
    
    public $id;
    public $controller_id;
    public $mac_address;
    public $ip_address;
    public $username;
    public $plan_id;
    public $voucher_id;
    public $start_time;
    public $end_time;
    public $duration_minutes;
    public $data_used_mb;
    public $status;
    public $device_info;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET controller_id = :controller_id,
                      mac_address = :mac_address,
                      ip_address = :ip_address,
                      username = :username,
                      plan_id = :plan_id,
                      voucher_id = :voucher_id,
                      start_time = NOW(),
                      status = 'active',
                      device_info = :device_info";
        
        $stmt = $this->conn->prepare($query);
        
        $this->mac_address = htmlspecialchars(strip_tags($this->mac_address));
        $this->ip_address = htmlspecialchars(strip_tags($this->ip_address));
        
        $stmt->bindParam(':controller_id', $this->controller_id);
        $stmt->bindParam(':mac_address', $this->mac_address);
        $stmt->bindParam(':ip_address', $this->ip_address);
        $stmt->bindParam(':username', $this->username);
        $stmt->bindParam(':plan_id', $this->plan_id);
        $stmt->bindParam(':voucher_id', $this->voucher_id);
        $stmt->bindParam(':device_info', $this->device_info);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function getActive($controller_id = null, $mac_address = null) {
        $query = "SELECT s.*, p.name as plan_name, c.name as controller_name,
                  TIMESTAMPDIFF(MINUTE, s.start_time, NOW()) as elapsed_minutes
                  FROM " . $this->table . " s
                  LEFT JOIN plans p ON s.plan_id = p.id
                  LEFT JOIN controllers c ON s.controller_id = c.id
                  WHERE s.status = 'active'";
        
        $params = [];
        
        if ($controller_id) {
            $query .= " AND s.controller_id = :controller_id";
            $params[':controller_id'] = $controller_id;
        }
        
        if ($mac_address) {
            $query .= " AND s.mac_address = :mac_address";
            $params[':mac_address'] = $mac_address;
        }
        
        $query .= " ORDER BY s.start_time DESC";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        $query = "SELECT s.*, p.name as plan_name, c.name as controller_name,
                  v.code as voucher_code,
                  TIMESTAMPDIFF(MINUTE, s.start_time, COALESCE(s.end_time, NOW())) as duration_minutes
                  FROM " . $this->table . " s
                  LEFT JOIN plans p ON s.plan_id = p.id
                  LEFT JOIN controllers c ON s.controller_id = c.id
                  LEFT JOIN vouchers v ON s.voucher_id = v.id
                  WHERE s.id = :id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function terminate($id, $reason = 'user_logout') {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'terminated',
                      end_time = NOW(),
                      duration_minutes = TIMESTAMPDIFF(MINUTE, start_time, NOW())
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function terminateByMac($mac_address) {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'terminated',
                      end_time = NOW(),
                      duration_minutes = TIMESTAMPDIFF(MINUTE, start_time, NOW())
                  WHERE mac_address = :mac_address 
                  AND status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':mac_address', $mac_address);
        
        return $stmt->execute();
    }
    
    public function updateUsage($id, $data_used_mb) {
        $query = "UPDATE " . $this->table . " 
                  SET data_used_mb = :data_used_mb 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':data_used_mb', $data_used_mb);
        
        return $stmt->execute();
    }
    
    public function getHistory($filters = []) {
        $query = "SELECT s.*, p.name as plan_name, c.name as controller_name,
                  v.code as voucher_code,
                  TIMESTAMPDIFF(MINUTE, s.start_time, COALESCE(s.end_time, NOW())) as duration_minutes
                  FROM " . $this->table . " s
                  LEFT JOIN plans p ON s.plan_id = p.id
                  LEFT JOIN controllers c ON s.controller_id = c.id
                  LEFT JOIN vouchers v ON s.voucher_id = v.id
                  WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['controller_id'])) {
            $query .= " AND s.controller_id = :controller_id";
            $params[':controller_id'] = $filters['controller_id'];
        }
        
        if (isset($filters['mac_address'])) {
            $query .= " AND s.mac_address = :mac_address";
            $params[':mac_address'] = $filters['mac_address'];
        }
        
        if (isset($filters['status'])) {
            $query .= " AND s.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (isset($filters['start_date'])) {
            $query .= " AND s.start_time >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $query .= " AND s.start_time <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        $query .= " ORDER BY s.start_time DESC";
        
        if (isset($filters['limit'])) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if (isset($filters['limit'])) {
            $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStats($controller_id = null, $start_date = null, $end_date = null) {
        $query = "SELECT 
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_sessions,
                    SUM(data_used_mb) as total_data_used_mb,
                    AVG(duration_minutes) as avg_duration_minutes,
                    SUM(duration_minutes) as total_duration_minutes
                  FROM " . $this->table . "
                  WHERE 1=1";
        
        $params = [];
        
        if ($controller_id) {
            $query .= " AND controller_id = :controller_id";
            $params[':controller_id'] = $controller_id;
        }
        
        if ($start_date) {
            $query .= " AND start_time >= :start_date";
            $params[':start_date'] = $start_date;
        }
        
        if ($end_date) {
            $query .= " AND start_time <= :end_date";
            $params[':end_date'] = $end_date;
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function cleanupExpired() {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'expired',
                      end_time = NOW()
                  WHERE status = 'active' 
                  AND start_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }
}
?>