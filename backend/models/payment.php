<?php
class Payment {
    private $conn;
    private $table = 'payments';
    
    public $id;
    public $transaction_id;
    public $user_id;
    public $plan_id;
    public $voucher_id;
    public $amount;
    public $currency;
    public $payment_method;
    public $status;
    public $gateway_response;
    public $customer_email;
    public $customer_phone;
    public $created_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET transaction_id = :transaction_id,
                      user_id = :user_id,
                      plan_id = :plan_id,
                      voucher_id = :voucher_id,
                      amount = :amount,
                      currency = :currency,
                      payment_method = :payment_method,
                      status = :status,
                      gateway_response = :gateway_response,
                      customer_email = :customer_email,
                      customer_phone = :customer_phone,
                      created_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        $this->transaction_id = htmlspecialchars(strip_tags($this->transaction_id));
        
        $stmt->bindParam(':transaction_id', $this->transaction_id);
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':plan_id', $this->plan_id);
        $stmt->bindParam(':voucher_id', $this->voucher_id);
        $stmt->bindParam(':amount', $this->amount);
        $stmt->bindParam(':currency', $this->currency);
        $stmt->bindParam(':payment_method', $this->payment_method);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':gateway_response', $this->gateway_response);
        $stmt->bindParam(':customer_email', $this->customer_email);
        $stmt->bindParam(':customer_phone', $this->customer_phone);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getByTransactionId($transactionId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE transaction_id = :transaction_id 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($id, $status, $gatewayResponse = null) {
        $query = "UPDATE " . $this->table . " 
                  SET status = :status,
                      gateway_response = :gateway_response,
                      updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':gateway_response', $gatewayResponse);
        
        return $stmt->execute();
    }
    
    public function getHistory($filters = []) {
        $query = "SELECT p.*, pl.name as plan_name 
                  FROM " . $this->table . " p
                  LEFT JOIN plans pl ON p.plan_id = pl.id
                  WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $query .= " AND p.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['status'])) {
            $query .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (isset($filters['payment_method'])) {
            $query .= " AND p.payment_method = :payment_method";
            $params[':payment_method'] = $filters['payment_method'];
        }
        
        if (isset($filters['start_date'])) {
            $query .= " AND p.created_at >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $query .= " AND p.created_at <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        $query .= " ORDER BY p.created_at DESC";
        
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
    
    public function getStats($startDate = null, $endDate = null) {
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as average_transaction
                  FROM " . $this->table . "
                  WHERE 1=1";
        
        $params = [];
        
        if ($startDate) {
            $query .= " AND created_at >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $query .= " AND created_at <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>