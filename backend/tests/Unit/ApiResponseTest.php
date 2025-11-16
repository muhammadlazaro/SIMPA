<?php

namespace Tests\Unit;

use App\Http\Helpers\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    /**
     * ID 2.1: Method success() pada class ApiResponse dengan data valid
     * Input: data = ['id' => 1], message = 'Success'
     * Expected Output: JSON response dengan success = true
     */
    public function test_success_returns_correct_json_structure()
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $message = 'Success';
        
        $response = ApiResponse::success($data, $message);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($data, $responseData['data']);
    }
    
    /**
     * ID 2.2: Method success() pada class ApiResponse tanpa data
     * Input: data = null, message = 'Success'
     * Expected Output: JSON response dengan success = true, data = null
     */
    public function test_success_returns_correct_json_without_data()
    {
        $message = 'Success';
        
        $response = ApiResponse::success(null, $message);
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertNull($responseData['data']);
    }
    
    /**
     * ID 3.1: Method error() pada class ApiResponse dengan message
     * Input: message = 'Error occurred'
     * Expected Output: JSON response dengan success = false
     */
    public function test_error_returns_correct_json_structure()
    {
        $message = 'Error occurred';
        
        $response = ApiResponse::error($message);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
    }
    
    /**
     * ID 3.2: Method error() pada class ApiResponse dengan errors
     * Input: message = 'Validation error', errors = ['email' => 'Invalid']
     * Expected Output: JSON response dengan success = false dan errors
     */
    public function test_error_returns_correct_json_with_errors()
    {
        $message = 'Validation error';
        $errors = ['email' => 'Invalid email format'];
        
        $response = ApiResponse::error($message, $errors);
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($errors, $responseData['errors']);
    }
    
    /**
     * ID 4.1: Method notFound() pada class ApiResponse
     * Input: message = 'Resource not found'
     * Expected Output: JSON response dengan status code 404
     */
    public function test_not_found_returns_404_status_code()
    {
        $message = 'Resource not found';
        
        $response = ApiResponse::notFound($message);
        
        $this->assertEquals(404, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
    }
    
    /**
     * ID 4.2: Method unauthorized() pada class ApiResponse
     * Input: message = 'Unauthorized'
     * Expected Output: JSON response dengan status code 401
     */
    public function test_unauthorized_returns_401_status_code()
    {
        $message = 'Unauthorized';
        
        $response = ApiResponse::unauthorized($message);
        
        $this->assertEquals(401, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
    }
    
    /**
     * ID 4.3: Method created() pada class ApiResponse
     * Input: data = ['id' => 1], message = 'Created'
     * Expected Output: JSON response dengan status code 201
     */
    public function test_created_returns_201_status_code()
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $message = 'Resource created successfully';
        
        $response = ApiResponse::created($data, $message);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($data, $responseData['data']);
    }
    
    /**
     * ID 4.4: Method forbidden() pada class ApiResponse
     * Input: message = 'Forbidden'
     * Expected Output: JSON response dengan status code 403
     */
    public function test_forbidden_returns_403_status_code()
    {
        $message = 'Forbidden';
        
        $response = ApiResponse::forbidden($message);
        
        $this->assertEquals(403, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
    }
    
    /**
     * ID 3.3: Method validationError() pada class ApiResponse
     * Input: errors = ['email' => 'Invalid'], message = 'Validation error'
     * Expected Output: JSON response dengan status code 422
     */
    public function test_validation_error_returns_422_status_code()
    {
        $errors = ['email' => 'Invalid email format'];
        $message = 'Validation error';
        
        $response = ApiResponse::validationError($errors, $message);
        
        $this->assertEquals(422, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals($message, $responseData['message']);
        $this->assertEquals($errors, $responseData['errors']);
    }
    
    /**
     * ID 2.3: Method noContent() pada class ApiResponse
     * Input: (tidak ada parameter)
     * Expected Output: JSON response dengan status code 204
     */
    public function test_no_content_returns_204_status_code()
    {
        $response = ApiResponse::noContent();
        
        $this->assertEquals(204, $response->getStatusCode());
        // 204 No Content - content bisa null, empty string, atau "null" (JSON encoded)
        // Yang penting adalah status code 204
        $this->assertNotNull($response);
    }
}

