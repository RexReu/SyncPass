<?php
require_once __DIR__ . '/Database.php';

class ClassRoom {
    private mysqli $conn;

    public function __construct() {
        $this->conn = Database::getConn();
    }

    public function count(): int {
        return $this->conn->query("SELECT COUNT(*) as c FROM classes")->fetch_assoc()['c'];
    }

    public function countByTeacher(int $teacherId): int {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as c FROM classes WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['c'];
    }

    public function countEnrolledByTeacher(int $teacherId): int {
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT class_students.student_id) as c
            FROM class_students
            JOIN classes ON class_students.class_id = classes.class_id
            WHERE classes.teacher_id = ?
        ");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['c'];
    }

    public function getAll(): mysqli_result {
        return $this->conn->query("
            SELECT classes.*, users.full_name AS teacher_name 
            FROM classes 
            JOIN users ON classes.teacher_id = users.user_id 
            ORDER BY classes.class_id ASC
        ");
    }

    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT classes.*, users.full_name AS teacher_name FROM classes JOIN users ON classes.teacher_id = users.user_id WHERE class_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getByTeacher(int $teacherId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT classes.*, users.full_name AS teacher_name 
            FROM classes 
            JOIN users ON classes.teacher_id = users.user_id 
            WHERE classes.teacher_id = ?
            ORDER BY classes.class_id ASC
        ");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getTeachers(): mysqli_result {
        return $this->conn->query("SELECT user_id, full_name FROM users WHERE role = 'teacher'");
    }

    public function add(string $name, string $subject, string $schedule, int $teacherId, int $year = 0, int $block = 0): bool {
        $stmt = $this->conn->prepare("INSERT INTO classes (class_name, year_level, block, subject, schedule, teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siissi", $name, $year, $block, $subject, $schedule, $teacherId);
        return $stmt->execute();
    }

    public function update(int $id, string $name, string $subject, string $schedule, int $teacherId): bool {
        $stmt = $this->conn->prepare("UPDATE classes SET class_name=?, subject=?, schedule=?, teacher_id=? WHERE class_id=?");
        $stmt->bind_param("sssii", $name, $subject, $schedule, $teacherId, $id);
        return $stmt->execute();
    }

    public function delete(int $id): void {
        $s1 = $this->conn->prepare("DELETE ar FROM attendance_records ar JOIN attendance_sessions s ON ar.session_id = s.session_id WHERE s.class_id = ?");
        $s1->bind_param("i", $id); $s1->execute();

        $s2 = $this->conn->prepare("DELETE FROM attendance_sessions WHERE class_id = ?");
        $s2->bind_param("i", $id); $s2->execute();

        $s3 = $this->conn->prepare("DELETE FROM class_students WHERE class_id = ?");
        $s3->bind_param("i", $id); $s3->execute();

        $s4 = $this->conn->prepare("DELETE FROM classes WHERE class_id = ?");
        $s4->bind_param("i", $id); $s4->execute();
    }

    public function getEnrolledStudents(int $classId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT students.* FROM students
            JOIN class_students ON students.student_id = class_students.student_id
            WHERE class_students.class_id = ?
            ORDER BY students.full_name ASC
        ");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getAvailableStudents(int $classId): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT * FROM students
            WHERE student_id NOT IN (SELECT student_id FROM class_students WHERE class_id = ?)
            ORDER BY full_name ASC
        ");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getAvailableStudentsByBlock(int $classId, int $year, int $block): mysqli_result {
        $stmt = $this->conn->prepare("
            SELECT * FROM students
            WHERE year_level = ? AND block = ?
            AND student_id NOT IN (SELECT student_id FROM class_students WHERE class_id = ?)
            ORDER BY full_name ASC
        ");
        $stmt->bind_param("iii", $year, $block, $classId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function isEnrolled(int $classId, int $studentId): bool {
        $stmt = $this->conn->prepare("SELECT id FROM class_students WHERE class_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $classId, $studentId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    public function enrollStudent(int $classId, int $studentId): void {
        $stmt = $this->conn->prepare("INSERT INTO class_students (class_id, student_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $classId, $studentId);
        $stmt->execute();
    }

    public function removeStudent(int $classId, int $studentId): void {
        $stmt = $this->conn->prepare("DELETE FROM class_students WHERE class_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $classId, $studentId);
        $stmt->execute();
    }

    public function enrollBlockStudents(int $classId, int $year, int $block, string $course = ''): int {
        if($course){
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO class_students (class_id, student_id)
                SELECT ?, student_id FROM students WHERE year_level = ? AND block = ? AND course = ?
            ");
            $stmt->bind_param("iiis", $classId, $year, $block, $course);
        } else {
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO class_students (class_id, student_id)
                SELECT ?, student_id FROM students WHERE year_level = ? AND block = ?
            ");
            $stmt->bind_param("iii", $classId, $year, $block);
        }
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function countBlockStudents(int $year, int $block, string $course = ''): int {
        if($course){
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM students WHERE year_level = ? AND block = ? AND course = ?");
            $stmt->bind_param("iis", $year, $block, $course);
        } else {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM students WHERE year_level = ? AND block = ?");
            $stmt->bind_param("ii", $year, $block);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['count'];
    }

    public function getDistinctByRole(string $role, int $teacherId = 0): mysqli_result {
        if($role === 'admin'){
            return $this->conn->query("SELECT DISTINCT class_id, class_name, subject FROM classes ORDER BY class_name ASC");
        }
        $stmt = $this->conn->prepare("SELECT DISTINCT class_id, class_name, subject FROM classes WHERE teacher_id = ? ORDER BY class_name ASC");
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        return $stmt->get_result();
    }
}
