# CareerStrand - ADN Career Progression Platform

## Overview

This project was developed as part of the **PIDEV - 2nd Year Engineering Program** at **Esprit School of Engineering** during the academic year **2025-2026**.

**CareerStrand** is a web platform designed to help students, beginners, junior freelancers, and young professionals prepare for the digital job market through a structured career progression journey.

The platform is built around the concept of **ADN**, which represents the user's professional identity inside the system. Unlike a simple static profile, the ADN grows progressively through real activity on the platform:

- profile completion
- skills and preferences
- course enrollment and progress
- Skill Hub participation
- challenge submissions
- event participation
- opportunity applications

CareerStrand is not only a learning platform, a networking platform, or a job board. It is a **career progression ecosystem** that helps users move from preparation to real opportunities.

---

## Main Objective

CareerStrand aims to reduce the gap between learning and professional opportunities by offering one integrated platform where users can:

- build a professional profile
- learn through courses and course videos
- prove skills through practical challenges
- join Skill Hubs and community discussions
- participate in workshops, hackathons, bootcamps, and events
- apply to internships, junior roles, freelance missions, volunteer roles, and beginner opportunities

---

## Core Concept: ADN

In CareerStrand, **ADN** is the digital reflection of the user's professional journey.

It is shaped by:

- personal profile data
- skills and preferences
- education history
- course activity
- challenge performance
- community engagement
- event participation
- opportunity applications

The ADN is therefore a **dynamic identity built through progression**, not only an automatically generated AI profile and not a static CV.

---

## Key Features

### User and Profile Module

- User registration and authentication
- Role-based redirection for front office and back office users
- Profile creation and update
- Skills management
- Preferences and career information
- Profile completion tracking
- Face login support using stored face descriptors
- Questionnaire-based profile enrichment

### Education Module

- Browse available courses
- Manage courses from the back office
- Add and manage course videos
- Track learning progress through calendar entries
- Support educational progression before users move to practical work

### Skill Hub and Challenge Module

- Browse Skill Hubs
- Join or leave hubs
- Publish tasks and projects as challenges
- Submit project links and descriptions
- Review submissions from the back office
- Score submissions and generate rankings
- Edit saved evaluations
- Generate AI feedback for submissions
- Delete challenges and hubs with their related submissions, posts, comments, and memberships

### Forum and Community Module

- Create hub posts
- Create challenge-linked discussion posts
- Comment on discussions
- Keep community activity linked to hubs and challenges

### Events Module

- Discover workshops, hackathons, bootcamps, and other events
- Register for events
- Track participation and attendance
- Manage sponsors
- Generate QR codes for attendance
- Analyze feedback and sentiment

### Opportunities and Applications Module

- Browse available opportunities
- Apply to internships, jobs, freelance work, volunteer missions, and beginner opportunities
- Track application status
- Manage opportunities from the back office
- Review, update, delete, and summarize applications
- Use compatibility scores to support application review

### Admin Back Office

- Live dashboard with platform metrics
- User management
- Profile management
- Course and course video management
- Calendar management
- Questionnaire management
- Skill Hub management
- Opportunity and application management
- Event and sponsor management
- Sign out from the dashboard

---

## Platform Flow

The platform follows this main user journey:

**Register -> Complete Profile -> Answer Questionnaire -> Enroll in Courses -> Join Skill Hubs -> Submit Challenges -> Participate in Events -> Apply to Opportunities**

This progression is one of the strongest aspects of the project because it reflects user growth and readiness.

---

## Main Actors

### User

The main front-office actor. This can be:

- a student
- a beginner
- a junior freelancer
- a young professional

The user completes a profile, learns through courses, joins hubs, submits challenges, participates in events, and applies to opportunities.

### Manager

The manager represents a person or organization that contributes opportunities, events, or platform content. This can represent:

- a recruiter
- a startup founder
- an opportunity provider
- a workshop or event organizer

### Admin

The admin supervises the platform and manages:

- users
- profiles
- courses
- course videos
- Skill Hubs
- challenges
- submissions
- events
- sponsors
- opportunities
- applications
- platform content

---

## System Modules

The system is organized around these main modules:

1. **User and Profile Module**
2. **Education Module**
3. **Skill Hub, Challenge, and Forum Module**
4. **Events and Participation Module**
5. **Opportunities and Applications Module**
6. **Admin Dashboard and Back Office Module**

---

## UML Logic

The system is based on these main entities:

- **User**
- **Profile**
- **UserSkill**
- **UserQuestionnaire**
- **Course**
- **CourseVideo**
- **Calendar**
- **SkillHub**
- **GroupMember**
- **Challenge**
- **Submission**
- **Post**
- **Comment**
- **Event**
- **Sponsor**
- **Participation**
- **Opportunity**
- **OpportunitySkill**
- **Application**

### Main Relationships

- One **User** has one **Profile**
- One **User** can have many **UserSkills**
- One **User** can have many **UserQuestionnaire** answers
- One **User** can have many **Calendar** course plans
- One **SkillHub** can have many **GroupMembers**
- One **SkillHub** can have many **Challenges**
- One **Challenge** can have many **Submissions**
- One **Challenge** can have many discussion **Posts**
- One **Post** can have many **Comments**
- One **User** can have many **Participations**
- One **User** can have many **Applications**
- One **Opportunity** can have many **Applications**
- One **Opportunity** can have many **OpportunitySkills**

This structure models the user's progression across learning, practice, engagement, and opportunity access.

---

## Why CareerStrand Is Different

CareerStrand does not focus only on:

- static professional profiles
- networking only
- direct job applications
- isolated online courses

Instead, it focuses on **progression**.

