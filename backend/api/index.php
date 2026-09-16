<?php

// CORS headers
header('Content-Type: application/json');

// Permissive CORS - reflects any origin
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/WineController.php';
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/CartSnapshotController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/ReviewController.php';
require_once __DIR__ . '/controllers/PiCallbackController.php';
require_once __DIR__ . '/controllers/PasswordResetController.php';
require_once __DIR__ . '/controllers/PartnerController.php';
require_once __DIR__ . '/controllers/ReferralController.php';
require_once __DIR__ . '/controllers/ContactController.php';
require_once __DIR__ . '/controllers/DiscountController.php';
require_once __DIR__ . '/controllers/WishlistController.php';
require_once __DIR__ . '/controllers/SupportController.php';
require_once __DIR__ . '/controllers/GiftCardController.php';

// Parse the request URI
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api';

// Remove query string for routing
$path = urldecode(parse_url($requestUri, PHP_URL_PATH));

// Remove base path prefix if present
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

$method = $_SERVER['REQUEST_METHOD'];

// Contact preview returns HTML, not JSON — handle before the JSON router
if ($path === '/contact/preview' && $method === 'POST') {
    $ctrl = new ContactController();
    $ctrl->preview();
}

// Printable ticket view returns HTML, not JSON — handle before the JSON router
if (preg_match('#^/support/tickets/(\d+)/render$#', $path, $renderMatch) && $method === 'GET') {
    $ctrl = new SupportController();
    $ctrl->render($renderMatch[1]);
    // render() calls exit after sending the HTML
}

// Callback endpoint returns a GIF, not JSON — handle before the JSON router
if ($path === '/pi-callback' && ($method === 'GET' || $method === 'POST')) {
    $ctrl = new PiCallbackController();
    $ctrl->callback();
    // callback() calls exit after sending the GIF
}

// Simple router
$response = null;

