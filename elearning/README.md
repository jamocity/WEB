# EduPlatform - E-Learning System

A comprehensive full-stack e-learning platform built with HTML, CSS, Bootstrap, JavaScript for the frontend and PHP with MySQL for the backend.

## 🌟 Features

### For Students
- **User Registration & Authentication** - Secure login/register system
- **Course Browsing & Enrollment** - Browse and enroll in courses
- **Video Streaming** - Watch course lessons with progress tracking
- **Interactive Quizzes** - Take quizzes with instant feedback
- **Progress Tracking** - Monitor learning progress and completion
- **Certificates** - Earn downloadable certificates upon course completion
- **Personal Dashboard** - View enrolled courses, progress, and recommendations
- **Learning Goals** - Set and track personal learning objectives
- **Study Schedule** - Set daily study goals and track study time

### For Instructors
- **Course Management** - Create, edit, and manage courses
- **Lesson Creation** - Add video lessons with descriptions
- **Quiz Builder** - Create quizzes with multiple-choice questions
- **Student Analytics** - View enrollment and completion statistics
- **Content Publishing** - Publish/unpublish courses
- **Instructor Dashboard** - Comprehensive overview of teaching activities

### General Features
- **Responsive Design** - Works on desktop, tablet, and mobile devices
- **Professional UI** - Clean, modern interface with Bootstrap
- **Search & Filtering** - Find courses by title, level, or instructor
- **Certificate Verification** - Verify certificate authenticity
- **Progress Analytics** - Detailed progress tracking and statistics

## 🏗️ Project Structure

```
elearning/
├── backend/
│   ├── api/
│   │   ├── auth.php           # Authentication endpoints
│   │   ├── courses.php        # Course management
│   │   ├── lessons.php        # Lesson management
│   │   ├── quizzes.php        # Quiz system
│   │   └── certificates.php   # Certificate generation
│   └── config/
│       └── database.php       # Database configuration
├── database/
│   └── schema.sql            # Database schema
├── frontend/
│   ├── css/
│   │   └── main.css          # Main stylesheet
│   ├── js/
│   │   └── app.js            # Main JavaScript application
│   ├── pages/
│   │   ├── login.html        # Login page
│   │   ├── register.html     # Registration page
│   │   ├── courses.html      # Course listing
│   │   └── student-dashboard.html  # Student dashboard
│   └── index.html            # Home page
├── uploads/
│   ├── videos/               # Course videos
│   └── materials/            # Course materials
└── README.md
```

## 🚀 Installation & Setup

### Prerequisites
- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Step 1: Database Setup
1. Create a MySQL database named `elearning_platform`
2. Import the database schema:
   ```sql
   mysql -u username -p elearning_platform < database/schema.sql
   ```

