<?php

require_once __DIR__ . '/../../TestCase.php';
require_once __DIR__ . '/../../../backend/utils/Validator.php';

use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testEmailValidation()
    {
        // Valid email
        $result = Validator::validate(['email' => 'test@example.com'], [
            'email' => 'required|email'
        ]);
        $this->assertTrue($result);

        // Invalid email
        $result = Validator::validate(['email' => 'invalid-email'], [
            'email' => 'required|email'
        ]);
        $this->assertFalse($result);
    }

    public function testRequiredValidation()
    {
        // Field present
        $result = Validator::validate(['name' => 'John'], [
            'name' => 'required'
        ]);
        $this->assertTrue($result);

        // Field missing
        $result = Validator::validate([], [
            'name' => 'required'
        ]);
        $this->assertFalse($result);
    }

    public function testPasswordStrength()
    {
        // Strong password
        $result = Validator::validatePasswordStrength('MyP@ssw0rd123');
        $this->assertTrue($result);

        // Weak password
        $result = Validator::validatePasswordStrength('password');
        $this->assertFalse($result);
    }
}
