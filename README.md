# HSMS Ethiopia - High School Management System

HSMS Ethiopia is a comprehensive, modular, and modern web-based application designed to manage the complex needs of high schools in Ethiopia. It provides a centralized platform for administrators, teachers, students, and parents to manage academic and administrative tasks efficiently.

## ✨ Features

- **User Management**: Role-based access control with pre-defined roles (Admin, Registrar, Teacher, Student).
- **Student Information System**: Complete lifecycle management of students from admission to graduation.
- **Teacher Management**: Manage teacher profiles, specializations, and assignments.
- **Attendance Tracking**: Record and monitor student attendance, with support for both manual and **biometric device integration**.
- **Assessments & Grading**: Manage quizzes, tests, and exams, and automatically calculate student grades.
- **Financial Management**: Handle fee structures, generate invoices, and track payments.
- **Multilingual Support**: Built-in support for English and Amharic, with an easily extendable language system.
- **RESTful API**: A secure, token-based (JWT) API for all major functionalities, enabling integration with mobile apps and other systems.
- **Payment Gateway Ready**: Modular integration for Ethiopian payment gateways like **Telebirr**, **CBE**, and **CBE Birr**.
- **SMS Gateway Ready**: Integrated with a modular service ready for **EthioTelecom's** SMS API to send notifications.
- **Reporting System**: Generate and export reports for attendance, academic performance, and financials.
- **Internal Communication**: Built-in messaging and notification system for seamless communication between users.

## 🛠️ Tech Stack

- **Backend**: PHP 8+
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (with a modern, responsive design)
- **API**: RESTful architecture with JSON Web Tokens (JWT) for authentication.
- **PHP Dependencies**: `firebase/php-jwt` for token management (via Composer).

## 🚀 Getting Started

Follow these instructions to get a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

- A local web server environment (e.g., XAMPP, WAMP, MAMP).
- PHP 8.0 or higher.
- MySQL or MariaDB database server.
- [Composer](https://getcomposer.org/) for managing PHP dependencies.

### Installation

1.  **Clone the repository:**
    ```sh
    git clone [your-repository-url]
    cd HSMS
    ```

2.  **Install PHP Dependencies:**
    Run Composer to install the required libraries (like `php-jwt`).
    ```sh
    composer install
    ```

3.  **Database Setup:**
    - Create a new database in your MySQL server (e.g., `hsms_ethiopia`).
    - Import the database schema and sample data from the `database/hsms_database.sql` file.
      ```sh
      mysql -u your_username -p hsms_ethiopia < database/hsms_database.sql
      ```

4.  **Configure the Application:**
    - Open the `config/database.php` file.
    - Update the database credentials (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) to match your local setup.

5.  **Configure API Security:**
    - Open `classes/APIAuth.php`.
    - Change the `$secret_key` variable to a strong, unique secret of your own.
      ```php
      private static $secret_key = 'Your_Very_Strong_And_Secret_Key_Here';
      ```

6.  **Run the Server:**
    - Point your local server's document root to the project directory.
    - Open your web browser and navigate to `http://localhost/` (or your configured local domain).

##  usage

Once the application is running, you can use the following default credentials to log in:

-   **Username**: `admin`
-   **Password**: `admin123`

From the dashboard, you can navigate through the different modules to manage students, teachers, and other system settings.

### Biometric Device Configuration

To connect a physical biometric device:

1.  Navigate to `Admin > Biometric Devices` in the web interface.
2.  Add a new device to generate an API Key.
3.  Configure your physical device to send a `POST` request to the following endpoint upon a successful scan:
    -   **URL**: `http://[your-server-address]/api/biometric/log.php`
    -   **Headers**:
        -   `Content-Type: application/json`
        -   `X-API-Key: [the-generated-api-key]`
    -   **Body**:
        ```json
        {
          "biometric_id": "12345",
          "timestamp": 1678886400
        }
        ```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
