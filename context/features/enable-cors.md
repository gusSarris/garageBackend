# Feature: Enable Cross-Origin Resource Sharing (`enable-cors`)

## Overview
Install and configure `nelmio/cors-bundle` via Symfony Flex to allow web-based frontend applications (e.g., Next.js, Vite, React running on `localhost:3000`, `localhost:5173`, etc.) to communicate seamlessly with the backend API (`http://localhost:8080`).

---

## Technical Specifications & Configuration

1. **Dependency Installation**:
   - Install `nelmio/cors-bundle` using the Docker workflow:
     `docker compose exec -T php composer require nelmio/cors-bundle`
   - Symfony Flex recipe registers `Nelmio\CorsBundle\NelmioCorsBundle` in `config/bundles.php` and generates `config/packages/nelmio_cors.yaml`.

2. **Environment Variable Configuration**:
   - `CORS_ALLOW_ORIGIN` in `.env` / `.env.test` / `.env.local`:
     Regex pattern allowing local development frontend origins:
     `^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$`

3. **CORS Rules (`config/packages/nelmio_cors.yaml`)**:
   - Target paths: `^/api/`
   - Allowed methods: `['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']`
   - Allowed headers: `['Content-Type', 'Authorization', 'Accept', 'Origin', 'X-Requested-With']`
   - Exposed headers: `['Link', 'Content-Disposition']`
   - Max age: `3600`
   - Allow credentials: `true` or configured based on header expectations.

4. **Preflight & Authentication Compatibility**:
   - Ensure preflight `OPTIONS` requests to all `/api/*` routes (including `/api/login_check` and `/api/auth/*`) bypass firewall restrictions and return `204 No Content` or `200 OK` with CORS headers.

---

## Testing Plan (`tests/Cors/CorsHeadersTest.php`)

1. `testPreflightRequestOnLoginCheck`:
   - Send `OPTIONS /api/login_check` with headers:
     - `Origin: http://localhost:3000`
     - `Access-Control-Request-Method: POST`
     - `Access-Control-Request-Headers: content-type`
   - Assert response status is 200 or 204.
   - Assert `Access-Control-Allow-Origin: http://localhost:3000`.
   - Assert `Access-Control-Allow-Methods` contains `POST`.

2. `testPreflightRequestOnProtectedApiRoute`:
   - Send `OPTIONS /api/garage/customers` with:
     - `Origin: http://localhost:5173`
     - `Access-Control-Request-Method: GET`
     - `Access-Control-Request-Headers: authorization,content-type`
   - Assert response returns CORS headers without requiring authentication.

3. `testActualRequestIncludesCorsHeaders`:
   - Send `POST /api/login_check` with `Origin: http://localhost:3000`.
   - Assert response includes `Access-Control-Allow-Origin: http://localhost:3000`.

4. `testAuthenticatedRequestIncludesCorsHeaders`:
   - Send authenticated `GET /api/auth/me` with `Origin: http://localhost:3000` and JWT Bearer token.
   - Assert status is 200 OK and includes `Access-Control-Allow-Origin: http://localhost:3000`.
