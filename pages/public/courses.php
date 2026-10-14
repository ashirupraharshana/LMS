<?php

declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!function_exists('coursePageEscape')) {
    function coursePageEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$searchTerm = trim((string) ($_GET['q'] ?? ''));
// Avoid needlessly long search strings while keeping normal searches useful.
if (function_exists('mb_substr')) {
    $searchTerm = mb_substr($searchTerm, 0, 100, 'UTF-8');
} else {
    $searchTerm = substr($searchTerm, 0, 100);
}


$courses = [];
$categories = [];
$pageError = '';
$pdo = null;

$rawCategoryId = $_GET['category'] ?? '';

$validatedCategoryId = filter_var(
    $rawCategoryId,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$selectedCategoryId = (
    $validatedCategoryId === false || $validatedCategoryId === null
) ? 0 : (int) $validatedCategoryId;

$selectedCategoryName = '';

try {
    require_once __DIR__ . '/../../config/database.php';

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException(
            'PDO database connection is unavailable.'
        );
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Load active categories for the filter dropdown.
    $categoryStmt = $pdo->query(
        "SELECT id, name
         FROM course_categories
         WHERE status = 'active'
         ORDER BY name ASC"
    );

    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Only allow category IDs that exist in the active category list.
    $validCategoryIds = array_map(
        'intval',
        array_column($categories, 'id')
    );

    if (
        $selectedCategoryId > 0 &&
        !in_array($selectedCategoryId, $validCategoryIds, true)
    ) {
        $selectedCategoryId = 0;
    }

    foreach ($categories as $category) {
        if ((int) $category['id'] === $selectedCategoryId) {
            $selectedCategoryName = (string) $category['name'];
            break;
        }
    }

    // Retrieve published courses and their active categories.
    $sql = "SELECT
                c.id,
                c.title,
                c.description,
                c.created_at,
                cc.name AS category_name
            FROM courses AS c
            INNER JOIN course_categories AS cc
                ON cc.id = c.category_id
            WHERE c.status = 'published'
              AND cc.status = 'active'";

    // Filter by selected category.
    if ($selectedCategoryId > 0) {
        $sql .= " AND c.category_id = :category_id";
    }

    // Keep the existing keyword search.
    if ($searchTerm !== '') {
        $sql .= " AND (
                    c.title LIKE :title_search
                    OR COALESCE(c.description, '') LIKE :description_search
                    OR cc.name LIKE :category_search
                  )";
    }

    $sql .= " ORDER BY cc.name ASC, c.title ASC";

    $stmt = $pdo->prepare($sql);

    if ($selectedCategoryId > 0) {
        $stmt->bindValue(
            ':category_id',
            $selectedCategoryId,
            PDO::PARAM_INT
        );
    }

    if ($searchTerm !== '') {
        $searchPattern = '%' . $searchTerm . '%';

        $stmt->bindValue(
            ':title_search',
            $searchPattern,
            PDO::PARAM_STR
        );

        $stmt->bindValue(
            ':description_search',
            $searchPattern,
            PDO::PARAM_STR
        );

        $stmt->bindValue(
            ':category_search',
            $searchPattern,
            PDO::PARAM_STR
        );
    }

    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Public courses page error: ' . $e->getMessage());
    $pageError = 'Courses are temporarily unavailable. Please try again later.';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111827">
    <title>Explore Courses | Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/LMS/Components/public/css/navbar.css">
    <style>
        :root {
            --public-course-primary: #4f46e5;
            --public-course-primary-dark: #3730a3;
            --public-course-text: #172033;
            --public-course-muted: #6b7280;
            --public-course-border: #e5e7eb;
            --public-course-background: #f6f7fb;
        }

        .public-courses-page,
        .public-courses-page * {
            box-sizing: border-box;
        }

        .public-courses-page {
            min-height: 75vh;
            padding: 42px 20px 60px;
            background: var(--public-course-background);
            color: var(--public-course-text);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        .public-courses-container {
            width: min(1180px, 100%);
            margin: 0 auto;
        }

        .public-courses-hero {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 26px;
            margin-bottom: 26px;
        }

        .public-courses-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--public-course-primary);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .public-courses-hero h1 {
            margin: 10px 0 8px;
            color: #111827;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.15;
            letter-spacing: -.04em;
        }

        .public-courses-hero p {
            max-width: 660px;
            margin: 0;
            color: var(--public-course-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .public-courses-count {
            flex-shrink: 0;
            padding: 10px 13px;
            border: 1px solid var(--public-course-border);
            border-radius: 12px;
            background: #fff;
            color: #4b5563;
            font-size: 12px;
            font-weight: 750;
        }

        .public-courses-search-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            border: 1px solid #e9ecf2;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 5px 20px rgba(15, 23, 42, .035);
        }

        .public-courses-search-form {
            display: flex;
            width: min(100%, 560px);
            gap: 9px;
        }

        .public-courses-search-input-wrap {
            position: relative;
            flex: 1;
            min-width: 100px;
        }

        .public-courses-search-input-wrap i {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
            pointer-events: none;
        }

        .public-courses-search-input {
            width: 100%;
            min-height: 44px;
            padding: 11px 13px 11px 39px;
            border: 1px solid #dfe3eb;
            border-radius: 10px;
            outline: none;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-size: 13px;
        }

        .public-courses-search-input:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
        }


        .public-courses-category-select {
            min-height: 44px;
            min-width: 180px;
            max-width: 240px;
            padding: 11px 32px 11px 12px;
            border: 1px solid #dfe3eb;
            border-radius: 10px;
            outline: none;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-size: 13px;
            cursor: pointer;
        }

        .public-courses-category-select:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
        }

        @media (max-width: 640px) {
            .public-courses-category-select {
                flex: 1;
                min-width: 0;
                max-width: none;
            }
        }


        .public-courses-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 11px 16px;
            border: 1px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
            white-space: nowrap;
        }

        .public-courses-btn:hover {
            transform: translateY(-1px);
        }

        .public-courses-btn-primary {
            background: var(--public-course-primary);
            color: #fff;
        }

        .public-courses-btn-primary:hover {
            background: var(--public-course-primary-dark);
        }

        .public-courses-btn-light {
            border-color: var(--public-course-border);
            background: #fff;
            color: #4b5563;
        }

        .public-courses-btn-light:hover {
            background: #f8fafc;
        }

        .public-courses-results-label {
            margin: 0 0 14px;
            color: #6b7280;
            font-size: 12px;
        }

        .public-courses-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .public-course-card {
            display: flex;
            min-width: 0;
            flex-direction: column;
            padding: 22px;
            border: 1px solid #e9ecf2;
            border-radius: 17px;
            background: #fff;
            box-shadow: 0 5px 20px rgba(15, 23, 42, .035);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .public-course-card:hover {
            transform: translateY(-3px);
            border-color: #d9dcfe;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        }

        .public-course-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 17px;
        }

        .public-course-icon {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: #eef2ff;
            color: var(--public-course-primary);
            font-size: 18px;
        }

        .public-course-category {
            display: inline-flex;
            max-width: 65%;
            align-items: center;
            padding: 7px 9px;
            border-radius: 9px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 10px;
            font-weight: 800;
            line-height: 1.4;
            overflow-wrap: anywhere;
            text-align: right;
        }

        .public-course-card h2 {
            margin: 0 0 10px;
            color: #111827;
            font-size: 18px;
            line-height: 1.4;
            letter-spacing: -.02em;
            overflow-wrap: anywhere;
        }

        .public-course-description {
            display: -webkit-box;
            min-height: 67px;
            margin: 0 0 20px;
            overflow: hidden;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.7;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }

        .public-course-description-empty {
            color: #9ca3af;
            font-style: italic;
        }

        .public-course-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid #f0f2f6;
        }

        .public-course-open-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #15803d;
            font-size: 11px;
            font-weight: 750;
        }

        .public-course-apply-btn {
            flex-shrink: 0;
        }

        .public-courses-message {
            padding: 22px 18px;
            border: 1px solid #e9ecf2;
            border-radius: 14px;
            background: #fff;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.65;
        }

        .public-courses-message i {
            margin-right: 8px;
        }

        .public-courses-message-error {
            border-color: #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .public-courses-empty {
            grid-column: 1 / -1;
            padding: 52px 20px;
            border: 1px dashed #d9deea;
            border-radius: 16px;
            background: #fff;
            text-align: center;
        }

        .public-courses-empty i {
            display: block;
            margin-bottom: 13px;
            color: #a5adbd;
            font-size: 30px;
        }

        .public-courses-empty h2 {
            margin: 0 0 8px;
            color: #263244;
            font-size: 17px;
        }

        .public-courses-empty p {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }

        .public-courses-footer-note {
            margin-top: 26px;
            color: #8a93a3;
            font-size: 12px;
            line-height: 1.7;
            text-align: center;
        }

        @media (max-width: 980px) {
            .public-courses-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .public-courses-page {
                padding: 28px 14px 42px;
            }

            .public-courses-hero {
                align-items: flex-start;
                flex-direction: column;
                gap: 14px;
            }

            .public-courses-search-panel {
                align-items: stretch;
                flex-direction: column;
            }

            .public-courses-search-form {
                width: 100%;
                flex-wrap: wrap;
            }

            .public-courses-search-input-wrap {
                flex-basis: 100%;
            }

            .public-courses-search-form .public-courses-btn {
                flex: 1;
            }

            .public-courses-grid {
                grid-template-columns: 1fr;
            }

            .public-course-card {
                padding: 19px;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../../Components/public/navbar.php'; ?>

    <main class="public-courses-page">
        <div class="public-courses-container">
            <section class="public-courses-hero" aria-labelledby="publicCoursesTitle">
                <div>
                    <div class="public-courses-eyebrow"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Find your next course</div>
                    <h1 id="publicCoursesTitle">Explore Courses</h1>
                    <p>Discover courses available at our Student Management System. Search by course name, description, or category, then submit an application for a course that interests you.</p>
                </div>
                <div class="public-courses-count">
                    <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                    <?= number_format(count($courses)) ?> course<?= count($courses) === 1 ? '' : 's' ?><?= $searchTerm !== '' ? ' found' : ' available' ?>
                </div>
            </section>

            <section class="public-courses-search-panel" aria-label="Search courses">
                <form class="public-courses-search-form" method="get" action="courses.php" role="search">
                    <div class="public-courses-search-input-wrap">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input
                            class="public-courses-search-input"
                            type="search"
                            name="q"
                            value="<?= coursePageEscape($searchTerm) ?>"
                            placeholder="Search courses or categories..."
                            aria-label="Search courses by title, description, or category"
                            maxlength="100">
                    </div>


                    <select
                        class="public-courses-category-select"
                        id="courseCategoryFilter"
                        name="category"
                        aria-label="Filter courses by category"
                        onchange="this.form.submit()">

                        <option value="">All categories</option>

                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= $selectedCategoryId === (int) $category['id']
                                    ? 'selected'
                                    : '' ?>>

                                <?= coursePageEscape($category['name']) ?>

                            </option>
                        <?php endforeach; ?>

                    </select>



                    <button class="public-courses-btn public-courses-btn-primary" type="submit">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search
                    </button>
                    <?php if ($searchTerm !== ''): ?>
                        <a class="public-courses-btn public-courses-btn-light" href="courses.php">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </section>

            <?php if ($pageError !== ''): ?>
                <div class="public-courses-message public-courses-message-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><?= coursePageEscape($pageError) ?>
                </div>
            <?php elseif ($courses): ?>

                <p class="public-courses-results-label">
                    <?php if ($searchTerm !== '' || $selectedCategoryId > 0): ?>

                        Showing <?= number_format(count($courses)) ?>
                        course<?= count($courses) === 1 ? '' : 's' ?>

                        <?php if ($searchTerm !== ''): ?>
                            matching “<?= coursePageEscape($searchTerm) ?>”
                        <?php endif; ?>

                        <?php if ($selectedCategoryName !== ''): ?>
                            in the category
                            “<?= coursePageEscape($selectedCategoryName) ?>”
                            <?php endif; ?>.

                        <?php else: ?>

                            Browse the published courses below. Applications are reviewed
                            by an administrator before a student account is created.

                        <?php endif; ?>
                </p>


                <section class="public-courses-grid" aria-label="Available courses">
                    <?php foreach ($courses as $course): ?>
                        <article class="public-course-card">
                            <div class="public-course-card-top">
                                <span class="public-course-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
                                <span class="public-course-category"><?= coursePageEscape($course['category_name'] ?? 'Uncategorized') ?></span>
                            </div>
                            <h2><?= coursePageEscape($course['title'] ?? 'Untitled course') ?></h2>
                            <?php $description = trim((string) ($course['description'] ?? '')); ?>
                            <?php if ($description !== ''): ?>
                                <p class="public-course-description"><?= coursePageEscape($description) ?></p>
                            <?php else: ?>
                                <p class="public-course-description public-course-description-empty">Course details will be provided by the course administrator.</p>
                            <?php endif; ?>
                            <div class="public-course-card-footer">
                                <span class="public-course-open-label"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Applications open</span>

                                <a class="public-courses-btn public-courses-btn-primary public-course-apply-btn"
                                    href="apply_course.php?course_id=<?= (int) $course['id'] ?>">
                                    Apply <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>

                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <section class="public-courses-empty">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                    <h2><?= $searchTerm !== '' ? 'No courses found' : 'No courses available yet' ?></h2>
                    <p><?= $searchTerm !== '' ? 'Try a different keyword or clear your search to see all published courses.' : 'Published courses will appear here when they are made available by the administrator.' ?></p>
                    <?php if ($searchTerm !== ''): ?>
                        <div style="margin-top:18px"><a class="public-courses-btn public-courses-btn-light" href="courses.php">View all courses</a></div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <p class="public-courses-footer-note">Applying for a course does not create a login account immediately. The administrator will review your application and create your student account if it is approved.</p>
        </div>
    </main>

    <script src="js/navbar.js"></script>
</body>

</html>