# Feature: JWT Authentication API

## Overview
Implement stateless **JWT Authentication** using `lexik/jwt-authentication-bundle` on the Symfony Docker backend.
This feature enables secure, multi-tenant authenticated access for the frontend application, authenticating against the `User` entity (table `app_user`) and injecting the user's `garage_id` and role profile into the JWT payload.

## Prerequisites
- `app_user` entity exists with `UserInterface`, `PasswordAuthenticatedUserInterface`, `email`, hashed `password`, `roles`, and `garage` relation.
- `symfony/security-bundle` is installed and configured with password hashers (`auto`).

## Specifications

### 1. Bundle Installation & Keypair Configuration
- Package: `lexik/jwt-authentication-bundle` installed via Composer inside Docker:
  ```bash
  docker compose exec -T php composer require lexik/jwt-authentication-bundle
  ```
- Generate SSL keypairs:
  ```bash
  docker compose exec -T php bin/console lexik:jwt:generate-keypair --skip-if-exists
  ```
- Keys located at:
  - `config/jwt/private.pem`
  - `config/jwt/public.pem`
- Configure `JWT_PASSPHRASE` in `.env` / `.env.local` if needed.

### 2. Security Configuration (`config/packages/security.yaml`)
- Update `security.yaml` firewalls:
  ```yaml
  security:
      firewalls:
          dev:
              pattern: ^/(_(profiler|wdt)|css|images|js)/
              security: false
          login:
              pattern: ^/api/login_check$
              stateless: true
              json_login:
                  check_path: /api/login_check
                  username_path: email
                  password_path: password
                  success_handler: lexik_jwt_authentication.handler.authentication_success
                  failure_handler: lexik_jwt_authentication.handler.authentication_failure
          api:
              pattern: ^/api
              stateless: true
              jwt: ~
          main:
              lazy: true
              provider: app_user_provider

      access_control:
          - { path: ^/api/login_check$, roles: PUBLIC_ACCESS }
          - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
  ```

### 3. JWT Payload Customization
- Implement an Event Listener for `JWTCreatedEvent` (`lexik_jwt_authentication.on_jwt_created`):
  - Injects tenant & user metadata into the JWT claims:
    - `id`: User UUID (`string`)
    - `email`: User email (`string`)
    - `fullName`: Combined first and last name (`string`)
    - `garageId`: Garage UUID (`string`)
    - `garageName`: Garage name (`string`)
    - `roles`: Array of granted roles

### 4. Current User Endpoint (`GET /api/auth/me`)
- **Route**: `GET /api/auth/me`
- **Controller**: `App\Controller\Api\AuthController`
- **Security**: Requires `IS_AUTHENTICATED_FULLY` (returns 401 if missing or invalid token).
- **Response Format** (JSON):
  ```json
  {
    "id": "01946812-3456-789a-bcde-f0123456789a",
    "email": "owner@garage.example.com",
    "firstName": "Γιώργος",
    "lastName": "Παπαδόπουλος",
    "roles": ["ROLE_GARAGE_ADMIN", "ROLE_USER"],
    "garage": {
      "id": "01946812-3456-789a-bcde-f0123456789b",
      "name": "Auto Moto Service",
      "subscriptionStatus": "active"
    }
  }
  ```

### 5. Automated Verification & Testing
- Functional HTTP tests (`WebTestCase`) in `tests/AuthApiTest.php`:
  - `testLoginSuccessWithValidCredentials`: `POST /api/login_check` returns 200, JWT token, and decodable payload with `garageId`.
  - `testLoginFailureWithInvalidPassword`: `POST /api/login_check` with wrong password returns 401 Unauthorized.
  - `testLoginFailureWithNonExistentUser`: `POST /api/login_check` with unregistered email returns 401 Unauthorized.
  - `testAuthMeUnauthenticated`: `GET /api/auth/me` without token returns 401 Unauthorized.
  - `testAuthMeSuccess`: `GET /api/auth/me` with `Bearer <token>` returns 200 with matching user & garage profile.
