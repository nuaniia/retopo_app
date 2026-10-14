<?php
/**
 * RETOPO.AI - Database Connection Module
 * Supports XAMPP MySQL (Default: localhost, root, no password)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_port = 3306;
$db_name = 'retopo_db';
$db_user = 'root';
$db_pass = '';

$pdo = null;

try {
    // 1. Attempt connecting to the specific database
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // 2. If database doesn't exist, try connecting to MySQL server to auto-create it
    try {
        $server_dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
        $server_pdo = new PDO($server_dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $server_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Re-connect with dbname
        $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        
        // Execute schema initialization
        initDatabaseSchema($pdo);
    } catch (PDOException $innerException) {
        $pdo = null;
        $db_error = $innerException->getMessage();
    }
}

/**
 * Initialize Tables & Seeds automatically if not exists
 */
function initDatabaseSchema($pdo) {
    // 1. Table users
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `role` ENUM('admin', 'user') DEFAULT 'user',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Table sample_models
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sample_models` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `code_name` VARCHAR(50) NOT NULL,
        `badge_text` VARCHAR(50) NOT NULL DEFAULT '3D Model',
        `badge_color` VARCHAR(50) DEFAULT 'sky',
        `poly_count_before` INT NOT NULL DEFAULT 0,
        `poly_count_after` INT NOT NULL DEFAULT 0,
        `quad_ratio` VARCHAR(20) DEFAULT '98.5%',
        `icon_class` VARCHAR(50) DEFAULT 'fa-solid fa-cube',
        `description` TEXT,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Table faqs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `faqs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `question` VARCHAR(255) NOT NULL,
        `answer` TEXT NOT NULL,
        `category` VARCHAR(50) DEFAULT 'General',
        `sort_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed default admin if table is empty
    $chkUser = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($chkUser == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $userPass  = password_hash('user123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPass, 'ผู้ดูแลระบบ (Admin)', 'admin@retopo.ai', 'admin']);
        $stmt->execute(['student', $userPass, 'นักศึกษา (Student User)', 'student@university.ac.th', 'user']);
    }

    // Seed default models if table is empty
    $chkModel = $pdo->query("SELECT COUNT(*) FROM `sample_models`")->fetchColumn();
    if ($chkModel == 0) {
        $stmt = $pdo->prepare("INSERT INTO `sample_models` (`name`, `code_name`, `badge_text`, `badge_color`, `poly_count_before`, `poly_count_after`, `quad_ratio`, `icon_class`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['High-Poly Icosphere Prop', 'sphere', 'High-Poly Sphere', 'sky', 5120, 2560, '98.2%', 'fa-solid fa-globe', 'โมเดลทรงกลมความละเอียดสูงสำหรับทดสอบการจัดระเบียบโครงสร้างเส้นแบบ Quad-Dominant']);
        $stmt->execute(['High-Poly Torus Ring Prop', 'torus', 'Torus Asset', 'purple', 1152, 576, '97.8%', 'fa-solid fa-ring', 'วัตถุทรงห่วงโดนัทสำหรับทดสอบความโค้งต่อเนื่องและการคงรูปทรงกระบอก']);
        $stmt->execute(['Capsule Character Base', 'capsule', 'Capsule Asset', 'emerald', 640, 320, '99.0%', 'fa-solid fa-capsules', 'โมเดลโครงร่างแคปซูลสำหรับเตรียมตัวละครเพื่อการ Rigging และ Joint Bending']);
    }

    // Seed default FAQs if table is empty
    $chkFaq = $pdo->query("SELECT COUNT(*) FROM `faqs`")->fetchColumn();
    if ($chkFaq == 0) {
        $stmt = $pdo->prepare("INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`) VALUES (?, ?, ?, ?)");
        $stmt->execute(['แอปพลิเคชันนี้ใช้หลักการ Retopology จากไฟล์อะไร?', 'แอปพลิเคชันนี้ยึดหลักการและมาตรฐาน Retopology สำหรับงาน 3D Animation จากเอกสาร Retopoforanimat.md เป็นหลัก ครอบคลุมทั้งแนว Edge Flow การไหลตามกล้ามเนื้อ, วง Edge Loop รอบข้อต่อพับ (เข่า, ข้อศอก, ดวงตา, ปาก), การวางจุดแยก Pole นอกพื้นที่ Deformation และการรักษาสัดส่วน Quad Topology 95-100%', 'Standard', 1]);
        $stmt->execute(['ไฟล์ที่รองรับการอัปโหลดเข้าสู่ระบบมีนามสกุลอะไรบ้าง และรองรับขนาดสูงสุดเท่าใด?', 'ระบบรองรับการอัปโหลดไฟล์โมเดล 3D ทั้งไฟล์ .OBJ (Wavefront OBJ), .FBX (Filmbox) และ .STL (Stereolithography) โดยรองรับไฟล์ขนาดใหญ่สูงสุดถึง 10GB ผ่านระบบ Stream Chunking ความเร็วสูง', 'Upload', 2]);
        $stmt->execute(['โครงเส้น Wireframe และพื้นผิว Shaded ใน 3D Viewport ทำงานตรงกัน 100% หรือไม่?', 'ใช่ครับ ระบบล็อกโครงเส้น Wireframe ซ้อนทับเข้าเป็น Child Node เดียวกันกับพื้นผิว Shaded Mesh โดยตรง ทำให้ทุกพิกัดตำแหน่ง การหมุน การขยาย และรูปทรง 3D ตรงกัน 100% โดยไม่มีการเหลื่อมหรือเบี้ยวหลุดจากกัน', 'Viewport', 3]);
    }
}
?>

