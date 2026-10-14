/**
 * RETOPO.AI - Authentication Modal & Dynamic Content Module
 * Handles:
 * - Backdrop-blurred Login & Register Modal Popup
 * - Session Management (Check session, Login, Register, Logout)
 * - Navbar User Profile & Role Badges (Admin Panel link if admin)
 * - Dynamic Data Fetching for Sample Models & FAQs (Connected to XAMPP MySQL)
 */

window.currentUser = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initAuthModal();
    checkUserSession();
    initDynamicContent();
});

/**
 * 1. INJECT AUTH MODAL INTO DOM
 */
function initAuthModal() {
    if (document.getElementById('authModal')) return;

    const modalHtml = `
    <!-- AUTHENTICATION POPUP MODAL (BLURRED BACKGROUND) -->
    <div id="authModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md transition-all duration-300">
        <div id="authModalBox" class="glass-panel w-full max-w-md rounded-3xl border border-slate-700/80 shadow-2xl p-6 sm:p-8 relative bg-slate-900/95 text-white transform transition-all duration-300 scale-95 opacity-0">
            
            <!-- Close Button -->
            <button type="button" onclick="closeAuthModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center text-sm transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <!-- Brand Header -->
            <div class="text-center mb-6">
                <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-purple-500 flex items-center justify-center text-white shadow-lg shadow-sky-500/30">
                    <i class="fa-solid fa-shield-halved text-2xl"></i>
                </div>
                <h3 id="authModalTitle" class="text-xl font-extrabold text-white">ระบบสมาชิก RETOPO.AI</h3>
                <p class="text-xs text-slate-400 mt-1">เข้าสู่ระบบเพื่อจัดการโมเดลและใช้งานระบบเต็มรูปแบบ</p>
            </div>

            <!-- Tab Switcher -->
            <div class="flex rounded-xl bg-slate-800/80 p-1 mb-6 border border-slate-700">
                <button type="button" id="tabLoginBtn" onclick="switchAuthTab('login')" class="flex-1 py-2 text-xs font-bold rounded-lg transition-all duration-200 text-white bg-sky-600 shadow-md">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i> เข้าสู่ระบบ (Sign In)
                </button>
                <button type="button" id="tabRegisterBtn" onclick="switchAuthTab('register')" class="flex-1 py-2 text-xs font-bold rounded-lg transition-all duration-200 text-slate-400 hover:text-white">
                    <i class="fa-solid fa-user-plus mr-1.5"></i> สมัครสมาชิก (Sign Up)
                </button>
            </div>

            <!-- Notification Alert Box -->
            <div id="authAlert" class="hidden mb-4 p-3 rounded-xl text-xs font-medium border flex items-center gap-2"></div>

            <!-- 1. SIGN IN FORM -->
            <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">ชื่อผู้ใช้ หรือ อีเมล</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                        <input type="text" id="loginUsername" required placeholder="admin หรือ student" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">รหัสผ่าน</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                        <input type="password" id="loginPassword" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-9 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 transition-colors">
                        <button type="button" onclick="togglePasswordVisibility('loginPassword', this)" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-200 text-xs">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Quick Test Login Buttons -->
                <div class="pt-1">
                    <span class="block text-[11px] text-slate-400 mb-2">บัญชีทดสอบด่วน (คลิกเพื่อกรอกอัตโนมัติ):</span>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="fillQuickAuth('admin', 'admin123')" class="px-2.5 py-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-300 text-[11px] font-bold text-left flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-crown text-amber-400"></i> Admin Panel
                        </button>
                        <button type="button" onclick="fillQuickAuth('student', 'user123')" class="px-2.5 py-1.5 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 text-sky-300 text-[11px] font-bold text-left flex items-center gap-1.5 transition">
                            <i class="fa-solid fa-user text-sky-400"></i> สมาชิกทั่วไป
                        </button>
                    </div>
                </div>

                <button type="submit" id="btnLoginSubmit" class="w-full btn-gradient text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-2 mt-4 cursor-pointer">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> เข้าสู่ระบบ
                </button>
            </form>

            <!-- 2. SIGN UP FORM -->
            <form id="registerForm" onsubmit="handleRegisterSubmit(event)" class="space-y-3.5 hidden">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">ชื่อผู้ใช้ (Username)</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                        <input type="text" id="regUsername" required placeholder="myusername" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">อีเมล (Email)</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                        <input type="email" id="regEmail" required placeholder="user@example.com" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">รหัสผ่าน</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                        <input type="password" id="regPassword" required placeholder="อย่างน้อย 4 ตัวอักษร" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-9 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 transition-colors">
                        <button type="button" onclick="togglePasswordVisibility('regPassword', this)" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-200 text-xs">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="btnRegisterSubmit" class="w-full btn-gradient text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-2 mt-4 cursor-pointer">
                    <i class="fa-solid fa-user-plus"></i> ยืนยันการสมัครสมาชิก
                </button>
            </form>

            <!-- Bottom Footnote -->
            <div class="mt-4 pt-4 border-t border-slate-800/80 text-center text-[11px] text-slate-400">
                ฐานข้อมูล XAMPP MySQL <span class="text-sky-400">retopo_db</span> • รองรับบทบาท Admin & User
            </div>

        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Close on clicking backdrop
    const modal = document.getElementById('authModal');
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeAuthModal();
    });

    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeAuthModal();
        }
    });
}

/**
 * 2. MODAL CONTROLS & ANIMATIONS
 */
function openAuthModal(tab = 'login') {
    initAuthModal();
    const modal = document.getElementById('authModal');
    const box = document.getElementById('authModalBox');
    hideAlert();
    switchAuthTab(tab);

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    requestAnimationFrame(() => {
        box.classList.remove('scale-95', 'opacity-0');
        box.classList.add('scale-100', 'opacity-100');
    });
}

function closeAuthModal() {
    const modal = document.getElementById('authModal');
    const box = document.getElementById('authModalBox');
    if (!modal) return;

    box.classList.remove('scale-100', 'opacity-100');
    box.classList.add('scale-95', 'opacity-0');

    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

function switchAuthTab(tab) {
    hideAlert();
    const tabLogin = document.getElementById('tabLoginBtn');
    const tabReg = document.getElementById('tabRegisterBtn');
    const formLogin = document.getElementById('loginForm');
    const formReg = document.getElementById('registerForm');
    const modalTitle = document.getElementById('authModalTitle');

    if (tab === 'login') {
        tabLogin.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition-all duration-200 text-white bg-sky-600 shadow-md';
        tabReg.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition-all duration-200 text-slate-400 hover:text-white';
        formLogin.classList.remove('hidden');
        formReg.classList.add('hidden');
        modalTitle.innerText = 'เข้าสู่ระบบ RETOPO.AI';
    } else {
        tabReg.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition-all duration-200 text-white bg-sky-600 shadow-md';
        tabLogin.className = 'flex-1 py-2 text-xs font-bold rounded-lg transition-all duration-200 text-slate-400 hover:text-white';
        formReg.classList.remove('hidden');
        formLogin.classList.add('hidden');
        modalTitle.innerText = 'สมัครสมาชิกใหม่';
    }
}

function togglePasswordVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
    }
}

function fillQuickAuth(username, password) {
    switchAuthTab('login');
    const userInput = document.getElementById('loginUsername');
    const passInput = document.getElementById('loginPassword');
    if (userInput) userInput.value = username;
    if (passInput) passInput.value = password;
}

function showAlert(message, isSuccess = false) {
    const alert = document.getElementById('authAlert');
    if (!alert) return;
    alert.classList.remove('hidden');
    if (isSuccess) {
        alert.className = 'mb-4 p-3 rounded-xl text-xs font-medium border flex items-center gap-2 bg-emerald-950/60 border-emerald-700/80 text-emerald-300';
        alert.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-400"></i> <span>${message}</span>`;
    } else {
        alert.className = 'mb-4 p-3 rounded-xl text-xs font-medium border flex items-center gap-2 bg-rose-950/60 border-rose-700/80 text-rose-300';
        alert.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-rose-400"></i> <span>${message}</span>`;
    }
}

