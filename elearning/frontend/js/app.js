// E-Learning Platform Main JavaScript

class ELearningApp {
    constructor() {
        this.baseURL = '../backend/api';
        this.currentUser = null;
        this.init();
    }

    init() {
        this.checkAuthStatus();
        this.attachEventListeners();
        this.loadInitialData();
    }

    // Authentication Methods
    async checkAuthStatus() {
        try {
            const response = await this.makeRequest('auth.php', 'GET');
            if (response.user) {
                this.currentUser = response.user;
                this.updateNavigation(true);
                this.updateUserInfo();
            } else {
                this.updateNavigation(false);
            }
        } catch (error) {
            this.updateNavigation(false);
        }
    }

    async login(username, password) {
        try {
            const response = await this.makeRequest('auth.php?action=login', 'POST', {
                username,
                password
            });

            if (response.success) {
                this.currentUser = response.user;
                this.updateNavigation(true);
                this.updateUserInfo();
                this.showAlert('Login successful!', 'success');
                return true;
            }
        } catch (error) {
            this.showAlert(error.message || 'Login failed', 'danger');
            return false;
        }
    }

    async register(userData) {
        try {
            const response = await this.makeRequest('auth.php?action=register', 'POST', userData);
            
            if (response.success) {
                this.showAlert('Registration successful! Please login.', 'success');
                return true;
            }
        } catch (error) {
            this.showAlert(error.message || 'Registration failed', 'danger');
            return false;
        }
    }

    async logout() {
        try {
            await this.makeRequest('auth.php?action=logout', 'POST');
            this.currentUser = null;
            this.updateNavigation(false);
            this.showAlert('Logged out successfully', 'info');
            window.location.href = 'index.html';
        } catch (error) {
            console.error('Logout error:', error);
        }
    }

    // Course Methods
    async loadCourses() {
        try {
            const response = await this.makeRequest('courses.php', 'GET');
            return response.courses || [];
        } catch (error) {
            console.error('Error loading courses:', error);
            return [];
        }
    }

    async getCourseDetail(courseId) {
        try {
            const response = await this.makeRequest(`courses.php?action=detail&id=${courseId}`, 'GET');
            return response.course;
        } catch (error) {
            throw new Error(error.message || 'Failed to load course details');
        }
    }

    async enrollInCourse(courseId) {
        try {
            const response = await this.makeRequest('courses.php?action=enroll', 'POST', {
                course_id: courseId
            });
            
            if (response.success) {
                this.showAlert('Successfully enrolled in course!', 'success');
                return true;
            }
        } catch (error) {
            this.showAlert(error.message || 'Enrollment failed', 'danger');
            return false;
        }
    }

    async createCourse(courseData) {
        try {
            const response = await this.makeRequest('courses.php?action=create', 'POST', courseData);
            
            if (response.success) {
                this.showAlert('Course created successfully!', 'success');
                return response.course_id;
            }
        } catch (error) {
            this.showAlert(error.message || 'Failed to create course', 'danger');
            return false;
        }
    }

    async getMyCourses() {
        try {
            if (this.currentUser.role === 'instructor') {
                const response = await this.makeRequest('courses.php?action=my-courses', 'GET');
                return response.courses || [];
            } else {
                const response = await this.makeRequest('courses.php?action=my-enrollments', 'GET');
                return response.enrollments || [];
            }
        } catch (error) {
            console.error('Error loading my courses:', error);
            return [];
        }
    }

    // Quiz Methods
    async getQuizDetail(quizId) {
        try {
            const response = await this.makeRequest(`quizzes.php?action=detail&id=${quizId}`, 'GET');
            return response.quiz;
        } catch (error) {
            throw new Error(error.message || 'Failed to load quiz');
        }
    }

    async submitQuiz(quizId, answers, timeTaken) {
        try {
            const response = await this.makeRequest('quizzes.php?action=submit', 'POST', {
                quiz_id: quizId,
                answers: answers,
                time_taken_minutes: timeTaken
            });
            
            if (response.success) {
                return response;
            }
        } catch (error) {
            throw new Error(error.message || 'Failed to submit quiz');
        }
    }

