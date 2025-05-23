<?php
require_once 'includes/session.php';  
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';


redirect_if_not_authenticated();

require_once './includes/header.php';

?>

<div class="container">
    <div class="jumbotron mt-4">
        <h1 class="display-4">Sistema de Control Vehicular</h1>
        <p class="lead">Bienvenido al sistema de administración vehicular, <?php echo htmlspecialchars($_SESSION['user_id']); ?>.</p>
        <hr class="my-4">
        <p>Utilice el menú lateral para acceder a las diferentes funciones del sistema.</p>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-card-heading"></i> Licencias</h5>
                    <p class="card-text">Administración de licencias de conducir.</p>
                    <a href="/licencias/" class="btn btn-primary">Ir a Licencias</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-person"></i> Conductores</h5>
                    <p class="card-text">Gestión de información de conductores.</p>
                    <a href="/conductores/" class="btn btn-primary">Ir a Conductores</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-car-front"></i> Vehículos</h5>
                    <p class="card-text">Administración de vehículos registrados.</p>
                    <a href="/vehiculos/" class="btn btn-primary">Ir a Vehículos</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>