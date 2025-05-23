<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Control Vehicular</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php">Control Vehicular</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> 
                                <?php echo htmlspecialchars($_SESSION['user_id']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'A'): ?>
                                    <li><a class="dropdown-item" href="/admin/users.php"><i class="bi bi-people"></i> Gestión de Usuarios</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/auth/login.php"><i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-light sidebar p-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/licencias/">
                            <i class="bi bi-card-heading"></i> Licencias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/conductores/">
                            <i class="bi bi-person"></i> Conductores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/vehiculos/">
                            <i class="bi bi-car-front"></i> Vehículos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/multas/">
                            <i class="bi bi-exclamation-triangle"></i> Multas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/tarjetas-circulacion/">
                            <i class="bi bi-card-text"></i> Tarjetas de Circulación
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/tarjetas-verificacion/">
                            <i class="bi bi-clipboard-check"></i> Verificaciones
                        </a>
                    </li>
                      <li class="nav-item">
                <a class="nav-link" href="/pagos/">
                    <i class="bi bi-cash-coin me-2"></i> Pagos
                </a>
            </li>
                </ul>
            </div>
            
            <!-- Main content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">