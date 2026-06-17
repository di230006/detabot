CREATE DATABASE IF NOT EXISTS detabot
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE detabot;

CREATE TABLE IF NOT EXISTS tbl_user (
  userID INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  userEmail VARCHAR(100) NOT NULL UNIQUE,
  userPassword VARCHAR(255) NOT NULL,
  userPhone VARCHAR(20) NOT NULL,
  userAge INT,
  userGender VARCHAR(10),
  userChronicHealthProblems TEXT,
  userAvatar VARCHAR(255),
  userRole VARCHAR(20) NOT NULL DEFAULT 'patient',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  createdDate DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_clinic (
  clinicID INT AUTO_INCREMENT PRIMARY KEY,
  clinicName VARCHAR(100) NOT NULL,
  location VARCHAR(255) NOT NULL,
  operatingHours VARCHAR(100) NOT NULL,
  contactNumber VARCHAR(20) NOT NULL,
  dentistName VARCHAR(100) NOT NULL,
  services TEXT NOT NULL,
  promotions TEXT,
  createdDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  updatedDate DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_appointment (
  appointmentID INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NOT NULL,
  clinicID INT NOT NULL,
  appointmentDate DATE NOT NULL,
  appointmentTime TIME NOT NULL,
  serviceType VARCHAR(100) NOT NULL,
  duration INT NOT NULL DEFAULT 60,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  patientAge INT,
  healthProblemCategory VARCHAR(30) NOT NULL DEFAULT 'none',
  healthProblemDetail TEXT,
  notes TEXT,
  createdDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  updatedDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_appointment_user (userID),
  INDEX idx_appointment_slot (clinicID, appointmentDate, appointmentTime, status),
  CONSTRAINT fk_appointment_user FOREIGN KEY (userID) REFERENCES tbl_user(userID),
  CONSTRAINT fk_appointment_clinic FOREIGN KEY (clinicID) REFERENCES tbl_clinic(clinicID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_chatbot (
  chatID INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NULL,
  messageText TEXT NOT NULL,
  responseText TEXT NOT NULL,
  messageType VARCHAR(20) NOT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  sessionID VARCHAR(80) NOT NULL,
  INDEX idx_chatbot_user (userID),
  CONSTRAINT fk_chatbot_user FOREIGN KEY (userID) REFERENCES tbl_user(userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_feedback (
  feedbackID INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NOT NULL,
  appointmentID INT NOT NULL,
  rating INT NOT NULL,
  comments TEXT NOT NULL,
  feedbackDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  adminResponse TEXT,
  responseDate DATETIME NULL,
  INDEX idx_feedback_user (userID),
  CONSTRAINT fk_feedback_user FOREIGN KEY (userID) REFERENCES tbl_user(userID),
  CONSTRAINT fk_feedback_appointment FOREIGN KEY (appointmentID) REFERENCES tbl_appointment(appointmentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_report (
  reportID INT AUTO_INCREMENT PRIMARY KEY,
  reportType VARCHAR(50) NOT NULL,
  generatedBy VARCHAR(50) NOT NULL,
  reportDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  reportData TEXT NOT NULL,
  parameters TEXT,
  exportFormat VARCHAR(20) NOT NULL DEFAULT 'screen'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_reward (
  rewardID INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NOT NULL,
  pointsEarned INT NOT NULL DEFAULT 0,
  pointsRedeemed INT NOT NULL DEFAULT 0,
  currentBalance INT NOT NULL DEFAULT 0,
  transactionType VARCHAR(20) NOT NULL,
  transactionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  rewardDescription TEXT NOT NULL,
  INDEX idx_reward_user (userID),
  CONSTRAINT fk_reward_user FOREIGN KEY (userID) REFERENCES tbl_user(userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_reward_catalog (
  rewardCatalogID INT AUTO_INCREMENT PRIMARY KEY,
  rewardName VARCHAR(100) NOT NULL,
  pointsRequired INT NOT NULL,
  description TEXT NOT NULL,
  isActive INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_activity_log (
  logID INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NULL,
  action VARCHAR(80) NOT NULL,
  details TEXT,
  createdDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_user (userID),
  CONSTRAINT fk_activity_user FOREIGN KEY (userID) REFERENCES tbl_user(userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_password_reset_otp (
  resetID INT AUTO_INCREMENT PRIMARY KEY,
  userID INT NOT NULL,
  userEmail VARCHAR(100) NOT NULL,
  otpHash VARCHAR(255) NOT NULL,
  expiresAt DATETIME NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  usedAt DATETIME NULL,
  createdDate DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_reset_user (userID, usedAt, expiresAt),
  INDEX idx_password_reset_email (userEmail, usedAt, expiresAt),
  CONSTRAINT fk_password_reset_user FOREIGN KEY (userID) REFERENCES tbl_user(userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_user (userID, username, userEmail, userPassword, userPhone, userRole, status) VALUES
(1, 'System Admin', 'admin@detabot.local', '$2y$10$6RiIhxb0yKpPluB0WTB/Xuol38Q2bwAlVk9E8ZWbsS2UQ0f/lYZ9u', '011-1000 1000', 'admin', 'active'),
(2, 'Clinic Staff', 'staff@detabot.local', '$2y$10$qS4blEut68TV6DUoCkhK9.9OtCHbDvWbXbcLGyVf3f/2cj7DzzFUa', '011-2000 2000', 'staff', 'active'),
(3, 'Demo Patient', 'patient@detabot.local', '$2y$10$LMiRaP5xa3It2GHHZDI1DeGJkN3WoXeVCXi7icEADXWXaKS8cy0qS', '011-3000 3000', 'patient', 'active');

INSERT IGNORE INTO tbl_clinic (clinicID, clinicName, location, operatingHours, contactNumber, dentistName, services, promotions) VALUES
(1, 'Clinic Putra Dental', 'Taman Universiti, Parit Raja, Batu Pahat, Johor', 'Monday to Saturday, 9:00 AM - 5:00 PM', '07-453 8899', 'Dr. Putra Dental Team', 'Dental consultation\nDental X-ray\nChildren dental prevention\nTooth extraction\nTooth filling\nScaling / teeth cleaning\nDentures\nCrown\nBridge\nFRC bridge\nRoot canal treatment\nTeeth whitening\nIcon treatment for fluorosis\nVeneer\nBraces\nRetainer\nMinor oral surgery', 'Earn 20 reward points after every completed appointment.');

INSERT IGNORE INTO tbl_reward_catalog (rewardCatalogID, rewardName, pointsRequired, description, isActive) VALUES
(1, 'RM10 treatment discount', 80, 'Redeem for a discount during the next eligible treatment.', 1),
(2, 'Free dental kit', 120, 'Redeem for a toothbrush, toothpaste, and floss kit.', 1),
(3, 'Scaling discount voucher', 180, 'Redeem for a selected scaling and polishing discount.', 1);
