<?php

require_once __DIR__ . '/../models/SupportTicket.php';
require_once __DIR__ . '/../middleware/authorize.php';

class SupportController {
    private $tickets;

    public function __construct() {
        $this->tickets = new SupportTicket();
    }

    /**
     * Server-rendered, printable HTML view of a ticket thread, opened by
     * support agents from the "Printable view" link.
     */
    public function render($ticketId) {
        $ticket = $this->tickets->getById($ticketId);
        header('Content-Type: text/html; charset=UTF-8');

        if (!$ticket) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><body style="background:#0A0A0B;color:#fff;font-family:sans-serif;padding:40px">Ticket not found.</body></html>';
            exit;
        }

        $rows = '';
        foreach ($ticket['messages'] as $m) {
            $who = $m['sender_role'] === 'admin' ? 'Support Team' : $ticket['user_name'];
            $rows .= '<div class="msg ' . $m['sender_role'] . '">'
                   . '<div class="who">' . $who . ' &middot; ' . $m['created_at'] . '</div>'
                   . '<div class="body">' . $m['message'] . '</div>'
                   . '</div>';
        }

        $id = $ticket['id'];
        $subject = $ticket['subject'];
        $name = $ticket['user_name'];
        $email = $ticket['user_email'];
        $status = $ticket['status'];

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket #$id &middot; $subject</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0A0A0B; color: #FAFAFA; min-height: 100vh; }
    .container { max-width: 720px; margin: 0 auto; padding: 40px 24px; }
    h1 { font-size: 22px; font-weight: 700; }
    h1 span { background: linear-gradient(135deg, #8B5CF6, #C084FC); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .subject { font-size: 18px; color: #E4E4E7; margin: 6px 0 2px; }
    .meta { color: #71717A; font-size: 13px; margin-bottom: 24px; }
    .thread { display: flex; flex-direction: column; gap: 12px; }
    .msg { background: #18181B; border: 1px solid #27272A; border-radius: 12px; padding: 14px 16px; }
    .msg.admin { background: #1a1730; border-color: #8B5CF640; }
    .who { color: #71717A; font-size: 12px; margin-bottom: 4px; }
    .body { color: #D4D4D8; font-size: 14px; white-space: pre-wrap; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Ticket <span>#$id</span></h1>
    <p class="subject">$subject</p>
    <p class="meta">From $name &lt;$email&gt; &middot; status: $status</p>
    <div class="thread">$rows</div>
  </div>
</body>
</html>
HTML;
        exit;
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
