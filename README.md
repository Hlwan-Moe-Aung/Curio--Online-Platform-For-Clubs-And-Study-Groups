# Curio - Online Platform for Clubs and Study Groups

Curio is a role-based, web-based community management platform designed to eliminate the manual overhead of organizing academic clubs and peer study groups. It provides dedicated management tools, approval workflows, and interactive discussion spaces to help students collaborate and learn effectively.

---

## 💡 Key Features & Architecture

The system enforces a **4-tier role-based access model**:

* **Administrators:** Platform-level moderation, reviewing report queues for inappropriate content, and final approval for creating/disbanding clubs or study groups.
* **Group Leaders:** Manage membership applications, approve/reject private post submissions, assign new leaders, and publish public announcements.
* **Members:** Participate in discussions, comment on posts, request private post creation within specific groups, and flag platform violations.
* **General Users:** Publicly browse available clubs, study groups, and discover open communities.

---

## 🛠️ Tech Stack

* **Backend:** PHP, Apache Server
* **Database:** MySQL / PostgreSQL
* **Frontend:** Responsive Web Interface (HTML5, CSS3, JavaScript)
* **Containerization:** Docker & Docker Compose
* **Protocol & Security:** HTTPS, Cryptographic Password Hashing

---

## 🚀 Quick Start with Docker

You can run the entire platform locally using Docker without needing to manually configure PHP or MySQL server environments.

### Prerequisites
* [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed on your machine.

### Installation Steps

1. **Clone the repository:**
   ```bash
   git clone [https://github.com/your-username/curio-platform.git](https://github.com/your-username/curio-platform.git)
   cd curio-platform

2. **Spin up the environment:**
   Bash
       docker-compose up -d --build

3. **Access the Application:**
   Open your browser and navigate to http://localhost:8080 (or your configured port).

4. **Stop the containers:**
   Bash
       docker-compose down

