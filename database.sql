-- ====================================================================
-- RETOPO.AI Database Setup Script (XAMPP MySQL / phpMyAdmin)
-- Database: retopo_db
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `retopo_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `retopo_db` ;

-- --------------------------------------------------------------------
-- Table 1: users (ระบบผู้ใช้และผู้ดูแลระบบ)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'user') DEFAULT 'user',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Table 2: sample_models (ข้อมูลหลักที่ 1: ตัวอย่างโมเดล 3D)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sample_models` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Table 3: faqs (ข้อมูลหลักที่ 2: คำถามที่พบบ่อย)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faqs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `question` VARCHAR(255) NOT NULL,
    `answer` TEXT NOT NULL,
    `category` VARCHAR(50) DEFAULT 'General',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Seed Initial Data
-- --------------------------------------------------------------------

-- Seed Users:
-- Admin: admin / admin123
-- User: student / user123
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('admin', '$2y$10$wOqJ0pL4m8xIq.1LzBvGguQW5u4rFfMhA0ZqK9n0t6z5mXg/R.sS6', 'ผู้ดูแลระบบสูงสุด (System Admin)', 'admin@retopo.ai', 'admin'),
('student', '$2y$10$fW3CjZ1T1yM2w0pQxKj5O.7V1u2sDfHhB0AqL9o0u6a5nXh/S.tT7', 'นักศึกษาทดสอบระบบ (Student User)', 'student@university.ac.th', 'user')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seed Sample Models (ข้อมูลตัวอย่างโมเดล 3D สำหรับแสดงหน้าบ้านและ 3D Viewport)
INSERT INTO `sample_models` (`name`, `code_name`, `badge_text`, `badge_color`, `poly_count_before`, `poly_count_after`, `quad_ratio`, `icon_class`, `description`) VALUES
('High-Poly Icosphere Prop', 'sphere', 'High-Poly Sphere', 'sky', 5120, 2560, '98.2%', 'fa-solid fa-globe', 'โมเดลทรงกลมความละเอียดสูงสำหรับทดสอบการจัดระเบียบโครงสร้างเส้นแบบ Quad-Dominant'),
('High-Poly Torus Ring Prop', 'torus', 'Torus Asset', 'purple', 1152, 576, '97.8%', 'fa-solid fa-ring', 'วัตถุทรงห่วงโดนัทสำหรับทดสอบความโค้งต่อเนื่องและการคงรูปทรงกระบอก'),
('Capsule Character Base', 'capsule', 'Capsule Asset', 'emerald', 640, 320, '99.0%', 'fa-solid fa-capsules', 'โมเดลโครงร่างแคปซูลสำหรับเตรียมตัวละครเพื่อการ Rigging และ Joint Bending'),
('High-Poly Monkey Head', 'suzanne', 'Suzanne Monkey', 'rose', 15000, 7500, '96.5%', 'fa-solid fa-face-smile', 'โมเดลหัวลิงความละเอียดสูงสำหรับการทดสอบ Facial Loops และ Organic Retopology')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed FAQs (ข้อมูลคำถามที่พบบ่อย)
INSERT INTO `faqs` (`question`, `answer`, `category`, `sort_order`) VALUES
('แอปพลิเคชันนี้ใช้หลักการ Retopology จากไฟล์อะไร?', 'แอปพลิเคชันนี้ยึดหลักการและมาตรฐาน Retopology สำหรับงาน 3D Animation จากเอกสาร Retopoforanimat.md เป็นหลัก ครอบคลุมทั้งแนว Edge Flow การไหลตามกล้ามเนื้อ, วง Edge Loop รอบข้อต่อพับ (เข่า, ข้อศอก, ดวงตา, ปาก), การวางจุดแยก Pole นอกพื้นที่ Deformation และการรักษาสัดส่วน Quad Topology 95-100%', 'Standard', 1),
('ไฟล์ที่รองรับการอัปโหลดเข้าสู่ระบบมีนามสกุลอะไรบ้าง และรองรับขนาดสูงสุดเท่าใด?', 'ระบบรองรับการอัปโหลดไฟล์โมเดล 3D ทั้งไฟล์ .OBJ (Wavefront OBJ), .FBX (Filmbox) และ .STL (Stereolithography) โดยรองรับไฟล์ขนาดใหญ่สูงสุดถึง 10GB ผ่านระบบ Stream Chunking ความเร็วสูง', 'Upload', 2),
('โครงเส้น Wireframe และพื้นผิว Shaded ใน 3D Viewport ทำงานตรงกัน 100% หรือไม่?', 'ใช่ครับ ระบบล็อกโครงเส้น Wireframe ซ้อนทับเข้าเป็น Child Node เดียวกันกับพื้นผิว Shaded Mesh โดยตรง ทำให้ทุกพิกัดตำแหน่ง การหมุน การขยาย และรูปทรง 3D ตรงกัน 100% โดยไม่มีการเหลื่อมหรือเบี้ยวหลุดจากกัน', 'Viewport', 3),
('สามารถนำไฟล์ OBJ ที่ Retopology เสร็จแล้วไปเปิดในโปรแกรมใดได้บ้าง?', 'ไฟล์ OBJ ที่ส่งออกจาก RETOPO.AI เป็นมาตรฐาน Native Quad Wavefront OBJ สามารถนำไปเปิดใช้งานต่อได้ทันทีใน Blender, Autodesk Maya, 3ds Max, ZBrush, Houdini, Unreal Engine และ Unity', 'Software', 4)
ON DUPLICATE KEY UPDATE `question`=`question`;

