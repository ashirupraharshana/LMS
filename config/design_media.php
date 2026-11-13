<?php
/** Optional design photos for the LMS layout.
 * Place YOUR OWN images in public/images/ and set the matching relative path.
 * Leave empty to display the styled placeholder; no missing-image icon appears.
 */
const LMS_DESIGN_IMAGES = [
    'admin_hero' => '',       // example: 'public/images/admin-hero.jpg'
    'instructor_hero' => '',  // example: 'public/images/instructors-hero.jpg'
    'student_hero' => '',     // example: 'public/images/student-hero.jpg'
    'course_placeholder' => '', // example: 'public/images/course-default.jpg'
];
function lmsDesignImage(string $key): ?string {
    $relative = LMS_DESIGN_IMAGES[$key] ?? '';
    if (!is_string($relative) || $relative === '' || !preg_match('~^public/images/[a-zA-Z0-9_./-]+\.(png|jpe?g|webp)$~iD', $relative) || str_contains($relative, '..')) return null;
    $root = dirname(__DIR__);
    if (!is_file($root.'/'.$relative)) return null;
    return '/LMS/'.$relative; // change /LMS/ if project folder is renamed
}
