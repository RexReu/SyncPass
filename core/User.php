<?php
require_once __DIR__ . '/Database.php';

class User {
    private mysqli $conn;

    public function __construct() {
        $this->conn = Database::getConn();
    }

    public function getAll(): mysqli_result {
        return $this->conn->query("SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC");
    }

    public function countTeachers(): int {
        return $this->conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'teacher'")->fetch_assoc()['c'];
    }

    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function getByUsername(string $username): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function usernameExists(string $username, int $excludeId = 0): bool {
        $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $excludeId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }

    public function add(string $name, string $username, string $hashed, string $role): bool {
        $stmt = $this->conn->prepare("INSERT INTO users (full_name, username, password, role, must_change_password) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $name, $username, $hashed, $role);
        return $stmt->execute();
    }

    public function update(int $id, string $name, string $username, string $role, ?string $hashed = null): bool {
        if($hashed){
            $stmt = $this->conn->prepare("UPDATE users SET full_name=?, username=?, role=?, password=? WHERE user_id=?");
            $stmt->bind_param("ssssi", $name, $username, $role, $hashed, $id);
        } else {
            $stmt = $this->conn->prepare("UPDATE users SET full_name=?, username=?, role=? WHERE user_id=?");
            $stmt->bind_param("sssi", $name, $username, $role, $id);
        }
        return $stmt->execute();
    }

    public function delete(int $id): void {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    public function updateProfilePicture(int $id, string $filename): void {
        $stmt = $this->conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
        $stmt->bind_param("si", $filename, $id);
        $stmt->execute();
    }

    public function updatePassword(int $id, string $hashed): void {
        $stmt = $this->conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?");
        $stmt->bind_param("si", $hashed, $id);
        $stmt->execute();
    }

    public function updateSettings(int $id, int $qrDuration, int $lateThreshold): void {
        $stmt = $this->conn->prepare("UPDATE users SET qr_duration=?, late_threshold=? WHERE user_id=?");
        $stmt->bind_param("iii", $qrDuration, $lateThreshold, $id);
        $stmt->execute();
    }

    public function updateProfile(int $id, string $name, string $username): bool {
        $stmt = $this->conn->prepare("UPDATE users SET full_name=?, username=? WHERE user_id=?");
        $stmt->bind_param("ssi", $name, $username, $id);
        return $stmt->execute();
    }

    public function resetPassword(int $id, string $username): void {
        $default = 'plm' . $username;
        $hashed  = password_hash($default, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->conn->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE user_id = ?");
        $stmt->bind_param("si", $hashed, $id);
        $stmt->execute();
    }
}