    async createQuiz(quizData) {
        try {
            const response = await this.makeRequest('quizzes.php?action=create', 'POST', quizData);
            
            if (response.success) {
                this.showAlert('Quiz created successfully!', 'success');
                return response.quiz_id;
            }
        } catch (error) {
            this.showAlert(error.message || 'Failed to create quiz', 'danger');
            return false;
        }
    }

    // Lesson Methods
    async getLessonDetail(lessonId) {
        try {
            const response = await this.makeRequest(`lessons.php?action=detail&id=${lessonId}`, 'GET');
            return response.lesson;
        } catch (error) {
            throw new Error(error.message || 'Failed to load lesson');
        }
    }

    async markLessonComplete(lessonId, watchTime = 0) {
        try {
            const response = await this.makeRequest('lessons.php?action=complete', 'POST', {
                lesson_id: lessonId,
                watch_time_seconds: watchTime
            });
            
            if (response.success) {
                this.showAlert('Lesson marked as complete!', 'success');
                return true;
            }
        } catch (error) {
            this.showAlert(error.message || 'Failed to mark lesson complete', 'danger');
            return false;
        }
    }

    async updateLessonProgress(lessonId, watchTime) {
        try {
            await this.makeRequest('lessons.php?action=update-progress', 'POST', {
                lesson_id: lessonId,
                watch_time_seconds: watchTime
            });
        } catch (error) {
            console.error('Failed to update progress:', error);
        }
    }

    async createLesson(lessonData) {
        try {
            const response = await this.makeRequest('lessons.php?action=create', 'POST', lessonData);
            
            if (response.success) {
                this.showAlert('Lesson created successfully!', 'success');
                return response.lesson_id;
            }
        } catch (error) {
            this.showAlert(error.message || 'Failed to create lesson', 'danger');
            return false;
        }
    }

    // Certificate Methods
    async generateCertificate(courseId) {
        try {
            const response = await this.makeRequest('certificates.php', 'POST', {
                course_id: courseId
            });
            
            if (response.success) {
                this.showAlert('Certificate generated successfully!', 'success');
                return response.certificate_code;
            }
        } catch (error) {
            this.showAlert(error.message || 'Failed to generate certificate', 'danger');
            return false;
        }
    }

    async getMyCertificates() {
        try {
            const response = await this.makeRequest('certificates.php?action=my-certificates', 'GET');
            return response.certificates || [];
        } catch (error) {
            console.error('Error loading certificates:', error);
            return [];
        }
    }

    async downloadCertificate(certificateCode) {
        try {
            const response = await this.makeRequest(`certificates.php?action=download&code=${certificateCode}`, 'GET');
            return response;
        } catch (error) {
            throw new Error(error.message || 'Failed to download certificate');
        }
    }

    // Utility Methods
    async makeRequest(endpoint, method = 'GET', data = null) {
        const url = `${this.baseURL}/${endpoint}`;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include'
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok || result.error) {
            throw new Error(result.error || 'Request failed');
        }

