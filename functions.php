<?php
require_once 'config.php';

// ==================== AUDIT TRAIL FUNCTIONS ====================

function logAudit($userId, $username, $action, $affectedRecord = null, $details = null) {
    global $pdo;
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $pdo->prepare("
        INSERT INTO audit_trail (user_id, username, action, affected_record, details, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$userId, $username, $action, $affectedRecord, $details, $ipAddress]);
}

// ==================== USER FUNCTIONS ====================

function getUserByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function getUserByUsername($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.*, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAllUsers($filters = []) {
    global $pdo;
    
    $sql = "
        SELECT u.*, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($filters['role'])) {
        $sql .= " AND u.role = ?";
        $params[] = $filters['role'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND u.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['department'])) {
        $sql .= " AND u.department_id = ?";
        $params[] = $filters['department'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
        $search = "%{$filters['search']}%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createUser($data) {
    global $pdo;
    
    // Check for duplicate email
    if (getUserByEmail($data['email'])) {
        return ['success' => false, 'message' => 'Email already exists in the system.'];
    }
    
    // Check for duplicate username
    if (getUserByUsername($data['username'])) {
        return ['success' => false, 'message' => 'Username already taken. Choose another.'];
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, username, password, role, department_id, status, force_change_password, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?)
    ");
    
    try {
        $stmt->execute([
            $data['full_name'],
            $data['email'],
            $data['username'],
            $hashedPassword,
            $data['role'],
            $data['department_id'],
            $data['status'],
            $_SESSION['user_id'] ?? null
        ]);
        
        $newUserId = $pdo->lastInsertId();
        
        // Log audit trail
        logAudit(
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? 'System',
            'CREATE_USER',
            "User ID: $newUserId",
            "Created user: {$data['full_name']} ({$data['role']})"
        );
        
        return ['success' => true, 'message' => 'User account created successfully.', 'user_id' => $newUserId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function updateUser($id, $data) {
    global $pdo;
    
    // Check for duplicate email (excluding current user)
    $existing = getUserByEmail($data['email']);
    if ($existing && $existing['id'] != $id) {
        return ['success' => false, 'message' => 'Email already exists in the system.'];
    }
    
    // Check for duplicate username (excluding current user)
    $existing = getUserByUsername($data['username']);
    if ($existing && $existing['id'] != $id) {
        return ['success' => false, 'message' => 'Username already taken. Choose another.'];
    }
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, email = ?, username = ?, role = ?, department_id = ?, status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    try {
        $stmt->execute([
            $data['full_name'],
            $data['email'],
            $data['username'],
            $data['role'],
            $data['department_id'],
            $data['status'],
            $id
        ]);
        
        // Log audit trail
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'UPDATE_USER',
            "User ID: $id",
            "Updated user: {$data['full_name']}"
        );
        
        return ['success' => true, 'message' => 'User updated successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function toggleUserStatus($id) {
    global $pdo;
    
    $user = getUserById($id);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }
    
    $newStatus = $user['status'] === 'Active' ? 'Inactive' : 'Active';
    
    $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    
    try {
        $stmt->execute([$newStatus, $id]);
        
        // Log audit trail
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'TOGGLE_USER_STATUS',
            "User ID: $id",
            "Changed status to: $newStatus"
        );
        
        return ['success' => true, 'message' => "User status changed to $newStatus.", 'status' => $newStatus];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function resetUserPassword($id) {
    global $pdo;
    
    // Generate temporary password
    $tempPassword = generateTempPassword();
    $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, force_change_password = TRUE, updated_at = NOW() 
        WHERE id = ?
    ");
    
    try {
        $stmt->execute([$hashedPassword, $id]);
        
        // Get user email for notification
        $user = getUserById($id);
        
        // Log audit trail
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'RESET_PASSWORD',
            "User ID: $id",
            "Password reset for: {$user['full_name']}"
        );
        
        // Here you would send email with new credentials
        // sendPasswordResetEmail($user['email'], $user['username'], $tempPassword);
        
        return ['success' => true, 'message' => 'Password reset. New credentials sent to user email.', 'temp_password' => $tempPassword];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function generateTempPassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = 'Balilihan@' . date('Y');
    return $password;
}

// ==================== DEPARTMENT FUNCTIONS ====================

function getAllDepartments($status = null) {
    global $pdo;
    
    $sql = "
        SELECT d.*, u.full_name as head_name, 
        (SELECT COUNT(*) FROM users WHERE department_id = d.id) as staff_count 
        FROM departments d 
        LEFT JOIN users u ON d.head_id = u.id 
        WHERE 1=1
    ";
    $params = [];
    
    if ($status) {
        $sql .= " AND d.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY d.department_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDepartmentById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createDepartment($data) {
    global $pdo;
    
    // Check for duplicate name or code
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_name = ? OR department_code = ?");
    $stmt->execute([$data['department_name'], $data['department_code']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Department already exists.'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO departments (department_name, department_code, status) 
        VALUES (?, ?, ?)
    ");
    
    try {
        $stmt->execute([
            $data['department_name'],
            $data['department_code'],
            $data['status']
        ]);
        
        $newDeptId = $pdo->lastInsertId();
        
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'CREATE_DEPARTMENT',
            "Department ID: $newDeptId",
            "Created department: {$data['department_name']}"
        );
        
        return ['success' => true, 'message' => 'Department added successfully.', 'department_id' => $newDeptId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function updateDepartment($id, $data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE departments 
        SET department_name = ?, department_code = ?, status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    try {
        $stmt->execute([
            $data['department_name'],
            $data['department_code'],
            $data['status'],
            $id
        ]);
        
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'UPDATE_DEPARTMENT',
            "Department ID: $id",
            "Updated department: {$data['department_name']}"
        );
        
        return ['success' => true, 'message' => 'Department updated successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ==================== FISCAL YEAR FUNCTIONS ====================

function getAllFiscalYears() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM fiscal_years ORDER BY year DESC");
    return $stmt->fetchAll();
}

function getActiveFiscalYear() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM fiscal_years WHERE status = 'Active' LIMIT 1");
    return $stmt->fetch();
}

function createFiscalYear($data) {
    global $pdo;
    
    // Check for duplicate year
    $stmt = $pdo->prepare("SELECT * FROM fiscal_years WHERE year = ?");
    $stmt->execute([$data['year']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Fiscal Year already exists.'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO fiscal_years (year, submission_deadline, status) 
        VALUES (?, ?, ?)
    ");
    
    try {
        $stmt->execute([
            $data['year'],
            $data['submission_deadline'],
            $data['status']
        ]);
        
        // If setting as active, deactivate others
        if ($data['status'] === 'Active') {
            $newId = $pdo->lastInsertId();
            $pdo->query("UPDATE fiscal_years SET status = 'Inactive' WHERE id != $newId");
        }
        
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'CREATE_FISCAL_YEAR',
            "Fiscal Year: {$data['year']}",
            "Created fiscal year: {$data['year']}"
        );
        
        return ['success' => true, 'message' => 'Fiscal Year added successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function updateFiscalYear($id, $data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE fiscal_years 
        SET year = ?, submission_deadline = ?, status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    try {
        $stmt->execute([
            $data['year'],
            $data['submission_deadline'],
            $data['status'],
            $id
        ]);
        
        // If setting as active, deactivate others
        if ($data['status'] === 'Active') {
            $pdo->query("UPDATE fiscal_years SET status = 'Inactive' WHERE id != $id");
        }
        
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'UPDATE_FISCAL_YEAR',
            "Fiscal Year ID: $id",
            "Updated fiscal year: {$data['year']}"
        );
        
        return ['success' => true, 'message' => 'Fiscal Year updated.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ==================== PROCUREMENT MODE FUNCTIONS ====================

function getAllProcurementModes() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM procurement_modes ORDER BY mode_name");
    return $stmt->fetchAll();
}

function createProcurementMode($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO procurement_modes (mode_name, description, threshold_amount, status) 
        VALUES (?, ?, ?, ?)
    ");
    
    try {
        $stmt->execute([
            $data['mode_name'],
            $data['description'],
            $data['threshold_amount'],
            $data['status']
        ]);
        
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'CREATE_PROCUREMENT_MODE',
            null,
            "Created mode: {$data['mode_name']}"
        );
        
        return ['success' => true, 'message' => 'Procurement Mode added successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function updateProcurementMode($id, $data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE procurement_modes 
        SET mode_name = ?, description = ?, threshold_amount = ?, status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    try {
        $stmt->execute([
            $data['mode_name'],
            $data['description'],
            $data['threshold_amount'],
            $data['status'],
            $id
        ]);
        
        logAudit(
            $_SESSION['user_id'],
            $_SESSION['username'],
            'UPDATE_PROCUREMENT_MODE',
            "Mode ID: $id",
            "Updated mode: {$data['mode_name']}"
        );
        
        return ['success' => true, 'message' => 'Procurement Mode updated.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// ==================== AUDIT TRAIL FUNCTIONS ====================

function getAuditTrail($filters = []) {
    global $pdo;
    
    $sql = "SELECT * FROM audit_trail WHERE 1=1";
    $params = [];
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['username'])) {
        $sql .= " AND username LIKE ?";
        $params[] = "%{$filters['username']}%";
    }
    
    if (!empty($filters['action'])) {
        $sql .= " AND action = ?";
        $params[] = $filters['action'];
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . (int)$filters['limit'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ==================== PPMP FUNCTIONS ====================

function getAllPPMPS($filters = []) {
    global $pdo;
    
    $sql = "
        SELECT p.*, d.department_name, fy.year as fiscal_year, u.full_name as prepared_by_name 
        FROM ppmps p 
        JOIN departments d ON p.department_id = d.id 
        JOIN fiscal_years fy ON p.fiscal_year_id = fy.id 
        JOIN users u ON p.prepared_by = u.id 
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($filters['department_id'])) {
        $sql .= " AND p.department_id = ?";
        $params[] = $filters['department_id'];
    }
    
    if (!empty($filters['fiscal_year_id'])) {
        $sql .= " AND p.fiscal_year_id = ?";
        $params[] = $filters['fiscal_year_id'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND p.status = ?";
        $params[] = $filters['status'];
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDashboardStats() {
    global $pdo;
    
    $stats = [];
    
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM users");
    $result = $stmt->fetch();
    $stats['total_users'] = $result['total'];
    $stats['active_users'] = $result['active'];
    $stats['inactive_users'] = $result['total'] - $result['active'];
    
    // Total Departments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments WHERE status = 'Active'");
    $stats['total_departments'] = $stmt->fetch()['total'];
    
    // Total PPMPS
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ppmps");
    $stats['total_ppmps'] = $stmt->fetch()['total'];
    
    // Pending Approvals
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ppmps WHERE status IN ('Submitted', 'Under Review')");
    $stats['pending_approvals'] = $stmt->fetch()['total'];
    
    // Approved PPMPS
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ppmps WHERE status = 'Approved'");
    $stats['approved_ppmps'] = $stmt->fetch()['total'];
    
    return $stats;
}

// ==================== SYSTEM SETTINGS FUNCTIONS ====================

function getSystemSetting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

function updateSystemSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
    ");
    return $stmt->execute([$key, $value, $value]);
}

function getAllSystemSettings() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM system_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}
?>
