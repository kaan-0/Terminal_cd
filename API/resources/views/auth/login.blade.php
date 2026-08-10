<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · RS485 API</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <style>
        :root {
            --cdt-blue: #0d4f8b;
            --cdt-blue-dark: #07345f;
            --cdt-green: #159b72;
            --cdt-bg: #eef4f7;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.25rem;
            background:
                radial-gradient(circle at 15% 15%, rgba(21, 155, 114, .16), transparent 30%),
                radial-gradient(circle at 85% 85%, rgba(13, 79, 139, .18), transparent 34%),
                var(--cdt-bg);
        }

        .login-card {
            width: min(100%, 430px);
            background: #fff;
            border: 1px solid #dce6ec;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(7, 52, 95, .12);
            overflow: hidden;
        }

        .login-head {
            padding: 2rem;
            color: #fff;
            background: linear-gradient(120deg, var(--cdt-blue-dark), var(--cdt-blue), var(--cdt-green));
        }

        .brand-mark {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .22);
            font-weight: 800;
        }

        .btn-cdt {
            background: var(--cdt-blue);
            color: #fff;
            border: none;
        }

        .btn-cdt:hover,
        .btn-cdt:focus {
            background: var(--cdt-blue-dark);
            color: #fff;
        }
    </style>
</head>
<body>
<section class="login-card">
    <header class="login-head">
        <div class="d-flex align-items-center gap-3">
            <div class="brand-mark">CDT</div>
            <div>
                <div class="small opacity-75">Gateway RS485 / Modbus</div>
                <h1 class="h4 mb-0">Panel de sensores</h1>
            </div>
        </div>
    </header>

    <div class="p-4 p-lg-5">
        <h2 class="h5 mb-1">Iniciar sesión</h2>
        <p class="text-secondary mb-4">
            Cada cliente accede únicamente a sus dispositivos.
        </p>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">
                    Correo electrónico
                </label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    autofocus
                    required
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">
                    Contraseña
                </label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="form-control @error('password') is-invalid @enderror"
                    autocomplete="current-password"
                    required
                >
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check mb-4">
                <input
                    id="remember"
                    name="remember"
                    value="1"
                    type="checkbox"
                    class="form-check-input"
                    @checked(old('remember'))
                >
                <label for="remember" class="form-check-label">
                    Mantener sesión iniciada
                </label>
            </div>

            <button type="submit" class="btn btn-cdt w-100 py-2">
                Ingresar
            </button>
        </form>
    </div>
</section>
</body>
</html>
