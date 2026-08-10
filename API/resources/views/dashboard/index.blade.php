<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis sensores</title>

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
            --danger: #d9534f;
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
            border-radius: 20px;
            box-shadow: 0 10px 35px rgba(24, 54, 75, .045);
        }

        .soft-label {
            color: var(--muted);
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .selector-bar {
            padding: 1rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .75rem;
            border-radius: 999px;
            background: #f1f4f6;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 650;
        }

        .status-pill.online {
            background: var(--accent-soft);
            color: #087253;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .sensor-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
        }

        .sensor-option {
            position: relative;
            min-height: 132px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            color: inherit;
            text-decoration: none;
            transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
        }

        .sensor-option:hover {
            transform: translateY(-2px);
            border-color: rgba(13, 79, 139, .35);
            box-shadow: 0 10px 24px rgba(25, 58, 81, .07);
        }

        .sensor-option.active {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(13, 79, 139, .08);
        }

        .sensor-slot {
            color: var(--muted);
            font-size: .76rem;
            font-weight: 700;
        }

        .sensor-name {
            min-height: 42px;
            font-weight: 700;
            line-height: 1.25;
        }

        .sensor-mini-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--brand-dark);
        }

        .sensor-mini-readings {
            display: grid;
            gap: .4rem;
        }

        .mini-reading-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .65rem;
            padding-top: .4rem;
            border-top: 1px solid var(--line);
            font-size: .82rem;
        }

        .mini-reading-name {
            min-width: 0;
            overflow: hidden;
            color: var(--muted);
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mini-reading-row strong {
            flex: 0 0 auto;
            color: var(--brand-dark);
            font-size: .92rem;
        }

        .mini-reading-row strong span {
            color: var(--muted);
            font-size: .72rem;
        }

        .more-readings {
            color: var(--muted);
            font-size: .72rem;
            font-weight: 650;
        }

        .reading-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: .85rem;
        }

        .reading-card {
            display: block;
            padding: 1rem;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            color: inherit;
            text-decoration: none;
            transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
        }

        .reading-card:hover {
            transform: translateY(-2px);
            border-color: rgba(13, 79, 139, .35);
            box-shadow: 0 10px 24px rgba(25, 58, 81, .07);
        }

        .reading-card.selected {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(13, 79, 139, .08);
        }

        .reading-card-name {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 700;
        }

        .reading-card-value {
            margin-top: .35rem;
            color: var(--brand-dark);
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.04em;
        }

        .reading-card-value span {
            color: var(--muted);
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0;
        }

        .reading-card-meta {
            margin-top: .7rem;
            color: var(--muted);
            font-size: .72rem;
        }

        .live-dot {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--danger);
        }

        .live-dot.online {
            background: var(--accent);
            box-shadow: 0 0 0 5px rgba(21, 155, 114, .1);
        }

        .hero-reading {
            overflow: hidden;
        }

        .hero-reading::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 5px;
            background: var(--accent);
        }

        .reading-value {
            font-size: clamp(3.2rem, 8vw, 6.2rem);
            font-weight: 800;
            line-height: .92;
            letter-spacing: -.065em;
            color: var(--brand-dark);
        }

        .reading-unit {
            margin-left: .35rem;
            font-size: clamp(1.15rem, 2.4vw, 1.75rem);
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 0;
        }

        .info-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem .75rem;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fafcfd;
            color: var(--muted);
            font-size: .84rem;
        }

        .section-heading {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 750;
            letter-spacing: -.015em;
        }

        .chart-wrap {
            position: relative;
            min-height: 340px;
        }

        .register-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: .75rem;
        }

        .register-item {
            padding: .9rem;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fafcfd;
        }

        .register-value {
            margin-top: .25rem;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--brand-dark);
        }

        .technical-toggle {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.15rem;
            border: 0;
            background: transparent;
            color: var(--text);
            font-weight: 700;
        }

        .technical-toggle[aria-expanded="true"] .toggle-icon {
            transform: rotate(180deg);
        }

        .toggle-icon {
            transition: transform .2s ease;
        }

        .table thead th {
            color: var(--muted);
            font-size: .75rem;
            letter-spacing: .045em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .table tbody td {
            vertical-align: middle;
            white-space: nowrap;
        }

        .value-badge {
            display: inline-flex;
            min-width: 66px;
            justify-content: center;
            padding: .35rem .65rem;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #087253;
            font-weight: 750;
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

        .empty-state {
            padding: 3.5rem 1rem;
            text-align: center;
            color: var(--muted);
        }

        .empty-icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            margin: 0 auto 1rem;
            border-radius: 18px;
            background: #eef3f6;
            color: var(--brand);
            font-size: 1.45rem;
        }

        @media (max-width: 991.98px) {
            .sensor-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .sensor-strip {
                grid-template-columns: 1fr;
            }

            .sensor-option {
                min-height: 112px;
            }

            .chart-wrap {
                min-height: 260px;
            }
        }
    </style>
</head>
<body>
<nav class="app-bar">
    <div class="container py-3">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-symbol">CDT</div>
                <div>
                    <div class="fw-bold lh-sm">Monitoreo RS485</div>
                    <div class="small text-secondary">{{ $cliente?->nombre ?? 'Panel de sensores' }}</div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                @if ($esAdministrador)
                    <a href="{{ route('admin.clientes.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-gear me-1"></i>Administración
                    </a>
                @endif

                <button id="btnActualizar" type="button" class="btn btn-light btn-sm" title="Actualizar datos">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>

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
    <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
        <div>
            <div class="soft-label mb-2">Panel del cliente</div>
            <h1 class="page-title h2 mb-1">Mis sensores</h1>
            <p class="text-secondary mb-0">Consulta el estado y las últimas mediciones de tus equipos.</p>
        </div>

        <div class="small text-secondary">
            Sesión: <span class="fw-semibold text-dark">{{ $usuario->name }}</span>
        </div>
    </div>

    <section class="surface selector-bar mb-4">
        <form method="GET" action="{{ route('dashboard') }}">
            <div class="row g-3 align-items-end">
                @if ($esAdministrador)
                    <div class="col-md-5 col-lg-4">
                        <label for="cliente" class="form-label small fw-semibold text-secondary">Cliente</label>
                        <select id="cliente" name="cliente" class="form-select" onchange="this.form.submit()" {{ $clientes->isEmpty() ? 'disabled' : '' }}>
                            @forelse ($clientes as $clienteOpcion)
                                <option value="{{ $clienteOpcion->codigo }}" @selected($cliente?->id === $clienteOpcion->id)>
                                    {{ $clienteOpcion->nombre }}
                                </option>
                            @empty
                                <option>No hay clientes activos</option>
                            @endforelse
                        </select>
                    </div>
                @endif

                <div class="{{ $esAdministrador ? 'col-md-7 col-lg-5' : 'col-md-8 col-lg-6' }}">
                    <label for="dispositivo" class="form-label small fw-semibold text-secondary">Equipo</label>
                    <select id="dispositivo" name="dispositivo" class="form-select" {{ $dispositivos->isEmpty() ? 'disabled' : '' }} onchange="this.form.submit()">
                        @forelse ($dispositivos as $dispositivo)
                            <option value="{{ $dispositivo->codigo }}" @selected($dispositivoSeleccionado?->id === $dispositivo->id)>
                                {{ $dispositivo->nombre }}@if ($dispositivo->ubicacion) · {{ $dispositivo->ubicacion }}@endif
                            </option>
                        @empty
                            <option>No hay equipos activos</option>
                        @endforelse
                    </select>
                </div>

                @if ($dispositivoSeleccionado)
                    @php
                        $equipoReciente = $dispositivoSeleccionado->ultima_conexion
                            && $dispositivoSeleccionado->ultima_conexion->greaterThan(now()->subMinutes(10));
                    @endphp
                    <div class="col-md-auto ms-md-auto">
                        <div class="status-pill {{ $equipoReciente ? 'online' : '' }}">
                            <span class="status-dot"></span>
                            {{ $equipoReciente ? 'Equipo conectado' : 'Sin conexión reciente' }}
                        </div>
                    </div>
                @endif
            </div>
        </form>
    </section>

    @if ($dispositivoSeleccionado)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small text-secondary">
                <i class="bi bi-cpu me-1"></i>{{ $dispositivoSeleccionado->codigo }}
            </span>
            @if ($dispositivoSeleccionado->ubicacion)
                <span class="small text-secondary">
                    <i class="bi bi-geo-alt me-1"></i>{{ $dispositivoSeleccionado->ubicacion }}
                </span>
            @endif
            <span class="small text-secondary">
                <i class="bi bi-clock me-1"></i>
                {{ $dispositivoSeleccionado->ultima_conexion?->format('d/m/Y H:i') ?? 'Sin comunicaciones' }}
            </span>
        </div>

        @if ($sensores->isNotEmpty())
            <section class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="section-heading">Sensores</h2>
                    <span class="small text-secondary">{{ $sensores->count() }} de 4 activos</span>
                </div>

                <div class="sensor-strip">
                    @foreach ($sensores as $sensor)
                        @php
                            $ultimaSensor = $sensor->ultimaMedicion;
                            $sensorReciente = $sensor->ultima_conexion
                                && $sensor->ultima_conexion->greaterThan(now()->subMinutes(10));
                        @endphp

                        <a class="sensor-option {{ $sensorSeleccionado?->id === $sensor->id ? 'active' : '' }}"
                           href="{{ route('dashboard', [
                                'cliente' => $cliente?->codigo,
                                'dispositivo' => $dispositivoSeleccionado->codigo,
                                'sensor' => $sensor->ranura,
                           ]) }}">
                            <span class="live-dot {{ $sensorReciente ? 'online' : '' }}"></span>
                            <div>
                                <div class="sensor-slot">SENSOR {{ $sensor->ranura }}</div>
                                <div class="sensor-name mt-2 pe-3">{{ $sensor->nombre }}</div>
                                <div class="small text-secondary">{{ $sensor->tipo ?: 'Sensor RS485' }}</div>
                            </div>
                            @php
                                $resumen = collect($resumenSensores->get($sensor->id, []));
                                $lecturasResumen = collect($resumen->get('lecturas', []));
                                $totalLecturas = (int) $resumen->get('total', 0);
                            @endphp
                            <div class="sensor-mini-readings mt-3">
                                @forelse ($lecturasResumen as $lecturaResumen)
                                    <div class="mini-reading-row">
                                        <span class="mini-reading-name">{{ $lecturaResumen['nombre'] }}</span>
                                        <strong>
                                            {{ number_format($lecturaResumen['valor'], $lecturaResumen['decimales'], ',', '.') }}
                                            @if ($lecturaResumen['unidad'])
                                                <span>{{ $lecturaResumen['unidad'] }}</span>
                                            @endif
                                        </strong>
                                    </div>
                                @empty
                                    <div class="sensor-mini-value">--</div>
                                @endforelse

                                @if ($totalLecturas > 2)
                                    <div class="more-readings">+{{ $totalLecturas - 2 }} lectura(s)</div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <section class="surface empty-state mb-4">
                <div class="empty-icon"><i class="bi bi-broadcast"></i></div>
                <h2 class="h5 text-dark">Aún no hay sensores registrados</h2>
                <p class="mb-0">Las ranuras aparecerán automáticamente cuando el equipo envíe su primera medición.</p>
            </section>
        @endif

        @if ($sensorSeleccionado)
            @php
                $sensorReciente = $sensorSeleccionado->ultima_conexion
                    && $sensorSeleccionado->ultima_conexion->greaterThan(now()->subMinutes(10));
            @endphp

            <section class="surface hero-reading position-relative p-4 p-lg-5 mb-4">
                <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-4 mb-4">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="badge rounded-pill text-bg-light">Sensor {{ $sensorSeleccionado->ranura }}</span>
                            <span class="status-pill {{ $sensorReciente ? 'online' : '' }}">
                                <span class="status-dot"></span>
                                {{ $sensorReciente ? 'Recibiendo datos' : 'Sin datos recientes' }}
                            </span>
                        </div>

                        <h2 class="h3 fw-bold mb-1">{{ $sensorSeleccionado->nombre }}</h2>
                        <p class="text-secondary mb-0">{{ $sensorSeleccionado->tipo ?: 'Sensor conectado por RS485' }}</p>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <div class="info-chip">
                            <i class="bi bi-calendar-check"></i>
                            <span>
                                Última lectura:<br>
                                <strong class="text-dark">{{ $ultimaMedicion?->fecha_recepcion?->format('d/m/Y H:i:s') ?? 'Sin mediciones' }}</strong>
                            </span>
                        </div>
                        <div class="info-chip">
                            <i class="bi bi-activity"></i>
                            <span>
                                Historial:<br>
                                <strong class="text-dark">{{ $historial?->total() ?? 0 }} mediciones</strong>
                            </span>
                        </div>
                    </div>
                </div>

                @if ($lecturasActuales->isNotEmpty())
                    <div class="reading-grid">
                        @foreach ($lecturasActuales as $lectura)
                            <a class="reading-card {{ $lecturaSeleccionada === $lectura['indice'] ? 'selected' : '' }}"
                               href="{{ route('dashboard', [
                                    'cliente' => $cliente?->codigo,
                                    'dispositivo' => $dispositivoSeleccionado->codigo,
                                    'sensor' => $sensorSeleccionado->ranura,
                                    'lectura' => $lectura['indice'],
                               ]) }}">
                                <div class="reading-card-name">{{ $lectura['nombre'] }}</div>
                                <div class="reading-card-value">
                                    {{ number_format($lectura['valor'], $lectura['decimales'], ',', '.') }}
                                    @if ($lectura['unidad'])
                                        <span>{{ $lectura['unidad'] }}</span>
                                    @endif
                                </div>
                                <div class="reading-card-meta">Lectura {{ $lectura['indice'] + 1 }}</div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state py-4">Este sensor todavía no tiene lecturas disponibles.</div>
                @endif
            </section>

            <section class="surface p-3 p-lg-4 mb-4">
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-md">
                        <h2 class="section-heading">
                            Evolución de {{ $lecturaSeleccionadaDatos['nombre'] ?? 'la lectura' }}
                        </h2>
                        <div class="small text-secondary mt-1">Últimos valores recibidos del dato seleccionado.</div>
                    </div>

                    @if ($lecturasActuales->count() > 1)
                        <div class="col-md-5 col-lg-4">
                            <form method="GET" action="{{ route('dashboard') }}">
                                <input type="hidden" name="cliente" value="{{ $cliente?->codigo }}">
                                <input type="hidden" name="dispositivo" value="{{ $dispositivoSeleccionado->codigo }}">
                                <input type="hidden" name="sensor" value="{{ $sensorSeleccionado->ranura }}">
                                <label for="lectura" class="form-label small fw-semibold text-secondary">Dato mostrado</label>
                                <select id="lectura" name="lectura" class="form-select form-select-sm" onchange="this.form.submit()">
                                    @foreach ($lecturasActuales as $lectura)
                                        <option value="{{ $lectura['indice'] }}" @selected($lecturaSeleccionada === $lectura['indice'])>
                                            {{ $lectura['nombre'] }}@if ($lectura['unidad']) · {{ $lectura['unidad'] }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($medicionesGrafica->isNotEmpty())
                    <div class="chart-wrap">
                        <canvas id="graficaMediciones" aria-label="Gráfica del sensor" role="img"></canvas>
                    </div>
                @else
                    <div class="empty-state py-5">No hay suficientes datos para mostrar la gráfica.</div>
                @endif
            </section>

            <section class="surface mb-4 overflow-hidden">
                <button class="technical-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#detallesTecnicos" aria-expanded="false" aria-controls="detallesTecnicos">
                    <span><i class="bi bi-sliders me-2 text-secondary"></i>Detalles técnicos</span>
                    <i class="bi bi-chevron-down toggle-icon"></i>
                </button>

                <div class="collapse" id="detallesTecnicos">
                    <div class="border-top p-3 p-lg-4">
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6 col-lg-3">
                                <div class="soft-label">ID esclavo</div>
                                <div class="fw-bold mt-1">{{ $ultimaMedicion?->slave ?? $sensorSeleccionado->slave ?? '--' }}</div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="soft-label">Función Modbus</div>
                                <div class="fw-bold mt-1">{{ $ultimaMedicion?->funcion ?? $sensorSeleccionado->funcion ?? '--' }}</div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="soft-label">Comunicación</div>
                                <div class="fw-bold mt-1">{{ $ultimaMedicion?->baudrate ?? '--' }} bps · {{ $ultimaMedicion?->paridad ?? '--' }}</div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="soft-label">Lecturas</div>
                                <div class="fw-bold mt-1">{{ $lecturasActuales->count() }}</div>
                            </div>
                        </div>

                        @if ($lecturasActuales->isNotEmpty())
                            <div class="register-grid">
                                @foreach ($lecturasActuales as $lectura)
                                    <div class="register-item">
                                        <div class="small fw-semibold">{{ $lectura['nombre'] }}</div>
                                        <div class="small text-secondary mt-1">
                                            Registro 0x{{ strtoupper(str_pad(dechex($lectura['registro']), 4, '0', STR_PAD_LEFT)) }} · Crudo: {{ number_format($lectura['valor_crudo'], 0, ',', '.') }}
                                        </div>
                                        <div class="register-value">
                                            {{ number_format($lectura['valor'], $lectura['decimales'], ',', '.') }}
                                            @if ($lectura['unidad'])
                                                <span class="fs-6 fw-semibold text-secondary">{{ $lectura['unidad'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="surface overflow-hidden">
                <div class="p-3 p-lg-4 border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <div>
                        <h2 class="section-heading">Historial</h2>
                        <div class="small text-secondary mt-1">Cada columna corresponde a una lectura del sensor.</div>
                    </div>
                    <span class="small text-secondary">Ranura {{ $sensorSeleccionado->ranura }}</span>
                </div>

                @if ($historial && $historial->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Fecha y hora</th>
                                    @foreach ($lecturasActuales as $lectura)
                                        <th>
                                            {{ $lectura['nombre'] }}
                                            @if ($lectura['unidad'])
                                                <span class="fw-normal text-secondary">({{ $lectura['unidad'] }})</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($historial as $medicion)
                                    @php
                                        $valoresFila = $medicion->valores->mapWithKeys(
                                            fn ($item) => [(int) $item->indice => (int) $item->valor]
                                        );
                                        if ($valoresFila->isEmpty()) {
                                            $valoresFila = collect([0 => (int) $medicion->valor]);
                                        }
                                    @endphp
                                    <tr>
                                        <td class="ps-4">{{ $medicion->fecha_recepcion?->format('d/m/Y H:i:s') }}</td>
                                        @foreach ($lecturasActuales as $lectura)
                                            <td>
                                                @if ($valoresFila->has($lectura['indice']))
                                                    @php
                                                        $valorConvertido = ($valoresFila->get($lectura['indice']) * $lectura['factor']) + $lectura['ajuste'];
                                                    @endphp
                                                    <span class="value-badge">
                                                        {{ number_format($valorConvertido, $lectura['decimales'], ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="text-secondary">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($historial->hasPages())
                        <div class="p-3 p-lg-4 border-top">
                            {{ $historial->onEachSide(1)->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                @else
                    <div class="empty-state">Este sensor todavía no tiene mediciones.</div>
                @endif
            </section>
        @endif
    @else
        <section class="surface empty-state">
            <div class="empty-icon"><i class="bi bi-cpu"></i></div>
            @if ($clientes->isEmpty())
                <h2 class="h5 text-dark">No hay clientes activos</h2>
                @if ($esAdministrador)
                    <a href="{{ route('admin.clientes.index') }}" class="btn btn-brand mt-2">Crear primer cliente</a>
                @endif
            @else
                <h2 class="h5 text-dark">No hay equipos activos</h2>
                <p class="mb-0">El cliente seleccionado aún no tiene un controlador disponible.</p>
            @endif
        </section>
    @endif
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    document.getElementById('btnActualizar')?.addEventListener('click', () => window.location.reload());

    @if ($medicionesGrafica->isNotEmpty())
        const canvas = document.getElementById('graficaMediciones');
        const etiquetas = @json(
            $medicionesGrafica
                ->map(fn ($medicion) => $medicion['fecha']?->format('d/m H:i'))
                ->values()
        );
        const valores = @json(
            $medicionesGrafica
                ->pluck('valor')
                ->map(fn ($valor) => (float) $valor)
                ->values()
        );
        const unidadSensor = @json($lecturaSeleccionadaDatos['unidad'] ?? '');
        const decimalesSensor = @json($lecturaSeleccionadaDatos['decimales'] ?? 0);

        if (canvas) {
            const contexto = canvas.getContext('2d');
            const degradado = contexto.createLinearGradient(0, 0, 0, 320);
            degradado.addColorStop(0, 'rgba(13, 79, 139, .18)');
            degradado.addColorStop(1, 'rgba(13, 79, 139, .01)');

            new Chart(contexto, {
                type: 'line',
                data: {
                    labels: etiquetas,
                    datasets: [{
                        data: valores,
                        borderColor: '#0d4f8b',
                        backgroundColor: degradado,
                        borderWidth: 2.5,
                        pointRadius: valores.length > 25 ? 0 : 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#159b72',
                        tension: .28,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => ` ${context.parsed.y}${unidadSensor ? ' ' + unidadSensor : ''}`
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: decimalesSensor },
                            grid: { color: 'rgba(24, 54, 75, .07)' }
                        }
                    }
                }
            });
        }
    @endif
</script>
</body>
</html>