try {
    // Auth routes
    if ($path === '/auth/register' && $method === 'POST') {
        $ctrl = new AuthController();
        $response = $ctrl->register();
    }
    elseif ($path === '/auth/login' && $method === 'POST') {
        $ctrl = new AuthController();
        $response = $ctrl->login();
    }
    elseif ($path === '/auth/me' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new AuthController();
        $response = $ctrl->me($authUser);
    }
    elseif ($path === '/auth/profile' && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new AuthController();
        $response = $ctrl->updateProfile($authUser);
    }
    elseif ($path === '/auth/email' && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new AuthController();
        $response = $ctrl->changeEmail($authUser);
    }
    elseif ($path === '/auth/password' && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new AuthController();
        $response = $ctrl->changePassword($authUser);
    }
    elseif ($path === '/auth/password/forgot' && $method === 'POST') {
        $ctrl = new PasswordResetController();
        $response = $ctrl->forgotPassword();
    }
    elseif ($path === '/auth/password/reset' && $method === 'POST') {
        $ctrl = new PasswordResetController();
        $response = $ctrl->resetPassword();
    }
    elseif ($path === '/partner/auth' && $method === 'POST') {
        $ctrl = new PartnerController();
        $response = $ctrl->auth();
    }
    elseif ($path === '/account/referral/redeem' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new ReferralController();
        $response = $ctrl->redeem($authUser);
    }
    // Wishlist routes (protected)
    elseif ($path === '/wishlist' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new WishlistController();
        $response = $ctrl->index($authUser);
    }
    elseif ($path === '/wishlist' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new WishlistController();
        $response = $ctrl->add($authUser);
    }
    elseif (preg_match('#^/wishlist/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $authUser = authenticateToken();
        $ctrl = new WishlistController();
        $response = $ctrl->remove($authUser, $matches[1]);
    }
    // Gift card routes (protected)
    elseif ($path === '/giftcards/welcome' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new GiftCardController();
        $response = $ctrl->welcome($authUser);
    }
    elseif ($path === '/giftcards/redeem' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new GiftCardController();
        $response = $ctrl->redeem($authUser);
    }
    // Discount code routes
    elseif ($path === '/discounts/validate' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new DiscountController();
        $response = $ctrl->validate($authUser);
    }
    // Support ticket routes (customer-facing, protected)
    elseif ($path === '/support/tickets' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->index($authUser);
    }
    elseif ($path === '/support/tickets' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->create($authUser);
    }
    elseif (preg_match('#^/support/tickets/(\d+)$#', $path, $matches) && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->show($authUser, $matches[1]);
    }
    elseif (preg_match('#^/support/tickets/(\d+)/reply$#', $path, $matches) && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->reply($authUser, $matches[1]);
    }
    // 2FA routes
    elseif ($path === '/auth/2fa/setup' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new AuthController();
        $response = $ctrl->setup2fa($authUser);
    }
    elseif ($path === '/auth/2fa/enable' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new AuthController();
        $response = $ctrl->enable2fa($authUser);
    }
    elseif ($path === '/auth/2fa/disable' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new AuthController();
        $response = $ctrl->disable2fa($authUser);
    }
    // Wine routes
    elseif ($path === '/wines' && $method === 'GET') {
        $ctrl = new WineController();
        $response = $ctrl->index();
    }
    elseif ($path === '/wines/regions' && $method === 'GET') {
        $ctrl = new WineController();
        $response = $ctrl->regions();
    }
    elseif ($path === '/wines/types' && $method === 'GET') {
        $ctrl = new WineController();
        $response = $ctrl->types();
    }
    elseif ($path === '/wines/ratings' && $method === 'GET') {
        $ctrl = new WineController();
        $response = $ctrl->ratings();
    }
    elseif ($path === '/wines/import-url' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new WineController();
        $response = $ctrl->importFromUrl($authUser);
    }
    elseif ($path === '/wines' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new WineController();
        $response = $ctrl->create($authUser);
    }
    elseif (preg_match('#^/wines/(\d+)/image$#', $path, $matches) && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new WineController();
        $response = $ctrl->uploadImage($authUser, $matches[1]);
    }
    elseif (preg_match('#^/wines/(\d+)/stock$#', $path, $matches) && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new WineController();
        $response = $ctrl->adjustStock($authUser, $matches[1]);
    }
    elseif (preg_match('#^/wines/(\d+)$#', $path, $matches) && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new WineController();
        $response = $ctrl->update($authUser, $matches[1]);
    }
    elseif (preg_match('#^/wines/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $authUser = authenticateToken();
        $ctrl = new WineController();
        $response = $ctrl->delete($authUser, $matches[1]);
    }
    elseif (preg_match('#^/wines/export/(.+)$#', $path, $matches) && $method === 'GET') {
        $ctrl = new WineController();
        $response = $ctrl->export($matches[1]);
    }
    // Wine reviews - must be BEFORE the catch-all /wines/:id route
    elseif (preg_match('#^/wines/(.+)/reviews$#', $path, $matches) && $method === 'GET') {
        $ctrl = new ReviewController();
        $response = $ctrl->list($matches[1]);
    }
    elseif (preg_match('#^/wines/(.+)/reviews$#', $path, $matches) && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new ReviewController();
        $response = $ctrl->create($authUser, $matches[1]);
    }
    elseif (preg_match('#^/wines/(.+)$#', $path, $matches) && $method === 'GET' && $matches[1] !== 'regions' && $matches[1] !== 'types' && $matches[1] !== 'ratings') {
        $ctrl = new WineController();
        $response = $ctrl->show($matches[1]);
    }
    // Cart routes (protected)
    elseif ($path === '/cart' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new CartController();
        $response = $ctrl->index($authUser);
    }
    elseif ($path === '/cart/add' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new CartController();
        $response = $ctrl->add($authUser);
    }
    elseif ($path === '/cart/update' && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new CartController();
        $response = $ctrl->update($authUser);
    }
    elseif ($path === '/cart/snapshot' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new CartSnapshotController();
        $response = $ctrl->snapshot($authUser);
    }
    elseif ($path === '/cart/restore' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new CartSnapshotController();
        $response = $ctrl->restore($authUser);
    }
    elseif (preg_match('#^/cart/remove/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $authUser = authenticateToken();
        $ctrl = new CartController();
        $response = $ctrl->remove($authUser, $matches[1]);
    }
    elseif ($path === '/orders' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new OrderController();
        $response = $ctrl->create($authUser);
    }
    elseif ($path === '/orders' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new OrderController();
        $response = $ctrl->index($authUser);
    }
    elseif ($path === '/orders/track' && $method === 'GET') {
        $ctrl = new OrderController();
        $response = $ctrl->track();
    }
    elseif (preg_match('#^/orders/(\d+)/tracking-link$#', $path, $matches) && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new OrderController();
        $response = $ctrl->trackingLink($authUser, $matches[1]);
    }
    elseif (preg_match('#^/orders/(\d+)$#', $path, $matches) && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new OrderController();
        $response = $ctrl->show($authUser, $matches[1]);
    }
    elseif (preg_match('#^/orders/(\d+)/status$#', $path, $matches) && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new OrderController();
        $response = $ctrl->updateStatus($authUser, $matches[1]);
    }
    // Admin routes (protected - admin only)
    elseif ($path === '/admin/orders' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new AdminController();
        $response = $ctrl->listOrders($authUser);
    }
    elseif (preg_match('#^/admin/orders/(\d+)$#', $path, $matches) && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new AdminController();
        $response = $ctrl->getOrder($authUser, $matches[1]);
    }
    elseif (preg_match('#^/admin/orders/(\d+)/status$#', $path, $matches) && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new AdminController();
        $response = $ctrl->updateOrderStatus($authUser, $matches[1]);
    }
    elseif ($path === '/admin/analytics' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new AdminController();
        $response = $ctrl->analytics($authUser);
    }
    elseif ($path === '/admin/users' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new AdminController();
        $response = $ctrl->users($authUser);
    }
    elseif (preg_match('#^/admin/users/(\d+)/role$#', $path, $matches) && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new AdminController();
        $response = $ctrl->updateUserRole($authUser, $matches[1]);
    }
    // Admin: discount codes
    elseif ($path === '/admin/discounts' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new DiscountController();
        $response = $ctrl->index($authUser);
    }
    elseif ($path === '/admin/discounts' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new DiscountController();
        $response = $ctrl->create($authUser);
    }
    elseif (preg_match('#^/admin/discounts/(\d+)$#', $path, $matches) && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new DiscountController();
        $response = $ctrl->update($authUser, $matches[1]);
    }
    elseif (preg_match('#^/admin/discounts/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $authUser = authenticateToken();
        $ctrl = new DiscountController();
        $response = $ctrl->delete($authUser, $matches[1]);
    }
    // Admin: referral codes
    elseif ($path === '/admin/referrals' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new ReferralController();
        $response = $ctrl->index($authUser);
    }
    elseif ($path === '/admin/referrals' && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new ReferralController();
        $response = $ctrl->create($authUser);
    }
    elseif (preg_match('#^/admin/referrals/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $authUser = authenticateToken();
        $ctrl = new ReferralController();
        $response = $ctrl->delete($authUser, $matches[1]);
    }
    // Admin/support: tickets
    elseif ($path === '/admin/support/tickets' && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->adminIndex($authUser);
    }
    elseif (preg_match('#^/admin/support/tickets/(\d+)$#', $path, $matches) && $method === 'GET') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->adminShow($authUser, $matches[1]);
    }
    elseif (preg_match('#^/admin/support/tickets/(\d+)/reply$#', $path, $matches) && $method === 'POST') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->adminReply($authUser, $matches[1]);
    }
    elseif (preg_match('#^/admin/support/tickets/(\d+)/status$#', $path, $matches) && $method === 'PUT') {
        $authUser = authenticateToken();
        $ctrl = new SupportController();
        $response = $ctrl->updateStatus($authUser, $matches[1]);
    }
    elseif ($path === '/pi-log-data' && $method === 'GET') {
        $ctrl = new PiCallbackController();
        $response = $ctrl->logData();
    }
    elseif ($path === '/password-reset-log' && $method === 'GET') {
        $ctrl = new PasswordResetController();
        $response = $ctrl->logData();
    }
    else {
        http_response_code(404);
        $response = ['success' => false, 'message' => 'Endpoint not found.'];
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Internal server error.'];
}

echo json_encode($response);
