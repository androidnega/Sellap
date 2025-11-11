<?php

namespace App\Controllers;

use App\Models\Phone;
use App\Middleware\AuthMiddleware;

class PhoneController {
    private $model;

    public function __construct() {
        $this->model = new Phone();
    }

    /**
     * GET /api/phones - Get all phones
     */
    public function index() {
        AuthMiddleware::handle();
        header('Content-Type: application/json');
        echo json_encode($this->model->all());
    }

    /**
     * GET /api/phones/{id} - Get single phone
     */
    public function show($id) {
        AuthMiddleware::handle();
        header('Content-Type: application/json');
        
        $phone = $this->model->findById($id);
        if (!$phone) {
            http_response_code(404);
            echo json_encode(['error' => 'Phone not found']);
            return;
        }
        echo json_encode($phone);
    }

    /**
     * POST /api/phones - Create new phone
     */
    public function store() {
        AuthMiddleware::handle();
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['brand']) || empty($data['model']) || empty($data['value'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Brand, model, and value are required']);
            return;
        }

        $data['unique_id'] = 'PH' . strtoupper(uniqid());
        $data['status'] = 'AVAILABLE';
        
        $result = $this->model->create($data);
        
        http_response_code(201);
        echo json_encode([
            'success' => $result,
            'message' => 'Phone added successfully'
        ]);
    }

    /**
     * PUT /api/phones/{id} - Update phone
     */
    public function update($id) {
        AuthMiddleware::handle();
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $this->model->update($id, $data);
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Phone updated successfully' : 'Failed to update phone'
        ]);
    }

    /**
     * DELETE /api/phones/{id} - Delete phone
     */
    public function destroy($id) {
        AuthMiddleware::handle();
        header('Content-Type: application/json');
        
        $result = $this->model->delete($id);
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Phone deleted successfully' : 'Failed to delete phone'
        ]);
    }
}

