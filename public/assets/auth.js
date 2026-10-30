async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            ...(options.headers || {})
        }
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'Ocurrió un error');
    }

    return data;
}

const registerForm = document.querySelector('#register-form');

if (registerForm) {
    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = document.querySelector('#message');
        const formData = new FormData(registerForm);

        try {
            await requestJson('/api/register', {
                method: 'POST',
                body: JSON.stringify({
                    name: formData.get('name'),
                    email: formData.get('email'),
                    password: formData.get('password')
                })
            });

            message.textContent = 'Usuario registrado exitosamente. Ahora puedes iniciar sesión.';
            message.classList.add('success');
            registerForm.reset();
        } catch (error) {
            message.textContent = error.message;
            message.classList.remove('success');
        }
    });
}

const loginForm = document.querySelector('#login-form');

const successMessage = document.querySelector('#success-message');

if (successMessage && new URLSearchParams(window.location.search).get('success') === 'registered') {
    successMessage.textContent = 'Usuario registrado exitosamente. Ahora puedes iniciar sesión.';
}

if (loginForm) {
    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = document.querySelector('#message');
        const formData = new FormData(loginForm);

        try {
            await requestJson('/api/login', {
                method: 'POST',
                body: JSON.stringify({
                    email: formData.get('email'),
                    password: formData.get('password')
                })
            });

            window.location.href = '/views/dashboard.html';
        } catch (error) {
            message.textContent = error.message;
        }
    });
}

const dashboard = document.querySelector('[data-dashboard]');

if (dashboard) {
    const welcome = document.querySelector('#welcome');

    requestJson('/api/me')
        .then((data) => {
            welcome.textContent = `Bienvenido, ${data.user.name}, al gestor de productos`;
        })
        .catch((error) => {
            welcome.textContent = `No se pudo cargar la sesión: ${error.message}`;
            welcome.classList.add('error');

            const description = document.querySelector('[data-dashboard] p');
            description.textContent = 'La cookie de autenticación no fue encontrada o el token no es válido.';

            const logoutButton = document.querySelector('#logout');
            logoutButton.textContent = 'Volver al login';
            logoutButton.onclick = () => {
                window.location.href = '/views/login.html';
            };
        });

    document.querySelector('#logout').addEventListener('click', async () => {
        await requestJson('/api/logout', { method: 'POST' });
        window.location.href = '/login.html';
    });
}