function hideAlert() {
    const alert = document.getElementById('authAlert');
    if (alert) alert.classList.add('hidden');
}

/**
 * 3. AUTH API INTERACTIONS (LOGIN, REGISTER, SESSION, LOGOUT)
 */
async function checkUserSession() {
    try {
        const res = await fetch('api.php?action=check_session');
        const data = await res.json();
        if (data && data.success && data.data) {
            window.currentUser = data.data;
        } else {
            window.currentUser = null;
        }
    } catch (e) {
        console.warn('Backend session check skipped or offline:', e);
        window.currentUser = null;
    }
    renderAuthNavbar();
}

async function handleLoginSubmit(e) {
    e.preventDefault();
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    const btn = document.getElementById('btnLoginSubmit');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังตรวจสอบ...';

    try {
        const formData = new FormData();
        formData.append('username', username);
        formData.append('password', password);

        const res = await fetch('api.php?action=login', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            showAlert('เข้าสู่ระบบสำเร็จ กำลังโหลดข้อมูล...', true);
            window.currentUser = data.data;
            renderAuthNavbar();
            setTimeout(() => {
                closeAuthModal();
                if (data.extra && data.extra.redirect) {
                    window.location.href = data.extra.redirect;
                }
            }, 800);
        } else {
            showAlert(data.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        }
    } catch (err) {
        showAlert('ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ โปรดตรวจสอบว่าเปิด Apache & MySQL ใน XAMPP แล้ว');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> เข้าสู่ระบบ';
    }
}

async function handleRegisterSubmit(e) {
    e.preventDefault();
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value.trim();
    const btn = document.getElementById('btnRegisterSubmit');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึกข้อมูล...';

    try {
        const formData = new FormData();
        formData.append('username', username);
        formData.append('email', email);
        formData.append('password', password);

        const res = await fetch('api.php?action=register', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            showAlert('สมัครสมาชิกสำเร็จ ยินดีต้อนรับสู่ระบบ!', true);
            window.currentUser = data.data;
            renderAuthNavbar();
            setTimeout(() => {
                closeAuthModal();
            }, 1000);
        } else {
            showAlert(data.message || 'ไม่สามารถสมัครสมาชิกได้');
        }
    } catch (err) {
        showAlert('ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ โปรดตรวจสอบการทำงานของ XAMPP');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-user-plus"></i> ยืนยันการสมัครสมาชิก';
    }
}

async function handleLogout() {
    try {
        await fetch('api.php?action=logout');
    } catch (err) {
        console.error('Logout error:', err);
    }
    window.currentUser = null;
    renderAuthNavbar();
    // If currently on admin page, redirect to index
    if (window.location.pathname.includes('admin.php')) {
        window.location.href = 'index.html';
    }
}

/**
 * 4. RENDER NAVBAR STATE
 */
function renderAuthNavbar() {
    const desktopContainer = document.getElementById('authNavContainer');
    const mobileContainer = document.getElementById('mobileAuthNavContainer');

    const u = window.currentUser;

    let desktopContent = '';
    let mobileContent = '';

    if (u) {
        const isAdmin = (u.role === 'admin');
        const roleBadge = isAdmin 
            ? `<span class="bg-amber-500/20 text-amber-300 border border-amber-500/40 text-[10px] font-extrabold px-2 py-0.5 rounded-full"><i class="fa-solid fa-crown text-amber-400 mr-1"></i>Admin</span>`
            : `<span class="bg-sky-500/20 text-sky-300 border border-sky-500/40 text-[10px] font-bold px-2 py-0.5 rounded-full"><i class="fa-solid fa-user text-sky-400 mr-1"></i>User</span>`;

        desktopContent = `
            <div class="flex items-center gap-2">
                <div class="hidden lg:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/90 border border-slate-700 text-xs text-white">
                    ${roleBadge}
                    <span class="font-bold truncate max-w-[100px]">${u.username}</span>
                </div>
                ${isAdmin ? `
                <a href="admin.php" class="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-extrabold text-xs px-3 py-2 rounded-lg flex items-center gap-1.5 shadow-md shadow-amber-500/20 transition-transform hover:scale-105">
                    <i class="fa-solid fa-gear"></i> <span>แผงผู้ดูแล</span>
                </a>` : ''}
                <button type="button" onclick="handleLogout()" title="ออกจากระบบ" class="bg-slate-800 hover:bg-rose-950/70 text-slate-400 hover:text-rose-400 border border-slate-700 hover:border-rose-500/40 text-xs px-3 py-2 rounded-lg flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-right-from-bracket"></i> <span class="hidden sm:inline">ออก</span>
                </button>
            </div>
        `;

        mobileContent = `
            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800 space-y-2 mt-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-300">เข้าสู่ระบบโดย: <strong>${u.username}</strong></span>
                    ${roleBadge}
                </div>
                ${isAdmin ? `
                <a href="admin.php" class="block text-center bg-amber-500 text-slate-950 font-bold py-2 rounded-lg text-xs">
                    <i class="fa-solid fa-gear mr-1"></i> เข้าสู่แผงจัดการผู้ดูแล (Admin Dashboard)
                </a>` : ''}
                <button type="button" onclick="handleLogout()" class="w-full text-center bg-slate-800 hover:bg-rose-900/60 text-rose-300 py-1.5 rounded-lg text-xs font-semibold border border-slate-700">
                    <i class="fa-solid fa-right-from-bracket mr-1"></i> ออกจากระบบ
                </button>
            </div>
        `;
    } else {
        desktopContent = ``;

        mobileContent = `
            <div class="pt-2 border-t border-slate-800 flex gap-2">
                <button type="button" onclick="openAuthModal('login')" class="flex-1 bg-sky-600 hover:bg-sky-500 text-white font-bold py-2 rounded-lg text-xs text-center">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> เข้าสู่ระบบ
                </button>
                <button type="button" onclick="openAuthModal('register')" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold py-2 rounded-lg text-xs text-center border border-slate-700">
                    <i class="fa-solid fa-user-plus mr-1"></i> สมัครสมาชิก
                </button>
            </div>
        `;
    }

    if (desktopContainer) desktopContainer.innerHTML = desktopContent;
    if (mobileContainer) mobileContainer.innerHTML = mobileContent;
}

/**
 * 5. DYNAMIC DATA INTEGRATION (TABLE 1: SAMPLE MODELS & TABLE 2: FAQS)
 * Fetches dynamic data from XAMPP MySQL (api.php) and updates front-end views
 */
async function initDynamicContent() {
    // 5.1 Sample Models Grid (In index.html & studio.html)
    const galleryGrid = document.getElementById('dynamicGalleryGrid');
    if (galleryGrid) {
        loadDynamicSampleModels(galleryGrid);
    }

    // 5.2 FAQs Accordion (In faq.html)
    const faqContainer = document.getElementById('dynamicFaqContainer');
    if (faqContainer) {
        loadDynamicFaqs(faqContainer);
    }
}

async function loadDynamicSampleModels(container) {
    try {
        const res = await fetch('api.php?action=get_models');
        const data = await res.json();
        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
            container.innerHTML = data.data.map(model => {
                const icon = model.thumbnail_icon || 'fa-solid fa-cube';
                const tag = model.tag || 'Standard Asset';
                const isStudio = window.location.pathname.includes('studio.html');
                const actionBtn = isStudio
                    ? `<button type="button" onclick="changeSampleModel('${model.code_name}')" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold py-2.5 rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-eye text-sky-400"></i> โหลดใน 3D Viewport
                       </button>`
                    : `<button type="button" onclick="selectPresetModel('${model.code_name}')" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold py-2.5 rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-eye text-sky-400"></i> โหลดใน 3D Viewport
                       </button>`;

                return `
                <div class="glass-panel p-5 rounded-2xl border border-slate-800 space-y-4 hover:border-sky-500/40 transition-all flex flex-col justify-between">
                    <div>
                        <div class="w-full h-44 bg-slate-900 rounded-xl overflow-hidden relative flex items-center justify-center border border-slate-800 mb-4">
                            <i class="${icon} text-6xl text-sky-500/30"></i>
                            <span class="absolute top-3 left-3 bg-sky-950/80 text-sky-300 text-[10px] font-bold px-2.5 py-1 rounded-md border border-sky-800">${escapeHtml(tag)}</span>
                        </div>
                        <h4 class="font-bold text-white text-base">${escapeHtml(model.name)}</h4>
                        <p class="text-xs text-slate-400 mt-1 line-clamp-2">${escapeHtml(model.description || '')}</p>
                        <div class="flex justify-between text-xs text-slate-400 mt-3 pt-3 border-t border-slate-800">
                            <span>ก่อน Retopo: <strong class="text-amber-400">${Number(model.polycount_before).toLocaleString()} faces</strong></span>
                            <span>หลัง Retopo: <strong class="text-emerald-400">${Number(model.polycount_after).toLocaleString()} quads</strong></span>
                        </div>
                    </div>
                    ${actionBtn}
                </div>
                `;
            }).join('');
        }
    } catch (err) {
        console.info('Using static fallback for sample models (API offline or static mode).');
    }
}

