<?php
/**
 * WiFight ISP System - Encryption Utility
 *
 * Provides encryption/decryption functionality for sensitive data
 */

class Encryption {
    private $key;
    private $cipher = 'AES-256-CBC';
    private $hashAlgo = 'sha256';

    public function __construct() {
        $this->key = $this->getEncryptionKey();
    }

    /**
     * Get encryption key from environment or generate one
     *
     * @return string Encryption key
     */
    private function getEncryptionKey() {
        $key = getenv('ENCRYPTION_KEY');

        if (!$key) {
            // Use JWT secret as fallback (not ideal but better than nothing)
            $key = getenv('JWT_SECRET');
        }

        if (!$key) {
            throw new Exception('Encryption key not configured. Set ENCRYPTION_KEY in .env');
        }

        // Derive a proper key from the secret
        return hash($this->hashAlgo, $key, true);
    }

    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     * @throws Exception
     */
    public function encrypt($data) {
        if (empty($data)) {
            return '';
        }

        // Generate random IV (Initialization Vector)
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);

        // Encrypt the data
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }

        // Combine IV and encrypted data, then base64 encode
        $result = base64_encode($iv . $encrypted);

        return $result;
    }

    /**
     * Decrypt data
     *
     * @param string $encryptedData Encrypted data (base64 encoded)
     * @return string|null Decrypted data or null on failure
     */
    public function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return '';
        }

        try {
            // Decode from base64
            $data = base64_decode($encryptedData, true);

            if ($data === false) {
                return null;
            }

            // Extract IV and encrypted data
            $ivLength = openssl_cipher_iv_length($this->cipher);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);

            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );

            return $decrypted !== false ? $decrypted : null;

        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Hash data (one-way)
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public function hash($data) {
        return hash($this->hashAlgo, $data);
    }

    /**
     * Generate HMAC for data integrity
     *
     * @param string $data Data to sign
     * @return string HMAC signature
     */
    public function hmac($data) {
        return hash_hmac($this->hashAlgo, $data, $this->key);
    }

    /**
     * Verify HMAC signature
     *
     * @param string $data Original data
     * @param string $signature HMAC signature to verify
     * @return bool True if valid
     */
    public function verifyHMAC($data, $signature) {
        $expected = $this->hmac($data);
        return hash_equals($expected, $signature);
    }

    /**
     * Generate random token
     *
     * @param int $length Token length in bytes (will be hex encoded, so output is 2x)
     * @return string Random token
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate random password
     *
     * @param int $length Password length
     * @param bool $includeSpecialChars Include special characters
     * @return string Random password
     */
    public function generatePassword($length = 16, $includeSpecialChars = true) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        if ($includeSpecialChars) {
            $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }

        $password = '';
        $charsLength = strlen($chars);

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }

        return $password;
    }

    /**
     * Encrypt array or object
     *
     * @param mixed $data Array or object to encrypt
     * @return string Encrypted JSON
     */
    public function encryptObject($data) {
        $json = json_encode($data);
        return $this->encrypt($json);
    }

    /**
     * Decrypt to array or object
     *
     * @param string $encryptedData Encrypted JSON
     * @param bool $assoc Return as associative array
     * @return mixed Decrypted data
     */
    public function decryptObject($encryptedData, $assoc = true) {
        $json = $this->decrypt($encryptedData);

        if ($json === null) {
            return null;
        }

        return json_decode($json, $assoc);
    }

    /**
     * Encrypt file
     *
     * @param string $inputFile Input file path
     * @param string $outputFile Output file path
     * @return bool Success status
     */
    public function encryptFile($inputFile, $outputFile) {
        if (!file_exists($inputFile)) {
            return false;
        }

        try {
            $data = file_get_contents($inputFile);
            $encrypted = $this->encrypt($data);
            file_put_contents($outputFile, $encrypted);
            return true;
        } catch (Exception $e) {
            error_log('File encryption error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypt file
     *
     * @param string $inputFile Encrypted file path
     * @param string $outputFile Output file path
     * @return bool Success status
     */
    public function decryptFile($inputFile, $outputFile) {
        if (!file_exists($inputFile)) {
            return false;
        }

        try {
            $encrypted = file_get_contents($inputFile);
            $data = $this->decrypt($encrypted);

            if ($data === null) {
                return false;
            }

            file_put_contents($outputFile, $data);
            return true;
        } catch (Exception $e) {
            error_log('File decryption error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mask sensitive data for display
     *
     * @param string $data Data to mask
     * @param int $visibleChars Number of characters to show at start/end
     * @param string $maskChar Character to use for masking
     * @return string Masked data
     */
    public function mask($data, $visibleChars = 4, $maskChar = '*') {
        $length = strlen($data);

        if ($length <= $visibleChars * 2) {
            return str_repeat($maskChar, $length);
        }

        $start = substr($data, 0, $visibleChars);
        $end = substr($data, -$visibleChars);
        $masked = str_repeat($maskChar, $length - ($visibleChars * 2));

        return $start . $masked . $end;
    }

    /**
     * Securely compare strings (timing attack safe)
     *
     * @param string $known Known string
     * @param string $user User-provided string
     * @return bool True if equal
     */
    public function secureCompare($known, $user) {
        return hash_equals($known, $user);
    }

    /**
     * Generate API key
     *
     * @param string $prefix Prefix for API key
     * @return string API key
     */
    public function generateAPIKey($prefix = 'wf_') {
        return $prefix . $this->generateToken(32);
    }

    /**
     * Encrypt database connection credentials
     *
     * @param array $credentials Database credentials
     * @return string Encrypted credentials
     */
    public function encryptDBCredentials($credentials) {
        return $this->encryptObject($credentials);
    }

    /**
     * Decrypt database connection credentials
     *
     * @param string $encryptedCredentials Encrypted credentials
     * @return array|null Database credentials
     */
    public function decryptDBCredentials($encryptedCredentials) {
        return $this->decryptObject($encryptedCredentials);
    }
}
