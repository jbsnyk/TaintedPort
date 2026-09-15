<?php

require_once __DIR__ . '/../config/database.php';

class SupportTicket {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($userId, $subject, $message) {
        $stmt = $this->db->prepare('INSERT INTO support_tickets (user_id, subject) VALUES (:user_id, :subject)');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':subject', $subject, SQLITE3_TEXT);
        $stmt->execute();
        $ticketId = $this->db->lastInsertRowID();

        $this->addMessage($ticketId, 'user', $userId, $message);

        return $ticketId;
    }

    public function getByUser($userId) {
        $stmt = $this->db->prepare(
            'SELECT t.*, (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) as message_count
             FROM support_tickets t WHERE t.user_id = :user_id ORDER BY t.updated_at DESC'
        );
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $tickets = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tickets[] = $row;
        }
        return $tickets;
    }

    public function getAll() {
        $result = $this->db->query(
            'SELECT t.*, u.name as user_name, u.email as user_email,
                    (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) as message_count
             FROM support_tickets t
             JOIN users u ON t.user_id = u.id
             ORDER BY t.updated_at DESC'
        );

        $tickets = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tickets[] = $row;
        }
        return $tickets;
    }

    public function getById($id) {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.name as user_name, u.email as user_email
             FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = :id'
        );
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $result = $stmt->execute();
        $ticket = $result->fetchArray(SQLITE3_ASSOC);
        if (!$ticket) return null;

        $stmt = $this->db->prepare('SELECT * FROM support_messages WHERE ticket_id = :id ORDER BY created_at ASC');
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $result = $stmt->execute();

        $messages = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $messages[] = $row;
        }
        $ticket['messages'] = $messages;
        return $ticket;
    }

    public function addMessage($ticketId, $senderRole, $senderId, $message) {
        $stmt = $this->db->prepare(
            'INSERT INTO support_messages (ticket_id, sender_role, sender_id, message) VALUES (:ticket_id, :role, :sender_id, :message)'
        );
        $stmt->bindValue(':ticket_id', intval($ticketId), SQLITE3_INTEGER);
        $stmt->bindValue(':role', $senderRole, SQLITE3_TEXT);
        $stmt->bindValue(':sender_id', intval($senderId), SQLITE3_INTEGER);
        $stmt->bindValue(':message', $message, SQLITE3_TEXT);
        $stmt->execute();

        $touch = $this->db->prepare('UPDATE support_tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $touch->bindValue(':id', intval($ticketId), SQLITE3_INTEGER);
        $touch->execute();

        return $this->db->lastInsertRowID();
    }

    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare('UPDATE support_tickets SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':id', intval($id), SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }
}
