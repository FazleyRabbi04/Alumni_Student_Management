# Alumni Network Management System

A comprehensive web-based alumni network management system built with PHP, MySQL, HTML5, CSS3, JavaScript, and Bootstrap 5. This system enables educational institutions to manage their alumni network, facilitate communication, organize events, manage job postings, and track achievements.

## 🚀 Features

### Core Functionality
- **User Management**: Registration and authentication for alumni and students
- **Profile Management**: Comprehensive user profiles with education, employment, and achievement tracking
- **Event Management**: Create, manage, and register for events with multiple roles
- **Job Board**: Post and browse job opportunities within the network
- **Communication System**: Integrated messaging and notification system
- **Session Management**: Mentorship and knowledge-sharing sessions
- **Achievement Tracking**: Showcase personal and professional accomplishments
- **Interest & Skills Management**: Connect users based on common interests and skills

### Database Entities
All entities from the original SQL schema are implemented:
- **Person Management**: person, alumni, student tables
- **Contact Information**: email_address, person_phone tables
- **Events**: events, registers tables with full event lifecycle
- **Jobs**: job, posts tables for career opportunities
- **Communications**: communication, sends tables for messaging
- **Sessions**: session, conducts tables for mentorship
- **Interests**: interest, alumni_interest, student_interest tables
- **History Tracking**: education_history, employment_history tables
- **Achievements**: achievement table for accomplishments
- **Skills**: person_skill table for skill management

## 🛠️ Technologies Used

### Frontend
- **HTML5**: Semantic markup and modern web standards
- **CSS3**: Custom styling with modern features
- **JavaScript (ES6+)**: Interactive functionality and AJAX
- **Bootstrap 5**: Responsive UI framework
- **Font Awesome 6**: Icon library
- **jQuery**: Enhanced DOM manipulation (optional)

### Backend
- **PHP 8+**: Server-side scripting
- **MySQL/MariaDB**: Database management
- **PDO**: Database abstraction layer for security

### Development Tools
- **IntelliJ IDEA**: Recommended IDE
- **Apache/Nginx**: Web server
- **XAMPP/WAMP/MAMP**: Local development environment

## 📋 Prerequisites

Before setting up the project, ensure you have:

1. **Web Server**: Apache 2.4+ or Nginx 1.18+
2. **PHP**: Version 8.0 or higher with extensions:
    - PDO MySQL
    - JSON
    - Session
    - Filter
    - PCRE
3. **Database**: MySQL 5.7+ or MariaDB 10.3+
4. **Browser**: Modern browser with JavaScript enabled

## 🔧 Installation & Setup

### Step 1: Download and Extract
1. Download or clone the project files
2. Extract to your web server's document root (e.g., `htdocs`, `www`, `public_html`)

### Step 2: Project Structure
```
alumni-network/
├── config/
│   ├── database.php          # Database configuration
│   └── config.php           # General configuration
├── includes/
│   ├── header.php           # Common header
│   ├── footer.php           # Common footer
│   └── navbar.php           # Navigation bar
├── assets/
│   ├── css/
│   │   └── custom.css       # Custom styles
│   ├── js/
│   │   └── custom.js        # Custom JavaScript
│   └── images/              # Image assets
├── pages/
│   ├── dashboard.php        # Main dashboard
│   ├── profile.php          # User profile management
│   ├── events.php           # Event management
│   ├── jobs.php             # Job board
│   ├── alumni.php           # Alumni directory
│   ├── students.php         # Student directory
│   ├── communications.php   # Messaging system
│   └── sessions.php         # Session management
├── auth/
│   ├── login.php            # User login
│   ├── register.php         # User registration
│   ├── logout.php           # User logout
│   └── verify.php           # Email verification
├── api/
│   ├── person.php           # Person API endpoints
│   ├── events.php           # Events API endpoints
│   ├── jobs.php             # Jobs API endpoints
│   ├── communications.php   # Communications API
│   └── sessions.php         # Sessions API
├── admin/
│   ├── index.php            # Admin dashboard
│   ├── manage_users.php     # User management
│   ├── manage_events.php    # Event management
│   └── reports.php          # System reports
├── index.php                # Landing page
└── README.md               # This file
```

### Step 3: Database Setup

1. **Create Database**:
   ```sql
   CREATE DATABASE alumni_network CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema**: Execute the provided SQL schema to create all tables with constraints

3. **Configure Database Connection**:
   Edit `config/database.php`:
   ```php
   private $host = 'localhost';        // Your database host
   private $db_name = 'alumni_network'; // Your database name
   private $username = 'your_username'; // Your database username
   private $password = 'your_password'; // Your database password
   ```

### Step 4: Web Server Configuration

#### Apache (.htaccess)
Create `.htaccess` in the project root:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx
Add to your server block:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### Step 5: File Permissions

Set appropriate permissions:
```bash
# Make directories writable
chmod 755 alumni-network/
chmod 644 alumni-network/config/database.php

