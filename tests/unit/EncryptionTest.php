<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/utils/Encryption.php';

class EncryptionTest extends TestCase
{
    private Encryption $encryption;

    protected function setUp(): void
    {
        $this->encryption = new Encryption();
    }

    public function testEncryptDecrypt()
    {
        $plaintext = 'Sensitive data to encrypt';

        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
        $this->assertNotEquals($plaintext, $encrypted);
    }

    public function testEncryptedDataIsDifferent()
    {
        $plaintext = 'Test data';

        $encrypted1 = $this->encryption->encrypt($plaintext);
        $encrypted2 = $this->encryption->encrypt($plaintext);

        // Same plaintext should produce different ciphertext due to random IV
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to same plaintext
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted1));
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted2));
    }

    public function testDecryptInvalidData()
    {
        $this->expectException(Exception::class);
        $this->encryption->decrypt('invalid-encrypted-data');
    }

    public function testTokenGeneration()
    {
        $token = $this->encryption->generateToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
    }

    public function testTokensAreUnique()
    {
        $token1 = $this->encryption->generateToken();
        $token2 = $this->encryption->generateToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function testMaskSensitiveData()
    {
        $email = 'john.doe@example.com';
        $masked = $this->encryption->mask($email);

        $this->assertStringContainsString('***', $masked);
        $this->assertNotEquals($email, $masked);
    }

    public function testHashData()
    {
        $data = 'password123';
        $hash = $this->encryption->hash($data);

        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash)); // SHA256 produces 64 hex characters
    }

    public function testVerifyHash()
    {
        $data = 'password123';
        $hash = $this->encryption->hash($data);

        $this->assertTrue($this->encryption->verifyHash($data, $hash));
        $this->assertFalse($this->encryption->verifyHash('wrongpassword', $hash));
    }
}
