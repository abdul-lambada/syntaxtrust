# SyntaxTrust Backend - PHP & SBAdmin2 Integration

## Overview
This is a complete backend rebuild using PHP and SBAdmin2 template, integrated with React frontend for dynamic interaction.

## Features
- ✅ **SBAdmin2 Template Integration** - Clean, professional admin interface
- ✅ **Session-based Authentication** - Secure login/logout system
- ✅ **RESTful API Endpoints** - JSON API for React integration
- ✅ **Database Integration** - MySQL with PDO prepared statements
- ✅ **Security Features** - CSRF protection, XSS prevention, SQL injection prevention
- ✅ **CORS Support** - Configured for React frontend integration

## Structure
```
backend_new/
├── index.php                 # Main dashboard
├── login.php                # Login page
├── logout.php               # Logout handler
├── config/
│   └── database.php         # Database configuration
├── includes/
│   ├── header.php          # HTML head section
│   ├── sidebar.php         # Navigation sidebar
│   ├── topbar.php          # Top navigation bar
│   ├── footer.php          # Footer section
│   └── scripts.php         # JavaScript includes
├── api/
│   ├── auth.php            # Authentication API
│   └── users.php           # Users management API
├── assets/
│   ├── css/               # SBAdmin2 styles
│   ├── js/                # SBAdmin2 scripts
│   └── vendor/            # Third-party libraries
└── setup/
    └── database_setup.sql   # Database initialization
```

## Quick Setup

### 1. Database Setup
```bash
# Import the database schema
mysql -u root -p company_profile_syntaxtrust < setup/database_setup.sql
```

### 2. Configuration
- Update `config/database.php` with your database credentials
- Ensure `backend_new/` has proper write permissions
- Update base URLs if needed

### 3. Test Login
- **URL**: `http://localhost/company_profile_syntaxtrust/backend_new/login.php`
- **Test Credentials**:
  - Email: `admin@syntaxtrust.com`
  - Password: `admin123`

## API Endpoints

### Authentication
- `POST /api/auth.php` - Login user
- `GET /api/auth.php` - Check auth status
- `DELETE /api/auth.php` - Logout user

### Users Management
- `GET /api/users.php` - Get all users
- `POST /api/users.php` - Create new user
- `PUT /api/users.php` - Update user
- `DELETE /api/users.php` - Delete user

## React Integration

### Frontend Services
- Located in `src/services/backendApi.js`
- Uses Axios for HTTP requests
- Handles session-based authentication
- Automatic redirect on unauthorized access

### Usage Example
```javascript
import { authService, userService } from './services/backendApi';

// Login
const response = await authService.login(email, password);

// Get users
const users = await userService.getUsers();

// Create user
await userService.createUser({ name, email, password });
```

## Security Features
- ✅ PDO prepared statements (SQL injection prevention)
- ✅ Password hashing with bcrypt
- ✅ Session security (HTTPOnly, Secure flags)
- ✅ XSS protection with htmlspecialchars
- ✅ CORS configuration for React
- ✅ Input validation and sanitization

## Troubleshooting

### Common Issues
1. **Session not working**: Check PHP session configuration
2. **CORS errors**: Ensure CORS headers are set in `config/database.php`
3. **Database connection**: Verify MySQL credentials and database exists
4. **Login redirect loop**: Check session path and domain settings

### Debug Mode
Enable debug mode by adding to `config/database.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Next Steps
1. Test all API endpoints with React frontend
2. Add additional features (profile management, settings)
3. Implement API rate limiting
4. Add audit logging
5. Setup SSL/HTTPS for production

## Support
For issues or questions, check the browser console for detailed error messages and verify the database connection.