async function loadDynamicFaqs(container) {
    try {
        const res = await fetch('api.php?action=get_faqs');
        const data = await res.json();
        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
            container.innerHTML = data.data.map((faq, idx) => {
                const id = faq.id || (idx + 1);
                return `
                <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
                    <button type="button" onclick="toggleDynamicFaq(${id})" class="w-full p-5 text-left font-bold text-white text-sm md:text-base flex justify-between items-center hover:bg-slate-800/40 transition-colors">
                        <span class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-md bg-sky-500/20 text-sky-400 text-xs flex items-center justify-center font-bold">Q</span>
                            ${escapeHtml(faq.question)}
                        </span>
                        <i id="dynamicFaqIcon${id}" class="fa-solid fa-chevron-down text-sky-400 transition-transform"></i>
                    </button>
                    <div id="dynamicFaqContent${id}" class="hidden p-5 pt-0 text-slate-300 text-xs md:text-sm leading-relaxed border-t border-slate-800/60">
                        ${escapeHtml(faq.answer)}
                    </div>
                </div>
                `;
            }).join('');
        }
    } catch (err) {
        console.info('Using static fallback for FAQs (API offline or static mode).');
    }
}

function toggleDynamicFaq(id) {
    const content = document.getElementById(`dynamicFaqContent${id}`);
    const icon = document.getElementById(`dynamicFaqIcon${id}`);
    if (!content) return;
    const isHidden = content.classList.contains('hidden');
    if (isHidden) {
        content.classList.remove('hidden');
        if (icon) icon.classList.add('rotate-180');
    } else {
        content.classList.add('hidden');
        if (icon) icon.classList.remove('rotate-180');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

