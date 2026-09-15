<?php

require_once __DIR__ . '/../models/SupportTicket.php';
require_once __DIR__ . '/../middleware/authorize.php';

class SupportController {
    private $tickets;

    public function __construct() {
        $this->tickets = new SupportTicket();
    }

    // --- Customer-facing ---

    public function create($authUser) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['subject']) || empty($data['message'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'subject and message are required.'];
        }

        $id = $this->tickets->create($authUser['user_id'], $data['subject'], $data['message']);
        http_response_code(201);
        return ['success' => true, 'message' => 'Ticket created.', 'ticket_id' => $id];
    }

    public function index($authUser) {
        return ['success' => true, 'tickets' => $this->tickets->getByUser($authUser['user_id'])];
    }

    public function show($authUser, $id) {
        $ticket = $this->tickets->getById($id);
        if (!$ticket || $ticket['user_id'] != $authUser['user_id']) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Ticket not found.'];
        }
        return ['success' => true, 'ticket' => $ticket];
    }

    public function reply($authUser, $id) {
        $ticket = $this->tickets->getById($id);
        if (!$ticket || $ticket['user_id'] != $authUser['user_id']) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Ticket not found.'];
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['message'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'message is required.'];
        }

        if ($ticket['status'] === 'closed') {
            $this->tickets->updateStatus($id, 'open');
        }

        $this->tickets->addMessage($id, 'user', $authUser['user_id'], $data['message']);
        return ['success' => true, 'message' => 'Reply sent.'];
    }

    // --- Staff-facing (admin + support roles) ---

    public function adminIndex($authUser) {
        requireRole($authUser, ['admin', 'support']);
        return ['success' => true, 'tickets' => $this->tickets->getAll()];
    }

    public function adminShow($authUser, $id) {
        requireRole($authUser, ['admin', 'support']);
        $ticket = $this->tickets->getById($id);
        if (!$ticket) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Ticket not found.'];
        }
        return ['success' => true, 'ticket' => $ticket];
    }

    public function adminReply($authUser, $id) {
        requireRole($authUser, ['admin', 'support']);
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['message'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'message is required.'];
        }

        if (!$this->tickets->getById($id)) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Ticket not found.'];
        }

        $this->tickets->addMessage($id, 'admin', $authUser['user_id'], $data['message']);
        $this->tickets->updateStatus($id, 'in_progress');
        return ['success' => true, 'message' => 'Reply sent.'];
    }

    public function updateStatus($authUser, $id) {
        requireRole($authUser, ['admin', 'support']);
        $data = json_decode(file_get_contents('php://input'), true);

        $validStatuses = ['open', 'in_progress', 'closed'];
        if (empty($data['status']) || !in_array($data['status'], $validStatuses, true)) {
            http_response_code(400);
            return ['success' => false, 'message' => 'status must be one of: ' . implode(', ', $validStatuses)];
        }

        if (!$this->tickets->updateStatus($id, $data['status'])) {
            http_response_code(404);
            return ['success' => false, 'message' => 'Ticket not found.'];
        }

        return ['success' => true, 'message' => 'Status updated.'];
    }
}
