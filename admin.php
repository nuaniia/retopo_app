<?php
/**
 * RETOPO.AI - Admin Management Dashboard
 * Full CRUD for Sample Models (Table 1), FAQs (Table 2), and Users Management
 */

require_once __DIR__ . '/db.php';

// Check if user is logged in as admin
$isAdmin = !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$currentUser = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="th" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | RETOPO.AI</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#0284c7', 600: '#0284c7' },
                        cyber: { bg: '#0f172a', panel: '#1e293b', accent: '#38bdf8' }
                    },
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] }
                }
            }
        }
    </script>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Prompt', sans-serif; }
        .glass-panel { background: rgba(30, 41, 59, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .btn-gradient { background: linear-gradient(135deg, #0284c7 0%, #4f46e5 100%); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Admin Header -->
    <header class="sticky top-0 z-40 glass-panel border-b border-slate-800 px-4 lg:px-8 py-3.5">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="index.html" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-sky-500/30">
                        <i class="fa-solid fa-cube text-xl"></i>
                    </div>
                    <div>
                        <span class="text-lg font-extrabold text-white">RETOPO<span class="text-sky-400">.AI</span></span>
                        <span class="block text-[10px] text-purple-400 font-bold uppercase tracking-wider">แผงควบคุมระบบ (Admin Panel)</span>
                    </div>
                </a>
            </div>

            <div class="flex items-center gap-3">
                <a href="index.html" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold px-3.5 py-2 rounded-xl border border-slate-700 flex items-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-sky-400"></i> ดูหน้าเว็บหลัก
                </a>

                <?php if ($isAdmin): ?>
                    <div class="flex items-center gap-2 bg-slate-900/90 border border-slate-800 px-3 py-1.5 rounded-xl text-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="font-bold text-white"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                        <span class="text-[10px] bg-purple-950 text-purple-300 font-bold px-2 py-0.5 rounded-md border border-purple-800">Admin</span>
                    </div>
                    <button onclick="handleLogout()" class="text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 font-bold px-3.5 py-2 rounded-xl border border-rose-500/30 flex items-center gap-1.5 transition-colors">
                        <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-grow max-w-7xl mx-auto w-full p-4 lg:p-8 space-y-6">

        <?php if (!$isAdmin): ?>
            <!-- Admin Login Required Screen -->
            <div class="max-w-md mx-auto my-12 glass-panel p-8 rounded-3xl border border-slate-800 shadow-2xl space-y-6 text-center">
                <div class="w-16 h-16 rounded-2xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-3xl mx-auto border border-rose-500/20">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <h2 class="text-xl font-extrabold text-white">ต้องการสิทธิ์ผู้ดูแลระบบ (Admin Access)</h2>
                    <p class="text-xs text-slate-400 mt-1">กรุณาเข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบเพื่อเข้าจัดการฐานข้อมูล</p>
                </div>

                <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 text-left text-xs font-mono">
                    <div class="text-purple-400 font-bold mb-1">👑 รหัสผู้ดูแลเริ่มต้น (Default Admin):</div>
                    <div class="text-slate-300">Username: <span class="text-white font-bold">admin</span></div>
                    <div class="text-slate-300">Password: <span class="text-white font-bold">admin123</span></div>
                </div>

                <form id="adminLoginForm" onsubmit="handleAdminLogin(event)" class="space-y-4 text-left">
                    <div id="adminLoginAlert" class="hidden p-3 rounded-xl text-xs font-bold bg-rose-950/80 border border-rose-500/50 text-rose-300"></div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">ชื่อผู้ใช้ (Username หรือ Email)</label>
                        <input type="text" id="adminUser" required class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-xl p-3 focus:outline-none focus:border-sky-500" placeholder="admin">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">รหัสผ่าน (Password)</label>
                        <input type="password" id="adminPass" required class="w-full bg-slate-900 border border-slate-700 text-white text-xs rounded-xl p-3 focus:outline-none focus:border-sky-500" placeholder="••••••••">
                    </div>
                    <button type="submit" class="w-full btn-gradient text-white font-bold py-3 rounded-xl text-xs flex items-center justify-center gap-2">
                        <i class="fa-solid fa-key"></i> เข้าสู่ระบบผู้ดูแล
                    </button>
                </form>
            </div>
        <?php else: ?>

            <!-- Dashboard Header & Tabs -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-800 pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-database text-sky-400"></i> การจัดการข้อมูลในฐานข้อมูล (MySQL / XAMPP)
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">เพิ่ม แก้ไข ลบ และนำข้อมูลจาก 2 ตารางหลักไปแสดงผลบนหน้าบ้านได้แบบเรียลไทม์</p>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center gap-2 bg-slate-900/90 p-1.5 rounded-2xl border border-slate-800 text-xs">
                    <button onclick="switchTab('models')" id="tabModelsBtn" class="px-4 py-2 rounded-xl font-bold transition-all bg-sky-500 text-white shadow-md">
                        <i class="fa-solid fa-cube mr-1"></i> 1. ตัวอย่างโมเดล 3D
                    </button>
                    <button onclick="switchTab('faqs')" id="tabFaqsBtn" class="px-4 py-2 rounded-xl font-bold transition-all text-slate-400 hover:text-white">
                        <i class="fa-solid fa-circle-question mr-1"></i> 2. คำถามที่พบบ่อย (FAQs)
                    </button>
                    <button onclick="switchTab('users')" id="tabUsersBtn" class="px-4 py-2 rounded-xl font-bold transition-all text-slate-400 hover:text-white">
                        <i class="fa-solid fa-users mr-1"></i> ผู้ใช้งานระบบ
                    </button>
                </div>
            </div>

            <!-- Global Alert Box -->
            <div id="globalAlert" class="hidden p-4 rounded-2xl text-xs font-bold transition-all"></div>

            <!-- ============================================================ -->
            <!-- TAB 1: SAMPLE MODELS MANAGEMENT (TABLE 1)                    -->
            <!-- ============================================================ -->
            <div id="tabModelsSection" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>ตารางที่ 1: `sample_models`</span>
                            <span id="modelCountBadge" class="text-[10px] bg-sky-950 text-sky-400 font-bold px-2.5 py-0.5 rounded-full border border-sky-800">0 รายการ</span>
                        </h2>
                        <p class="text-xs text-slate-400">ข้อมูลที่แสดงในการ์ดตัวอย่างโมเดลด้านล่างหน้าแรก (Home) และหน้า 3D Studio</p>
                    </div>

                    <button onclick="openModelModal('add')" class="btn-gradient text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-lg shadow-sky-500/20">
                        <i class="fa-solid fa-plus"></i> เพิ่มตัวอย่างโมเดลใหม่
                    </button>
                </div>

                <!-- Models Table -->
                <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-900/90 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800">
                                <tr>
                                    <th class="p-4">ID</th>
                                    <th class="p-4">ชื่อโมเดล / ไอคอน</th>
                                    <th class="p-4">รหัสพรีเซ็ต</th>
                                    <th class="p-4">ป้ายกำกับ</th>
                                    <th class="p-4">ก่อน Retopo</th>
                                    <th class="p-4">หลัง Retopo</th>
                                    <th class="p-4">สัดส่วน Quad</th>
                                    <th class="p-4 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="modelsTableBody" class="divide-y divide-slate-800/80 text-slate-300">
                                <tr><td colspan="8" class="p-6 text-center text-slate-500">กำลังโหลดข้อมูล...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 2: FAQS MANAGEMENT (TABLE 2)                             -->
            <!-- ============================================================ -->
            <div id="tabFaqsSection" class="hidden space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <span>ตารางที่ 2: `faqs`</span>
                            <span id="faqCountBadge" class="text-[10px] bg-purple-950 text-purple-400 font-bold px-2.5 py-0.5 rounded-full border border-purple-800">0 รายการ</span>
                        </h2>
                        <p class="text-xs text-slate-400">คำถาม-คำตอบที่แสดงในหน้า FAQ (คำถามที่พบบ่อย) ของเว็บไซต์</p>
                    </div>

                    <button onclick="openFaqModal('add')" class="btn-gradient text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-1.5 shadow-lg shadow-purple-500/20">
                        <i class="fa-solid fa-plus"></i> เพิ่มคำถามใหม่
                    </button>
                </div>

                <!-- FAQs Table -->
                <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-900/90 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800">
                                <tr>
                                    <th class="p-4">ID</th>
                                    <th class="p-4">หมวดหมู่</th>
                                    <th class="p-4">คำถาม (Question)</th>
                                    <th class="p-4">คำตอบย่อ (Answer)</th>
                                    <th class="p-4 text-center">ลำดับ</th>
                                    <th class="p-4 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="faqsTableBody" class="divide-y divide-slate-800/80 text-slate-300">
                                <tr><td colspan="6" class="p-6 text-center text-slate-500">กำลังโหลดข้อมูล...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAB 3: USERS LIST (Admin View)                               -->
            <!-- ============================================================ -->
            <div id="tabUsersSection" class="hidden space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-white">ตาราง `users` (รายชื่อสมาชิกในระบบ)</h2>
                        <p class="text-xs text-slate-400">ตรวจสอบบัญชีผู้ดูแลระบบและสมาชิกทั่วไปที่สมัครเข้ามา</p>
                    </div>
                </div>

                <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-900/90 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-800">
                                <tr>
                                    <th class="p-4">ID</th>
                                    <th class="p-4">Username</th>
                                    <th class="p-4">ชื่อ-นามสกุล</th>
                                    <th class="p-4">Email</th>
                                    <th class="p-4">สิทธิ์ (Role)</th>
                                    <th class="p-4">วันที่สมัคร</th>
                                    <th class="p-4 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody" class="divide-y divide-slate-800/80 text-slate-300">
                                <tr><td colspan="7" class="p-6 text-center text-slate-500">กำลังโหลดข้อมูล...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <!-- ============================================================ -->
    <!-- MODAL 1: ADD / EDIT SAMPLE MODEL                             -->
    <!-- ============================================================ -->
    <div id="modelModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
        <div class="glass-panel max-w-xl w-full rounded-3xl border border-slate-800 overflow-hidden shadow-2xl p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 id="modelModalTitle" class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-cube text-sky-400"></i> เพิ่มตัวอย่างโมเดลใหม่
                </h3>
                <button type="button" onclick="closeModelModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <form id="modelForm" onsubmit="saveModel(event)" class="space-y-4 text-xs">
                <input type="hidden" id="modelId" value="">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">ชื่อโมเดล (Display Name)</label>
                        <input type="text" id="modelName" required class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500" placeholder="เช่น High-Poly Icosphere Prop">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">รหัสพรีเซ็ต 3D Viewport (Code Name)</label>
                        <select id="modelCodeName" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500">
                            <option value="sphere">sphere (ทรงกลม)</option>
                            <option value="torus">torus (ห่วงโดนัท)</option>
                            <option value="capsule">capsule (แคปซูล)</option>
                            <option value="suzanne">suzanne (หัวลิง Suzanne)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">ข้อความบนป้าย (Badge Text)</label>
                        <input type="text" id="modelBadgeText" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500" placeholder="เช่น High-Poly Sphere">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">ไอคอน FontAwesome</label>
                        <select id="modelIconClass" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500">
                            <option value="fa-solid fa-globe">🌐 fa-globe (ลูกโลก/ทรงกลม)</option>
                            <option value="fa-solid fa-ring">💍 fa-ring (ห่วง)</option>
                            <option value="fa-solid fa-capsules">💊 fa-capsules (แคปซูล)</option>
                            <option value="fa-solid fa-cube">🧊 fa-cube (ลูกบาศก์)</option>
                            <option value="fa-solid fa-face-smile">🐵 fa-face-smile (ใบหน้า)</option>
                            <option value="fa-solid fa-shapes">🔺 fa-shapes (เรขาคณิต)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">โพลีกอนก่อน (Faces)</label>
                        <input type="number" id="modelPolyBefore" required class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500" placeholder="5120">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">โพลีกอนหลัง (Quads)</label>
                        <input type="number" id="modelPolyAfter" required class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500" placeholder="2560">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">สัดส่วน Quad (%)</label>
                        <input type="text" id="modelQuadRatio" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500" placeholder="98.5%">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">คำอธิบายโมเดล</label>
                    <textarea id="modelDesc" rows="2" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-sky-500" placeholder="คำอธิบายสั้นๆ ของโมเดล"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModelModal()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold px-4 py-2.5 rounded-xl">ยกเลิก</button>
                    <button type="submit" class="btn-gradient text-white font-bold px-6 py-2.5 rounded-xl flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MODAL 2: ADD / EDIT FAQ                                      -->
    <!-- ============================================================ -->
    <div id="faqModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
        <div class="glass-panel max-w-xl w-full rounded-3xl border border-slate-800 overflow-hidden shadow-2xl p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 id="faqModalTitle" class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-circle-question text-purple-400"></i> เพิ่มคำถามใหม่
                </h3>
                <button type="button" onclick="closeFaqModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <form id="faqForm" onsubmit="saveFaq(event)" class="space-y-4 text-xs">
                <input type="hidden" id="faqId" value="">
                
                <div>
                    <label class="block font-bold text-slate-300 mb-1">คำถาม (Question)</label>
                    <input type="text" id="faqQuestion" required class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-purple-500" placeholder="พิมพ์คำถามที่พบบ่อย">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">หมวดหมู่ (Category)</label>
                        <select id="faqCategory" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-purple-500">
                            <option value="General">ทั่วไป (General)</option>
                            <option value="Standard">มาตรฐาน (Standard)</option>
                            <option value="Upload">การอัปโหลดไฟล์ (Upload)</option>
                            <option value="Viewport">การแสดงผล 3D (Viewport)</option>
                            <option value="Software">ซอฟต์แวร์ที่รองรับ (Software)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 mb-1">ลำดับการแสดงผล (Sort Order)</label>
                        <input type="number" id="faqOrder" value="1" class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-purple-500">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">คำตอบ (Answer)</label>
                    <textarea id="faqAnswer" rows="4" required class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl p-2.5 focus:border-purple-500" placeholder="พิมพ์คำอธิบายคำตอบแบบละเอียด"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeFaqModal()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold px-4 py-2.5 rounded-xl">ยกเลิก</button>
                    <button type="submit" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold px-6 py-2.5 rounded-xl flex items-center gap-1.5">
                        <i class="fa-solid fa-floppy-disk"></i> บันทึกคำถาม
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-side Admin Logic -->
    <script>
        let cachedModels = [];
        let cachedFaqs = [];

        function showNotification(msg, isSuccess = true) {
            const box = document.getElementById('globalAlert');
            if (!box) return;
            box.className = isSuccess 
                ? 'p-4 rounded-2xl text-xs font-bold bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 transition-all flex items-center gap-2'
                : 'p-4 rounded-2xl text-xs font-bold bg-rose-950/80 border border-rose-500/50 text-rose-300 transition-all flex items-center gap-2';
            box.innerHTML = `<i class="fa-solid ${isSuccess ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i> ${msg}`;
            box.classList.remove('hidden');
            setTimeout(() => { box.classList.add('hidden'); }, 4000);
        }

        function switchTab(tab) {
            document.getElementById('tabModelsSection').classList.toggle('hidden', tab !== 'models');
            document.getElementById('tabFaqsSection').classList.toggle('hidden', tab !== 'faqs');
            document.getElementById('tabUsersSection').classList.toggle('hidden', tab !== 'users');

            document.getElementById('tabModelsBtn').className = tab === 'models' 
                ? 'px-4 py-2 rounded-xl font-bold transition-all bg-sky-500 text-white shadow-md' 
                : 'px-4 py-2 rounded-xl font-bold transition-all text-slate-400 hover:text-white';
            document.getElementById('tabFaqsBtn').className = tab === 'faqs' 
                ? 'px-4 py-2 rounded-xl font-bold transition-all bg-purple-600 text-white shadow-md' 
                : 'px-4 py-2 rounded-xl font-bold transition-all text-slate-400 hover:text-white';
            document.getElementById('tabUsersBtn').className = tab === 'users' 
                ? 'px-4 py-2 rounded-xl font-bold transition-all bg-indigo-600 text-white shadow-md' 
                : 'px-4 py-2 rounded-xl font-bold transition-all text-slate-400 hover:text-white';

            if (tab === 'models') loadModels();
            if (tab === 'faqs') loadFaqs();
            if (tab === 'users') loadUsers();
        }

        async function handleAdminLogin(e) {
            e.preventDefault();
            const username = document.getElementById('adminUser').value;
            const password = document.getElementById('adminPass').value;
            const alertBox = document.getElementById('adminLoginAlert');

            try {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('password', password);

                const res = await fetch('api.php?action=login', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    if (data.data && data.data.role === 'admin') {
                        window.location.reload();
                    } else {
                        alertBox.innerText = 'บัญชีนี้ไม่ใช่ผู้ดูแลระบบ (Admin)';
                        alertBox.classList.remove('hidden');
                    }
                } else {
                    alertBox.innerText = data.message || 'เข้าสู่ระบบไม่สำเร็จ';
                    alertBox.classList.remove('hidden');
                }
            } catch (err) {
                alertBox.innerText = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์ MySQL';
                alertBox.classList.remove('hidden');
            }
        }

        async function handleLogout() {
            if (confirm('คุณต้องการออกจากระบบหรือไม่?')) {
                await fetch('api.php?action=logout');
                window.location.href = 'index.html';
            }
        }

        // ============================================================
        // Models Management (Table 1)
        // ============================================================
        async function loadModels() {
            try {
                const res = await fetch('api.php?action=get_models');
                const json = await res.json();
                const tbody = document.getElementById('modelsTableBody');
                if (!tbody) return;

                if (json.success && Array.isArray(json.data)) {
                    cachedModels = json.data;
                    document.getElementById('modelCountBadge').innerText = `${json.data.length} รายการ`;

                    if (json.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="8" class="p-6 text-center text-slate-500">ยังไม่มีข้อมูลในตาราง</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = json.data.map(m => `
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-400">#${m.id}</td>
                            <td class="p-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-400 flex items-center justify-center text-sm">
                                        <i class="${m.icon_class || 'fa-solid fa-cube'}"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-white">${m.name}</div>
                                        <div class="text-[11px] text-slate-400 truncate max-w-xs">${m.description || '-'}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 font-mono text-purple-300 font-bold">${m.code_name}</td>
                            <td class="p-4"><span class="bg-sky-950 text-sky-300 border border-sky-800 px-2 py-0.5 rounded text-[10px] font-bold">${m.badge_text}</span></td>
                            <td class="p-4 font-bold text-amber-400">${parseInt(m.poly_count_before).toLocaleString()}</td>
                            <td class="p-4 font-bold text-emerald-400">${parseInt(m.poly_count_after).toLocaleString()}</td>
                            <td class="p-4 font-bold text-purple-400">${m.quad_ratio}</td>
                            <td class="p-4 text-center">
                                <div class="inline-flex items-center gap-2">
                                    <button onclick="editModel(${m.id})" class="p-1.5 rounded-lg bg-slate-800 hover:bg-sky-500/20 text-sky-400 border border-slate-700 transition-colors" title="แก้ไข">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button onclick="deleteModel(${m.id}, '${m.name.replace(/'/g, "\\'")}')" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-rose-400 border border-slate-700 transition-colors" title="ลบ">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (err) {
                console.error(err);
            }
        }

        function openModelModal(mode) {
            document.getElementById('modelForm').reset();
            document.getElementById('modelId').value = '';
            document.getElementById('modelModalTitle').innerHTML = mode === 'add' 
                ? '<i class="fa-solid fa-cube text-sky-400"></i> เพิ่มตัวอย่างโมเดลใหม่' 
                : '<i class="fa-solid fa-pen-to-square text-sky-400"></i> แก้ไขข้อมูลตัวอย่างโมเดล';
            document.getElementById('modelModal').classList.remove('hidden');
        }

        function closeModelModal() {
            document.getElementById('modelModal').classList.add('hidden');
        }

        function editModel(id) {
            const m = cachedModels.find(x => x.id == id);
            if (!m) return;
            openModelModal('edit');
            document.getElementById('modelId').value = m.id;
            document.getElementById('modelName').value = m.name;
            document.getElementById('modelCodeName').value = m.code_name;
            document.getElementById('modelBadgeText').value = m.badge_text;
            document.getElementById('modelIconClass').value = m.icon_class;
            document.getElementById('modelPolyBefore').value = m.poly_count_before;
            document.getElementById('modelPolyAfter').value = m.poly_count_after;
            document.getElementById('modelQuadRatio').value = m.quad_ratio;
            document.getElementById('modelDesc').value = m.description;
        }

        async function saveModel(e) {
            e.preventDefault();
            const id = document.getElementById('modelId').value;
            const action = id ? 'update_model' : 'add_model';

            const formData = new FormData();
            if (id) formData.append('id', id);
            formData.append('name', document.getElementById('modelName').value);
            formData.append('code_name', document.getElementById('modelCodeName').value);
            formData.append('badge_text', document.getElementById('modelBadgeText').value);
            formData.append('icon_class', document.getElementById('modelIconClass').value);
            formData.append('poly_count_before', document.getElementById('modelPolyBefore').value);
            formData.append('poly_count_after', document.getElementById('modelPolyAfter').value);
            formData.append('quad_ratio', document.getElementById('modelQuadRatio').value);
            formData.append('description', document.getElementById('modelDesc').value);

            try {
                const res = await fetch(`api.php?action=${action}`, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    closeModelModal();
                    showNotification(data.message);
                    loadModels();
                } else {
                    alert(data.message || 'บันทึกข้อมูลไม่สำเร็จ');
                }
            } catch (err) {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
        }

        async function deleteModel(id, name) {
            if (confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบโมเดล "${name}" ?`)) {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('api.php?action=delete_model', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showNotification(data.message);
                    loadModels();
                } else {
                    alert(data.message);
                }
            }
        }

        // ============================================================
        // FAQs Management (Table 2)
        // ============================================================
        async function loadFaqs() {
            try {
                const res = await fetch('api.php?action=get_faqs');
                const json = await res.json();
                const tbody = document.getElementById('faqsTableBody');
                if (!tbody) return;

                if (json.success && Array.isArray(json.data)) {
                    cachedFaqs = json.data;
                    document.getElementById('faqCountBadge').innerText = `${json.data.length} รายการ`;

                    if (json.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" class="p-6 text-center text-slate-500">ยังไม่มีข้อมูลในตาราง</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = json.data.map(f => `
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-400">#${f.id}</td>
                            <td class="p-4"><span class="bg-purple-950 text-purple-300 border border-purple-800 px-2 py-0.5 rounded text-[10px] font-bold">${f.category}</span></td>
                            <td class="p-4 font-bold text-white max-w-xs">${f.question}</td>
                            <td class="p-4 text-slate-400 truncate max-w-sm">${f.answer}</td>
                            <td class="p-4 text-center font-mono font-bold">${f.sort_order}</td>
                            <td class="p-4 text-center">
                                <div class="inline-flex items-center gap-2">
                                    <button onclick="editFaq(${f.id})" class="p-1.5 rounded-lg bg-slate-800 hover:bg-purple-500/20 text-purple-400 border border-slate-700 transition-colors" title="แก้ไข">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button onclick="deleteFaq(${f.id})" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-rose-400 border border-slate-700 transition-colors" title="ลบ">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (err) {
                console.error(err);
            }
        }

        function openFaqModal(mode) {
            document.getElementById('faqForm').reset();
            document.getElementById('faqId').value = '';
            document.getElementById('faqModalTitle').innerHTML = mode === 'add'
                ? '<i class="fa-solid fa-circle-question text-purple-400"></i> เพิ่มคำถามใหม่'
                : '<i class="fa-solid fa-pen-to-square text-purple-400"></i> แก้ไขคำถามที่พบบ่อย';
            document.getElementById('faqModal').classList.remove('hidden');
        }

        function closeFaqModal() {
            document.getElementById('faqModal').classList.add('hidden');
        }

        function editFaq(id) {
            const f = cachedFaqs.find(x => x.id == id);
            if (!f) return;
            openFaqModal('edit');
            document.getElementById('faqId').value = f.id;
            document.getElementById('faqQuestion').value = f.question;
            document.getElementById('faqCategory').value = f.category;
            document.getElementById('faqOrder').value = f.sort_order;
            document.getElementById('faqAnswer').value = f.answer;
        }

        async function saveFaq(e) {
            e.preventDefault();
            const id = document.getElementById('faqId').value;
            const action = id ? 'update_faq' : 'add_faq';

            const formData = new FormData();
            if (id) formData.append('id', id);
            formData.append('question', document.getElementById('faqQuestion').value);
            formData.append('category', document.getElementById('faqCategory').value);
            formData.append('sort_order', document.getElementById('faqOrder').value);
            formData.append('answer', document.getElementById('faqAnswer').value);

            try {
                const res = await fetch(`api.php?action=${action}`, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    closeFaqModal();
                    showNotification(data.message);
                    loadFaqs();
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
        }

        async function deleteFaq(id) {
            if (confirm('คุณแน่ใจหรือไม่ว่าต้องการลบคำถามนี้?')) {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('api.php?action=delete_faq', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showNotification(data.message);
                    loadFaqs();
                } else {
                    alert(data.message);
                }
            }
        }

        // ============================================================
        // Users Management
        // ============================================================
        async function loadUsers() {
            try {
                const res = await fetch('api.php?action=get_users');
                const json = await res.json();
                const tbody = document.getElementById('usersTableBody');
                if (!tbody) return;

                if (json.success && Array.isArray(json.data)) {
                    tbody.innerHTML = json.data.map(u => `
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-400">#${u.id}</td>
                            <td class="p-4 font-bold text-white">${u.username}</td>
                            <td class="p-4">${u.full_name}</td>
                            <td class="p-4 text-slate-400">${u.email}</td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${u.role === 'admin' ? 'bg-purple-950 text-purple-300 border-purple-800' : 'bg-emerald-950 text-emerald-300 border-emerald-800'}">
                                    ${u.role}
                                </span>
                            </td>
                            <td class="p-4 text-slate-400 font-mono text-[11px]">${u.created_at}</td>
                            <td class="p-4 text-center">
                                <button onclick="deleteUser(${u.id}, '${u.username}')" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-rose-400 border border-slate-700 transition-colors" title="ลบผู้ใช้">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function deleteUser(id, username) {
            if (confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ "${username}"?`)) {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('api.php?action=delete_user', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showNotification(data.message);
                    loadUsers();
                } else {
                    alert(data.message);
                }
            }
        }

        // Initialize on load
        window.addEventListener('DOMContentLoaded', () => {
            <?php if ($isAdmin): ?>
                loadModels();
            <?php endif; ?>
        });
    </script>
</body>
</html>

