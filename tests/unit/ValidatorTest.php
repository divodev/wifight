<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../backend/utils/Validator.php';

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testRequiredValidation()
    {
        $data = ['name' => 'John'];
        $rules = ['name' => 'required'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testRequiredValidationFails()
    {
        $data = ['name' => ''];
        $rules = ['name' => 'required'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testEmailValidation()
    {
        $data = ['email' => 'test@example.com'];
        $rules = ['email' => 'email'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testEmailValidationFails()
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => 'email'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testMinValidation()
    {
        $data = ['username' => 'john'];
        $rules = ['username' => 'min:3'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testMinValidationFails()
    {
        $data = ['username' => 'ab'];
        $rules = ['username' => 'min:3'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testMaxValidation()
    {
        $data = ['username' => 'john'];
        $rules = ['username' => 'max:10'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testNumericValidation()
    {
        $data = ['age' => '25'];
        $rules = ['age' => 'numeric'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testMacAddressValidation()
    {
        $data = ['mac' => '00:1A:2B:3C:4D:5E'];
        $rules = ['mac' => 'mac'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testMacAddressValidationFails()
    {
        $data = ['mac' => 'invalid-mac'];
        $rules = ['mac' => 'mac'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertArrayHasKey('mac', $errors);
    }

    public function testIpValidation()
    {
        $data = ['ip' => '192.168.1.1'];
        $rules = ['ip' => 'ip'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testUrlValidation()
    {
        $data = ['website' => 'https://example.com'];
        $rules = ['website' => 'url'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testInValidation()
    {
        $data = ['role' => 'admin'];
        $rules = ['role' => 'in:admin,user,reseller'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testInValidationFails()
    {
        $data = ['role' => 'superadmin'];
        $rules = ['role' => 'in:admin,user,reseller'];

        $errors = $this->validator->validate($data, $rules);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testMultipleRules()
    {
        $data = [
            'email' => 'test@example.com',
            'username' => 'johndoe',
            'age' => '25'
        ];
        $rules = [
            'email' => 'required|email',
            'username' => 'required|min:3|max:20',
            'age' => 'required|numeric'
        ];

        $errors = $this->validator->validate($data, $rules);
        $this->assertEmpty($errors);
    }

    public function testSanitizeInput()
    {
        $input = '<script>alert("XSS")</script>';
        $sanitized = $this->validator->sanitize($input);

        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    public function testPasswordStrength()
    {
        $strongPassword = 'SecureP@ssw0rd';
        $weakPassword = 'weak';

        $this->assertTrue($this->validator->isPasswordStrong($strongPassword));
        $this->assertFalse($this->validator->isPasswordStrong($weakPassword));
    }
}