# If using file uploads (future feature)
chmod 755 alumni-network/uploads/
```

### Step 6: IntelliJ IDEA Setup

1. **Open Project**: File → Open → Select the alumni-network folder
2. **Configure PHP Interpreter**:
    - Go to File → Settings → Languages & Frameworks → PHP
    - Set CLI Interpreter to your PHP installation
3. **Enable Plugins**:
    - PHP
    - Database Tools and SQL
    - HTML Tools
    - CSS
    - JavaScript
4. **Configure Database Connection**:
    - Open Database tool window
    - Add new MySQL/MariaDB connection
    - Use your database credentials

## 🔐 Security Features

### Implemented Security Measures
1. **Password Hashing**: Uses PHP's `password_hash()` with strong algorithms
2. **SQL Injection Prevention**: PDO prepared statements throughout
3. **XSS Protection**: HTML output escaping with `htmlspecialchars()`
4. **CSRF Protection**: Session-based token validation
5. **Input Validation**: Server-side and client-side validation
6. **Secure Session Management**: Proper session configuration
7. **SQL Constraints**: Database-level data integrity checks

### Additional Security Recommendations
1. **SSL/TLS**: Use HTTPS in production
2. **Database Security**: Create dedicated database user with minimal privileges
3. **File Permissions**: Restrict access to configuration files
4. **Regular Updates**: Keep PHP, web server, and database updated
5. **Backup Strategy**: Regular database and file backups

## 🎨 Customization

### Styling
- Edit `assets/css/custom.css` for visual customization
- Modify CSS variables in `:root` for theme colors
- Bootstrap classes can be extended or overridden

### Functionality
- Add new pages in the `pages/` directory
- Extend API endpoints in the `api/` directory
- Customize dashboard widgets in `pages/dashboard.php`

### Database
- Additional tables can be added with corresponding PHP models
- Modify existing table structures as needed
- Update database queries in respective PHP files

## 🧪 Testing

### Manual Testing Checklist
- [ ] User registration and login
- [ ] Profile creation and updates
- [ ] Event creation and registration
- [ ] Job posting and browsing
- [ ] Communication system
- [ ] Session management
- [ ] Achievement tracking
- [ ] Search and filter functionality
- [ ] Responsive design on mobile devices

### Database Testing
- [ ] All constraints work correctly
- [ ] Foreign key relationships maintained
- [ ] Data validation at database level
- [ ] Proper indexing for performance

## 🚀 Deployment

### Production Checklist
1. **Environment Configuration**:
    - Set production database credentials
    - Enable error logging (disable display_errors)
    - Configure proper session settings

2. **Security Hardening**:
    - Remove development files
    - Set restrictive file permissions
    - Configure firewall rules
    - Enable HTTPS

3. **Performance Optimization**:
    - Enable OPcache
    - Configure proper caching headers
    - Optimize database queries
    - Compress static assets

4. **Monitoring**:
    - Set up error logging
    - Monitor database performance
    - Configure backup procedures

## 📚 Usage Guide

### For Administrators
1. **Initial Setup**: Create admin account through registration
2. **User Management**: Monitor and manage user accounts
3. **Content Moderation**: Review and approve events, jobs, and communications
4. **System Monitoring**: Check reports and system health

### For Alumni/Students
1. **Registration**: Sign up with valid credentials
2. **Profile Setup**: Complete profile with education, employment, and achievements
3. **Networking**: Browse and connect with other members
4. **Events**: Register for events or create new ones
5. **Job Board**: Post job opportunities or browse available positions
6. **Communication**: Send and receive messages within the network

## 🤝 Contributing

### Development Guidelines
1. Follow PSR-12 coding standards for PHP
2. Use semantic HTML5 elements
3. Write responsive CSS with mobile-first approach
4. Include proper error handling
5. Add comments for complex logic
6. Test thoroughly before submitting changes

### Feature Requests
- Open an issue describing the feature
- Include use cases and expected behavior
- Consider backward compatibility

## 📄 License

This project is licensed under the MIT License. See LICENSE file for details.

## 🆘 Support

### Common Issues
1. **Database Connection Failed**: Check credentials in `config/database.php`
2. **Page Not Found**: Verify web server configuration and URL rewriting
3. **Login Issues**: Clear browser cache and check session configuration
4. **Styling Issues**: Ensure CSS and JS files are loading correctly

### Getting Help
1. Check this README for common solutions
2. Review error logs for specific issues
3. Verify database structure matches schema
4. Test with different browsers

## 🔄 Version History

- **v1.0.0**: Initial release with core functionality
    - User management and authentication
    - Profile management with history tracking
    - Event management system
    - Job board functionality
    - Communication system
    - Session management
    - Achievement tracking
    - Responsive Bootstrap UI

## 🎯 Future Enhancements

### Planned Features
- [ ] Email notifications for events and messages
- [ ] Advanced search and filtering
- [ ] File upload for profiles and events
- [ ] Mobile app API endpoints
- [ ] Social media integration
- [ ] Advanced reporting and analytics
- [ ] Multi-language support
- [ ] Integration with external job boards
- [ ] Video conferencing for sessions
- [ ] Alumni donation management

### Technical Improvements
- [ ] Caching layer implementation
- [ ] API rate limiting
- [ ] Enhanced security measures
- [ ] Performance optimizations
- [ ] Automated testing suite
- [ ] CI/CD pipeline setup

---

**Built with ❤️ for educational institutions and their alumni communities.**