### Step 2: Backend Configuration
1. Update database credentials in `backend/config/database.php`:
   ```php
   private $host = 'localhost';
   private $db_name = 'elearning_platform';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

### Step 3: Web Server Setup
1. Copy the project to your web server directory
2. Ensure the web server has read/write permissions for the `uploads/` directory
3. Configure virtual host or access via localhost

### Step 4: Access the Application
- Open your browser and navigate to the project URL
- Use the demo accounts or register a new account

## 👥 Demo Accounts

The system comes with pre-configured demo accounts for testing:

**Student Account:**
- Username: `jane_student`
- Password: `password`

**Instructor Account:**
- Username: `john_instructor`
- Password: `password`

**Admin Account:**
- Username: `admin`
- Password: `password`

## 🎯 Usage Guide

### For Students

1. **Registration/Login**
   - Register as a student or use demo account
   - Complete your profile information

2. **Browse Courses**
   - View available courses on the courses page
   - Use search and filters to find specific courses
   - Check course details, lessons, and requirements

3. **Enroll in Courses**
   - Click "Enroll Now" on course detail page
   - Access course content immediately after enrollment

4. **Learn & Progress**
   - Watch video lessons in order
   - Take quizzes to test your knowledge
   - Track your progress on the dashboard

5. **Earn Certificates**
   - Complete all lessons and pass required quizzes
   - Generate and download your certificate
   - Share your achievements

### For Instructors

1. **Course Creation**
   - Access instructor dashboard after login
   - Click "Create New Course"
   - Add course details, description, and level

2. **Add Content**
   - Create lessons with video content
   - Add lesson descriptions and duration
   - Upload course materials

3. **Create Assessments**
   - Build quizzes with multiple-choice questions
   - Set passing scores and attempt limits
   - Configure time limits

4. **Manage Students**
   - View enrollment statistics
   - Monitor student progress
   - Generate reports

## 🔧 API Endpoints

### Authentication
- `POST /api/auth.php?action=login` - User login
- `POST /api/auth.php?action=register` - User registration
- `POST /api/auth.php?action=logout` - User logout
- `GET /api/auth.php` - Get current user

### Courses
- `GET /api/courses.php` - List all courses
- `GET /api/courses.php?action=detail&id={id}` - Get course details
- `POST /api/courses.php?action=create` - Create new course
- `POST /api/courses.php?action=enroll` - Enroll in course
- `GET /api/courses.php?action=my-courses` - Get instructor's courses
- `GET /api/courses.php?action=my-enrollments` - Get student's enrollments

### Lessons
- `GET /api/lessons.php?action=detail&id={id}` - Get lesson details
- `POST /api/lessons.php?action=create` - Create new lesson
- `POST /api/lessons.php?action=complete` - Mark lesson as complete
- `POST /api/lessons.php?action=update-progress` - Update lesson progress

### Quizzes
- `GET /api/quizzes.php?action=detail&id={id}` - Get quiz details
- `POST /api/quizzes.php?action=create` - Create new quiz
- `POST /api/quizzes.php?action=submit` - Submit quiz answers

### Certificates
- `POST /api/certificates.php` - Generate certificate
- `GET /api/certificates.php?action=my-certificates` - Get user's certificates
- `GET /api/certificates.php?action=download&code={code}` - Download certificate

## 🛠️ Technologies Used

### Frontend
- **HTML5** - Semantic markup
- **CSS3** - Modern styling with custom properties
- **Bootstrap 5** - Responsive framework
- **JavaScript (ES6+)** - Interactive functionality
- **Font Awesome** - Icons

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL** - Database management
- **PDO** - Database abstraction layer

### Additional Libraries
- **Bootstrap JS** - Interactive components
- **Custom CSS** - Enhanced styling and animations

## 📱 Responsive Design

The platform is fully responsive and optimized for:
- **Desktop** - Full-featured experience
- **Tablet** - Touch-friendly interface
- **Mobile** - Optimized for small screens

## 🔒 Security Features

- **Password Hashing** - Secure password storage with PHP's password_hash()
- **SQL Injection Prevention** - Prepared statements with PDO
- **Session Management** - Secure session handling
- **Input Validation** - Client and server-side validation
- **Access Control** - Role-based permissions

## 🎨 Customization

### Styling
- Modify `frontend/css/main.css` for custom styles
- Update CSS custom properties in `:root` for theme colors
- Add custom Bootstrap themes

### Functionality
- Extend API endpoints in `backend/api/` directory
- Add new features in `frontend/js/app.js`
- Create additional pages in `frontend/pages/`

## 📊 Database Schema

### Key Tables
- **users** - User accounts and profiles
- **courses** - Course information
- **lessons** - Individual lesson content
- **quizzes** - Quiz definitions
- **quiz_questions** - Quiz questions and answers
- **enrollments** - Student-course relationships
- **certificates** - Generated certificates
- **lesson_progress** - Learning progress tracking

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `backend/config/database.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **Videos Not Playing**
   - Check video file paths in lesson data
   - Ensure proper file permissions
   - Verify video format compatibility

3. **Certificates Not Generating**
   - Check course completion requirements
   - Verify quiz passing scores
   - Ensure all lessons are marked complete

4. **Login Issues**
   - Clear browser cache and cookies
   - Check username/password combination
   - Verify user exists in database

## 🔄 Future Enhancements

### Potential Features
- **Video Upload System** - Direct video upload interface
- **Payment Integration** - Paid course support
- **Discussion Forums** - Student-instructor communication
- **Live Classes** - Real-time video sessions
- **Mobile App** - Native mobile applications
- **Advanced Analytics** - Detailed learning analytics
- **Multi-language Support** - Internationalization
- **Email Notifications** - Course updates and reminders

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📞 Support

For support and questions:
- Create an issue in the repository
- Check the troubleshooting section
- Review the API documentation

## 🙏 Acknowledgments

- Bootstrap team for the excellent framework
- Font Awesome for the comprehensive icon library
- PHP community for excellent documentation
- All contributors and testers

---

**Built with ❤️ for learners worldwide**