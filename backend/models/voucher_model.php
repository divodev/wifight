<?php
class Voucher {
    private $conn;
    private $table = 'vouchers';
    
    public $id;
    public $code;
    public $plan_id;
    public $batch_id;
    public $status;
    public $price;
    public $duration_hours;
    public $data_limit_mb;
    public $expires_at;
    public $created_at;
    public $used_at;
    public $used_by;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function generate($plan_id, $quantity, $batch_name = null) {
        $vouchers = [];
        $batch_id = uniqid('batch_');
        
        // Get plan details
        $query = "SELECT * FROM plans WHERE id = :plan_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':plan_id', $plan_id);
        $stmt->execute();
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            return false;
        }
        
        $expiry_days = defined('VOUCHER_EXPIRY_DAYS') ? VOUCHER_EXPIRY_DAYS : 30;
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
        
        $query = "INSERT INTO " . $this->table . " 
                  (code, plan_id, batch_id, price, duration_hours, data_limit_mb, 
                   expires_at, status, created_at) 
                  VALUES (:code, :plan_id, :batch_id, :price, :duration_hours, 
                  :data_limit_mb, :expires_at, 'unused', NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        for ($i = 0; $i < $quantity; $i++) {
            $code = $this->generateCode();
            
            $stmt->bindParam(':code', $code);
            $stmt->bindParam(':plan_id', $plan_id);
            $stmt->bindParam(':batch_id', $batch_id);
            $stmt->bindParam(':price', $plan['price']);
            $stmt->bindParam(':duration_hours', $plan['duration_hours']);
            $stmt->bindParam(':data_limit_mb', $plan['data_limit_mb']);
            $stmt->bindParam(':expires_at', $expires_at);
            
            if ($stmt->execute()) {
                $vouchers[] = [
                    'id' => $this->conn->lastInsertId(),
                    'code' => $code,
                    'plan_name' => $plan['name'],
                    'price' => $plan['price'],
                    'duration_hours' => $plan['duration_hours'],
                    'data_limit_mb' => $plan['data_limit_mb'],
                    'expires_at' => $expires_at
                ];
            }
        }
        
        return [
            'batch_id' => $batch_id,
            'batch_name' => $batch_name ?: $batch_id,
            'vouchers' => $vouchers
        ];
    }
    
    private function generateCode() {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        
        for ($i = 0; $i < 12; $i++) {
            if ($i > 0 && $i % 4 == 0) {
                $code .= '-';
            }
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if code exists
        $query = "SELECT id FROM " . $this->table . " WHERE code = :code LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $this->generateCode(); // Recursive call if duplicate
        }
        
        return $code;
    }
    
    public function validate($code) {
        $query = "SELECT v.*, p.name as plan_name, p.bandwidth_up, p.bandwidth_down 
                  FROM " . $this->table . " v
                  LEFT JOIN plans p ON v.plan_id = p.id
                  WHERE v.code = :code 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            return ['valid' => false, 'message' => 'Invalid voucher code'];
        }
        
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($voucher['status'] == 'used') {
            return ['valid' => false, 'message' => 'Voucher already used'];
        }
        
        if ($voucher['status'] == 'expired') {
            return ['valid' => false, 'message' => 'Voucher has expired'];
        }
        
        if (strtotime($voucher['expires_at']) < time()) {
            $this->updateStatus($voucher['id'], 'expired');
            return ['valid' => false, 'message' => 'Voucher has expired'];
        }
        
        return ['valid' => true, 'voucher' => $voucher];
    }
    
    public function redeem($code, $mac_address = null, $user_info = null) {
        $validation = $this->validate($code);
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        $voucher = $validation['voucher'];
        
        $query = "UPDATE " . $this->table . " 
                  SET status = 'used', 
                      used_at = NOW(),
                      used_by = :mac_address 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $voucher['id']);
        $stmt->bindParam(':mac_address', $mac_address);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Voucher redeemed successfully',
                'voucher' => $voucher
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to redeem voucher'];
    }
    
    public function getAll($filters = []) {
        $query = "SELECT v.*, p.name as plan_name 
                  FROM " . $this->table . " v
                  LEFT JOIN plans p ON v.plan_id = p.id
                  WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $query .= " AND v.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (isset($filters['plan_id'])) {
            $query .= " AND v.plan_id = :plan_id";
            $params[':plan_id'] = $filters['plan_id'];
        }
        
        if (isset($filters['batch_id'])) {
            $query .= " AND v.batch_id = :batch_id";
            $params[':batch_id'] = $filters['batch_id'];
        }
        
        $query .= " ORDER BY v.created_at DESC";
        
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
    
    public function getStats($location_id = null) {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused,
                    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN status = 'used' THEN price ELSE 0 END) as total_revenue
                  FROM " . $this->table;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        return $stmt->execute();
    }
    
    public function expireOldVouchers() {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'expired' 
                  WHERE status = 'unused' 
                  AND expires_at < NOW()";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }
}
?>