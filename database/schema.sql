-- ============================================================
-- HR Management System — MySQL / MariaDB (XAMPP)
-- Import: C:/xampp/mysql/bin/mysql.exe -u root < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS hr_management
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hr_management;

-- ---------- Departments ----------
CREATE TABLE IF NOT EXISTS departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Employees ----------
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(30) UNIQUE,           -- para sa biometric device user-id mapping
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('employee','hr','admin') NOT NULL DEFAULT 'employee',
  department_id INT NULL,
  position VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  salary DECIMAL(12,2) NULL,
  date_hired DATE NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Leave Requests ----------
CREATE TABLE IF NOT EXISTS leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  leave_type ENUM('vacation','sick','personal','emergency','maternity','paternity','bereavement','other')
    NOT NULL DEFAULT 'vacation',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days DECIMAL(5,1) NOT NULL,
  reason TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  review_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES employees(id) ON DELETE SET NULL,
  INDEX idx_leave_status (status),
  INDEX idx_leave_emp (employee_id)
) ENGINE=InnoDB;

-- ---------- Attendance ----------
-- method:
--   manual    = HR/manager ang nag-mark (walk-through o hawak ang device)
--   app       = clock-in thru website/app (may selfie photo + GPS geofence)
--   biometric = in-import galing sa fingerprint/face device (ZKTeco, Realand, atbp.)
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  time_in DATETIME NULL,
  time_out DATETIME NULL,
  method ENUM('manual','app','biometric') NOT NULL DEFAULT 'app',
  photo_url VARCHAR(255) NULL,               -- selfie proof of presence
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_verified TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = nasa loob ng geofence
  distance_m DECIMAL(8,1) NULL,              -- layo mula sa office centroid
  ip_address VARCHAR(45) NULL,
  device_info VARCHAR(255) NULL,
  notes VARCHAR(255) NULL,
  verified_by INT NULL,                      -- HR na nag-verify (manual/app flag)
  verified_at DATETIME NULL,
  status ENUM('present','late','half_day','absent','on_leave') NOT NULL DEFAULT 'present',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_emp_date (employee_id, attendance_date),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES employees(id) ON DELETE SET NULL,
  INDEX idx_att_date (attendance_date)
) ENGINE=InnoDB;

-- ---------- App notifications (in-app log) ----------
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NULL,                      -- NULL = broadcast sa lahat
  title VARCHAR(150) NOT NULL,
  body VARCHAR(500) NULL,
  event VARCHAR(80) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;
