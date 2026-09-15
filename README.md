# 🏫 Campus Issue Tracker (FixMyCampus)

[![Live Web App](https://img.shields.io/badge/🚀_Live_Demo-campus--issue--tracker-00D4FF?style=for-the-badge&logo=render&logoColor=white)](https://campus-issue-tracker-main.onrender.com)
[![Hosted on Render](https://img.shields.io/badge/Hosted_on-Render-46E3B7?style=for-the-badge&logo=render&logoColor=black)](https://campus-issue-tracker-main.onrender.com)
[![Cloud Storage](https://img.shields.io/badge/Cloud_Media-Cloudinary-3448C5?style=for-the-badge&logo=cloudinary&logoColor=white)](https://cloudinary.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Gemini AI](https://img.shields.io/badge/Google_Gemini-1.5_Flash-4285F4?style=for-the-badge&logo=google&logoColor=white)](https://ai.google.dev/)
[![License](https://img.shields.io/badge/License-Proprietary_%2F_All_Rights_Reserved-red?style=for-the-badge)](#-license--proprietary-notice)

> **A centralized, cloud-hosted platform for reporting, managing, and resolving campus facility issues.** Fully deployed on **Render** with **Cloudinary** media storage and **Google Gemini AI** integration.

---

## 🌐 Live Website Access

You can directly explore the live production platform without any local installation:

<div align="center">

### 🔗 **[Click Here to Launch FixMyCampus Live](https://campus-issue-tracker-main.onrender.com)**
`https://campus-issue-tracker-main.onrender.com`

</div>

---

### 🔑 Instant Demo Login Credentials

The live deployment on Render comes pre-loaded with active accounts for testing all user roles:

| Role | Email Address | Password | Portal Scope |
| :--- | :--- | :--- | :--- |
| **🛡️ Administrator** | `admin@fixmycampus.com` | `password123` | Master triage, analytics, staff assignment & user control |
| **🎓 Student (Reporter)** | `student@fixmycampus.com` | `password123` | Submit issues with photos, AI assistant, track live status |
| **👩‍🏫 Staff (Reporter)** | `staff@fixmycampus.com` | `password123` | Departmental facility reports & status history |
| **🔧 Maintenance Staff** | `maintenance@fixmycampus.com` | `password123` | View assigned work orders, update progress, log resolutions |
| **💻 IT Technician** | `tech@fixmycampus.com` | `password123` | IT & network tickets, add technical remarks & close |

---

## 📌 Project Overview

**Campus Issue Tracker (FixMyCampus)** is a web-based facility management application designed to eliminate paper logs, informal messages, and untracked complaints across educational institutions. 

The application is deployed on **Render Cloud** and utilizes **Cloudinary** for image storage and asset optimization, providing instant access from any device.

### 🌟 Key Highlights:
* ☁️ **Hosted on Render:** Containerized and continuously deployed on Render's cloud infrastructure for high availability.
* 📸 **Cloudinary Cloud Storage:** High-speed cloud photo storage and CDN delivery for all uploaded issue evidence.
* 🤖 **AI-Powered Reporting:** Integrated conversational AI assistant and multilingual voice translation to auto-fill ticket details.
* 👥 **Role-Based Access Control (RBAC):** Customized dashboards for Students, Faculty, Maintenance Teams, and Administrators.
* ⚡ **Live Status Tracking:** Real-time visibility through the entire lifecycle (`Pending` ➔ `In Progress` ➔ `Resolved` ➔ `Closed`).

---

## 🔄 Issue Lifecycle Flowchart

```mermaid
graph TD
    A["📝 Student / Staff Reports Issue<br><i>(Attaches Cloudinary Photos & AI Input)</i>"] --> B["⏳ Status: PENDING<br><i>(Admin Dashboard Alert Triggered)</i>"]
    B --> C{"🛡️ Admin Triage & Review"}
    C -->|"Assign Staff"| D["⚙️ Status: IN PROGRESS<br><i>(Dispatched to Maintenance / IT Dept)</i>"]
    C -->|"Invalid / Duplicate"| X["❌ Status: REJECTED<br><i>(Reporter Notified with Reason)</i>"]
    D --> E["🔧 Technician Executes Field Work<br><i>(Logs Progress Notes)</i>"]
    E --> F["✅ Status: RESOLVED<br><i>(Technician Submits Resolution Notes)</i>"]
    F --> G{"🔍 Verification & Quality Check"}
    G -->|"Repair Verified"| H["🔒 Status: CLOSED<br><i>(Ticket Archived Successfully)</i>"]
    G -->|"Fault Persists"| I["🔄 Status: REOPENED<br><i>(Reporter Flags Incomplete Repair)</i>"]
    I --> D

    classDef pending fill:#fef3c7,stroke:#d97706,stroke-width:2px,color:#78350f;
    classDef progress fill:#dbeafe,stroke:#2563eb,stroke-width:2px,color:#1e3a8a;
    classDef resolved fill:#d1fae5,stroke:#059669,stroke-width:2px,color:#064e3b;
    classDef closed fill:#e0e7ff,stroke:#4338ca,stroke-width:2px,color:#312e81;
    classDef rejected fill:#fee2e2,stroke:#dc2626,stroke-width:2px,color:#7f1d1d;
    classDef action fill:#1e293b,stroke:#00D4FF,stroke-width:2px,color:#ffffff;

    class A,E action;
    class B pending;
    class D,I progress;
    class F resolved;
    class H closed;
    class X rejected;
```

---

## ☁️ Cloud System Architecture

```mermaid
flowchart LR
    subgraph Users ["👥 Multi-Role Access"]
        U1["🎓 Student / Staff<br><b>Reporter Portal</b>"]
        U2["🔧 Maintenance Crew<br><b>Technician Board</b>"]
        U3["🛡️ Administrator<br><b>Command Center</b>"]
    end

    subgraph RenderPlatform ["☁️ Hosted on Render"]
        App["🚀 <b>FixMyCampus Web Application</b><br>PHP 8.2 • Apache • RBAC Middleware"]
    end

    subgraph CloudServices ["🌐 Integrated Cloud Services"]
        CDN["📸 <b>Cloudinary CDN</b><br>Fast Image Upload & Media Delivery"]
        AI["🤖 <b>Google Gemini 1.5</b><br>Voice Translation & NLP Extraction"]
    end

    U1 -->|"1. Submit Issues & Voice Reports"| App
    U2 -->|"2. Update Progress & Resolve Tasks"| App
    U3 -->|"3. Triage, Assign Staff & View Analytics"| App
    
    App <-->|"Upload / Retrieve Evidence Photos"| CDN
    App <-->|"Translate Audio & Parse Chat"| AI
```

---

## ✨ Features by User Role

### 🎓 1. Students & Faculty (Reporters)
* **Smart Issue Reporting:** Submit maintenance complaints with categories, exact location, urgency level, and detailed descriptions.
* **Photo Evidence via Cloudinary:** Attach damage photos directly uploaded to Cloudinary CDN for instant verification.
* **AI Chat & Voice Assistant:** Describe problems in natural language or speak in regional languages (Hindi, Marathi, Konkani, etc.) with automatic English translation and field extraction.
* **Live Status & Timeline:** Track issue progress in real-time with full timestamped audit history and staff remarks.
* **Ticket Reopening:** Reopen tickets if repairs are incomplete or issues recur.

### 🔧 2. Staff & Maintenance Team (Technicians)
* **Assigned Work Orders:** Dedicated technician queue showing tickets dispatched by the administration.
* **Progress Logging:** Update ticket status to `In Progress` and log repair notes.
* **Resolution Documentation:** Mark work orders as `Resolved` with technical remarks and completion logs.
* **Instant Notifications:** Receive real-time alerts when new tasks are assigned or updated.

### 🛡️ 3. Administrators (Facility Overseers)
* **KPI Metrics Dashboard:** Overview of total complaints, pending issues, active repairs, resolution efficiency, and department breakdown.
* **Master Triage & Assignment:** Review incoming complaints and assign specialized technicians (Electrical, Plumbing, IT, Infrastructure, etc.).
* **Duplicate Complaint Clustering:** Group duplicate student complaints under a primary ticket with aggregated affected counts.
* **User & Role Management:** Manage registered accounts, departments, and access permissions.
* **Analytical Trend Reports:** Visual monthly charts and resolution performance analytics.

---

## 🛠️ Cloud & Technology Stack

| Component | Technology | Purpose |
| :--- | :--- | :--- |
| **Live Hosting** | **Render** | Cloud hosting running containerized PHP 8.2 & Apache environment |
| **Media & Storage** | **Cloudinary** | Cloud storage for issue evidence photos and CDN image delivery |
| **Backend** | **PHP 8.2** | Secure session management, RBAC routing, and business logic |
| **AI Integration** | **Google Gemini 1.5** | Natural language ticket parsing & multilingual voice translation |
| **Frontend** | **HTML5 / Vanilla JS** | Modern interactive UI and Web Speech API voice capture |
| **Styling** | **Custom Vanilla CSS3** | Dark-mode glassmorphic theme tokens and responsive layouts |
| **Icons** | **Bootstrap Icons** | Vector iconography across navigation and status badges |

---

## 🚀 Live Demo & Links

* 🔗 **Live Website:** [https://campus-issue-tracker-main.onrender.com](https://campus-issue-tracker-main.onrender.com)
* 📁 **Source Repository:** [https://github.com/krishashetdz/Campus-issue-Tracker](https://github.com/krishashetdz/Campus-issue-Tracker)

---

## 👥 Project Team

* 👨‍💻 **Kris Rodrigues**
* 👩‍💻 **Jeolita Parpatekar**
* 👩‍💻 **Krisha Shet** ([@krishashetdz](https://github.com/krishashetdz))

---

## ⚖️ License & Proprietary Notice

**Copyright © 2026. All Rights Reserved.** 

This platform and its codebase are **proprietary and confidential**. Unauthorized copying, modification, distribution, or commercial deployment of this software is strictly prohibited without explicit written permission from the project creators.