        return result;
    }

    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Insert at the top of the main content
        const main = document.querySelector('main') || document.querySelector('.container');
        if (main) {
            main.insertBefore(alertDiv, main.firstChild);
        }

        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    updateNavigation(isLoggedIn) {
        const authLinks = document.querySelectorAll('.auth-required');
        const guestLinks = document.querySelectorAll('.guest-only');
        const userInfo = document.querySelector('#user-info');
        const loginBtn = document.querySelector('#login-btn');
        const logoutBtn = document.querySelector('#logout-btn');

        authLinks.forEach(link => {
            link.style.display = isLoggedIn ? 'block' : 'none';
        });

        guestLinks.forEach(link => {
            link.style.display = isLoggedIn ? 'none' : 'block';
        });

        if (userInfo) {
            userInfo.style.display = isLoggedIn ? 'block' : 'none';
        }
        
        if (loginBtn) {
            loginBtn.style.display = isLoggedIn ? 'none' : 'block';
        }
        
        if (logoutBtn) {
            logoutBtn.style.display = isLoggedIn ? 'block' : 'none';
        }
    }

    updateUserInfo() {
        if (this.currentUser) {
            const userNameElements = document.querySelectorAll('.user-name');
            const userRoleElements = document.querySelectorAll('.user-role');

            userNameElements.forEach(el => {
                el.textContent = this.currentUser.full_name;
            });

            userRoleElements.forEach(el => {
                el.textContent = this.currentUser.role;
            });
        }
    }

    attachEventListeners() {
        // Login form
        const loginForm = document.querySelector('#login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(loginForm);
                const success = await this.login(
                    formData.get('username'),
                    formData.get('password')
                );
                if (success) {
                    // Redirect based on role
                    if (this.currentUser.role === 'instructor') {
                        window.location.href = 'instructor-dashboard.html';
                    } else {
                        window.location.href = 'student-dashboard.html';
                    }
                }
            });
        }

        // Register form
        const registerForm = document.querySelector('#register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(registerForm);
                const success = await this.register({
                    username: formData.get('username'),
                    email: formData.get('email'),
                    password: formData.get('password'),
                    full_name: formData.get('full_name'),
                    role: formData.get('role')
                });
                if (success) {
                    registerForm.reset();
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 2000);
                }
            });
        }

        // Logout button
        const logoutBtn = document.querySelector('#logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }

        // Course enrollment buttons
        document.addEventListener('click', async (e) => {
            if (e.target.classList.contains('enroll-btn')) {
                const courseId = e.target.dataset.courseId;
                if (courseId) {
                    await this.enrollInCourse(courseId);
                    location.reload(); // Refresh to show updated status
                }
            }

            if (e.target.classList.contains('complete-lesson-btn')) {
                const lessonId = e.target.dataset.lessonId;
                if (lessonId) {
                    await this.markLessonComplete(lessonId);
                    e.target.textContent = 'Completed';
                    e.target.disabled = true;
                    e.target.classList.remove('btn-primary');
                    e.target.classList.add('btn-success');
                }
            }

            if (e.target.classList.contains('generate-cert-btn')) {
                const courseId = e.target.dataset.courseId;
                if (courseId) {
                    const certCode = await this.generateCertificate(courseId);
                    if (certCode) {
                        location.reload(); // Refresh to show certificate
                    }
                }
            }
        });
    }

    loadInitialData() {
        const page = this.getCurrentPage();
        
        switch (page) {
            case 'index':
                this.loadHomePage();
                break;
            case 'courses':
                this.loadCoursesPage();
                break;
            case 'course-detail':
                this.loadCourseDetailPage();
                break;
            case 'lesson':
                this.loadLessonPage();
                break;
            case 'quiz':
                this.loadQuizPage();
                break;
            case 'student-dashboard':
                this.loadStudentDashboard();
                break;
            case 'instructor-dashboard':
                this.loadInstructorDashboard();
                break;
            case 'certificates':
                this.loadCertificatesPage();
                break;
        }
    }

    getCurrentPage() {
        const path = window.location.pathname;
        const filename = path.split('/').pop().split('.')[0];
        return filename || 'index';
    }

    async loadHomePage() {
        const coursesContainer = document.querySelector('#featured-courses');
        if (coursesContainer) {
            const courses = await this.loadCourses();
            const featuredCourses = courses.slice(0, 6); // Show first 6 courses
            this.renderCourseCards(featuredCourses, coursesContainer);
        }
    }

    async loadCoursesPage() {
        const coursesContainer = document.querySelector('#courses-container');
        if (coursesContainer) {
            const courses = await this.loadCourses();
            this.renderCourseCards(courses, coursesContainer);
        }
    }

    async loadCourseDetailPage() {
        const courseId = new URLSearchParams(window.location.search).get('id');
        if (courseId) {
            try {
                const course = await this.getCourseDetail(courseId);
                this.renderCourseDetail(course);
            } catch (error) {
                this.showAlert(error.message, 'danger');
            }
        }
    }

    async loadStudentDashboard() {
        if (this.currentUser && this.currentUser.role === 'student') {
            const enrollments = await this.getMyCourses();
            this.renderStudentDashboard(enrollments);
        }
    }

    async loadInstructorDashboard() {
        if (this.currentUser && this.currentUser.role === 'instructor') {
            const courses = await this.getMyCourses();
            this.renderInstructorDashboard(courses);
        }
    }

    async loadCertificatesPage() {
        if (this.currentUser && this.currentUser.role === 'student') {
            const certificates = await this.getMyCertificates();
            this.renderCertificates(certificates);
        }
    }

    renderCourseCards(courses, container) {
        container.innerHTML = courses.map(course => `
            <div class="col-md-4 mb-4">
                <div class="card">
                    ${course.thumbnail ? `<img src="${course.thumbnail}" class="card-img-top" alt="${course.title}">` : ''}
                    <div class="card-body">
                        <h5 class="card-title">${course.title}</h5>
                        <p class="card-text">${course.description}</p>
                        <div class="course-meta">
                            <span class="course-level level-${course.level}">${course.level}</span>
                            <small class="text-muted">By ${course.instructor_name}</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">${course.enrollment_count || 0} students</small>
                            <a href="course-detail.html?id=${course.id}" class="btn btn-primary">View Course</a>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    renderCourseDetail(course) {
        const container = document.querySelector('#course-detail');
        if (container) {
            container.innerHTML = `
                <div class="row">
                    <div class="col-lg-8">
                        <h1>${course.title}</h1>
                        <p class="lead">${course.description}</p>
                        
                        <div class="course-meta mb-4">
                            <span class="course-level level-${course.level}">${course.level}</span>
                            <span class="ms-3">Instructor: ${course.instructor_name}</span>
                            <span class="ms-3">Duration: ${course.duration_hours || 0} hours</span>
                        </div>

                        ${course.is_enrolled ? 
                            `<div class="progress mb-3">
                                <div class="progress-bar" style="width: ${course.progress}%">${course.progress}%</div>
                            </div>` : ''
                        }

                        <h3>Lessons</h3>
                        <div class="lessons-list">
                            ${course.lessons.map((lesson, index) => `
                                <div class="course-item">
                                    <h5>${index + 1}. ${lesson.title}</h5>
                                    <p>${lesson.description}</p>
                                    <small class="text-muted">${lesson.duration_minutes} minutes</small>
                                    ${course.is_enrolled || lesson.is_free ? 
                                        `<a href="lesson.html?id=${lesson.id}" class="btn btn-sm btn-outline-primary">Watch</a>` :
                                        `<span class="badge bg-warning">Premium</span>`
                                    }
                                </div>
                            `).join('')}
                        </div>

                        ${course.quizzes.length > 0 ? `
                            <h3>Quizzes</h3>
                            <div class="quizzes-list">
                                ${course.quizzes.map(quiz => `
                                    <div class="course-item">
                                        <h5>${quiz.title}</h5>
                                        <p>${quiz.description}</p>
                                        ${course.is_enrolled ? 
                                            `<a href="quiz.html?id=${quiz.id}" class="btn btn-sm btn-outline-primary">Take Quiz</a>` :
                                            `<span class="badge bg-warning">Enrollment Required</span>`
                                        }
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body text-center">
                                ${course.is_enrolled ? 
                                    `<h4 class="text-success">Enrolled</h4>
                                     <p>Progress: ${course.progress}%</p>
                                     ${course.progress >= 100 ? 
                                        `<button class="btn btn-success generate-cert-btn" data-course-id="${course.id}">Get Certificate</button>` : ''
                                     }` :
                                    `<h4>$${course.price || 'Free'}</h4>
                                     <button class="btn btn-primary enroll-btn" data-course-id="${course.id}">Enroll Now</button>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    renderStudentDashboard(enrollments) {
        const container = document.querySelector('#student-dashboard');
        if (container) {
            container.innerHTML = `
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-number">${enrollments.length}</div>
                        <div class="stat-label">Enrolled Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">${enrollments.filter(e => e.completed_at).length}</div>
                        <div class="stat-label">Completed Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">${Math.round(enrollments.reduce((sum, e) => sum + parseFloat(e.progress_percentage), 0) / enrollments.length) || 0}%</div>
                        <div class="stat-label">Average Progress</div>
                    </div>
                </div>

                <h3>My Courses</h3>
                <div class="row">
                    ${enrollments.map(enrollment => `
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">${enrollment.title}</h5>
                                    <p class="card-text">${enrollment.description}</p>
                                    <div class="progress mb-3">
                                        <div class="progress-bar" style="width: ${enrollment.progress_percentage}%">
                                            ${enrollment.progress_percentage}%
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Instructor: ${enrollment.instructor_name}</small>
                                        <a href="course-detail.html?id=${enrollment.id}" class="btn btn-sm btn-primary">Continue</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
    }

    renderInstructorDashboard(courses) {
        const container = document.querySelector('#instructor-dashboard');
        if (container) {
            container.innerHTML = `
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-number">${courses.length}</div>
                        <div class="stat-label">My Courses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">${courses.reduce((sum, c) => sum + (c.enrollment_count || 0), 0)}</div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">${courses.filter(c => c.status === 'published').length}</div>
                        <div class="stat-label">Published Courses</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>My Courses</h3>
                    <a href="create-course.html" class="btn btn-primary">Create New Course</a>
                </div>

                <div class="row">
                    ${courses.map(course => `
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">${course.title}</h5>
                                    <p class="card-text">${course.description}</p>
                                    <div class="course-meta">
                                        <span class="course-level level-${course.level}">${course.level}</span>
                                        <span class="badge bg-${course.status === 'published' ? 'success' : 'warning'}">${course.status}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <small class="text-muted">${course.enrollment_count || 0} students • ${course.lesson_count || 0} lessons</small>
                                        <div>
                                            <a href="edit-course.html?id=${course.id}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="course-detail.html?id=${course.id}" class="btn btn-sm btn-primary">View</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
    }

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new ELearningApp();
});

// Video player functionality
class VideoPlayer {
    constructor(videoElement, lessonId) {
        this.video = videoElement;
        this.lessonId = lessonId;
        this.progressUpdateInterval = 10; // seconds
        this.lastProgressUpdate = 0;
        
        this.init();
    }

    init() {
        this.video.addEventListener('timeupdate', () => {
            const currentTime = Math.floor(this.video.currentTime);
            
            // Update progress every 10 seconds
            if (currentTime - this.lastProgressUpdate >= this.progressUpdateInterval) {
                this.updateProgress(currentTime);
                this.lastProgressUpdate = currentTime;
            }
        });

        this.video.addEventListener('ended', () => {
            this.markAsComplete();
        });
    }

    async updateProgress(watchTime) {
        if (window.app && this.lessonId) {
            await window.app.updateLessonProgress(this.lessonId, watchTime);
        }
    }

    async markAsComplete() {
        if (window.app && this.lessonId) {
            await window.app.markLessonComplete(this.lessonId, Math.floor(this.video.currentTime));
        }
    }
}

// Quiz functionality
class Quiz {
    constructor(container, quizData) {
        this.container = container;
        this.quiz = quizData;
        this.answers = {};
        this.startTime = Date.now();
        
        this.render();
        this.attachEventListeners();
    }

    render() {
        this.container.innerHTML = `
            <div class="quiz-container">
                <div class="quiz-header">
                    <h2>${this.quiz.title}</h2>
                    <p>${this.quiz.description}</p>
                    <div class="quiz-info">
                        <span>Time Limit: ${this.quiz.time_limit_minutes} minutes</span>
                        <span class="ms-3">Passing Score: ${this.quiz.passing_score}%</span>
                        <span class="ms-3">Questions: ${this.quiz.questions.length}</span>
                    </div>
                </div>

                <div class="questions-container">
                    ${this.quiz.questions.map((question, index) => `
                        <div class="question" data-question-id="${question.id}">
                            <div class="question-title">
                                Question ${index + 1}: ${question.question}
                            </div>
                            <div class="options">
                                <label class="option">
                                    <input type="radio" name="question_${question.id}" value="a">
                                    A) ${question.option_a}
                                </label>
                                <label class="option">
                                    <input type="radio" name="question_${question.id}" value="b">
                                    B) ${question.option_b}
                                </label>
                                <label class="option">
                                    <input type="radio" name="question_${question.id}" value="c">
                                    C) ${question.option_c}
                                </label>
                                <label class="option">
                                    <input type="radio" name="question_${question.id}" value="d">
                                    D) ${question.option_d}
                                </label>
                            </div>
                        </div>
                    `).join('')}
                </div>

                <div class="quiz-footer">
                    <button class="btn btn-primary" id="submit-quiz">Submit Quiz</button>
                </div>
            </div>
        `;
    }

    attachEventListeners() {
        // Track answer selections
        this.container.addEventListener('change', (e) => {
            if (e.target.type === 'radio') {
                const questionId = e.target.name.replace('question_', '');
                this.answers[questionId] = e.target.value;
                
                // Update option styling
                const questionDiv = e.target.closest('.question');
                questionDiv.querySelectorAll('.option').forEach(opt => opt.classList.remove('selected'));
                e.target.closest('.option').classList.add('selected');
            }
        });

        // Submit quiz
        document.getElementById('submit-quiz').addEventListener('click', () => {
            this.submit();
        });

        // Timer
        if (this.quiz.time_limit_minutes > 0) {
            this.startTimer();
        }
    }

    startTimer() {
        const timeLimit = this.quiz.time_limit_minutes * 60 * 1000; // Convert to milliseconds
        const timerElement = document.createElement('div');
        timerElement.className = 'quiz-timer';
        timerElement.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #f8f9fa; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);';
        document.body.appendChild(timerElement);

        const updateTimer = () => {
            const elapsed = Date.now() - this.startTime;
            const remaining = Math.max(0, timeLimit - elapsed);
            
            if (remaining === 0) {
                this.submit();
                return;
            }
            
            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);
            timerElement.textContent = `Time Remaining: ${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            setTimeout(updateTimer, 1000);
        };
        
        updateTimer();
    }

    async submit() {
        const timeTaken = Math.floor((Date.now() - this.startTime) / 60000); // Convert to minutes
        
        try {
            const result = await window.app.submitQuiz(this.quiz.id, this.answers, timeTaken);
            this.showResults(result);
        } catch (error) {
            window.app.showAlert(error.message, 'danger');
        }
    }

    showResults(result) {
        this.container.innerHTML = `
            <div class="quiz-results text-center">
                <h2>Quiz Results</h2>
                <div class="result-score">
                    <div class="score ${result.passed ? 'text-success' : 'text-danger'}">
                        ${result.score}%
                    </div>
                    <p>You answered ${result.correct_answers} out of ${result.total_questions} questions correctly.</p>
                </div>
                
                <div class="result-status">
                    ${result.passed ? 
                        '<div class="alert alert-success"><strong>Congratulations!</strong> You passed the quiz!</div>' :
                        '<div class="alert alert-danger"><strong>Sorry,</strong> you did not meet the passing score of ' + result.passing_score + '%.</div>'
                    }
                </div>
                
                <div class="result-actions">
                    <a href="course-detail.html?id=${this.quiz.course_id}" class="btn btn-primary">Back to Course</a>
                    ${!result.passed && this.quiz.attempts_allowed > 1 ? 
                        '<button class="btn btn-outline-primary" onclick="location.reload()">Try Again</button>' : ''
                    }
                </div>
            </div>
        `;
    }
}