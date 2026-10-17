import * as authService from '../services/auth-service.js';
import * as notifications from '../core/notifications.js';
import { STORAGE_KEYS, readString, remove } from '../core/storage.js';

export function initAuthForm() {
    const btnLogin = document.getElementById('btn-show-login');
    const btnRegister = document.getElementById('btn-show-register');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const loginMessage = document.getElementById('login-message');
    const registerMessage = document.getElementById('register-message');

    if (!btnLogin || !btnRegister || !loginForm || !registerForm) return;

    const showLogin = () => {
        loginForm.removeAttribute('hidden');
        registerForm.setAttribute('hidden', 'true');
        btnLogin.classList.replace('btn-outline', 'btn-primary');
        btnLogin.setAttribute('aria-pressed', 'true');
        btnRegister.classList.replace('btn-primary', 'btn-outline');
        btnRegister.setAttribute('aria-pressed', 'false');
    };

    const showRegister = () => {
        registerForm.removeAttribute('hidden');
        loginForm.setAttribute('hidden', 'true');
        btnRegister.classList.replace('btn-outline', 'btn-primary');
        btnRegister.setAttribute('aria-pressed', 'true');
        btnLogin.classList.replace('btn-primary', 'btn-outline');
        btnLogin.setAttribute('aria-pressed', 'false');
    };

    btnLogin.addEventListener('click', showLogin);
    btnRegister.addEventListener('click', showRegister);

    registerForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const email = document.getElementById('reg-email').value.trim();
        const password = document.getElementById('reg-password').value;
        const passwordConfirm = document.getElementById('reg-password-confirm').value;
        const errors = [];

        if (!email || !email.includes('@')) errors.push('Email invalide.');
        if (password.length < 8) errors.push('Le mot de passe doit contenir au moins 8 caractères.');
        if (password !== passwordConfirm) errors.push('Les mots de passe ne correspondent pas.');

        if (errors.length > 0) {
            const errorMsg = errors.join(' ');
            if (registerMessage) {
                registerMessage.textContent = errorMsg;
                registerMessage.className = 'auth-message error';
            }
            notifications.error(errorMsg);
            return;
        }

        try {
            const prefix = email.split('@')[0];
            const parts = prefix.split(/[._-]/);
            const nom = (parts[0] || "Utilisateur");
            const prenom = (parts[1] || "Nouveau");

            await authService.register(email, password, nom, prenom);
            
            // Auto login apres register
            const user = await authService.login(email, password);
            
            const successMsg = 'Inscription réussie ! Connexion en cours...';
            if (registerMessage) {
                registerMessage.textContent = successMsg;
                registerMessage.className = 'auth-message success';
            }
            notifications.success(successMsg);
            
            const pending = readString(STORAGE_KEYS.pendingOrchid);
            if (pending) {
                remove(STORAGE_KEYS.pendingOrchid);
                window.location.href = 'encyclopedie.php';
            } else if (user && user.role === 'admin') { window.location.href = 'administration.php'; } else { window.location.href = 'macollection.php'; }
        } catch (error) {
            if (registerMessage) {
                registerMessage.textContent = error.message;
                registerMessage.className = 'auth-message error';
            }
            notifications.error(error.message);
        }
    });

    loginForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        if (!email || !password) {
            const msg = 'Veuillez remplir tous les champs.';
            if (loginMessage) {
                loginMessage.textContent = msg;
                loginMessage.className = 'auth-message error';
            }
            notifications.error(msg);
            return;
        }

        try {
            const user = await authService.login(email, password);
            const successMsg = 'Connexion réussie !';
            if (loginMessage) {
                loginMessage.textContent = successMsg;
                loginMessage.className = 'auth-message success';
            }
            notifications.success(successMsg);
            
            const pending = readString(STORAGE_KEYS.pendingOrchid);
            if (pending) {
                remove(STORAGE_KEYS.pendingOrchid);
                window.location.href = 'encyclopedie.php';
            } else if (user && user.role === 'admin') { window.location.href = 'administration.php'; } else { window.location.href = 'macollection.php'; }
        } catch (error) {
            if (loginMessage) {
                loginMessage.textContent = error.message;
                loginMessage.className = 'auth-message error';
            }
            notifications.error(error.message);
        }
    });
}

