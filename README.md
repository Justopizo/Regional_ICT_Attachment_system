# Regional_ICT_Attachment_system
A system I developed to aid students studying in various instituations to apply for an Attachment opportunity

```markdown
# Student Attachment Management System

A web-based application for managing student internship applications at Regional ICT Authority, with separate dashboards for students and administrators.

![System Screenshot](https://via.placeholder.com/800x400?text=Student+Attachment+System+Screenshot)

## Features

### Student Features
- User registration and authentication
- Application form with document uploads (CV, Insurance, Letters)
- Application status tracking
- View admin feedback

### Admin Features
- Review and process applications
- Accept/Reject applications with feedback
- Track available attachment slots (max 10 students)
- View all submitted applications

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Server**: XAMPP/Apache

## Installation

### Prerequisites
- XAMPP/WAMP/MAMP installed
- PHP 7.4+ 
- MySQL 5.7+
- Web browser (Chrome, Firefox recommended)

### Setup Steps

1. **Clone the repository**:
   ```bash
   git clone [https://github.com/yourusername/student-attachment-system.git](https://github.com/Justopizo/Regional_ICT_Attachment_system/tree/main)
   ```

2. **Database Setup**:
   - Import the `attachment_system.sql` file into your MySQL database
   - Default admin credentials:
     - HR Admin: `hr_admin` / `password`
     - ICT Admin: `ict_admin` / `password`

3. **Configuration**:
   - Update database credentials in `db_connect.php`
   ```php
   $host = 'localhost';
   $dbname = 'attachment_system';
   $username = 'your_db_username';
   $password = 'your_db_password';
   ```

4. **File Permissions**:
   - Create an `uploads` directory and set write permissions:
   ```bash
   mkdir uploads
   chmod 777 uploads
   ```

5. **Run the Application**:
   - Start your local server (XAMPP/WAMP/MAMP)
   - Access the system at `http://localhost/student-attachment-system`

## File Structure

```
student-attachment-system/
├── config.php             # Configuration settings
├── db_connect.php        # Database connection
├── index.php             # Main entry point
├── login.php             # Login page
├── register.php          # Student registration
├── dashboard.php         # Student dashboard
├── apply.php             # Application form
├── view_application.php  # Student view of application
├── review_applications.php # Admin dashboard
├── process_application.php # Application processing
├── view_application_admin.php # Admin view of application
├── logout.php            # Logout handler
├── upload_handler.php    # File upload handler
├── scripts.js            # Client-side scripts
├── styles.css            # Global styles
├── uploads/              # Document storage
└── attachment_system.sql # Database schema
```

## Usage Guide

### For Students
1. Register a new account
2. Login and complete the application form
3. Upload required documents
4. Track application status

### For Administrators
1. Login with admin credentials
2. Review pending applications
3. Accept/Reject applications with feedback
4. Monitor filled slots

## Security Notes

- Always change default admin passwords
- Consider implementing HTTPS in production
- Regularly backup the database
- Keep the system updated

## Contributing

Contributions are welcome! Please follow these steps:
1. Fork the repository
2. Create your feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes (`git commit -m 'Add some feature'`)
4. Push to the branch (`git push origin feature/your-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contact

For support or questions, please contact:  
[Justin Omare] - [+254793031269]  
Project Link: [[https://github.com/yourusername/student-attachment-system](https://github.com/yourusername/student-attachment-system](https://github.com/Justopizo/Regional_ICT_Attachment_system/tree/main))
```

