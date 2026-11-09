# 14 Destinos

Mapa interactivo para guardar recuerdos de viajes (con foto, historia y fecha) y generar planes de viaje por ciudad.

- **Frontend:** Vue 3 + Vite (Composition API), Leaflet, Bootstrap 5
- **Backend:** PHP 8 (API REST con JWT) + MySQL
- **Extras:** PWA instalable, tema claro/oscuro, geolocalización

## Estructura

```
├── .htaccess                 Rutas de Apache: /api → backend, el resto → frontend/dist
├── backend/
│   ├── public/index.php      Router de la API
│   ├── api/                  Controladores (auth, memories, plans, countries, places)
│   ├── config/               Configuración y conexión a la base de datos
│   └── middleware/           Seguridad (CORS, rate limit, subida de archivos) y auth
├── database/                 Scripts SQL (schema.sql, places.sql)
├── uploads/                  Fotos de los recuerdos
├── frontend/
│   ├── src/
│   │   ├── App.vue
│   │   ├── components/       NavBar, MapView, SidePanel, modales...
│   │   ├── composables/      Estado global, mapa, modales, tema, avisos
│   │   ├── services/         Cliente de la API y utilidades
│   │   ├── data/             Ciudades y nombres de países
│   │   └── assets/style.css
│   ├── public/               Iconos, manifest, service worker, GeoJSON
│   └── dist/                 Versión compilada (la que se sube al servidor)
├── Dockerfile
└── docker-compose.yml
```

## Desarrollo en local

**Backend** (necesita Docker Desktop), desde la raíz del proyecto:

```bash
docker compose up -d --build
```

| Servicio   | URL                    |
|------------|------------------------|
| Web + API  | http://localhost:8080  |
| phpMyAdmin | http://localhost:8081  |

Las tablas se crean solas la primera vez a partir de `database/`.

**Frontend:**

```bash
cd frontend
npm install
npm run dev        # http://localhost:5173
```

Vite redirige `/api` y `/uploads` al backend de `localhost:8080`.

### Mapa (opcional)

Copia `frontend/.env.example` como `frontend/.env` y pon una key gratuita de CARTO
(https://carto.com/basemaps/apikey/). Sin key se usa el mapa de Esri.

## Despliegue (Apache / Hestia)

1. `cd frontend && npm run build`
2. Crear la base de datos e importar `database/schema.sql` y `database/places.sql`.
3. Poner los datos de la base de datos en `backend/config/bootstrap.php`.
4. Subir a `public_html`: `.htaccess`, `backend/`, `uploads/` y `frontend/dist/`.
5. Comprobar `https://tudominio/api/ping`.

Funciona en la raíz del dominio o en una subcarpeta (por ejemplo `public_html/14dest/`).
