// Mostrar/ocultar el menú desplegable
document.getElementById('userDropdown').addEventListener('click', function(e) {
    // Evitar que el clic se propague si se hace en el menú desplegable
    if (e.target.closest('.dropdown-menu')) return;
    
    document.getElementById('dropdownMenu').classList.toggle('show');
});

// Cerrar el menú si se hace clic fuera de él
document.addEventListener('click', function(e) {
    if (!e.target.closest('#userDropdown')) {
        document.getElementById('dropdownMenu').classList.remove('show');
    }
});
