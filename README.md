# Password Cracker

This project is a web application designed for cracking MD5-hashed passwords using various attack types. The application comprises a web interface that allows launching cracking tasks and an asynchronous PHP worker that processes these tasks in the background.

## Project Description

The project has been developed to demonstrate concepts of asynchronous task processing, frontend-backend interaction, and basic password cracking techniques. It simulates the process of recovering passwords from a user database using several predefined attack strategies.

## Features

* **Various Attack Types:** Support for different cracking methods, including:
    * Easy: 5-digit numbers (easy_numbers)
    * Medium: Dictionary words (medium_dict)
    * Medium: 3 uppercase letters + 1 number (medium_alpha_num)
    * Hard: 6-character mixed set (letters and numbers) (hard_mixed)
* **Asynchronous Task Processing:** Cracking tasks are executed by a separate PHP worker, preventing web server blocking.
* **Task Monitoring:** The web interface displays the current status, progress, and found passwords for each initiated task.
* **Task Management:** Ability to cancel active tasks.
* **Automatic Retries:** The worker attempts to restart failed tasks up to a maximum number of attempts.

## Technologies Used

* **Backend:** PHP (>= 8.1)
* **Database:** MySQL / MariaDB
* **Frontend:** HTML, CSS, Bootstrap 5, Vue.js 3
* **Hashing:** MD5 with salt (`ThisIs-A-Salt123`)

## Getting Started

To deploy and run the project, follow these steps.

### Prerequisites

* Docker and Docker Compose (recommended for straightforward MySQL installation)

### Installation and Deployment

1.  **Clone the repository:**
    ```bash
    git clone [https://github.com/AbadonnaAbbys/PasswordCrackerTest.git](https://github.com/AbadonnaAbbys/PasswordCrackerTest.git)
    cd PasswordCrackerTest
    ```

2.  **Prepare the dictionary file (crucial for the `medium_dict` attack):**

    **This repository DOES NOT CONTAIN the dictionary file** (`dictionary.txt`) due to its large size.

    For the `Medium: Dictionary words` (medium_dict) attacks to function correctly, you need to:
    * Ensure you have an archive containing the `dictionary.txt` file (or a similarly structured dictionary file you wish to use).
    * **Extract this archive so that the `dictionary.txt` file is located in the `src/` directory.**
        * The full path to the file should look something like: `/path/to/your/project/src/dictionary.txt`.

3.  **Use Makefile commands to manage the project:**
    From the root directory of the `PasswordCrackerTest` project, execute:
    ```bash
    make setup
    ```

    The web application interface will be available at: `http://localhost:8000`

## Usage

1.  Open `http://localhost:8000` in your browser.
2.  Select the desired "Attack Type" from the dropdown list.
3.  Click the "Start Cracking" button.
4.  A new task will appear in the "Task Status" section, where you can monitor its progress and status in real-time. Found passwords will be displayed upon task completion.
5.  You can cancel tasks that are in "Pending", "Running", or "Failed (retry)" status by clicking the "Cancel" button.

## Project Structure

* `docker/`: Files required for building the project.
* `src/`: Main PHP application code, including cracking logic, worker, and helper classes (AttackType, JobStatus, PasswordCracker, config).
* `public/`: Files accessible via the web server. Contains the main HTML file with the UI (`index.html`) and API endpoints (`index.php`, `jobs.php`, `cancel.php`).
* `docker-compose.yml`: Docker Compose configuration for running MySQL.