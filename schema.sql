-- =========================
-- USER
-- =========================
CREATE TABLE IF NOT EXISTS Users (
    userId INT PRIMARY KEY AUTO_INCREMENT,
    fullName VARCHAR(255),
    email VARCHAR(255),
    password VARCHAR(255),
    role VARCHAR(50),
    status VARCHAR(50),
    createdAt DATE
);

-- =========================
-- PROFILE
-- =========================
CREATE TABLE IF NOT EXISTS Profile (
    profileId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT UNIQUE,
    bio VARCHAR(255),
    photoUrl VARCHAR(255),
    location VARCHAR(255),
    preferences VARCHAR(255),
    completionScore INT,
    level VARCHAR(50),
    FOREIGN KEY (userId) REFERENCES Users(userId)
);

-- =========================
-- USER SKILL
-- =========================
CREATE TABLE IF NOT EXISTS UserSkill (
    userSkillId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT,
    skillName VARCHAR(100),
    source VARCHAR(50),
    certificateUrl VARCHAR(255),
    validatedAt DATE,
    FOREIGN KEY (userId) REFERENCES Users(userId)
);

-- =========================
-- COURSE
-- =========================
CREATE TABLE IF NOT EXISTS Course (
    courseId INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255),
    description VARCHAR(255),
    category VARCHAR(100),
    skill VARCHAR(100),
    difficulty VARCHAR(50),
    duration INT,
    status VARCHAR(50),
    createdAt DATE
);

-- =========================
-- CALENDAR
-- =========================
CREATE TABLE IF NOT EXISTS Calendar (
    calendarId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT,
    courseId INT,
    startDate DATE,
    endDate DATE,
    progress INT,
    status VARCHAR(50),
    FOREIGN KEY (userId) REFERENCES Users(userId),
    FOREIGN KEY (courseId) REFERENCES Course(courseId)
);

-- =========================
-- GROUP (SkillHub)
-- =========================
CREATE TABLE IF NOT EXISTS SkillHub (
    groupId INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    category VARCHAR(100),
    description VARCHAR(255),
    createdAt DATE,
    status VARCHAR(50)
);

-- =========================
-- CHALLENGE
-- =========================
CREATE TABLE IF NOT EXISTS Challenge (
    challengeId INT PRIMARY KEY AUTO_INCREMENT,
    groupId INT,
    managerId INT,
    title VARCHAR(255),
    description VARCHAR(255),
    difficulty VARCHAR(50),
    deadline DATE,
    status VARCHAR(50),
    createdAt DATE,
    FOREIGN KEY (groupId) REFERENCES SkillHub(groupId),
    FOREIGN KEY (managerId) REFERENCES Users(userId)
);

-- =========================
-- SUBMISSION
-- =========================
CREATE TABLE IF NOT EXISTS Submission (
    submissionId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT,
    challengeId INT,
    projectLink VARCHAR(255),
    description VARCHAR(255),
    submittedAt DATE,
    score INT,
    rank INT,
    status VARCHAR(50),
    FOREIGN KEY (userId) REFERENCES Users(userId),
    FOREIGN KEY (challengeId) REFERENCES Challenge(challengeId)
);

-- =========================
-- GROUP MEMBER
-- =========================
CREATE TABLE IF NOT EXISTS GroupMember (
    groupMemberId INT PRIMARY KEY AUTO_INCREMENT,
    groupId INT,
    userId INT,
    joinedAt DATE,
    status VARCHAR(50),
    FOREIGN KEY (groupId) REFERENCES SkillHub(groupId),
    FOREIGN KEY (userId) REFERENCES Users(userId)
);

-- =========================
-- POST
-- =========================
CREATE TABLE IF NOT EXISTS Post (
    postId INT PRIMARY KEY AUTO_INCREMENT,
    groupId INT,
    userId INT,
    title VARCHAR(255),
    content VARCHAR(255),
    createdAt DATE,
    FOREIGN KEY (groupId) REFERENCES SkillHub(groupId),
    FOREIGN KEY (userId) REFERENCES Users(userId)
);

