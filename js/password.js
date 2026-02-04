const passwordInput = document.getElementById('motdepasse');
const currentPasswordInput = document.getElementById('motdepasse_actuel');
const submitBtn = document.querySelector('button[type="submit"]');

const rulesBox = document.getElementById('passwordRules');

const rules = {
    length: document.getElementById('rule-length'),
    upper: document.getElementById('rule-upper'),
    lower: document.getElementById('rule-lower'),
    number: document.getElementById('rule-number'),
    special: document.getElementById('rule-special')
};

if (passwordInput && submitBtn) {
    passwordInput.addEventListener('input', function () {
        const value = passwordInput.value;

        // 🔹 Si le champ est vide → cacher le bloc de sécurité
        if (value.length === 0) {
            if (rulesBox) rulesBox.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        // 🔹 Dès qu'il y a au moins 1 caractère → afficher le bloc
        if (rulesBox) rulesBox.style.display = 'block';

        const checks = {
            length: value.length >= 8,
            upper: /[A-Z]/.test(value),
            lower: /[a-z]/.test(value),
            number: /[0-9]/.test(value),
            special: /[^A-Za-z0-9]/.test(value)
        };

        toggleRule(rules.length, checks.length);
        toggleRule(rules.upper, checks.upper);
        toggleRule(rules.lower, checks.lower);
        toggleRule(rules.number, checks.number);
        toggleRule(rules.special, checks.special);

        if (submitBtn) submitBtn.disabled = !Object.values(checks).every(Boolean);
    });
}

// 🔹 Afficher / masquer les deux champs de mot de passe
const togglePassword = document.getElementById('togglePassword');
if (togglePassword) {
    togglePassword.addEventListener('change', function () {
        const type = this.checked ? 'text' : 'password';
        if (passwordInput) passwordInput.type = type;
        if (currentPasswordInput) currentPasswordInput.type = type;
    });
}

function toggleRule(element, isValid) {
    if (!element) return;

    if (isValid) {
        element.classList.remove('text-danger');
        element.classList.add('text-success');
        element.innerHTML = element.innerHTML.replace('❌', '✔️');
    } else {
        element.classList.remove('text-success');
        element.classList.add('text-danger');
        element.innerHTML = element.innerHTML.replace('✔️', '❌');
    }
}