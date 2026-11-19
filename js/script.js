document.addEventListener('DOMContentLoaded', () => {
    const year = document.getElementById('year');
    if (year) year.textContent = new Date().getFullYear();

    // Tampilkan / sembunyikan password
    const toggle = document.querySelector('.password-toggle');
    const password = document.getElementById('password');
    toggle?.addEventListener('click', () => {
        const visible = password.type === 'text';
        password.type = visible ? 'password' : 'text';
        const label = visible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
        toggle.classList.toggle('password-visible', !visible);
    });

    // Info "Akun dikelola administrator"
    const helpBtn = document.querySelector('.login-account-help');
    const helpDetail = document.getElementById('login-account-detail');
    helpBtn?.addEventListener('click', () => {
        const expanded = helpBtn.getAttribute('aria-expanded') === 'true';
        helpBtn.setAttribute('aria-expanded', String(!expanded));
        helpDetail.hidden = expanded;
    });

    // ===== Login =====
    const form = document.getElementById('login-form');
    const flash = document.getElementById('flash');
    const submitBtn = form?.querySelector('.login-submit');
    let csrfToken = '';

    const showFlash = (msg) => {
        flash.textContent = msg;
        flash.hidden = false;
        flash.style.cssText =
            'margin:0 0 12px;padding:10px 12px;border-radius:10px;font-size:12px;' +
            'color:#ab5353;background:#fff0ef;border:1px solid #f1d0cd';
    };

    const loadCsrf = async () => {
        try {
            const res = await fetch('api/csrf.php', { credentials: 'same-origin' });
            const data = await res.json();
            csrfToken = data.token;
        } catch {
            showFlash('Tidak dapat terhubung ke server.');
        }
    };
    loadCsrf();

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        flash.hidden = true;

        const email = form.email.value.trim();
        const pass = form.password.value;

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showFlash('Masukkan email yang valid.');
        if (!pass) return showFlash('Kata sandi wajib diisi.');
        if (!csrfToken) { await loadCsrf(); if (!csrfToken) return; }

        submitBtn.disabled = true;
        try {
            const res = await fetch('api/login.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ email, password: pass, csrf: csrfToken })
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = data.redirect;
                return;
            }
            showFlash(data.message || 'Login gagal.');
            if (res.status === 403) loadCsrf();
        } catch {
            showFlash('Tidak dapat terhubung ke server.');
        } finally {
            submitBtn.disabled = false;
        }
    });
});