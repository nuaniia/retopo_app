<?php
/**
 * RETOPO.AI - 1-Click Database Setup & Test Installer
 * Run via browser: http://localhost/retopo_app-main/retopo_app-main/install.php
 */

$db_host = '127.0.0.1';
$db_port = 3306;
$db_name = 'retopo_db';
$db_user = 'root';
$db_pass = '';

$status = [];
$error = null;

try {
    // 1. Connect to MySQL server
    $server_pdo = new PDO("mysql:host={$db_host};port={$db_port};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $status[] = ['title' => 'เชื่อมต่อ MySQL Server สำเร็จ', 'status' => 'success', 'desc' => "Host: {$db_host}:{$db_port} | User: {$db_user}"];

    // 2. Create database
    $server_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $status[] = ['title' => "สร้างหรือพบฐานข้อมูล `{$db_name}` สำเร็จ", 'status' => 'success', 'desc' => 'utf8mb4_unicode_ci collation'];

    // 3. Connect to database
    $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 4. Create Table 1: users
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `role` ENUM('admin', 'user') DEFAULT 'user',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $status[] = ['title' => 'ตาราง `users` พร้อมใช้งาน', 'status' => 'success', 'desc' => 'ระบบยืนยันตัวตน ผู้ใช้ทั่วไป (user) และผู้ดูแลระบบ (admin)'];

    // 5. Create Table 2: sample_models
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
    $status[] = ['title' => 'ตาราง `sample_models` (ข้อมูลหลักที่ 1) พร้อมใช้งาน', 'status' => 'success', 'desc' => 'จัดเก็บและจัดการข้อมูลตัวอย่างโมเดล 3D'];

    // 6. Create Table 3: faqs
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
    $status[] = ['title' => 'ตาราง `faqs` (ข้อมูลหลักที่ 2) พร้อมใช้งาน', 'status' => 'success', 'desc' => 'จัดเก็บและจัดการคำถาม-คำตอบที่พบบ่อย'];

    // 7. Seed Initial Users
    $chkUser = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($chkUser == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $userPass  = password_hash('user123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPass, 'ผู้ดูแลระบบสูงสุด (System Admin)', 'admin@retopo.ai', 'admin']);
        $stmt->execute(['student', $userPass, 'นักศึกษาทดสอบระบบ (Student User)', 'student@university.ac.th', 'user']);
        $status[] = ['title' => 'สร้างบัญชีทดสอบเริ่มต้นสำเร็จ', 'status' => 'success', 'desc' => 'Admin: admin / admin123 | User: student / user123'];
    } else {
        $status[] = ['title' => 'พบบัญชีผู้ใช้ในระบบแล้ว', 'status' => 'info', 'desc' => "มีผู้ใช้ทั้งหมด {$chkUser} บัญชี"];
    }

    // 8. Seed Initial Models
    $chkModel = $pdo->query("SELECT COUNT(*) FROM `sample_models`")->fetchColumn();
    if ($chkModel == 0) {
        $stmt = $pdo->prepare("INSERT INTO `sample_models` (`name`, `code_name`, `badge_text`, `badge_color`, `poly_count_before`, `poly_count_after`, `quad_ratio`, `icon_class`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['High-Poly Icosphere Prop', 'sphere', 'High-Poly Sphere', 'sky', 5120, 2560, '98.2%', 'fa-solid fa-globe', 'โมเดลทรงกลมความละเอียดสูงสำหรับทดสอบการจัดระเบียบโครงสร้างเส้นแบบ Quad-Dominant']);
        $stmt->execute(['High-Poly Torus Ring Prop', 'torus', 'Torus Asset', 'purple', 1152, 576, '97.8%', 'fa-solid fa-ring', 'วัตถุทรงห่วงโดนัทสำหรับทดสอบความโค้งต่อเนื่องและการคงรูปทรงกระบอก']);
        $stmt->execute(['Capsule Character Base', 'capsule', 'Capsule Asset', 'emerald', 640, 320, '99.0%', 'fa-solid fa-capsules', 'โมเดลโครงร่างแคปซูลสำหรับเตรียมตัวละครเพื่อการ Rigging และ Joint Bending']);
        $stmt->execute(['High-Poly Monkey Head', 'suzanne', 'Suzanne Monkey', 'rose', 15000, 7500, '96.5%', 'fa-solid fa-face-smile', 'โมเดลหัวลิงความละเอียดสูงสำหรับการทดสอบ Facial Loops และ Organic Retopology']);
        $status[] = ['title' => 'สร้างข้อมูลตัวอย่างโมเดล 3D เริ่มต้นสำเร็จ', 'status' => 'success', 'desc' => 'เพิ่มข้อมูล 4 โมเดลหลัก'];
    } else {
        $status[] = ['title' => 'พบข้อมูลตัวอย่างโมเดล 3D ในระบบแล้ว', 'status' => 'info', 'desc' => "มีทั้งหมด {$chkModel} โมเดล"];
    }

    // 9. Seed Initial FAQs
    $chkFaq = $pdo->query("SELECT COUNT(*) FROM `faqs`")->fetchColumn();
    if ($chkFaq == 0) {
        $stmt = $pdo->prepare("INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`) VALUES (?, ?, ?, ?)");
        $stmt->execute(['แอปพลิเคชันนี้ใช้หลักการ Retopology จากไฟล์อะไร?', 'แอปพลิเคชันนี้ยึดหลักการและมาตรฐาน Retopology สำหรับงาน 3D Animation จากเอกสาร Retopoforanimat.md เป็นหลัก ครอบคลุมทั้งแนว Edge Flow การไหลตามกล้ามเนื้อ, วง Edge Loop รอบข้อต่อพับ (เข่า, ข้อศอก, ดวงตา, ปาก), การวางจุดแยก Pole นอกพื้นที่ Deformation และการรักษาสัดส่วน Quad Topology 95-100%', 'Standard', 1]);
        $stmt->execute(['ไฟล์ที่รองรับการอัปโหลดเข้าสู่ระบบมีนามสกุลอะไรบ้าง และรองรับขนาดสูงสุดเท่าใด?', 'ระบบรองรับการอัปโหลดไฟล์โมเดล 3D ทั้งไฟล์ .OBJ (Wavefront OBJ), .FBX (Filmbox) และ .STL (Stereolithography) โดยรองรับไฟล์ขนาดใหญ่สูงสุดถึง 10GB ผ่านระบบ Stream Chunking ความเร็วสูง', 'Upload', 2]);
        $stmt->execute(['โครงเส้น Wireframe และพื้นผิว Shaded ใน 3D Viewport ทำงานตรงกัน 100% หรือไม่?', 'ใช่ครับ ระบบล็อกโครงเส้น Wireframe ซ้อนทับเข้าเป็น Child Node เดียวกันกับพื้นผิว Shaded Mesh โดยตรง ทำให้ทุกพิกัดตำแหน่ง การหมุน การขยาย และรูปทรง 3D ตรงกัน 100% โดยไม่มีการเหลื่อมหรือเบี้ยวหลุดจากกัน', 'Viewport', 3]);
        $status[] = ['title' => 'สร้างข้อมูลคำถามที่พบบ่อย (FAQs) เริ่มต้นสำเร็จ', 'status' => 'success', 'desc' => 'เพิ่มข้อมูล 3 คำถามหลัก'];
    } else {
        $status[] = ['title' => 'พบข้อมูล FAQs ในระบบแล้ว', 'status' => 'info', 'desc' => "มีทั้งหมด {$chkFaq} คำถาม"];
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Installation | RETOPO.AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="glass-panel max-w-2xl w-full p-8 rounded-3xl shadow-2xl space-y-6">
        
        <div class="flex items-center gap-4 border-b border-slate-800 pb-5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center text-white text-2xl shadow-lg shadow-sky-500/30">
                <i class="fa-solid fa-database"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-white">RETOPO.AI Database Setup</h1>
                <p class="text-xs text-slate-400">ระบบติดตั้งและตรวจสอบฐานข้อมูล MySQL สำหรับ XAMPP</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="p-4 rounded-2xl bg-rose-950/60 border border-rose-500/40 text-rose-200 space-y-2">
                <div class="font-bold flex items-center gap-2 text-rose-400">
                    <i class="fa-solid fa-triangle-exclamation"></i> ไม่สามารถเชื่อมต่อ MySQL ได้
                </div>
                <p class="text-xs leading-relaxed"><?= htmlspecialchars($error) ?></p>
                <div class="text-xs text-slate-300 mt-2 bg-slate-900/60 p-3 rounded-xl border border-slate-800">
                    <strong>วิธีแก้ไข:</strong><br>
                    1. เปิดโปรแกรม <strong>XAMPP Control Panel</strong><br>
                    2. กดปุ่ม <strong>Start</strong> ที่โมดูล <strong>MySQL</strong><br>
                    3. รีเฟรชหน้านี้ใหม่อีกครั้ง
                </div>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($status as $item): ?>
                    <div class="p-3.5 rounded-xl bg-slate-900/90 border border-slate-800 flex items-start gap-3">
                        <div class="mt-0.5 text-<?= $item['status'] === 'success' ? 'emerald' : 'sky' ?>-400 text-base">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="text-xs">
                            <div class="font-bold text-white"><?= htmlspecialchars($item['title']) ?></div>
                            <div class="text-slate-400 mt-0.5"><?= htmlspecialchars($item['desc']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="p-4 rounded-xl bg-sky-950/40 border border-sky-800/60 text-sky-200 text-xs space-y-1">
                <div class="font-bold flex items-center gap-2 text-sky-400">
                    <i class="fa-solid fa-key"></i> บัญชีผู้ใช้สำหรับการทดสอบระบบ:
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 font-mono">
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <span class="text-purple-400 font-bold">👑 ผู้ดูแลระบบ (Admin):</span><br>
                        User: <span class="text-white">admin</span> | Pass: <span class="text-white">admin123</span>
                    </div>
                    <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-800">
                        <span class="text-emerald-400 font-bold">👤 สมาชิกทั่วไป (User):</span><br>
                        User: <span class="text-white">student</span> | Pass: <span class="text-white">user123</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <a href="index.html" class="flex-1 bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-bold py-3 px-4 rounded-xl text-xs text-center flex items-center justify-center gap-2 transition-all shadow-lg shadow-sky-500/20">
                    <i class="fa-solid fa-house"></i> ไปยังหน้าแรก (Home Page)
                </a>
                <a href="admin.php" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 px-4 rounded-xl text-xs text-center border border-slate-700 flex items-center justify-center gap-2 transition-all">
                    <i class="fa-solid fa-sliders"></i> เข้าสู่หน้าจัดการระบบ (Admin Panel)
                </a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>

