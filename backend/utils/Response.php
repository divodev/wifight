<?php
/**
 * WiFight ISP System - Response Utility
 *
 * Standardized API response formatter
 */

class Response {

    /**
     * Send success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $code HTTP status code
     * @return void
     */
    public function success($data = null, $message = 'Success', $code = 200) {
        $this->send([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }

    /**
     * Send error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $errors Additional error details
     * @return void
     */
    public function error($message = 'An error occurred', $code = 400, $errors = []) {
        $this->send([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }

    /**
     * Send paginated response
     *
     * @param array $data Response data
     * @param int $total Total records
     * @param int $page Current page
     * @param int $perPage Records per page
     * @return void
     */
    public function paginated($data, $total, $page = 1, $perPage = 20) {
        $this->send([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'count' => count($data),
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Send validation error response
     *
     * @param array $errors Validation errors
     * @return void
     */
    public function validationError($errors) {
        $this->error('Validation failed', 422, $errors);
    }

    /**
     * Send unauthorized response
     *
     * @param string $message Error message
     * @return void
     */
    public function unauthorized($message = 'Unauthorized') {
        $this->error($message, 401);
    }

    /**
     * Send forbidden response
     *
     * @param string $message Error message
     * @return void
     */
    public function forbidden($message = 'Forbidden') {
        $this->error($message, 403);
    }

    /**
     * Send not found response
     *
     * @param string $message Error message
     * @return void
     */
    public function notFound($message = 'Resource not found') {
        $this->error($message, 404);
    }

    /**
     * Send internal server error response
     *
     * @param string $message Error message
     * @return void
     */
    public function serverError($message = 'Internal server error') {
        $this->error($message, 500);
    }

    /**
     * Send custom response
     *
     * @param array $data Response data
     * @param int $code HTTP status code
     * @return void
     */
    private function send($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send file download response
     *
     * @param string $filepath Path to file
     * @param string $filename Download filename
     * @return void
     */
    public function download($filepath, $filename = null) {
        if (!file_exists($filepath)) {
            $this->notFound('File not found');
        }

        $filename = $filename ?: basename($filepath);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        readfile($filepath);
        exit;
    }
}
