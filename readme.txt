UniVroom - By Students, For Students
=====================================

A comprehensive ride-sharing and marketplace platform built specifically for students.

FEATURES:
---------
✅ User Authentication (signup/login with .edu/.ac emails)
✅ Ride Booking System with Mapbox Integration
✅ Real-time Ride Tracking & Chat
✅ Student Marketplace
✅ Shopping Cart & Checkout
✅ Admin Panel
✅ Responsive Design (Mobile & Desktop)
✅ Dark/Light Mode Support

TECH STACK:
-----------
- Frontend: PHP, HTML5, CSS3, JavaScript
- Backend: PHP 7.4+
- Database: MySQL 5.7+
- UI Framework: MDBootstrap 6.4.2
- Maps: Mapbox API
- Icons: Font Awesome 6.0

SETUP INSTRUCTIONS:
------------------

1. XAMPP SETUP:
   - Install XAMPP (PHP 7.4+ recommended)
   - Start Apache and MySQL services
   - Extract project to: C:\xampp\htdocs\univroom\

2. DATABASE SETUP:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import database.sql file
   - Database will be created automatically with demo data

3. CONFIGURATION:
   - No additional configuration needed
   - Mapbox API key is already included
   - SMTP settings can be configured in config/config.php

4. ACCESS THE APPLICATION:
   - Main Site: http://localhost/univroom/
   - Admin Panel: http://localhost/univroom/admin/

DEMO ACCOUNTS:
--------------

USERS:
- Email: john@university.edu
- Password: password123
- Role: Regular User

- Email: jane@university.edu  
- Password: password123
- Role: Rider (Approved)

- Email: mike@university.edu
- Password: password123
- Role: Rider (Approved)

ADMIN:
- Username: admin
- Password: password123
- Role: Super Admin

FEATURES OVERVIEW:
-----------------

USER FEATURES:
- Institutional email signup (.edu/.ac domains)
- Profile management with photo upload
- Real-time ride booking with GPS
- Live fare calculation (15 BDT/km)
- Mapbox integration for routes
- Real-time chat with riders
- Marketplace for buying/selling
- Shopping cart with coupons
- Order tracking
- Notification system

RIDER FEATURES:
- Vehicle registration
- Ride request notifications
- Accept/decline ride requests
- Real-time location tracking
- Chat with passengers
- Earnings dashboard
- Online/offline status

ADMIN FEATURES:
- User management
- Rider approval system
- Product management
- Order management
- Coupon system
- Announcements
- Analytics dashboard
- System settings

MARKETPLACE:
- Product categories
- Search and filters
- Product reviews
- Shopping cart
- Coupon system
- Order management
- COD and bKash payment options

TECHNICAL FEATURES:
- Responsive design (mobile-first)
- Real-time updates (AJAX polling)
- Secure authentication
- SQL injection protection
- XSS protection
- Session management
- File upload security
- Error handling

FOLDER STRUCTURE:
----------------
univroom/
├── config/           # Configuration files
├── auth/            # Authentication pages
├── marketplace/     # Marketplace functionality
├── admin/           # Admin panel
├── api/             # API endpoints
├── uploads/         # File uploads
├── assets/          # Static assets
├── database.sql     # Database schema
└── readme.txt       # This file

API ENDPOINTS:
-------------
- /api/get-products.php    # Get product details
- /api/send-message.php    # Send chat messages
- /api/get-messages.php    # Get chat messages
- /api/get-ride-status.php # Get ride status updates

SECURITY FEATURES:
-----------------
- Password hashing (PHP password_hash)
- SQL prepared statements
- Input sanitization
- CSRF protection
- File upload validation
- Session security
- Admin role-based access

MOBILE FEATURES:
---------------
- Responsive design
- Touch-friendly interface
- Bottom navigation
- Mobile-optimized forms
- GPS location access
- Mobile-friendly maps

CUSTOMIZATION:
-------------
- Colors: Edit CSS variables in style sections
- Site name: Change SITE_NAME in config/config.php
- Fare rates: Modify BASE_FARE_PER_KM constant
- Email settings: Configure SMTP in config/config.php
- Payment methods: Add/modify in respective forms

TROUBLESHOOTING:
---------------

1. Database Connection Error:
   - Check MySQL service is running
   - Verify database credentials in config/database.php
   - Ensure database exists and is imported

2. File Upload Issues:
   - Check uploads/ folder permissions
   - Verify PHP upload settings
   - Ensure max file size limits

3. Map Not Loading:
   - Check internet connection
   - Verify Mapbox API key
   - Check browser console for errors

4. Session Issues:
   - Clear browser cache/cookies
   - Check PHP session configuration
   - Verify session folder permissions

DEVELOPMENT NOTES:
-----------------
- All passwords in demo data are hashed using password_hash()
- Real-time features use AJAX polling (10-second intervals)
- File uploads are validated for security
- All user inputs are sanitized
- Database uses prepared statements
- Responsive breakpoints: 576px, 768px, 992px, 1200px

PRODUCTION DEPLOYMENT:
---------------------
1. Change database credentials
2. Update SITE_URL in config/config.php
3. Configure SMTP settings
4. Set up SSL certificate
5. Enable error logging (disable display_errors)
6. Set up backup system
7. Configure proper file permissions

SUPPORT:
--------
This is a demo application for educational purposes.
All features are fully functional and ready for use.

For any issues or questions, please check the code comments
and configuration files for detailed explanations.

VERSION: 1.0
CREATED: 2024
LICENSE: Educational Use