-- =========================
-- COMMENT
-- =========================
CREATE TABLE IF NOT EXISTS Comment (
    commentId INT PRIMARY KEY AUTO_INCREMENT,
    postId INT,
    userId INT,
    content VARCHAR(255),
    Likes INT,
    createdAt DATE,
    FOREIGN KEY (postId) REFERENCES Post(postId),
    FOREIGN KEY (userId) REFERENCES Users(userId)
);

-- =========================
-- EVENT
-- =========================
CREATE TABLE IF NOT EXISTS Event (
    eventId INT PRIMARY KEY AUTO_INCREMENT,
    managerId INT,
    title VARCHAR(255),
    description VARCHAR(255),
    type VARCHAR(50),
    location VARCHAR(255),
    date DATE,
    capacity INT,
    status VARCHAR(50),
    createdAt DATE,
    FOREIGN KEY (managerId) REFERENCES Users(userId)
);

-- =========================
-- EVENT FORM
-- =========================
CREATE TABLE IF NOT EXISTS EventForm (
    formId INT PRIMARY KEY AUTO_INCREMENT,
    eventId INT UNIQUE,
    title VARCHAR(255),
    description VARCHAR(255),
    formLink VARCHAR(255),
    status VARCHAR(50),
    FOREIGN KEY (eventId) REFERENCES Event(eventId)
);

-- =========================
-- PARTICIPATION
-- =========================
CREATE TABLE IF NOT EXISTS Participation (
    participationId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT,
    eventId INT,
    formId INT,
    registrationDate DATE,
    attendanceStatus VARCHAR(50),
    status VARCHAR(50),
    FOREIGN KEY (userId) REFERENCES Users(userId),
    FOREIGN KEY (eventId) REFERENCES Event(eventId),
    FOREIGN KEY (formId) REFERENCES EventForm(formId)
);

-- =========================
-- SPONSOR
-- =========================
CREATE TABLE IF NOT EXISTS Sponsor (
    sponsorId INT PRIMARY KEY AUTO_INCREMENT,
    eventId INT,
    name VARCHAR(255),
    company VARCHAR(255),
    email VARCHAR(255),
    contribution VARCHAR(255),
    status VARCHAR(50),
    amount FLOAT,
    FOREIGN KEY (eventId) REFERENCES Event(eventId)
);

-- =========================
-- OPPORTUNITY
-- =========================
CREATE TABLE IF NOT EXISTS Opportunity (
    opportunityId INT PRIMARY KEY AUTO_INCREMENT,
    managerId INT,
    title VARCHAR(255),
    description VARCHAR(255),
    type VARCHAR(50),
    category VARCHAR(100),
    deadline DATE,
    requiredLevel VARCHAR(50),
    status VARCHAR(50),
    createdAt DATE,
    FOREIGN KEY (managerId) REFERENCES User(userId)
);

-- =========================
-- APPLICATION
-- =========================
CREATE TABLE IF NOT EXISTS Application (
    applicationId INT PRIMARY KEY AUTO_INCREMENT,
    userId INT,
    opportunityId INT,
    appliedAt DATE,
    status VARCHAR(50),
    motivation VARCHAR(255),
    compatibilityScore INT,
    FOREIGN KEY (userId) REFERENCES User(userId),
    FOREIGN KEY (opportunityId) REFERENCES Opportunity(opportunityId)
);
-- =========================
-- MANAGER PROFILE
-- =========================
CREATE TABLE IF NOT EXISTS ManagerProfile (
    managerProfileId INT PRIMARY KEY AUTO_INCREMENT,
    userId           INT UNIQUE NOT NULL,
    organization     VARCHAR(255) NOT NULL,
    categoryFocus    VARCHAR(100) NOT NULL,
    description      VARCHAR(500),
    FOREIGN KEY (userId) REFERENCES Users(userId) ON DELETE CASCADE
);

-- =========================
-- RECRUITER PROFILE
-- =========================
CREATE TABLE IF NOT EXISTS RecruiterProfile (
    recruiterProfileId INT PRIMARY KEY AUTO_INCREMENT,
    userId             INT UNIQUE NOT NULL,
    companyName        VARCHAR(255) NOT NULL,
    jobTitle           VARCHAR(100) NOT NULL,
    industry           VARCHAR(100) NOT NULL,
    companyWebsite     VARCHAR(255),
    opportunityTypes   VARCHAR(255),
    FOREIGN KEY (userId) REFERENCES Users(userId) ON DELETE CASCADE
);
