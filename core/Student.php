<?php
require_once __DIR__ . '/Database.php';

class Student {
    private mysqli $conn;

    public function __construct() {
        $this->conn = Database::getConn();
    }

    public function count(): int {
        return $this->conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
    }

    public function getAll(): mysqli_result {
        return $this->conn->query("SELECT * FROM students ORDER BY student_number ASC");
    }

    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getByNumber(string $number): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM students WHERE student_number = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getByNumberStripped(string $stripped): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM students WHERE REPLACE(student_number, '-', '') = ?");
        $stmt->bind_param("s", $stripped);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function numberExists(string $number, int $excludeId = 0): bool {
        $stmt = $this->conn->prepare("SELECT student_id FROM students WHERE student_number = ? AND student_id != ?");
        $stmt->bind_param("si", $number, $excludeId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    private function formatNumber(string $number): string {
        $clean = preg_replace('/[^0-9]/', '', $number);
        if(strlen($clean) === 9) return substr($clean, 0, 4) . '-' . substr($clean, 4);
        return $number;
    }

    public function add(string $number, string $name, string $course, int $year, int $block, string $hashed, string $ser_image = '', string $email = ''): bool {
        $number    = $this->formatNumber($number);
        $number    = $this->conn->real_escape_string($number);
        $name      = $this->conn->real_escape_string($name);
        $course    = $this->conn->real_escape_string($course);
        $hashed    = $this->conn->real_escape_string($hashed);
        $ser_image = $this->conn->real_escape_string($ser_image);
        $email     = $this->conn->real_escape_string($email);
        return $this->conn->query("
            INSERT INTO students (student_number, full_name, course, year_level, block, password, ser_image, email, must_change_password)
            VALUES ('$number', '$name', '$course', $year, $block, '$hashed', '$ser_image', '$email', 1)
        ");
    }

    public function update(int $id, string $number, string $name, string $course, int $year, int $block): bool {
        $number = $this->formatNumber($number);
        $stmt = $this->conn->prepare("UPDATE students SET student_number=?, full_name=?, course=?, year_level=?, block=? WHERE student_id=?");
        $stmt->bind_param("sssiii", $number, $name, $course, $year, $block, $id);
        return $stmt->execute();
    }

    public function delete(int $id): void {
        $s1 = $this->conn->prepare("DELETE FROM attendance_records WHERE student_id = ?");
        $s1->bind_param("i", $id); $s1->execute();

        $s2 = $this->conn->prepare("DELETE FROM class_students WHERE student_id = ?");
        $s2->bind_param("i", $id); $s2->execute();

        $s3 = $this->conn->prepare("DELETE FROM students WHERE student_id = ?");
        $s3->bind_param("i", $id); $s3->execute();
    }

    public function updateProfilePicture(int $id, string $filename): void {
        $stmt = $this->conn->prepare("UPDATE students SET profile_picture = ? WHERE student_id = ?");
        $stmt->bind_param("si", $filename, $id);
        $stmt->execute();
    }

    public function updatePassword(int $id, string $hashed): void {
        $stmt = $this->conn->prepare("UPDATE students SET password = ?, must_change_password = 0 WHERE student_id = ?");
        $stmt->bind_param("si", $hashed, $id);
        $stmt->execute();
    }

    public function updateSer(int $id, string $filename): void {
        $stmt = $this->conn->prepare("UPDATE students SET ser_image = ? WHERE student_id = ?");
        $stmt->bind_param("si", $filename, $id);
        $stmt->execute();
    }

    public function updateProfile(int $id, string $number, string $name, string $course, int $year, int $block, string $email = ''): bool {
        $number = $this->formatNumber($number);
        $stmt = $this->conn->prepare("UPDATE students SET student_number=?, full_name=?, course=?, year_level=?, block=?, email=? WHERE student_id=?");
        $stmt->bind_param("sssiisi", $number, $name, $course, $year, $block, $email, $id);
        return $stmt->execute();
    }

    public function resetPassword(int $id, string $studentNumber): void {
        $default  = 'plm' . str_replace('-', '', $studentNumber);
        $hashed   = password_hash($default, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->conn->prepare("UPDATE students SET password = ?, must_change_password = 1 WHERE student_id = ?");
        $stmt->bind_param("si", $hashed, $id);
        $stmt->execute();
    }

    public function getMustChangePassword(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT must_change_password, full_name FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getEnrolledClassesWithSchedule(int $studentId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT classes.class_id, classes.class_name, classes.subject,
                   classes.schedule, users.full_name AS teacher_name
            FROM class_students
            JOIN classes ON class_students.class_id = classes.class_id
            JOIN users ON classes.teacher_id = users.user_id
            WHERE class_students.student_id = ?
            ORDER BY classes.class_name ASC
        ");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getClassAttendanceDetail(int $studentId, int $classId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT
                attendance_sessions.session_id,
                attendance_sessions.session_date,
                attendance_sessions.start_time,
                attendance_sessions.expiry_time,
                attendance_records.time_scanned,
                attendance_records.status
            FROM attendance_sessions
            LEFT JOIN attendance_records ON attendance_records.session_id = attendance_sessions.session_id
                AND attendance_records.student_id = ?
            WHERE attendance_sessions.class_id = ? AND attendance_sessions.status = 'closed'
            ORDER BY attendance_sessions.session_date DESC, attendance_sessions.start_time DESC
        ");
        $stmt->bind_param("ii", $studentId, $classId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getEnrolledClasses(int $studentId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT 
                classes.class_id, classes.class_name, classes.subject,
                COUNT(DISTINCT attendance_sessions.session_id) AS total_sessions,
                COUNT(DISTINCT CASE WHEN attendance_records.status IN ('present','late','excused') THEN attendance_records.record_id END) AS attended,
                SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) AS total_present,
                SUM(CASE WHEN attendance_records.status = 'late'    THEN 1 ELSE 0 END) AS total_late,
                SUM(CASE WHEN attendance_records.status = 'excused' THEN 1 ELSE 0 END) AS total_excused
            FROM class_students
            JOIN classes ON class_students.class_id = classes.class_id
            LEFT JOIN attendance_sessions ON attendance_sessions.class_id = classes.class_id AND attendance_sessions.status != 'active'
            LEFT JOIN attendance_records ON attendance_records.session_id = attendance_sessions.session_id AND attendance_records.student_id = ?
            WHERE class_students.student_id = ?
            GROUP BY classes.class_id
        ");
        $stmt->bind_param("ii", $studentId, $studentId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getAttendanceTimeline(int $studentId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT
                attendance_sessions.session_date,
                attendance_sessions.start_time,
                classes.class_name,
                classes.subject,
                attendance_records.time_scanned,
                attendance_records.status
            FROM class_students
            JOIN classes ON class_students.class_id = classes.class_id
            JOIN attendance_sessions ON attendance_sessions.class_id = classes.class_id
                AND attendance_sessions.status = 'closed'
            LEFT JOIN attendance_records ON attendance_records.session_id = attendance_sessions.session_id
                AND attendance_records.student_id = ?
            WHERE class_students.student_id = ?
            ORDER BY attendance_sessions.session_date DESC, attendance_sessions.start_time DESC
        ");
        $stmt->bind_param("ii", $studentId, $studentId);
        $stmt->execute();
        return $stmt->get_result();
    }
}
