<?php
/**
 * WiFight ISP System - Validator Utility
 *
 * Input validation and sanitization
 */

class Validator {
    private $data;
    private $errors = [];
    private $rules = [];

    public function __construct($data = []) {
        $this->data = $data;
    }

    /**
     * Set validation rules
     *
     * @param array $rules
     * @return self
     */
    public function setRules($rules) {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Validate data against rules
     *
     * @return bool
     */
    public function validate() {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply single validation rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return void
     */
    private function applyRule($field, $value, $rule) {
        // Parse rule and parameters
        $params = [];
        if (strpos($rule, ':') !== false) {
            list($rule, $paramString) = explode(':', $rule, 2);
            $params = explode(',', $paramString);
        }

        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "{$field} is required");
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$field} must be a valid email address");
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < $params[0]) {
                    $this->addError($field, "{$field} must be at least {$params[0]} characters");
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > $params[0]) {
                    $this->addError($field, "{$field} must not exceed {$params[0]} characters");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "{$field} must be numeric");
                }
                break;

            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "{$field} must be an integer");
                }
                break;

            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, "{$field} must contain only letters");
                }
                break;

            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, "{$field} must contain only letters and numbers");
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "{$field} must be a valid URL");
                }
                break;

            case 'ip':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
                    $this->addError($field, "{$field} must be a valid IP address");
                }
                break;

            case 'mac':
                if (!empty($value) && !$this->validateMacAddress($value)) {
                    $this->addError($field, "{$field} must be a valid MAC address");
                }
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, "{$field} must be one of: " . implode(', ', $params));
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match($params[0], $value)) {
                    $this->addError($field, "{$field} format is invalid");
                }
                break;

            case 'unique':
                // TODO: Implement database unique check
                break;

            case 'exists':
                // TODO: Implement database exists check
                break;
        }
    }

    /**
     * Validate MAC address
     *
     * @param string $mac
     * @return bool
     */
    private function validateMacAddress($mac) {
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac);
    }

    /**
     * Add validation error
     *
     * @param string $field
     * @param string $message
     * @return void
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get first error for a field
     *
     * @param string $field
     * @return string|null
     */
    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Sanitize input data
     *
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    public static function sanitize($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }

        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);

            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);

            case 'int':
            case 'integer':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);

            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            case 'html':
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

            case 'string':
            default:
                return strip_tags(trim($data));
        }
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @param int $minLength
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validatePassword($password, $minLength = 8) {
        $errors = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
