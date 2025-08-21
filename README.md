# SyntaxTrust Website

A PHP (PDO) + Tailwind site with admin CRUD and dynamic public pages.

## Requirements
- PHP 8.0+
- MySQL/MariaDB (PDO)
- Apache/LiteSpeed with mod_rewrite

## Project Structure
- `public/` Web root (recommended)
- `config/` App/env/database/session config
- `admin/` Admin panel
- `setup/` SQL dump and migrations
- `uploads/` User-uploaded assets

## Local Development (XAMPP)
1. Clone to `htdocs/syntaxtrust`
2. Point browser to `http://localhost/syntaxtrust/public/`
3. DB setup:
   - Create DB locally, import `setup/syntaxtrust_db.sql`
   - Configure `config/env.php` development:
     ```php
     'development' => [
       'host' => 'localhost',
       'name' => 'syntaxtrust_db',
       'user' => 'root',
       'pass' => '',
       'socket' => null
     ]
     ```
4. Run migrations:
   ```bash
   php setup/run_migrations.php
   ```

## Environment Configuration
Edit `config/env.php`.
- Auto-detects `APP_ENV` (production/development). Defaults to `production`.
- Base path:
  - Production: `''` (domain root)
  - Development: `'/syntaxtrust'`
- Database blocks per environment with optional `socket` for UNIX socket connections.
- CORS origins per environment via `app_cors_origins()`.

## Database Connection
`config/database.php` builds DSN from env:
- Host DSN: `mysql:host=HOST;dbname=DB;charset=utf8mb4`
- Socket DSN: `mysql:unix_socket=/path/mysql.sock;dbname=DB;charset=utf8mb4`

## Sessions & CORS
- `config/session.php` sets CORS headers from env, handles OPTIONS, and auto-enables `session.cookie_secure` on HTTPS.

## Deployment
Recommended: point your domain’s document root directly to `public/`.
- If webroot is not `public/`, project root has:
  - `.htaccess` to rewrite to `public/`
  - `index.php` to redirect `/` → `/public/`
- `config/app.php` computes `PUBLIC_BASE_PATH` dynamically:
  - Includes `/public` only when the current script path contains `/public`.

### Production DB (example)
```php
'production' => [
  'host'   => 'localhost',
  'name'   => 'syntaxtr_db',
  'user'   => 'syntaxtr_db',
  'pass'   => 'YOUR_STRONG_PASSWORD',
  'socket' => '/home/mysql/mysql.sock'
]
```
Grant example:
```sql
GRANT ALL PRIVILEGES ON syntaxtr_db.* TO 'syntaxtr_db'@'localhost';
FLUSH PRIVILEGES;
```

## Migrations
- SQL files in `setup/migrations/` run in filename order.
- Apply using:
```bash
php setup/run_migrations.php
```

## Uploads
Ensure these are writable by the web server:
- `uploads/blog/`
- `uploads/clients/`
- `uploads/portofolio/`
- `uploads/services/`

## Troubleshooting
- Access denied (DB): verify user/password in `config/env.php` match hosting panel; confirm privileges and socket path.
- 403 on root: ensure webroot is `public/` or keep project-root `.htaccess` and `index.php` redirect.
- Badge clipped on pricing: card uses `overflow-visible` and badge `z-10`.

## Security Notes
- Never commit real production passwords.
- Use least-privilege DB users.
- `display_errors` disabled in production automatically.
