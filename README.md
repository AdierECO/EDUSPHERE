````markdown
# 📚 EduSphere – Plataforma Educativa Integral  

EduSphere es una **aplicación web** para instituciones educativas de nivel básico que busca **digitalizar procesos escolares y administrativos**.  
La plataforma facilita la **gestión académica, comunicación con padres de familia y administración escolar**, todo en un solo lugar.  

---

## ✨ Características principales  
- 👨‍🏫 **Gestión académica**: control de asistencia, calificaciones y tareas.  
- 📢 **Comunicación institucional**: avisos, notificaciones y chat con padres.  
- 📊 **Reportes automáticos**: desempeño académico en tiempo real.  
- 🔐 **Gestión de usuarios**: accesos personalizados para estudiantes, docentes, padres y administradores.  
- ☁️ **Seguridad y accesibilidad**: aplicación web responsiva con soporte en la nube.  

---

## 🛠️ Tecnologías utilizadas  

### 🔹 Frontend  
- HTML5, CSS3, JavaScript  
- Framework: *(React, Angular o Vue – según implementación)*  
- Bootstrap / TailwindCSS  

### 🔹 Backend  
- PHP 8 *(con sesiones y seguridad)*  
- Node.js *(opcional, para módulos en tiempo real)*  

### 🔹 Base de datos  
- MySQL / MariaDB  

### 🔹 Servidor y despliegue  
- Apache / Nginx  
- Hosting en la nube *(AWS, Azure u otro)*  

---

## ⚙️ Instalación y configuración  

### 1️⃣ Clonar el repositorio  
```bash
git clone https://github.com/TU-USUARIO/edusphere.git
cd edusphere
````

### 2️⃣ Configurar el backend

1. Instalar servidor local (XAMPP, Laragon o WAMP).
2. Copiar archivos del proyecto en la carpeta `htdocs` (XAMPP).
3. Crear base de datos `edusphere_db` en MySQL.
4. Importar `database/edusphere.sql`.
5. Configurar conexión en `php/Conexion.php`:

```php
$host = "localhost";
$usuario = "root";
$password = "";
$base_datos = "edusphere_db";
```

### 3️⃣ Configurar el frontend

Si usas framework (React/Vue/Angular):

```bash
npm install
npm run dev
```

Si es HTML/CSS/JS plano: abre `index.html` en tu navegador.

### 4️⃣ Levantar el servidor

* Con XAMPP: iniciar **Apache** y **MySQL**.
* Acceder a:

```
http://localhost/edusphere
```

---

## 👥 Roles de usuario

* **Administrador** → gestiona usuarios, módulos y reportes.
* **Docente** → registra asistencia, tareas y calificaciones.
* **Estudiante** → consulta calificaciones, tareas y avisos.
* **Padre de familia** → recibe notificaciones y consulta desempeño académico.

---

## 📌 Estructura del proyecto

```
edusphere/
│── index.html            # Página principal
│── css/                  # Estilos
│── js/                   # Scripts frontend
│── php/                  # Archivos backend
│   ├── Conexion.php      # Conexión a BD
│   ├── Usuario.php       # Controlador de usuarios
│── database/             # Scripts SQL
│   ├── edusphere.sql
│── images/               # Recursos gráficos
│── README.md             # Documentación
```

---

## 🚀 Futuras mejoras

* ✅ Notificaciones en tiempo real.
* ✅ Aplicación móvil (Android/iOS).
* ✅ Integración con Google Classroom y Microsoft Teams.

---

## 👨‍💻 Autores

* **Erick Adier Ortiz Cabrera** – Director General & Backend Developer
* **Christopher Osiel Nava Cruz** – Frontend Developer
* **Jesús Gregorio Castelán Lira** – Backend Developer
* **José Luis Curiel López** – Backend Developer

---

