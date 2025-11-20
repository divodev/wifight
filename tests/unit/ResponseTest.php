<?php
/**
 * Response Utility Test
 */

namespace Tests\Unit;

use Tests\TestCase;

require_once __DIR__ . '/../../backend/utils/Response.php';

class ResponseTest extends TestCase
{
    private \Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->response = new \Response();
    }

    public function testSuccessResponseStructure()
    {
        ob_start();
        $this->response->success(['user' => 'test'], 'Operation successful');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKeys(['success', 'message', 'data', 'timestamp'], $data);
        $this->assertTrue($data['success']);
        $this->assertEquals('Operation successful', $data['message']);
        $this->assertArrayHasKey('user', $data['data']);
    }

    public function testErrorResponseStructure()
    {
        ob_start();
        $this->response->error('Error occurred', 400, ['field' => 'error']);
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKeys(['success', 'message', 'errors', 'timestamp'], $data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Error occurred', $data['message']);
        $this->assertArrayHasKey('field', $data['errors']);
    }

    public function testPaginatedResponseStructure()
    {
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2']
        ];

        ob_start();
        $this->response->paginated($items, 100, 1, 20);
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('pagination', $data);

        $pagination = $data['pagination'];
        $this->assertEquals(100, $pagination['total']);
        $this->assertEquals(2, $pagination['count']);
        $this->assertEquals(20, $pagination['per_page']);
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(5, $pagination['total_pages']);
        $this->assertTrue($pagination['has_more']);
    }

    public function testPaginationCalculations()
    {
        ob_start();
        $this->response->paginated([], 50, 3, 10);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $pagination = $data['pagination'];

        $this->assertEquals(5, $pagination['total_pages']); // 50 / 10
        $this->assertTrue($pagination['has_more']); // page 3 of 5
    }

    public function testLastPageHasNoMore()
    {
        ob_start();
        $this->response->paginated([], 50, 5, 10);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $pagination = $data['pagination'];

        $this->assertFalse($pagination['has_more']); // last page
    }

    public function testValidationErrorResponse()
    {
        $errors = [
            'email' => 'Email is required',
            'password' => 'Password must be at least 8 characters'
        ];

        ob_start();
        $this->response->validationError($errors);
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Validation failed', $data['message']);
        $this->assertEquals($errors, $data['errors']);
    }

    public function testUnauthorizedResponse()
    {
        ob_start();
        $this->response->unauthorized('Invalid credentials');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Invalid credentials', $data['message']);
    }

    public function testForbiddenResponse()
    {
        ob_start();
        $this->response->forbidden('Access denied');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Access denied', $data['message']);
    }

    public function testNotFoundResponse()
    {
        ob_start();
        $this->response->notFound('User not found');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('User not found', $data['message']);
    }

    public function testServerErrorResponse()
    {
        ob_start();
        $this->response->serverError('Database connection failed');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Database connection failed', $data['message']);
    }

    public function testResponseIncludesTimestamp()
    {
        ob_start();
        $this->response->success();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertArrayHasKey('timestamp', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['timestamp']);
    }

    public function testJsonEncodingOptions()
    {
        ob_start();
        $this->response->success(['url' => 'https://example.com/path']);
        $output = ob_get_clean();

        // Verify JSON is pretty printed and slashes are not escaped
        $this->assertStringContainsString("https://example.com/path", $output);
        $this->assertStringNotContainsString("\\/", $output); // No escaped slashes
    }
}
