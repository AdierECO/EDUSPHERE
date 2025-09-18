# 📚 EduSphere  
### Plataforma Educativa Integral  

✨ Una aplicación web que digitaliza procesos escolares y administrativos en instituciones de nivel básico.  

📌 Gestión académica | Comunicación | Reportes | Seguridad  

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
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)  
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)  
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

### 🔹 Backend  
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)  

### 🔹 Base de datos  
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)  

### 🔹 Servidor y despliegue  
![Apache](https://img.shields.io/badge/Apache-D22128?style=for-the-badge&logo=apache&logoColor=white)   

---

## ⚙️ Instalación y configuración  

### 1️⃣ Clonar el repositorio  
```bash
git clone https://github.com/AdierECO/edusphere.git
cd edusphere
````

### 2️⃣ Configurar el backend

1. Instalar servidor local (XAMPP, Laragon o WAMP).
2. Copiar archivos del proyecto en la carpeta `htdocs`.
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

Si es HTML/CSS/JS plano: abre `index.html`.

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
* ✅ Aplicación móvil (Android/iOS).

---

## 👨‍💻 Autores

* **Erick Adier Ortiz Cabrera** – Director General & Backend Developer
* **Jesús Gregorio Castelán Lira** – Backend Developer
* **José Luis Curiel López** – Backend Developer
* **Christopher Osiel Nava Cruz** – Frontend Developer
---
