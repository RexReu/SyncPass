<?php
session_start();
require_once __DIR__ . '/core/Database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['error' => 'unauthorized']); exit();
}

$conn  = Database::getConn();
$role  = $_SESSION['role'];
$uid   = (int)$_SESSION['user_id'];
$range = in_array($_GET['range'] ?? '', ['7','30','semester','all']) ? $_GET['range'] : '7';

// --- Date boundaries ---
$today = date('Y-m-d');
$today = $conn->real_escape_string($today);
switch($range){
    case '30':
        $from = date('Y-m-d', strtotime('-29 days'));
        break;
    case 'semester':
        $month = (int)date('n');
        $year  = (int)date('Y');
        $from  = $month >= 6 ? "$year-06-01" : "$year-01-01";
        break;
    case 'all':
        $from = '2000-01-01';
        break;
    default:
        $from = date('Y-m-d', strtotime('-6 days'));
        break;
}
$from = $conn->real_escape_string($from);
$uid  = (int)$_SESSION['user_id'];

$tf_s2 = $role === 'teacher' ? "AND s2.teacher_id = $uid" : "";
$tf_s  = $role === 'teacher' ? "AND s.teacher_id = $uid"  : "";

// Reusable subquery: per-session rate, skipping sessions with 0 enrolled
$rate_subquery = "
    SELECT s2.session_id, s2.session_date,
           COUNT(ar.record_id) / enrolled_sub.enrolled * 100 AS rate
    FROM attendance_sessions s2
    JOIN (
        SELECT ses.session_id, COUNT(*) AS enrolled
        FROM class_students
        JOIN classes ON class_students.class_id = classes.class_id
        JOIN attendance_sessions ses ON ses.class_id = classes.class_id
        GROUP BY ses.session_id
        HAVING COUNT(*) > 0
    ) enrolled_sub ON enrolled_sub.session_id = s2.session_id
    LEFT JOIN attendance_records ar ON ar.session_id = s2.session_id
    WHERE s2.status = 'closed'
    AND s2.session_date BETWEEN '$from' AND '$today'
    $tf_s2
    GROUP BY s2.session_id, enrolled_sub.enrolled
";

// =====================
// TREND CHART
// =====================

// Determine grouping: day for 7, week for 30 and semester, month for all
$day_diff = (strtotime($today) - strtotime($from)) / 86400;

if($range === '7'){
    // Daily — always exactly 7 points, null for days with no sessions
    $sql = "
        SELECT DATE(session_date) AS period, AVG(rate) AS avg_rate
        FROM ($rate_subquery) AS rated
        GROUP BY DATE(session_date)
        ORDER BY period ASC
    ";
    $result = $conn->query($sql);
    $map = [];
    while($row = $result->fetch_assoc()) $map[$row['period']] = round((float)$row['avg_rate'], 1);

    $labels = []; $data = [];
    for($i = 6; $i >= 0; $i--){
        $d = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($d));
        $data[]   = array_key_exists($d, $map) ? $map[$d] : null; // null = gap, not 0
    }

} elseif($range === '30' || ($range === 'semester' && $day_diff <= 90)){
    // Weekly grouping
    $sql = "
        SELECT YEARWEEK(session_date, 1) AS period,
               MIN(DATE(session_date)) AS week_start,
               AVG(rate) AS avg_rate
        FROM ($rate_subquery) AS rated
        GROUP BY YEARWEEK(session_date, 1)
        ORDER BY period ASC
    ";
    $result = $conn->query($sql);
    $labels = []; $data = [];
    while($row = $result->fetch_assoc()){
        $labels[] = 'Wk ' . date('M d', strtotime($row['week_start']));
        $data[]   = round((float)$row['avg_rate'], 1);
    }

} else {
    // Monthly grouping for semester > 90 days and all time
    $sql = "
        SELECT DATE_FORMAT(session_date, '%Y-%m') AS period,
               AVG(rate) AS avg_rate
        FROM ($rate_subquery) AS rated
        GROUP BY DATE_FORMAT(session_date, '%Y-%m')
        ORDER BY period ASC
    ";
    $result = $conn->query($sql);
    $labels = []; $data = [];
    while($row = $result->fetch_assoc()){
        $labels[] = date('M Y', strtotime($row['period'] . '-01'));
        $data[]   = round((float)$row['avg_rate'], 1);
    }
}

// No data in range — return empty with flag
$trend = [
    'labels'  => $labels,
    'data'    => $data,
    'empty'   => count(array_filter($data, fn($v) => $v !== null)) === 0
];

// =====================
// DOUGHNUT CHART
// =====================
$sql_slots = "
    SELECT SUM(enrolled_sub.enrolled) AS total_slots
    FROM attendance_sessions s
    JOIN (
        SELECT ses.session_id, COUNT(*) AS enrolled
        FROM class_students
        JOIN classes ON class_students.class_id = classes.class_id
        JOIN attendance_sessions ses ON ses.class_id = classes.class_id
        GROUP BY ses.session_id
        HAVING COUNT(*) > 0
    ) enrolled_sub ON enrolled_sub.session_id = s.session_id
    WHERE s.status = 'closed'
    AND s.session_date BETWEEN '$from' AND '$today'
    $tf_s
";
$total_slots = (int)($conn->query($sql_slots)->fetch_assoc()['total_slots'] ?? 0);

$sql_status = "
    SELECT ar.status, COUNT(*) AS count
    FROM attendance_records ar
    JOIN attendance_sessions s ON ar.session_id = s.session_id
    WHERE s.status = 'closed'
    AND s.session_date BETWEEN '$from' AND '$today'
    $tf_s
    GROUP BY ar.status
";
$result  = $conn->query($sql_status);
$scanned = ['present' => 0, 'late' => 0, 'excused' => 0];
while($row = $result->fetch_assoc()) $scanned[$row['status']] = (int)$row['count'];

$absent = max(0, $total_slots - $scanned['present'] - $scanned['late'] - $scanned['excused']);

// No data — return zeros with empty flag
$doughnut = $total_slots > 0 ? [
    'present' => round($scanned['present'] / $total_slots * 100, 1),
    'late'    => round($scanned['late']    / $total_slots * 100, 1),
    'excused' => round($scanned['excused'] / $total_slots * 100, 1),
    'absent'  => round($absent             / $total_slots * 100, 1),
    'empty'   => false,
] : [
    'present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0, 'empty' => true
];

echo json_encode(['trend' => $trend, 'doughnut' => $doughnut]);
?>