What makes it unique is the combination of:

- identity building
- structured learning
- practical proof through challenges
- community engagement
- events and participation
- real opportunity access

CareerStrand helps users become opportunity-ready before they enter the market.

---

## Value Proposition

**CareerStrand helps beginners build their professional ADN through learning, practice, engagement, and progression before accessing real opportunities.**

Another way to define it:

> CareerStrand is a career progression platform that transforms students and beginners into opportunity-ready users through a structured digital journey.

---

## Tech Stack

### Frontend

- HTML
- CSS
- JavaScript
- Three.js for the animated ADN/DNA visual background
- face-api.js for face authentication

### Backend

- PHP
- PDO
- MVC-inspired structure with Controllers and Models

### Database

- MySQL
- SQL dump included in the repository

### AI Integrations

- Hugging Face API for application summaries and Skill Hub submission feedback

---

## Repository Structure

```bash
Careerstrand/
|
|-- api/
|   |-- face.php
|   |-- questionnaire_ai.php
|
|-- Controller/
|   |-- ApplicationController.php
|   |-- ControlCourses.php
|   |-- ControlCourseVideos.php
|   |-- EventsController.php
|   |-- OpportunityController.php
|   |-- ProfileController.php
|   |-- SkillHubCoreController.php
|   |-- SkillHubEngagementController.php
|   |-- UserController.php
|   `-- ...
|
|-- Model/
|   |-- Application.php
|   |-- Courses.php
|   |-- Event.php
|   |-- Opportunity.php
|   |-- Profile.php
|   |-- SkillHubCore.php
|   |-- SkillHubEngagement.php
|   |-- User.php
|   `-- ...
|
|-- View/
|   |-- BackOffice/
|   |   |-- admin-dashboard.php
|   |   |-- admin-users.php
|   |   |-- admin-profiles.php
|   |   |-- admin-courses.php
|   |   |-- admin-course-videos.php
|   |   |-- admin-skills.php
|   |   |-- admin-skillhub-reviews.php
|   |   |-- admin-opportunities.php
|   |   |-- admin-applications.php
|   |   |-- admin-feedback.php
|   |   |-- assets/
|   |   `-- partials/
|   |
|   `-- FrontOffice/
|       |-- index.php
|       |-- home.php
|       |-- login.php
|       |-- signup.php
|       |-- profile.php
|       |-- course.php
|       |-- skillhub.php
|       |-- hub.php
|       |-- thread.php
|       |-- forum.php
|       |-- events.php
|       |-- opportunities.php
|       |-- assets/
|       |-- images/
|       `-- partials/
|
|-- utils/
|   |-- AuthRedirect.php
|   `-- FrontOfficeAuth.php
|
|-- config.php
|-- careerstrand (1).sql
`-- README.md
```

---

## Installation and Setup

### 1. Requirements

- XAMPP or another Apache/PHP/MySQL environment
- PHP 8+
- MySQL or MariaDB
- A modern web browser

### 2. Clone the Repository

```bash
git clone https://github.com/Firas443/Esprit-PIDEV-2A28-2026-CareerStrand.git
cd CareerStrand
```

If you are using XAMPP, place the project folder inside:

```bash
C:\xampp\htdocs\
```

The expected local path is usually:

```bash
C:\xampp\htdocs\Careerstrand
```

### 3. Import the Database

1. Start Apache and MySQL from XAMPP.
2. Open phpMyAdmin.
3. Create a database named:

```sql
careerstrand
```

4. Import the SQL dump:

```bash
careerstrand (1).sql
```

### 4. Configure Database Connection

Open:

```bash
config.php
```

Default local configuration:

```php
$host = "localhost";
$dbName = "careerstrand";
$username = "root";
$password = "";
```

You can also configure these values with environment variables:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

### 5. Configure AI API Key

The project uses Hugging Face for AI-powered features. Configure the key in `config.php` or through environment variables:

- `HF_TOKEN`
- `HF_FEEDBACK_MODEL`

AI-powered features include:

- application summary generation
- Skill Hub submission feedback
- questionnaire/profile assistance

### 6. Run the Project

Start Apache and MySQL, then open:

```bash
http://localhost/Careerstrand/View/FrontOffice/index.php
```

Front-office signed-in homepage:

```bash
http://localhost/Careerstrand/View/FrontOffice/home.php
```

Back-office dashboard:

```bash
http://localhost/Careerstrand/View/BackOffice/admin-dashboard.php
```

---

## Authentication and Redirection

CareerStrand supports role-based navigation:

- Admin users are redirected to the back office dashboard.
- Front-office users are redirected to the signed-in home page.
- Users who still need to complete the questionnaire are redirected to the questionnaire page.

Important files:

- `View/FrontOffice/login.php`
- `utils/AuthRedirect.php`
- `utils/FrontOfficeAuth.php`
- `api/face.php`

---

## Recent Improvements

- Functional admin dashboard with live database metrics
- Separate signed-in front-office home page
- Three.js ADN/DNA animated background on the signed-in home page
- Skill Hub AI feedback analyzer fixes
- Ability to edit reviewed Skill Hub submissions
- Cascade cleanup when deleting challenges and hubs
- Dashboard sign-out button restored

---

## Notes

- This project is designed for a local XAMPP-style PHP environment.
- The database name expected by default is `careerstrand`.
- Some AI features require a valid Hugging Face API key.
- Face login requires browser camera permission and the face-api.js model files in `View/FrontOffice/assets/models`.

---

## Authors

Project developed for **Esprit School of Engineering - PIDEV 2A**.

Repository:

```bash
https://github.com/Firas443/Esprit-PIDEV-2A28-2026-CareerStrand
```
