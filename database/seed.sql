-- ============================================================
-- Seed data — import AFTER schema.sql
-- Default passwords (bcrypt):
--   hr@company.com   / admin123   (HR admin)
--   juan@company.com / emp123     (employee)
-- ============================================================
USE hr_management;

INSERT INTO departments (name) VALUES
  ('Human Resources'),
  ('Operations'),
  ('Finance'),
  ('IT Department')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO employees
  (employee_code, first_name, last_name, email, password_hash, role, department_id, position, phone, salary, date_hired, status)
VALUES
  ('EMP-001', 'Maria', 'Santos', 'hr@company.com',
   '$2y$10$dHzC1NdWu6iP.8Iddjiv1O7Xhb/luPp7TWEQHQPrEDjisMxnA//YK',
   'admin', 1, 'HR Manager', '09171234567', 45000.00, '2023-01-15', 'active'),
  ('EMP-002', 'Juan', 'Dela Cruz', 'juan@company.com',
   '$2y$10$NEm9Er/SqoUaVNqCTFL6b.L5Jf/nxQp9bSUnY94Ag.uB2WJ4wQ6QW',
   'employee', 2, 'Operations Staff', '09181234567', 22000.00, '2024-03-01', 'active'),
  ('EMP-003', 'Ana', 'Reyes', 'ana@company.com',
   '$2y$10$NEm9Er/SqoUaVNqCTFL6b.L5Jf/nxQp9bSUnY94Ag.uB2WJ4wQ6QW',
   'employee', 3, 'Accountant', '09191234567', 28000.00, '2023-08-20', 'active'),
  ('EMP-004', 'Paolo', 'Garcia', 'paolo@company.com',
   '$2y$10$NEm9Er/SqoUaVNqCTFL6b.L5Jf/nxQp9bSUnY94Ag.uB2WJ4wQ6QW',
   'employee', 4, 'System Analyst', '09201234567', 35000.00, '2024-06-10', 'active')
ON DUPLICATE KEY UPDATE first_name = VALUES(first_name);

-- Sample attendance (app clock-in na may GPS + selfie)
INSERT INTO attendance
  (employee_id, attendance_date, time_in, time_out, method, location_verified, distance_m, status)
VALUES
  (2, CURDATE(), CONCAT(CURDATE(), ' 08:03:00'), NULL, 'app', 1, 12.5, 'present'),
  (3, CURDATE(), CONCAT(CURDATE(), ' 08:15:00'), NULL, 'biometric', 0, NULL, 'present'),
  (4, CURDATE(), CONCAT(CURDATE(), ' 09:20:00'), NULL, 'app', 1, 340.0, 'late')
ON DUPLICATE KEY UPDATE time_in = VALUES(time_in);

-- Sample leave request
INSERT INTO leave_requests
  (employee_id, leave_type, start_date, end_date, days, reason, status)
VALUES
  (2, 'vacation', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 2.0,
   'Family outing sa Baguio', 'pending')
ON DUPLICATE KEY UPDATE reason = VALUES(reason);
