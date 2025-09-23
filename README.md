# Brightway LMS - Library Management System

Brightway LMS is a comprehensive Library Management System designed for schools and educational institutions. It provides a user-friendly interface for both librarians and students to manage library resources efficiently.

## Features

*   **User Roles:** Separate interfaces for Librarians and Students.
*   **Librarian Dashboard:**
    *   Manage books (add, edit, delete).
    *   Manage students (add, edit, delete).
    *   Manage book checkouts and returns.
    *   View activity logs.
    *   Generate reports in PDF format.
*   **Student Dashboard:**
    *   Browse the library catalog.
    *   View their checked-out books and due dates.
    *   Check their profile.
*   **Secure Login:** User authentication with password hashing.
*   **Activity Logging:** Tracks important user actions for security and auditing.

## Technologies Used

*   **Backend:** PHP
*   **Database:** MySQL
*   **PDF Generation:** FPDF library
*   **Frontend:** HTML, CSS, JavaScript

## Setup and Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/Library-Management-System.git
    cd Library-Management-System
    ```

2.  **Database Setup:**
    *   Make sure you have a MySQL server running.
    *   Open your MySQL client (e.g., phpMyAdmin) and create a new database named `school_lms`.
    *   You will need to create the necessary tables. A `database.sql` file is not provided, so you will need to create the tables manually. Here is a possible schema based on the code:

    **`users` table:**
    ```sql
    CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `full_name` varchar(255) NOT NULL,
      `username` varchar(50) NOT NULL UNIQUE,
      `password` varchar(255) NOT NULL,
      `role` enum('librarian','student') NOT NULL,
      PRIMARY KEY (`id`)
    );
    ```

    **`books` table:**
    ```sql
    CREATE TABLE `books` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(255) NOT NULL,
      `author` varchar(255) NOT NULL,
      `isbn` varchar(20) DEFAULT NULL,
      `quantity` int(11) NOT NULL,
      `available` int(11) NOT NULL,
      `cover_image` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`)
    );
    ```

    **`students` table:**
    ```sql
    CREATE TABLE `students` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `full_name` varchar(255) NOT NULL,
      `student_id` varchar(50) NOT NULL UNIQUE,
      `email` varchar(100) DEFAULT NULL,
      PRIMARY KEY (`id`)
    );
    ```

    **`checkouts` table:**
    ```sql
    CREATE TABLE `checkouts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `book_id` int(11) NOT NULL,
      `student_id` int(11) NOT NULL,
      `checkout_date` date NOT NULL,
      `due_date` date NOT NULL,
      `return_date` date DEFAULT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`book_id`) REFERENCES `books`(`id`),
      FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)
    );
    ```

    **`activity_log` table:**
    ```sql
    CREATE TABLE `activity_log` (
      `log_id` int(11) NOT NULL AUTO_INCREMENT,
      `id` int(11) DEFAULT NULL,
      `activity_type` varchar(50) NOT NULL,
      `description` text,
      `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`log_id`)
    );
    ```

3.  **Configure Database Connection:**
    *   The database connection settings are in `db_config.php`. By default, the configuration is:
        *   Server: `localhost`
        *   Username: `root`
        *   Password: (empty)
        *   Database Name: `school_lms`
    *   If your database credentials are different, update them in `db_config.php`.

4.  **Web Server:**
    *   Place the project files in your web server's root directory (e.g., `htdocs` for XAMPP, `www` for WAMP).
    *   Start your Apache and MySQL services.

## Usage

1.  **Access the application:**
    *   Open your web browser and navigate to `http://localhost/Library-Management-System/home.php`.

2.  **Login:**
    *   **Librarian:** You will need to create a librarian user in the `users` table manually.
        *   Example: `INSERT INTO users (full_name, username, password, role) VALUES ('Admin User', 'librarian', PASSWORD_HASH('password123', PASSWORD_BCRYPT), 'librarian');`
    *   **Student:** Students can be added by the librarian. You can also add one manually for testing.
        *   Example: `INSERT INTO users (full_name, username, password, role) VALUES ('Test Student', 'student', PASSWORD_HASH('password123', PASSWORD_BCRYPT), 'student');`

## Screenshots
* Librarian Interfaces
<img width="797" height="368" alt="image" src="https://github.com/user-attachments/assets/d578824c-e622-4dc6-b6dd-5c9d4cdf6436" />
<img width="798" height="373" alt="image" src="https://github.com/user-attachments/assets/a7d1fd91-dcf9-496a-b5db-e1b75cda91c0" />
<img width="805" height="374" alt="image" src="https://github.com/user-attachments/assets/93937a4b-2b1c-4334-b74f-cd4a8d43a8f9" />
<img width="798" height="371" alt="image" src="https://github.com/user-attachments/assets/8dbff5a0-cbf4-4423-959c-74728205c06d" />
<img width="872" height="404" alt="image" src="https://github.com/user-attachments/assets/cddbb6f9-34a5-4708-a717-91ae130773c1" />
<img width="867" height="404" alt="image" src="https://github.com/user-attachments/assets/35f41919-b5ee-4ba6-a505-4dab892f03b8" />
<img width="872" height="404" alt="image" src="https://github.com/user-attachments/assets/8afbb8ed-060b-4d3e-9c7a-4ac56e218b5a" />
<img width="869" height="402" alt="image" src="https://github.com/user-attachments/assets/85c1582a-d96a-45d5-8b87-dffe04364825" />
<img width="852" height="391" alt="image" src="https://github.com/user-attachments/assets/f2acc8d7-0bfd-47eb-a4bb-cb21dd7a6da5" />

* Student Interfaces
<img width="852" height="391" alt="image" src="https://github.com/user-attachments/assets/0fb33d63-5342-4550-8a84-55062745747f" />
<img width="852" height="391" alt="image" src="https://github.com/user-attachments/assets/94b4f415-0e54-45a3-8f15-8e7ccc5fd416" />
<img width="852" height="394" alt="image" src="https://github.com/user-attachments/assets/43e8ad2f-76b8-4d3c-82e0-b4fdd2e4ff30" />
<img width="842" height="397" alt="image" src="https://github.com/user-attachments/assets/62034f0e-cb8a-4149-9c58-eb9b6e46216f" />
<img width="857" height="396" alt="image" src="https://github.com/user-attachments/assets/cb958d4b-1f52-4946-8c03-b2f82f5944d8" />
<img width="974" height="450" alt="image" src="https://github.com/user-attachments/assets/d97bc36b-83a8-40b1-956f-cc658159044d" />
---

*This README was generated by an AI assistant.*
