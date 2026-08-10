<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración de clientes</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #0d4f8b;
            --brand-dark: #07345f;
            --accent: #159b72;
            --accent-soft: #eaf8f3;
            --page: #f5f7fa;
            --card: #ffffff;
            --text: #172b3a;
            --muted: #6f7f8b;
            --line: #e2e9ee;
        }

        body {
            min-height: 100vh;
            background: var(--page);
            color: var(--text);
        }

        .app-bar {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(12px);
        }

        .brand-symbol {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: var(--brand);
            color: #fff;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .page-title {
            font-weight: 750;
            letter-spacing: -.03em;
        }

        .surface {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 10px 35px rgba(24, 54, 75, .04);
        }

        .summary-card {
            height: 100%;
            padding: 1rem 1.15rem;
        }

        .summary-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: #eef4f8;
            color: var(--brand);
        }

        .summary-value {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--brand-dark);
        }

        .soft-label {
            color: var(--muted);
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .btn-brand:hover,
        .btn-brand:focus {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
            color: #fff;
        }

        .client-accordion .accordion-item {
            overflow: hidden;
            margin-bottom: .8rem;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
        }

        .client-accordion .accordion-button {
            padding: 1rem 1.15rem;
            background: #fff;
            box-shadow: none;
        }

        .client-accordion .accordion-button:not(.collapsed) {
            color: var(--text);
            background: #fbfcfd;
            border-bottom: 1px solid var(--line);
        }

        .client-accordion .accordion-button::after {
            margin-left: 1rem;
        }

        .client-code,
        .device-code,
        .token-value {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .count-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .55rem;
            border-radius: 999px;
            background: #f0f3f5;
            color: var(--muted);
            font-size: .77rem;
            font-weight: 650;
        }

        .nav-tabs-clean {
            gap: .35rem;
            border: 0;
        }

        .nav-tabs-clean .nav-link {
            border: 0;
            border-radius: 10px;
            color: var(--muted);
            font-weight: 650;
        }

        .nav-tabs-clean .nav-link.active {
            background: #edf4fa;
            color: var(--brand-dark);
        }

        .device-card {
            border: 1px solid var(--line);
            border-radius: 15px;
            background: #fff;
        }

        .device-header {
            padding: 1rem;
            border-bottom: 1px solid var(--line);
            background: #fbfcfd;
        }

        .sensor-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
        }

        .sensor-slot-card {
            min-width: 0;
            padding: .85rem;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: #fafcfd;
        }

        .sensor-slot-card.configured {
            background: var(--accent-soft);
            border-color: rgba(21, 155, 114, .25);
        }

        .slot-number {
            color: var(--muted);
            font-size: .72rem;
            font-weight: 750;
            letter-spacing: .04em;
        }

        .slot-title {
            min-height: 42px;
            margin-top: .4rem;
            font-size: .92rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .slot-meta {
            color: var(--muted);
            font-size: .76rem;
        }

        .user-row {
            padding: 1rem;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: #fff;
        }

        .token-modal .modal-content {
            border: 0;
            border-radius: 20px;
        }

        .token-box {
            border: 1px solid rgba(21, 155, 114, .3);
            background: var(--accent-soft);
            border-radius: 14px;
        }

        .token-value {
            min-height: 108px;
            resize: none;
            word-break: break-all;
        }

        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
            color: var(--muted);
        }

        .search-wrap {
            position: relative;
        }

        .search-wrap .bi-search {
            position: absolute;
            top: 50%;
            left: .9rem;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .search-wrap .form-control {
            padding-left: 2.45rem;
        }

        @media (max-width: 991.98px) {
            .sensor-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .sensor-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
@php
    $totalDispositivos = $clientes->sum(fn ($cliente) => $cliente->dispositivos->count());
    $totalActivos = $clientes->sum(fn ($cliente) => $cliente->dispositivos->where('activo', true)->count());
    $totalUsuarios = $clientes->sum(fn ($cliente) => $cliente->usuarios->count());
    $erroresNuevoCliente = $errors->getBag('nuevoCliente');
@endphp

<nav class="app-bar">
    <div class="container py-3">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-symbol">CDT</div>
                <div>
                    <div class="fw-bold lh-sm">Administración</div>
                    <div class="small text-secondary">Clientes, equipos y accesos</div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-light btn-sm" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5">
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if ($pdfPendiente)
        <div class="alert alert-info border-0 shadow-sm d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" role="alert">
            <div>
                <div class="fw-bold"><i class="bi bi-file-earmark-pdf me-2"></i>Ficha PDF disponible</div>
                <div class="small mt-1">
                    Configura los sensores del equipo antes de descargarla. La ficha incluirá únicamente los datos guardados en el panel.
                </div>
            </div>
            <a href="{{ $pdfPendiente['pdf_url'] }}" class="btn btn-danger flex-shrink-0">
                <i class="bi bi-download me-1"></i>Descargar ficha
            </a>
        </div>
    @endif

    <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
        <div>
            <div class="soft-label mb-2">Panel administrativo</div>
            <h1 class="page-title h2 mb-1">Clientes</h1>
            <p class="text-secondary mb-0">Abre un cliente únicamente cuando necesites configurarlo.</p>
        </div>

        <button type="button" class="btn btn-brand px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoCliente">
            <i class="bi bi-plus-lg me-1"></i>Nuevo cliente
        </button>
    </div>

    <section class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="surface summary-card d-flex align-items-center gap-3">
                <div class="summary-icon"><i class="bi bi-buildings"></i></div>
                <div>
                    <div class="summary-value">{{ $clientes->count() }}</div>
                    <div class="small text-secondary">Clientes</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="surface summary-card d-flex align-items-center gap-3">
                <div class="summary-icon"><i class="bi bi-cpu"></i></div>
                <div>
                    <div class="summary-value">{{ $totalActivos }}/{{ $totalDispositivos }}</div>
                    <div class="small text-secondary">Equipos activos</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="surface summary-card d-flex align-items-center gap-3">
                <div class="summary-icon"><i class="bi bi-person-check"></i></div>
                <div>
                    <div class="summary-value">{{ $totalUsuarios }}</div>
                    <div class="small text-secondary">Accesos</div>
                </div>
            </div>
        </div>
    </section>

    <section class="surface p-3 p-lg-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Clientes registrados</h2>
                <div class="small text-secondary">Selecciona uno para ver sus equipos y usuarios.</div>
            </div>
            <div class="search-wrap" style="min-width: min(100%, 310px);">
                <i class="bi bi-search"></i>
                <input id="buscarCliente" type="search" class="form-control" placeholder="Buscar cliente o código">
            </div>
        </div>

        @if ($clientes->isNotEmpty())
            <div class="accordion client-accordion" id="clientesAccordion">
                @foreach ($clientes as $cliente)
                    @php
                        $bagDispositivo = $errors->getBag('dispositivo_'.$cliente->id);
                        $bagUsuario = $errors->getBag('usuario_'.$cliente->id);
                        $hayErrorSensor = false;
                        $hayErrorPassword = false;

                        foreach ($cliente->dispositivos as $dispositivoError) {
                            for ($ranuraError = 1; $ranuraError <= 4; $ranuraError++) {
                                if ($errors->getBag('sensor_'.$dispositivoError->id.'_'.$ranuraError)->any()) {
                                    $hayErrorSensor = true;
                                }
                            }
                        }

                        foreach ($cliente->usuarios as $usuarioError) {
                            if ($errors->getBag('password_usuario_'.$usuarioError->id)->any()) {
                                $hayErrorPassword = true;
                            }
                        }

                        $abrirAccesos = $bagUsuario->any() || $hayErrorPassword;
                        $abrirCliente = $bagDispositivo->any()
                            || $hayErrorSensor
                            || $abrirAccesos
                            || (int) ($clienteAbierto ?? 0) === $cliente->id;

                        $tabAccesosActivo = $abrirCliente
                            && (($adminTab ?? 'equipos') === 'accesos' || $abrirAccesos);
                    @endphp

                    <article class="accordion-item client-entry" data-search="{{ strtolower($cliente->nombre.' '.$cliente->codigo) }}">
                        <h3 class="accordion-header" id="headingCliente{{ $cliente->id }}">
                            <button class="accordion-button {{ $abrirCliente ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#cliente{{ $cliente->id }}" aria-expanded="{{ $abrirCliente ? 'true' : 'false' }}" aria-controls="cliente{{ $cliente->id }}">
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 w-100 pe-2">
                                    <div class="min-w-0">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="fw-bold text-truncate">{{ $cliente->nombre }}</span>
                                            <span class="badge {{ $cliente->activo ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $cliente->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>
                                        <div class="small text-secondary client-code mt-1">{{ $cliente->codigo }}</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="count-chip"><i class="bi bi-cpu"></i>{{ $cliente->dispositivos->count() }} equipos</span>
                                        <span class="count-chip"><i class="bi bi-person"></i>{{ $cliente->usuarios->count() }} accesos</span>
                                    </div>
                                </div>
                            </button>
                        </h3>

                        <div id="cliente{{ $cliente->id }}" class="accordion-collapse collapse {{ $abrirCliente ? 'show' : '' }}" aria-labelledby="headingCliente{{ $cliente->id }}" data-bs-parent="#clientesAccordion">
                            <div class="accordion-body p-3 p-lg-4">
                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                                    <div>
                                        <div class="soft-label">Cliente</div>
                                        <div class="fw-bold mt-1">{{ $cliente->nombre }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('admin.clientes.estado', $cliente) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm {{ $cliente->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                            {{ $cliente->activo ? 'Desactivar cliente' : 'Activar cliente' }}
                                        </button>
                                    </form>
                                </div>

                                <ul class="nav nav-tabs nav-tabs-clean mb-4" id="tabsCliente{{ $cliente->id }}" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $tabAccesosActivo ? '' : 'active' }}" id="equipos-tab-{{ $cliente->id }}" data-bs-toggle="tab" data-bs-target="#equipos-{{ $cliente->id }}" type="button" role="tab">
                                            <i class="bi bi-cpu me-1"></i>Equipos
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $tabAccesosActivo ? 'active' : '' }}" id="accesos-tab-{{ $cliente->id }}" data-bs-toggle="tab" data-bs-target="#accesos-{{ $cliente->id }}" type="button" role="tab">
                                            <i class="bi bi-people me-1"></i>Accesos
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <div class="tab-pane fade {{ $tabAccesosActivo ? '' : 'show active' }}" id="equipos-{{ $cliente->id }}" role="tabpanel">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                            <div>
                                                <h4 class="h6 fw-bold mb-1">Controladores RS485</h4>
                                                <div class="small text-secondary">Cada equipo admite hasta cuatro sensores.</div>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#agregarEquipo{{ $cliente->id }}">
                                                <i class="bi bi-plus-lg me-1"></i>Agregar equipo
                                            </button>
                                        </div>

                                        <div class="collapse {{ $bagDispositivo->any() ? 'show' : '' }} mb-3" id="agregarEquipo{{ $cliente->id }}">
                                            <div class="border rounded-3 p-3 bg-light">
                                                <form method="POST" action="{{ route('admin.dispositivos.store', $cliente) }}">
                                                    @csrf
                                                    <div class="row g-3 align-items-end">
                                                        <div class="col-md-4">
                                                            <label class="form-label small fw-semibold">ID del equipo</label>
                                                            <input name="codigo" type="text" class="form-control text-uppercase {{ $bagDispositivo->has('codigo') ? 'is-invalid' : '' }}" value="{{ $bagDispositivo->any() ? old('codigo') : '' }}" placeholder="LC0002C" maxlength="50" required>
                                                            @if ($bagDispositivo->has('codigo'))<div class="invalid-feedback">{{ $bagDispositivo->first('codigo') }}</div>@endif
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small fw-semibold">Nombre</label>
                                                            <input name="nombre" type="text" class="form-control {{ $bagDispositivo->has('nombre') ? 'is-invalid' : '' }}" value="{{ $bagDispositivo->any() ? old('nombre') : '' }}" placeholder="Estación principal" maxlength="150" required>
                                                            @if ($bagDispositivo->has('nombre'))<div class="invalid-feedback">{{ $bagDispositivo->first('nombre') }}</div>@endif
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small fw-semibold">Ubicación</label>
                                                            <input name="ubicacion" type="text" class="form-control {{ $bagDispositivo->has('ubicacion') ? 'is-invalid' : '' }}" value="{{ $bagDispositivo->any() ? old('ubicacion') : '' }}" placeholder="Tegucigalpa" maxlength="200">
                                                            @if ($bagDispositivo->has('ubicacion'))<div class="invalid-feedback">{{ $bagDispositivo->first('ubicacion') }}</div>@endif
                                                        </div>
                                                        <div class="col-12 d-flex justify-content-end">
                                                            <button type="submit" class="btn btn-brand btn-sm">Crear equipo y token</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-3">
                                            @forelse ($cliente->dispositivos as $dispositivo)
                                                <section class="device-card">
                                                    <div class="device-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                                        <div>
                                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                                <span class="fw-bold">{{ $dispositivo->nombre }}</span>
                                                                <span class="badge {{ $dispositivo->activo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $dispositivo->activo ? 'Activo' : 'Inactivo' }}</span>
                                                            </div>
                                                            <div class="small text-secondary mt-1">
                                                                <span class="device-code">{{ $dispositivo->codigo }}</span>
                                                                @if ($dispositivo->ubicacion) · {{ $dispositivo->ubicacion }}@endif
                                                            </div>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <form method="POST" action="{{ route('admin.dispositivos.regenerar-token', $dispositivo) }}" onsubmit="return confirm('El token anterior dejará de funcionar. ¿Desea continuar?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-key me-1"></i>Nuevo token</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('admin.dispositivos.estado', $dispositivo) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-sm {{ $dispositivo->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                                    {{ $dispositivo->activo ? 'Desactivar' : 'Activar' }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <div class="p-3">
                                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                                            <div>
                                                                <div class="fw-semibold">Sensores</div>
                                                                <div class="small text-secondary">Configura el sensor y, si entrega varios datos, nombra cada lectura por separado.</div>
                                                            </div>
                                                            <span class="count-chip">{{ $dispositivo->sensores->where('activo', true)->count() }} de 4 activos</span>
                                                        </div>

                                                        <div class="sensor-grid">
                                                            @for ($ranura = 1; $ranura <= 4; $ranura++)
                                                                @php
                                                                    $sensor = $dispositivo->sensores->firstWhere('ranura', $ranura);
                                                                    $bagSensor = $errors->getBag('sensor_'.$dispositivo->id.'_'.$ranura);
                                                                @endphp
                                                                <div class="sensor-slot-card {{ $sensor ? 'configured' : '' }}">
                                                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                                                        <span class="slot-number">RANURA {{ $ranura }}</span>
                                                                        @if ($sensor)
                                                                            <span class="badge {{ $sensor->activo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $sensor->activo ? 'Visible' : 'Oculto' }}</span>
                                                                        @else
                                                                            <span class="badge text-bg-light">Vacía</span>
                                                                        @endif
                                                                    </div>
                                                                    <div class="slot-title">{{ $sensor?->nombre ?? 'Sin configurar' }}</div>
                                                                    <div class="slot-meta mb-3">{{ $sensor?->tipo ?: 'Tipo no definido' }}@if ($sensor) · {{ max(1, (int) ($sensor->cantidad_registros ?: ($sensor->lecturas->count() ?: 1))) }} lectura(s)@endif</div>

                                                                    <button class="btn btn-sm btn-light w-100" type="button" data-bs-toggle="collapse" data-bs-target="#editarSensor{{ $dispositivo->id }}_{{ $ranura }}">
                                                                        <i class="bi bi-pencil me-1"></i>Editar
                                                                    </button>

                                                                    <div class="collapse {{ $bagSensor->any() ? 'show' : '' }} mt-3" id="editarSensor{{ $dispositivo->id }}_{{ $ranura }}">
                                                                        <form method="POST" action="{{ route('admin.sensores.guardar', ['dispositivo' => $dispositivo, 'ranura' => $ranura]) }}">
                                                                            @csrf
                                                                            @method('PUT')
                                                                            <div class="mb-2">
                                                                                <label class="form-label small fw-semibold">Nombre visible</label>
                                                                                <input name="nombre" type="text" class="form-control form-control-sm {{ $bagSensor->has('nombre') ? 'is-invalid' : '' }}" value="{{ $bagSensor->any() ? old('nombre') : $sensor?->nombre }}" placeholder="Temperatura ambiental" maxlength="150" required>
                                                                                @if ($bagSensor->has('nombre'))<div class="invalid-feedback">{{ $bagSensor->first('nombre') }}</div>@endif
                                                                            </div>
                                                                            <div class="mb-2">
                                                                                <label class="form-label small fw-semibold">Tipo</label>
                                                                                <input name="tipo" type="text" class="form-control form-control-sm {{ $bagSensor->has('tipo') ? 'is-invalid' : '' }}" value="{{ $bagSensor->any() ? old('tipo') : $sensor?->tipo }}" placeholder="Temperatura" maxlength="100">
                                                                                @if ($bagSensor->has('tipo'))<div class="invalid-feedback">{{ $bagSensor->first('tipo') }}</div>@endif
                                                                            </div>
                                                                            <div class="row g-2 mb-2">
                                                                                <div class="col-6">
                                                                                    <label class="form-label small fw-semibold">ID esclavo</label>
                                                                                    <input name="slave" type="number" class="form-control form-control-sm {{ $bagSensor->has('slave') ? 'is-invalid' : '' }}" value="{{ $bagSensor->any() ? old('slave') : ($sensor?->slave ?? 1) }}" min="1" max="247" required>
                                                                                    @if ($bagSensor->has('slave'))<div class="invalid-feedback">{{ $bagSensor->first('slave') }}</div>@endif
                                                                                </div>
                                                                                <div class="col-6">
                                                                                    <label class="form-label small fw-semibold">Función</label>
                                                                                    <select name="funcion" class="form-select form-select-sm {{ $bagSensor->has('funcion') ? 'is-invalid' : '' }}" required>
                                                                                        <option value="3" @selected((int) ($bagSensor->any() ? old('funcion') : ($sensor?->funcion ?? 3)) === 3)>03</option>
                                                                                        <option value="4" @selected((int) ($bagSensor->any() ? old('funcion') : ($sensor?->funcion ?? 3)) === 4)>04</option>
                                                                                    </select>
                                                                                    @if ($bagSensor->has('funcion'))<div class="invalid-feedback">{{ $bagSensor->first('funcion') }}</div>@endif
                                                                                </div>
                                                                                <div class="col-7">
                                                                                    <label class="form-label small fw-semibold">Registro inicial</label>
                                                                                    <input name="registro_inicial" type="number" class="form-control form-control-sm {{ $bagSensor->has('registro_inicial') ? 'is-invalid' : '' }}" value="{{ $bagSensor->any() ? old('registro_inicial') : ($sensor?->registro_inicial ?? 0) }}" min="0" max="65535" required>
                                                                                    @if ($bagSensor->has('registro_inicial'))<div class="invalid-feedback">{{ $bagSensor->first('registro_inicial') }}</div>@endif
                                                                                </div>
                                                                                <div class="col-5">
                                                                                    <label class="form-label small fw-semibold">Cantidad</label>
                                                                                    <input name="cantidad_registros" type="number" class="form-control form-control-sm {{ $bagSensor->has('cantidad_registros') ? 'is-invalid' : '' }}" value="{{ $bagSensor->any() ? old('cantidad_registros') : ($sensor?->cantidad_registros ?? 1) }}" min="1" max="16" required>
                                                                                    @if ($bagSensor->has('cantidad_registros'))<div class="invalid-feedback">{{ $bagSensor->first('cantidad_registros') }}</div>@endif
                                                                                </div>
                                                                            </div>
                                                                            <button type="submit" class="btn btn-brand btn-sm w-100">Guardar sensor</button>
                                                                        </form>

                                                                        @if ($sensor)
                                                                            <div class="slot-meta border-top pt-2 mt-3">
                                                                                ID {{ $sensor->slave ?? '--' }} · F{{ $sensor->funcion ?? '--' }} · {{ $sensor->cantidad_registros ?? 1 }} lectura(s)
                                                                            </div>
                                                                            <button
                                                                                type="button"
                                                                                class="btn btn-sm btn-outline-primary w-100 mt-2"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#lecturasSensor{{ $sensor->id }}"
                                                                            >
                                                                                <i class="bi bi-list-columns-reverse me-1"></i>Configurar lecturas
                                                                            </button>
                                                                            <form method="POST" action="{{ route('admin.sensores.estado', $sensor) }}" class="mt-2">
                                                                                @csrf
                                                                                @method('PATCH')
                                                                                <button type="submit" class="btn btn-sm {{ $sensor->activo ? 'btn-outline-danger' : 'btn-outline-success' }} w-100">
                                                                                    {{ $sensor->activo ? 'Ocultar al cliente' : 'Mostrar al cliente' }}
                                                                                </button>
                                                                            </form>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endfor
                                                        </div>

                                                        @foreach ($dispositivo->sensores as $sensorConfig)
                                                            @php
                                                                $bagLecturas = $errors->getBag('lecturas_sensor_'.$sensorConfig->id);
                                                                $cantidadLecturas = max(
                                                                    1,
                                                                    min(16, (int) ($sensorConfig->cantidad_registros ?: ($sensorConfig->lecturas->count() ?: 1)))
                                                                );
                                                            @endphp

                                                            <div
                                                                class="modal fade js-lecturas-modal"
                                                                id="lecturasSensor{{ $sensorConfig->id }}"
                                                                tabindex="-1"
                                                                aria-labelledby="lecturasSensorLabel{{ $sensorConfig->id }}"
                                                                aria-hidden="true"
                                                                data-open-on-error="{{ $bagLecturas->any() ? '1' : '0' }}"
                                                            >
                                                                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                                                    <div class="modal-content border-0 rounded-4">
                                                                        <form method="POST" action="{{ route('admin.sensores.lecturas.guardar', $sensorConfig) }}">
                                                                            @csrf
                                                                            @method('PUT')

                                                                            <div class="modal-header border-bottom">
                                                                                <div>
                                                                                    <div class="soft-label mb-1">Ranura {{ $sensorConfig->ranura }}</div>
                                                                                    <h2 class="modal-title h5 fw-bold" id="lecturasSensorLabel{{ $sensorConfig->id }}">
                                                                                        Lecturas de {{ $sensorConfig->nombre }}
                                                                                    </h2>
                                                                                    <div class="small text-secondary mt-1">
                                                                                        Pon un nombre y una unidad a cada dato que entrega el sensor.
                                                                                    </div>
                                                                                </div>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                                            </div>

                                                                            <div class="modal-body p-4">
                                                                                @if ($bagLecturas->has('lecturas'))
                                                                                    <div class="alert alert-danger small">{{ $bagLecturas->first('lecturas') }}</div>
                                                                                @endif
                                                                                <div class="alert alert-light border small mb-4">
                                                                                    El valor mostrado se calcula como <strong>valor crudo × factor + ajuste</strong>.
                                                                                    Para mostrar 253 como 25,3 °C usa factor 0.1 y 1 decimal.
                                                                                </div>

                                                                                <div class="d-grid gap-3">
                                                                                    @for ($indiceLectura = 0; $indiceLectura < $cantidadLecturas; $indiceLectura++)
                                                                                        @php
                                                                                            $lecturaConfig = $sensorConfig->lecturas->firstWhere('indice', $indiceLectura);
                                                                                            $registroLectura = (int) ($sensorConfig->registro_inicial ?? 0) + $indiceLectura;
                                                                                            $prefijoLectura = 'lecturas.'.$indiceLectura.'.';
                                                                                        @endphp

                                                                                        <div class="border rounded-3 p-3 bg-light-subtle">
                                                                                            <input type="hidden" name="lecturas[{{ $indiceLectura }}][indice]" value="{{ $indiceLectura }}">

                                                                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                                                                                <div>
                                                                                                    <div class="fw-bold">Lectura {{ $indiceLectura + 1 }}</div>
                                                                                                    <div class="small text-secondary">
                                                                                                        Registro 0x{{ strtoupper(str_pad(dechex($registroLectura), 4, '0', STR_PAD_LEFT)) }}
                                                                                                    </div>
                                                                                                </div>
                                                                                                <div class="form-check form-switch">
                                                                                                    <input type="hidden" name="lecturas[{{ $indiceLectura }}][activo]" value="0">
                                                                                                    <input
                                                                                                        class="form-check-input"
                                                                                                        type="checkbox"
                                                                                                        role="switch"
                                                                                                        name="lecturas[{{ $indiceLectura }}][activo]"
                                                                                                        value="1"
                                                                                                        @checked($bagLecturas->any()
                                                                                                            ? old($prefijoLectura.'activo', 0)
                                                                                                            : ($lecturaConfig?->activo ?? true))
                                                                                                    >
                                                                                                    <label class="form-check-label small fw-semibold">Mostrar al cliente</label>
                                                                                                </div>
                                                                                            </div>

                                                                                            <div class="row g-3">
                                                                                                <div class="col-md-6">
                                                                                                    <label class="form-label small fw-semibold">Nombre de la lectura</label>
                                                                                                    <input
                                                                                                        name="lecturas[{{ $indiceLectura }}][nombre]"
                                                                                                        type="text"
                                                                                                        class="form-control {{ $bagLecturas->has($prefijoLectura.'nombre') ? 'is-invalid' : '' }}"
                                                                                                        value="{{ $bagLecturas->any() ? old($prefijoLectura.'nombre') : ($lecturaConfig?->nombre ?? 'Lectura '.($indiceLectura + 1)) }}"
                                                                                                        placeholder="Temperatura, humedad, presión..."
                                                                                                        maxlength="100"
                                                                                                        required
                                                                                                    >
                                                                                                    @if ($bagLecturas->has($prefijoLectura.'nombre'))
                                                                                                        <div class="invalid-feedback">{{ $bagLecturas->first($prefijoLectura.'nombre') }}</div>
                                                                                                    @endif
                                                                                                </div>

                                                                                                <div class="col-md-3">
                                                                                                    <label class="form-label small fw-semibold">Unidad</label>
                                                                                                    <input
                                                                                                        name="lecturas[{{ $indiceLectura }}][unidad]"
                                                                                                        type="text"
                                                                                                        class="form-control {{ $bagLecturas->has($prefijoLectura.'unidad') ? 'is-invalid' : '' }}"
                                                                                                        value="{{ $bagLecturas->any() ? old($prefijoLectura.'unidad') : $lecturaConfig?->unidad }}"
                                                                                                        placeholder="°C, %, hPa"
                                                                                                        maxlength="30"
                                                                                                    >
                                                                                                    @if ($bagLecturas->has($prefijoLectura.'unidad'))
                                                                                                        <div class="invalid-feedback">{{ $bagLecturas->first($prefijoLectura.'unidad') }}</div>
                                                                                                    @endif
                                                                                                </div>

                                                                                                <div class="col-md-3">
                                                                                                    <label class="form-label small fw-semibold">Decimales</label>
                                                                                                    <select name="lecturas[{{ $indiceLectura }}][decimales]" class="form-select">
                                                                                                        @for ($decimales = 0; $decimales <= 6; $decimales++)
                                                                                                            <option value="{{ $decimales }}" @selected((int) ($bagLecturas->any() ? old($prefijoLectura.'decimales', 0) : ($lecturaConfig?->decimales ?? 0)) === $decimales)>
                                                                                                                {{ $decimales }}
                                                                                                            </option>
                                                                                                        @endfor
                                                                                                    </select>
                                                                                                </div>
                                                                                            </div>

                                                                                            <details class="mt-3">
                                                                                                <summary class="small fw-semibold text-secondary">Conversión avanzada</summary>
                                                                                                <div class="row g-3 mt-1">
                                                                                                    <div class="col-md-6">
                                                                                                        <label class="form-label small fw-semibold">Factor</label>
                                                                                                        <input
                                                                                                            name="lecturas[{{ $indiceLectura }}][factor]"
                                                                                                            type="number"
                                                                                                            step="0.000001"
                                                                                                            class="form-control {{ $bagLecturas->has($prefijoLectura.'factor') ? 'is-invalid' : '' }}"
                                                                                                            value="{{ $bagLecturas->any() ? old($prefijoLectura.'factor', 1) : ($lecturaConfig?->factor ?? 1) }}"
                                                                                                            required
                                                                                                        >
                                                                                                        @if ($bagLecturas->has($prefijoLectura.'factor'))
                                                                                                            <div class="invalid-feedback">{{ $bagLecturas->first($prefijoLectura.'factor') }}</div>
                                                                                                        @endif
                                                                                                    </div>
                                                                                                    <div class="col-md-6">
                                                                                                        <label class="form-label small fw-semibold">Ajuste</label>
                                                                                                        <input
                                                                                                            name="lecturas[{{ $indiceLectura }}][ajuste]"
                                                                                                            type="number"
                                                                                                            step="0.000001"
                                                                                                            class="form-control {{ $bagLecturas->has($prefijoLectura.'ajuste') ? 'is-invalid' : '' }}"
                                                                                                            value="{{ $bagLecturas->any() ? old($prefijoLectura.'ajuste', 0) : ($lecturaConfig?->ajuste ?? 0) }}"
                                                                                                            required
                                                                                                        >
                                                                                                        @if ($bagLecturas->has($prefijoLectura.'ajuste'))
                                                                                                            <div class="invalid-feedback">{{ $bagLecturas->first($prefijoLectura.'ajuste') }}</div>
                                                                                                        @endif
                                                                                                    </div>
                                                                                                </div>
                                                                                            </details>
                                                                                        </div>
                                                                                    @endfor
                                                                                </div>
                                                                            </div>

                                                                            <div class="modal-footer border-top">
                                                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                                                                <button type="submit" class="btn btn-brand">Guardar lecturas</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </section>
                                            @empty
                                                <div class="empty-state border rounded-3">Este cliente todavía no tiene equipos.</div>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="tab-pane fade {{ $tabAccesosActivo ? 'show active' : '' }}" id="accesos-{{ $cliente->id }}" role="tabpanel">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                            <div>
                                                <h4 class="h6 fw-bold mb-1">Usuarios del cliente</h4>
                                                <div class="small text-secondary">Estos usuarios solo pueden ver sus propios sensores.</div>
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#agregarUsuario{{ $cliente->id }}">
                                                <i class="bi bi-person-plus me-1"></i>Crear acceso
                                            </button>
                                        </div>

                                        <div class="collapse {{ $bagUsuario->any() ? 'show' : '' }} mb-3" id="agregarUsuario{{ $cliente->id }}">
                                            <div class="border rounded-3 p-3 bg-light">
                                                <form method="POST" action="{{ route('admin.usuarios.store', $cliente) }}">
                                                    @csrf
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Nombre</label>
                                                            <input name="name" type="text" class="form-control {{ $bagUsuario->has('name') ? 'is-invalid' : '' }}" value="{{ $bagUsuario->any() ? old('name') : '' }}" maxlength="255" required>
                                                            @if ($bagUsuario->has('name'))<div class="invalid-feedback">{{ $bagUsuario->first('name') }}</div>@endif
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Correo</label>
                                                            <input name="email" type="email" class="form-control {{ $bagUsuario->has('email') ? 'is-invalid' : '' }}" value="{{ $bagUsuario->any() ? old('email') : '' }}" maxlength="255" required>
                                                            @if ($bagUsuario->has('email'))<div class="invalid-feedback">{{ $bagUsuario->first('email') }}</div>@endif
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Contraseña</label>
                                                            <input name="password" type="password" class="form-control {{ $bagUsuario->has('password') ? 'is-invalid' : '' }}" minlength="8" autocomplete="new-password" required>
                                                            @if ($bagUsuario->has('password'))<div class="invalid-feedback">{{ $bagUsuario->first('password') }}</div>@endif
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Confirmar contraseña</label>
                                                            <input name="password_confirmation" type="password" class="form-control" minlength="8" autocomplete="new-password" required>
                                                        </div>
                                                        <div class="col-12 d-flex justify-content-end">
                                                            <button type="submit" class="btn btn-brand btn-sm">Crear acceso</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2">
                                            @forelse ($cliente->usuarios as $usuarioCliente)
                                                @php $bagPassword = $errors->getBag('password_usuario_'.$usuarioCliente->id); @endphp
                                                <div class="user-row">
                                                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                                        <div>
                                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                                <span class="fw-bold">{{ $usuarioCliente->name }}</span>
                                                                <span class="badge {{ $usuarioCliente->activo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $usuarioCliente->activo ? 'Activo' : 'Inactivo' }}</span>
                                                            </div>
                                                            <div class="small text-secondary mt-1">{{ $usuarioCliente->email }}</div>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#passwordUsuario{{ $usuarioCliente->id }}">Cambiar contraseña</button>
                                                            <form method="POST" action="{{ route('admin.usuarios.estado', $usuarioCliente) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-sm {{ $usuarioCliente->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                                    {{ $usuarioCliente->activo ? 'Desactivar' : 'Activar' }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <div class="collapse {{ $bagPassword->any() ? 'show' : '' }} mt-3" id="passwordUsuario{{ $usuarioCliente->id }}">
                                                        <form method="POST" action="{{ route('admin.usuarios.password', $usuarioCliente) }}" class="border-top pt-3">
                                                            @csrf
                                                            @method('PATCH')
                                                            <div class="row g-2 align-items-end">
                                                                <div class="col-md-5">
                                                                    <label class="form-label small fw-semibold">Nueva contraseña</label>
                                                                    <input name="password" type="password" class="form-control form-control-sm {{ $bagPassword->has('password') ? 'is-invalid' : '' }}" minlength="8" required>
                                                                    @if ($bagPassword->has('password'))<div class="invalid-feedback">{{ $bagPassword->first('password') }}</div>@endif
                                                                </div>
                                                                <div class="col-md-5">
                                                                    <label class="form-label small fw-semibold">Confirmación</label>
                                                                    <input name="password_confirmation" type="password" class="form-control form-control-sm" minlength="8" required>
                                                                </div>
                                                                <div class="col-md-2 d-grid">
                                                                    <button type="submit" class="btn btn-brand btn-sm">Guardar</button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="empty-state border rounded-3">No hay accesos creados para este cliente.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div id="sinResultados" class="empty-state d-none">No se encontraron clientes con ese texto.</div>
        @else
            <div class="empty-state">
                <div class="mb-2 fs-3 text-primary"><i class="bi bi-buildings"></i></div>
                <h3 class="h6 text-dark">Todavía no hay clientes</h3>
                <p>Usa el botón “Nuevo cliente” para registrar el primero.</p>
            </div>
        @endif
    </section>
</main>

<div class="modal fade" id="modalNuevoCliente" tabindex="-1" aria-labelledby="modalNuevoClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-bottom">
                <div>
                    <h2 class="modal-title h5 fw-bold" id="modalNuevoClienteLabel">Nuevo cliente</h2>
                    <div class="small text-secondary">Crea el cliente y su primer equipo. La ficha PDF usará únicamente estos datos y los sensores que configures.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form method="POST" action="{{ route('admin.clientes.store') }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="soft-label mb-3">1. Datos del cliente</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="cliente_codigo" class="form-label fw-semibold">Código</label>
                            <input id="cliente_codigo" name="cliente_codigo" type="text" class="form-control text-uppercase @error('cliente_codigo', 'nuevoCliente') is-invalid @enderror" value="{{ old('cliente_codigo') }}" placeholder="CLI-HN-0002" maxlength="30" required>
                            @error('cliente_codigo', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label for="cliente_nombre" class="form-label fw-semibold">Nombre o razón social</label>
                            <input id="cliente_nombre" name="cliente_nombre" type="text" class="form-control @error('cliente_nombre', 'nuevoCliente') is-invalid @enderror" value="{{ old('cliente_nombre') }}" maxlength="150" required>
                            @error('cliente_nombre', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="soft-label mb-3">2. Primer equipo</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="dispositivo_codigo" class="form-label fw-semibold">ID del equipo</label>
                            <input id="dispositivo_codigo" name="dispositivo_codigo" type="text" class="form-control text-uppercase @error('dispositivo_codigo', 'nuevoCliente') is-invalid @enderror" value="{{ old('dispositivo_codigo') }}" placeholder="LC0002C" maxlength="50" required>
                            @error('dispositivo_codigo', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="dispositivo_nombre" class="form-label fw-semibold">Nombre del equipo</label>
                            <input id="dispositivo_nombre" name="dispositivo_nombre" type="text" class="form-control @error('dispositivo_nombre', 'nuevoCliente') is-invalid @enderror" value="{{ old('dispositivo_nombre') }}" placeholder="Estación principal" maxlength="150" required>
                            @error('dispositivo_nombre', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="dispositivo_ubicacion" class="form-label fw-semibold">Ubicación</label>
                            <input id="dispositivo_ubicacion" name="dispositivo_ubicacion" type="text" class="form-control @error('dispositivo_ubicacion', 'nuevoCliente') is-invalid @enderror" value="{{ old('dispositivo_ubicacion') }}" placeholder="Tegucigalpa" maxlength="200">
                            @error('dispositivo_ubicacion', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="soft-label mb-1">3. Acceso al dashboard</div>
                    <div class="small text-secondary mb-3">Opcional. Puedes crearlo después desde la ficha del cliente.</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="acceso_nombre" class="form-label fw-semibold">Nombre del usuario</label>
                            <input id="acceso_nombre" name="acceso_nombre" type="text" class="form-control @error('acceso_nombre', 'nuevoCliente') is-invalid @enderror" value="{{ old('acceso_nombre') }}" maxlength="255">
                            @error('acceso_nombre', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="acceso_email" class="form-label fw-semibold">Correo</label>
                            <input id="acceso_email" name="acceso_email" type="email" class="form-control @error('acceso_email', 'nuevoCliente') is-invalid @enderror" value="{{ old('acceso_email') }}" maxlength="255">
                            @error('acceso_email', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="acceso_password" class="form-label fw-semibold">Contraseña</label>
                            <input id="acceso_password" name="acceso_password" type="password" class="form-control @error('acceso_password', 'nuevoCliente') is-invalid @enderror" minlength="8" autocomplete="new-password">
                            @error('acceso_password', 'nuevoCliente')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="acceso_password_confirmation" class="form-label fw-semibold">Confirmar contraseña</label>
                            <input id="acceso_password_confirmation" name="acceso_password_confirmation" type="password" class="form-control" minlength="8" autocomplete="new-password">
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-brand"><i class="bi bi-file-earmark-pdf me-1"></i>Crear cliente y generar ficha</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($tokenGenerado)
    <div class="modal fade token-modal" id="modalToken" tabindex="-1" aria-labelledby="modalTokenLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 px-4 pt-4">
                    <div>
                        <div class="soft-label text-success mb-2">Credenciales generadas</div>
                        <h2 class="modal-title h5 fw-bold" id="modalTokenLabel">{{ $tokenGenerado['titulo'] }}</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body px-4 pb-4">
                    <div class="alert alert-warning small">
                        Copia el token ahora. Por seguridad, no podrá volver a consultarse.
                    </div>
                    <div class="token-box p-3 mb-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">X-Device-ID</label>
                                <div class="input-group">
                                    <input id="deviceIdGenerado" type="text" class="form-control device-code" value="{{ $tokenGenerado['dispositivo_codigo'] }}" readonly>
                                    <button type="button" class="btn btn-outline-secondary" data-copy-target="deviceIdGenerado"><i class="bi bi-copy"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Cliente</label>
                                <input type="text" class="form-control" value="{{ $tokenGenerado['cliente_nombre'] }}" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">X-Device-Token</label>
                                <textarea id="tokenGeneradoValor" class="form-control token-value" readonly>{{ $tokenGenerado['token'] }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if (!empty($tokenGenerado['pdf_url']))
                            <a href="{{ $tokenGenerado['pdf_url'] }}" target="_blank" rel="noopener" class="btn btn-danger" id="descargarPdfConfiguracion">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Descargar ficha PDF
                            </a>
                        @endif
                        <button type="button" class="btn btn-success" id="copiarCredenciales"><i class="bi bi-copy me-1"></i>Copiar ID y token</button>
                        <span id="copyFeedback" class="small fw-semibold text-success"></span>
                    </div>
                    @if (!empty($tokenGenerado['pdf_url']))
                        <div class="small text-secondary mt-3">
                            La ficha estará disponible hasta las {{ $tokenGenerado['pdf_disponible_hasta'] }}. Puedes configurar los sensores antes de descargarla; solo mostrará datos guardados en el panel.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const normalizar = (texto) => texto
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const buscador = document.getElementById('buscarCliente');
    const entradas = [...document.querySelectorAll('.client-entry')];
    const sinResultados = document.getElementById('sinResultados');

    buscador?.addEventListener('input', () => {
        const consulta = normalizar(buscador.value.trim());
        let visibles = 0;

        entradas.forEach((entrada) => {
            const coincide = normalizar(entrada.dataset.search || '').includes(consulta);
            entrada.classList.toggle('d-none', !coincide);
            if (coincide) visibles++;
        });

        sinResultados?.classList.toggle('d-none', visibles !== 0);
    });

    async function copiarTexto(texto) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(texto);
            return;
        }

        const temporal = document.createElement('textarea');
        temporal.value = texto;
        temporal.style.position = 'fixed';
        temporal.style.opacity = '0';
        document.body.appendChild(temporal);
        temporal.select();
        document.execCommand('copy');
        temporal.remove();
    }

    function mostrarConfirmacion(mensaje) {
        const feedback = document.getElementById('copyFeedback');
        if (!feedback) return;
        feedback.textContent = mensaje;
        window.setTimeout(() => feedback.textContent = '', 2500);
    }

    document.querySelectorAll('[data-copy-target]').forEach((boton) => {
        boton.addEventListener('click', async () => {
            const campo = document.getElementById(boton.dataset.copyTarget);
            if (!campo) return;
            await copiarTexto(campo.value.trim());
            mostrarConfirmacion('Copiado.');
        });
    });

    document.getElementById('copiarCredenciales')?.addEventListener('click', async () => {
        const id = document.getElementById('deviceIdGenerado')?.value.trim();
        const token = document.getElementById('tokenGeneradoValor')?.value.trim();
        if (!id || !token) return;
        await copiarTexto(`X-Device-ID: ${id}\nX-Device-Token: ${token}`);
        mostrarConfirmacion('Credenciales copiadas.');
    });


    @if ($erroresNuevoCliente->any())
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevoCliente')).show();
    @endif

    document.querySelectorAll('.js-lecturas-modal[data-open-on-error="1"]').forEach((modal) => {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    @if ($tokenGenerado)
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalToken')).show();
    @endif
</script>
</body>
</html>
