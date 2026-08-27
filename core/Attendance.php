<?php
require_once __DIR__ . '/Database.php';

class Attendance {
    private mysqli $conn;

    public function __construct() {
        $this->conn = Database::getConn();
    }

    public function getTotalSessions(): int {
        return $this->conn->query("SELECT COUNT(*) as c FROM attendance_sessions")->fetch_assoc()['c'];
    }

    public function getTodayAttendanceRate(int $teacherId): float {
        $today = date('Y-m-d');
        $stmt = $this->conn->prepare("
            SELECT
                COUNT(ar.record_id) as scanned,
                COUNT(DISTINCT cs.student_id) * COUNT(DISTINCT s.session_id) as total_slots
            FROM attendance_sessions s
            JOIN class_students cs ON cs.class_id = s.class_id
            LEFT JOIN attendance_records ar ON ar.session_id = s.session_id AND ar.student_id = cs.student_id
                AND ar.status IN ('present','late','excused')
            WHERE s.teacher_id = ? AND s.session_date = ? AND s.status = 'closed'
        ");
        $stmt->bind_param("is", $teacherId, $today);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if(!$row || !$row['total_slots']) return 0.0;
        return round(($row['scanned'] / $row['total_slots']) * 100, 1);
    }

    public function getOverallAttendanceRate(int $teacherId): float {
        $stmt = $this->conn->prepare("
            SELECT
                COUNT(ar.record_id) as scanned,
                COUNT(DISTINCT cs.student_id) * COUNT(DISTINCT s.session_id) as total_slots
            FROM attendance_sessions s
            JOIN class_students cs ON cs.class_id = s.class_id
            LEFT JOIN attendance_records ar ON ar.session_id = s.session_id AND ar.student_id = cs.student_id
                AND ar.status IN ('present','late','excused')
            WHERE s.teacher_id = ? AND s.status = 'closed'
        ");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if(!$row || !$row['total_slots']) return 0.0;
        return round(($row['scanned'] / $row['total_slots']) * 100, 1);
    }

    public function updateRecordStatus(int $recordId, string $status): bool {
        $stmt = $this->conn->prepare("UPDATE attendance_records SET status = ? WHERE record_id = ?");
        $stmt->bind_param("si", $status, $recordId);
        return $stmt->execute();
    }

    public function insertExcused(int $sessionId, int $studentId): bool {
        $stmt = $this->conn->prepare("INSERT IGNORE INTO attendance_records (session_id, student_id, time_scanned, status) VALUES (?, ?, NOW(), 'excused')");
        $stmt->bind_param("ii", $sessionId, $studentId);
        return $stmt->execute();
    }

    public function getLiveCounts(int $sessionId, int $classId): array {
        $counts = $this->getStatusCounts($sessionId);
        $total_enrolled = $this->conn->prepare("SELECT COUNT(*) as c FROM class_students WHERE class_id = ?");
        $total_enrolled->bind_param("i", $classId);
        $total_enrolled->execute();
        $enrolled = $total_enrolled->get_result()->fetch_assoc()['c'];
        $scanned  = $counts['present'] + $counts['late'] + $counts['excused'];
        return [
            'present'     => $counts['present'],
            'late'        => $counts['late'],
            'not_scanned' => max(0, $enrolled - $scanned),
            'total'       => $enrolled,
        ];
    }

    public function startSession(int $classId, int $teacherId, string $date, string $start, string $expiry, string $token): int {
        $stmt = $this->conn->prepare("
            INSERT INTO attendance_sessions (class_id, teacher_id, session_date, start_time, expiry_time, qr_token, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("iissss", $classId, $teacherId, $date, $start, $expiry, $token);
        $stmt->execute();
        return $this->conn->insert_id;
    }

    public function closeExpiredSessions(): void {
        $now_time = date('H:i:s');
        $today    = date('Y-m-d');

        $stmt = $this->conn->prepare("
            UPDATE attendance_sessions
            SET status = 'closed'
            WHERE status = 'active'
            AND session_date = ?
            AND expiry_time <= ?
        ");
        $stmt->bind_param("ss", $today, $now_time);
        $stmt->execute();

        $stmt2 = $this->conn->prepare("
            UPDATE attendance_sessions
            SET status = 'closed'
            WHERE status = 'active'
            AND session_date < ?
        ");
        $stmt2->bind_param("s", $today);
        $stmt2->execute();
    }

    public function closeSession(int $sessionId): void {
        $stmt = $this->conn->prepare("UPDATE attendance_sessions SET status='closed' WHERE session_id=?");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
    }

    public function rotateToken(int $sessionId, string $newToken): bool {
        $stmt = $this->conn->prepare("UPDATE attendance_sessions SET qr_token_prev = qr_token, qr_token = ? WHERE session_id = ? AND status = 'active'");
        $stmt->bind_param("si", $newToken, $sessionId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function getSessionByToken(string $token): ?array {
        $stmt = $this->conn->prepare("
            SELECT attendance_sessions.*, classes.class_name, classes.subject
            FROM attendance_sessions
            JOIN classes ON attendance_sessions.class_id = classes.class_id
            WHERE attendance_sessions.qr_token = ? OR attendance_sessions.qr_token_prev = ?
        ");
        $stmt->bind_param("ss", $token, $token);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getSessionById(int $sessionId): ?array {
        $stmt = $this->conn->prepare("
            SELECT attendance_sessions.*, classes.class_name, classes.subject
            FROM attendance_sessions
            JOIN classes ON attendance_sessions.class_id = classes.class_id
            WHERE attendance_sessions.session_id = ?
        ");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getSessionWithTeacher(int $sessionId): ?array {
        $stmt = $this->conn->prepare("
            SELECT attendance_sessions.*, classes.class_name, classes.subject, users.full_name AS teacher_name
            FROM attendance_sessions
            JOIN classes ON attendance_sessions.class_id = classes.class_id
            JOIN users ON attendance_sessions.teacher_id = users.user_id
            WHERE attendance_sessions.session_id = ?
        ");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function isEnrolledInClass(int $classId, int $studentId): bool {
        $stmt = $this->conn->prepare("SELECT id FROM class_students WHERE class_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $classId, $studentId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    public function isAlreadyRecorded(int $sessionId, int $studentId): bool {
        $stmt = $this->conn->prepare("SELECT record_id FROM attendance_records WHERE session_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $sessionId, $studentId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    public function isIpAlreadyScanned(int $sessionId, string $ip): bool {
        $stmt = $this->conn->prepare("SELECT record_id FROM attendance_records WHERE session_id = ? AND scanned_ip = ?");
        $stmt->bind_param("is", $sessionId, $ip);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    public function recordAttendance(int $sessionId, int $studentId, string $time, string $status, string $ip = ''): bool {
        $stmt = $this->conn->prepare("INSERT INTO attendance_records (session_id, student_id, time_scanned, scanned_ip, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $sessionId, $studentId, $time, $ip, $status);
        return $stmt->execute();
    }

    public function getRecords(int $sessionId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT attendance_records.*, students.full_name, students.student_number
            FROM attendance_records
            JOIN students ON attendance_records.student_id = students.student_id
            WHERE attendance_records.session_id = ?
            ORDER BY attendance_records.time_scanned ASC
        ");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getRecordsWithCourse(int $sessionId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT attendance_records.*, students.full_name, students.student_number, students.course, students.year_level, students.block
            FROM attendance_records
            JOIN students ON attendance_records.student_id = students.student_id
            WHERE attendance_records.session_id = ?
            ORDER BY attendance_records.time_scanned ASC
        ");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getStatusCounts(int $sessionId): array {
        $stmt = $this->conn->prepare("SELECT status, COUNT(*) as count FROM attendance_records WHERE session_id = ? GROUP BY status");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts = ['present' => 0, 'late' => 0, 'excused' => 0];
        while($row = $result->fetch_assoc()) $counts[$row['status']] = $row['count'];
        return $counts;
    }

    public function getAbsentCount(int $classId, int $sessionId): int {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM class_students
            WHERE class_id = ? AND student_id NOT IN (SELECT student_id FROM attendance_records WHERE session_id = ?)
        ");
        $stmt->bind_param("ii", $classId, $sessionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['count'];
    }

    public function getAbsentStudents(int $classId, int $sessionId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT students.student_id, students.full_name, students.student_number, students.course, students.year_level, students.block
            FROM class_students
            JOIN students ON class_students.student_id = students.student_id
            WHERE class_students.class_id = ?
            AND students.student_id NOT IN (SELECT student_id FROM attendance_records WHERE session_id = ?)
            ORDER BY students.full_name ASC
        ");
        $stmt->bind_param("ii", $classId, $sessionId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getDates(int $classId, string $role, int $teacherId = 0): mysqli_result {
        if($role === 'admin'){
            $stmt = $this->conn->prepare("SELECT DISTINCT session_date FROM attendance_sessions WHERE class_id = ? ORDER BY session_date DESC");
            $stmt->bind_param("i", $classId);
        } else {
            $stmt = $this->conn->prepare("SELECT DISTINCT session_date FROM attendance_sessions WHERE class_id = ? AND teacher_id = ? ORDER BY session_date DESC");
            $stmt->bind_param("ii", $classId, $teacherId);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getSessions(int $classId, string $date, string $role, int $teacherId = 0): mysqli_result {
        if($role === 'admin'){
            $stmt = $this->conn->prepare("
                SELECT attendance_sessions.*, classes.class_name, classes.subject
                FROM attendance_sessions
                JOIN classes ON attendance_sessions.class_id = classes.class_id
                WHERE attendance_sessions.class_id = ? AND attendance_sessions.session_date = ?
                ORDER BY attendance_sessions.start_time ASC
            ");
            $stmt->bind_param("is", $classId, $date);
        } else {
            $stmt = $this->conn->prepare("
                SELECT attendance_sessions.*, classes.class_name, classes.subject
                FROM attendance_sessions
                JOIN classes ON attendance_sessions.class_id = classes.class_id
                WHERE attendance_sessions.class_id = ? AND attendance_sessions.session_date = ? AND attendance_sessions.teacher_id = ?
                ORDER BY attendance_sessions.start_time ASC
            ");
            $stmt->bind_param("isi", $classId, $date, $teacherId);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getRecentSessions(string $role, int $teacherId = 0): mysqli_result {
        if($role === 'admin'){
            return $this->conn->query("
                SELECT attendance_sessions.*, classes.class_name, classes.subject
                FROM attendance_sessions
                JOIN classes ON attendance_sessions.class_id = classes.class_id
                ORDER BY attendance_sessions.created_at DESC LIMIT 5
            ");
        }
        $stmt = $this->conn->prepare("
            SELECT attendance_sessions.*, classes.class_name, classes.subject
            FROM attendance_sessions
            JOIN classes ON attendance_sessions.class_id = classes.class_id
            WHERE attendance_sessions.teacher_id = ?
            ORDER BY attendance_sessions.created_at DESC LIMIT 5
        ");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getTodayStats(string $today, string $role, int $teacherId = 0): array {
        if($role === 'admin'){
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM attendance_sessions WHERE session_date = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $sessions = $stmt->get_result()->fetch_assoc()['count'];

            $stmt2 = $this->conn->prepare("
                SELECT COUNT(*) as count FROM attendance_records
                JOIN attendance_sessions ON attendance_records.session_id = attendance_sessions.session_id
                WHERE attendance_sessions.session_date = ?
            ");
            $stmt2->bind_param("s", $today);
            $stmt2->execute();
            $scanned = $stmt2->get_result()->fetch_assoc()['count'];
        } else {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM attendance_sessions WHERE session_date = ? AND teacher_id = ?");
            $stmt->bind_param("si", $today, $teacherId);
            $stmt->execute();
            $sessions = $stmt->get_result()->fetch_assoc()['count'];

            $stmt2 = $this->conn->prepare("
                SELECT COUNT(*) as count FROM attendance_records
                JOIN attendance_sessions ON attendance_records.session_id = attendance_sessions.session_id
                WHERE attendance_sessions.session_date = ? AND attendance_sessions.teacher_id = ?
            ");
            $stmt2->bind_param("si", $today, $teacherId);
            $stmt2->execute();
            $scanned = $stmt2->get_result()->fetch_assoc()['count'];
        }
        return ['sessions' => $sessions, 'scanned' => $scanned];
    }

    public function getTrendLast7Days(string $role, int $teacherId = 0): array {
        if($role === 'admin'){
            $result = $this->conn->query("
                SELECT DATE(attendance_sessions.session_date) as date,
                       COUNT(attendance_records.record_id) as scanned
                FROM attendance_sessions
                LEFT JOIN attendance_records ON attendance_records.session_id = attendance_sessions.session_id
                WHERE attendance_sessions.session_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(attendance_sessions.session_date)
                ORDER BY date ASC
            ");
        } else {
            $stmt = $this->conn->prepare("
                SELECT DATE(attendance_sessions.session_date) as date,
                       COUNT(attendance_records.record_id) as scanned
                FROM attendance_sessions
                LEFT JOIN attendance_records ON attendance_records.session_id = attendance_sessions.session_id
                WHERE attendance_sessions.session_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                AND attendance_sessions.teacher_id = ?
                GROUP BY DATE(attendance_sessions.session_date)
                ORDER BY date ASC
            ");
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = [];
        while($row = $result->fetch_assoc()) $data[] = $row;
        return $data;
    }

    public function getSectionAttendanceRates(): array {
        $result = $this->conn->query("
            SELECT
                CONCAT(st.course, ' ', st.year_level, '-', st.block) AS label,
                ROUND(
                    SUM(CASE WHEN ar.status IN ('present','late','excused') THEN 1 ELSE 0 END) /
                    COUNT(*) * 100
                , 1) AS rate
            FROM class_students cs
            JOIN students st ON cs.student_id = st.student_id
            JOIN attendance_sessions s ON s.class_id = cs.class_id AND s.status = 'closed'
            LEFT JOIN attendance_records ar ON ar.session_id = s.session_id AND ar.student_id = cs.student_id
            WHERE st.course != '' AND st.year_level > 0 AND st.block > 0
            GROUP BY st.course, st.year_level, st.block
            HAVING COUNT(*) > 0
            ORDER BY label ASC
        ");
        $data = [];
        while($row = $result->fetch_assoc()) $data[] = $row;
        return $data;
    }

    public function getClassAttendanceRates(string $role, int $teacherId = 0): array {
        if($role === 'admin'){
            $result = $this->conn->query("
                SELECT
                    c.class_name AS label,
                    ROUND(
                        COUNT(ar.record_id) /
                        (SELECT COUNT(*) FROM class_students cs2 WHERE cs2.class_id = c.class_id) /
                        COUNT(DISTINCT s.session_id) * 100
                    , 1) AS rate
                FROM classes c
                JOIN attendance_sessions s ON s.class_id = c.class_id AND s.status = 'closed'
                LEFT JOIN attendance_records ar ON ar.session_id = s.session_id
                WHERE (SELECT COUNT(*) FROM class_students cs2 WHERE cs2.class_id = c.class_id) > 0
                GROUP BY c.class_id, c.class_name
                HAVING COUNT(DISTINCT s.session_id) > 0
                ORDER BY rate DESC
                LIMIT 8
            ");
        } else {
            $stmt = $this->conn->prepare("
                SELECT
                    CONCAT(c.subject, ' — ', c.class_name) AS label,
                    ROUND(
                        COUNT(ar.record_id) /
                        (SELECT COUNT(*) FROM class_students cs2 WHERE cs2.class_id = c.class_id) /
                        COUNT(DISTINCT s.session_id) * 100
                    , 1) AS rate
                FROM classes c
                JOIN attendance_sessions s ON s.class_id = c.class_id AND s.status = 'closed'
                LEFT JOIN attendance_records ar ON ar.session_id = s.session_id
                WHERE c.teacher_id = ?
                AND (SELECT COUNT(*) FROM class_students cs2 WHERE cs2.class_id = c.class_id) > 0
                GROUP BY c.class_id, c.class_name, c.subject
                HAVING COUNT(DISTINCT s.session_id) > 0
                ORDER BY c.class_name ASC
            ");
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = [];
        while($row = $result->fetch_assoc()) $data[] = $row;
        return $data;
    }

    public function getAtRiskStudents(string $role, int $teacherId = 0): array {
        if($role === 'admin'){
            $result = $this->conn->query("
                SELECT * FROM (
                    SELECT
                        students.student_id, students.full_name, students.student_number,
                        students.course, students.year_level, students.block,
                        COUNT(DISTINCT attendance_sessions.session_id) AS total_sessions,
                        COUNT(DISTINCT CASE WHEN attendance_records.status IN ('present','late','excused') THEN attendance_records.record_id END) AS attended
                    FROM class_students
                    JOIN students ON class_students.student_id = students.student_id
                    JOIN classes ON class_students.class_id = classes.class_id
                    JOIN attendance_sessions ON attendance_sessions.class_id = classes.class_id AND attendance_sessions.status = 'closed'
                    LEFT JOIN attendance_records ON attendance_records.session_id = attendance_sessions.session_id AND attendance_records.student_id = students.student_id
                    GROUP BY students.student_id
                ) AS sub
                WHERE total_sessions >= 8 AND (attended / total_sessions) < 0.60
                ORDER BY course ASC, year_level ASC, block ASC, (attended / total_sessions) ASC
            ");
        } else {
            $stmt = $this->conn->prepare("
                SELECT * FROM (
                    SELECT
                        students.student_id, students.full_name, students.student_number,
                        students.course, students.year_level, students.block,
                        classes.class_id, classes.class_name, classes.subject,
                        COUNT(DISTINCT attendance_sessions.session_id) AS total_sessions,
                        COUNT(DISTINCT CASE WHEN attendance_records.status IN ('present','late','excused') THEN attendance_records.record_id END) AS attended
                    FROM class_students
                    JOIN students ON class_students.student_id = students.student_id
                    JOIN classes ON class_students.class_id = classes.class_id
                    JOIN attendance_sessions ON attendance_sessions.class_id = classes.class_id AND attendance_sessions.status = 'closed'
                    LEFT JOIN attendance_records ON attendance_records.session_id = attendance_sessions.session_id AND attendance_records.student_id = students.student_id
                    WHERE classes.teacher_id = ?
                    GROUP BY students.student_id, classes.class_id
                ) AS sub
                WHERE total_sessions >= 8 AND (attended / total_sessions) < 0.60
                ORDER BY class_name ASC, (attended / total_sessions) ASC
            ");
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $data = [];
        while($row = $result->fetch_assoc()) $data[] = $row;
        return $data;
    }

    public function getOverallStatusBreakdown(string $role, int $teacherId = 0): array {
        if($role === 'admin'){
            $result = $this->conn->query("
                SELECT status, COUNT(*) as count FROM attendance_records GROUP BY status
            ");
            $enrolled = $this->conn->query("
                SELECT COUNT(*) as count
                FROM class_students
                JOIN classes ON class_students.class_id = classes.class_id
                JOIN attendance_sessions ON attendance_sessions.class_id = classes.class_id
                WHERE attendance_sessions.status = 'closed'
            ")->fetch_assoc()['count'];
        } else {
            $stmt = $this->conn->prepare("
                SELECT attendance_records.status, COUNT(*) as count
                FROM attendance_records
                JOIN attendance_sessions ON attendance_records.session_id = attendance_sessions.session_id
                WHERE attendance_sessions.teacher_id = ?
                GROUP BY attendance_records.status
            ");
            $stmt->bind_param("i", $teacherId);
            $stmt->execute();
            $result = $stmt->get_result();

            $stmt2 = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM class_students
                JOIN classes ON class_students.class_id = classes.class_id
                JOIN attendance_sessions ON attendance_sessions.class_id = classes.class_id
                WHERE attendance_sessions.status = 'closed'
                AND attendance_sessions.teacher_id = ?
            ");
            $stmt2->bind_param("i", $teacherId);
            $stmt2->execute();
            $enrolled = $stmt2->get_result()->fetch_assoc()['count'];
        }

        $data = ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0];
        while($row = $result->fetch_assoc()) $data[$row['status']] = (int)$row['count'];
        $scanned = $data['present'] + $data['late'] + $data['excused'];
        $data['absent'] = max(0, (int)$enrolled - $scanned);
        return $data;
    }
}
