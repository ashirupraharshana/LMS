<?php
declare(strict_types=1);
// Public self-registration is disabled. Administrators alone create student accounts.
// Keep the old URL working for bookmarks but direct applicants to the course form.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Self-registration is not available. Please apply for a course instead.');
}
header('Location: pages/public/apply_course.php', true, 302);
exit;
