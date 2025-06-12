document.getElementById('notificationBell').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    
    if(dropdown.style.display === 'block') {
        cargarNotificaciones();
    }
});

// Función para cargar notificaciones via AJAX
function cargarNotificaciones() {
    fetch('../php/obtenerAvisos.php?padreId=<?php echo $idPadre; ?>')
        .then(response => response.text())
        .then(data => {
            document.getElementById('notificationList').innerHTML = data;
        });
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(e) {
    if(!e.target.closest('.notification-icon')) {
        document.getElementById('notificationDropdown').style.display = 'none';
    }
    if(!e.target.closest('.user-info')) {
        document.getElementById('dropdownMenu').style.display = 'none';
    }
});