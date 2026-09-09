<?php
include("admin_area/includes/db.php");

echo "<div style='font-family: sans-serif; padding: 40px; line-height: 1.6; max-width: 800px; margin: 0 auto;'>";
echo "<h1 style='color: #1e293b;'>Cadlete Database Setup & Migrations Sync</h1>";
echo "<p style='color: #64748b;'>Synchronizing database schema and migrations table...</p><hr style='border: 1px solid #f1f5f9; margin: 20px 0;'>";

// Create migrations tracking table if not exists
$create_migrations_table_sql = "CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL DEFAULT 1,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($con, $create_migrations_table_sql);

/**
 * Record migration entry
 * @param mysqli $con
 * @param string $migration_name
 * @param int $batch
 */
function record_migration($con, $migration_name, $batch = 1)
{
  $migration_name = mysqli_real_escape_string($con, $migration_name);
  $check = mysqli_query($con, "SELECT id FROM migrations WHERE migration = '$migration_name'");
  if ($check && mysqli_num_rows($check) == 0) {
    mysqli_query($con, "INSERT INTO migrations (migration, batch) VALUES ('$migration_name', $batch)");
  }
}

// Log migrations table itself
record_migration($con, "create_migrations_table", 1);

$tables = array(
  'admins' => 'CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` int(10) NOT NULL AUTO_INCREMENT,
  `admin_name` varchar(255) NOT NULL,
  `admin_email` varchar(255) NOT NULL,
  `admin_pass` varchar(255) NOT NULL,
  `admin_image` text NOT NULL,
  `admin_contact` varchar(255) NOT NULL,
  `admin_country` text NOT NULL,
  `admin_job` varchar(255) NOT NULL,
  `admin_about` text NOT NULL,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `permissions` text DEFAULT NULL,
  `department` varchar(255) DEFAULT \'Management\',
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'announcement_read' => 'CREATE TABLE IF NOT EXISTS `announcement_read` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ann_read_emp` (`emp_id`),
  CONSTRAINT `fk_ann_read_emp` FOREIGN KEY (`emp_id`) REFERENCES `emp_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'announcements' => 'CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `publish_date` datetime DEFAULT current_timestamp(),
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'attendance' => 'CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum(\'present\',\'absent\',\'late\') DEFAULT \'present\',
  `remarks` text DEFAULT NULL,
  `work_photos` text DEFAULT NULL,
  `performance` int(11) DEFAULT NULL,
  `total_duration_secs` int(11) DEFAULT 0,
  `last_resume_time` datetime DEFAULT NULL,
  `is_working` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_date` (`emp_id`,`attendance_date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `emp_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'attendance_logs' => 'CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `att_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `action_time` datetime NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `att_id` (`att_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'categories' => 'CREATE TABLE IF NOT EXISTS `categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_title` text NOT NULL,
  `cat_top` text NOT NULL,
  `cat_image` text NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'client_industries' => 'CREATE TABLE IF NOT EXISTS `client_industries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `industry_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'client_project_remarks' => 'CREATE TABLE IF NOT EXISTS `client_project_remarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `posted_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `client_project_remarks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `client_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'client_projects' => 'CREATE TABLE IF NOT EXISTS `client_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `currency` varchar(20) DEFAULT \'INR\',
  `status` varchar(50) DEFAULT \'Active\',
  `source` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deadline` date DEFAULT NULL,
  `project_desc` text DEFAULT NULL,
  `project_image` varchar(255) DEFAULT NULL,
  `assigned_employees` text DEFAULT NULL,
  `assigned_users` text DEFAULT NULL,
  `assigned_admins` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `client_projects_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'clients' => 'CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum(\'Active\',\'Inactive\') DEFAULT \'Active\',
  `industry` varchar(100) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'company_links' => 'CREATE TABLE IF NOT EXISTS `company_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_name` varchar(255) NOT NULL,
  `link_url` text NOT NULL,
  `category` varchar(255) DEFAULT \'General\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_pinned` tinyint(1) DEFAULT 0,
  `uploaded_by_type` enum(\'admin\',\'employee\') DEFAULT \'admin\',
  `uploaded_by_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'company_links_assignments' => 'CREATE TABLE IF NOT EXISTS `company_links_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(255) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cat_emp` (`category`,`emp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'customer_feedback' => 'CREATE TABLE IF NOT EXISTS `customer_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) NOT NULL,
  `contact_number` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `service_month` varchar(100) DEFAULT NULL,
  `service_quality` varchar(100) DEFAULT NULL,
  `service_on_time` varchar(10) DEFAULT NULL,
  `professionalism` varchar(100) DEFAULT NULL,
  `overall_satisfaction` varchar(100) DEFAULT NULL,
  `liked` text DEFAULT NULL,
  `improvement` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `recommend` varchar(10) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'emp_list' => 'CREATE TABLE IF NOT EXISTS `emp_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone_number` int(11) NOT NULL,
  `address` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `blood_group` varchar(11) NOT NULL,
  `gender` varchar(11) NOT NULL,
  `join_date` date NOT NULL,
  `salary` int(11) NOT NULL,
  `basic_salary` decimal(10,2) DEFAULT NULL,
  `hra` decimal(10,2) DEFAULT NULL,
  `allowance` decimal(10,2) DEFAULT NULL,
  `deductions` decimal(10,2) DEFAULT NULL,
  `documents` varchar(255) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `work_experience` varchar(255) DEFAULT NULL,
  `marital_status` varchar(50) DEFAULT NULL,
  `num_dependents` int(11) DEFAULT 0,
  `emergency_name` varchar(100) DEFAULT NULL,
  `emergency_relationship` varchar(100) DEFAULT NULL,
  `emergency_address` text DEFAULT NULL,
  `emergency_phone` varchar(15) DEFAULT NULL,
  `education_json` text DEFAULT NULL,
  `employment_json` text DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `bank_branch` varchar(255) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_type_ifsc` varchar(100) DEFAULT NULL,
  `employee_image` varchar(255) DEFAULT NULL,
  `offer_latter` varchar(255) DEFAULT NULL,
  `NDA` varchar(255) DEFAULT NULL,
  `Aadhar_card` varchar(255) DEFAULT NULL,
  `Pan_card` varchar(255) DEFAULT NULL,
  `Passportsize_photo` varchar(255) DEFAULT NULL,
  `old_company_slary_slip` varchar(255) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `otp_expire` datetime DEFAULT NULL,
  `last_birthday_wish_year` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT \'Not Assigned\',
  `designation` varchar(100) DEFAULT \'Not Assigned\',
  `status` enum(\'Active\',\'Inactive\') DEFAULT \'Active\',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'emp_performance' => 'CREATE TABLE IF NOT EXISTS `emp_performance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `perf_year` int(11) NOT NULL,
  `perf_month` int(11) NOT NULL,
  `absent` tinyint(3) unsigned DEFAULT 0,
  `late` tinyint(3) unsigned DEFAULT 0,
  `task_sheet` tinyint(3) unsigned DEFAULT 0,
  `performance_score` tinyint(3) unsigned DEFAULT 0,
  `dressing_behaviour` tinyint(3) unsigned DEFAULT 0,
  `rnd` tinyint(3) unsigned DEFAULT 0,
  `total` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_month` (`emp_id`,`perf_year`,`perf_month`),
  CONSTRAINT `emp_performance_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `emp_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'emp_personal_categories' => 'CREATE TABLE IF NOT EXISTS `emp_personal_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'emp_personal_documents' => 'CREATE TABLE IF NOT EXISTS `emp_personal_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `doc_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'emp_personal_resources' => 'CREATE TABLE IF NOT EXISTS `emp_personal_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `category` varchar(255) NOT NULL,
  `resource_type` varchar(50) DEFAULT \'link\',
  `link_name` varchar(255) NOT NULL,
  `link_url` text DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'emp_salary_history' => 'CREATE TABLE IF NOT EXISTS `emp_salary_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `month` varchar(10) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `hra` decimal(10,2) NOT NULL,
  `pf` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL,
  `allowance` decimal(10,2) NOT NULL,
  `deductions` decimal(10,2) NOT NULL,
  `gross_pay` decimal(10,2) NOT NULL,
  `total_deductions` decimal(10,2) NOT NULL,
  `net_pay` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_month` (`emp_id`,`month`),
  CONSTRAINT `fk_salary_emp` FOREIGN KEY (`emp_id`) REFERENCES `emp_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'employee_documents' => 'CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `emp_id` (`emp_id`),
  CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `emp_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'experience_letters' => 'CREATE TABLE IF NOT EXISTS `experience_letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `number` varchar(20) DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `relieve_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'lead_followups' => 'CREATE TABLE IF NOT EXISTS `lead_followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `followup_date` date NOT NULL,
  `followup_method` enum(\'Phone\',\'Email\',\'WhatsApp\',\'Meeting\',\'Other\',\'Follow-up Q&A\',\'Q&A\',\'Note\') DEFAULT \'Phone\',
  `followup_type` varchar(100) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  CONSTRAINT `lead_followups_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'lead_question_answers' => 'CREATE TABLE IF NOT EXISTS `lead_question_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL DEFAULT 0,
  `emp_name` varchar(255) NOT NULL,
  `question_num` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `answer` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_q_num` (`question_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'lead_sources' => 'CREATE TABLE IF NOT EXISTS `lead_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_name` (`source_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'leads' => 'CREATE TABLE IF NOT EXISTS `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `project_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `budget` varchar(50) DEFAULT NULL,
  `currency` varchar(10) DEFAULT \'INR\',
  `remark` text DEFAULT NULL,
  `lead_source` varchar(255) DEFAULT NULL,
  `status` enum(\'active\',\'future\',\'expired\') DEFAULT \'active\',
  `followup_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'leave_applications' => 'CREATE TABLE IF NOT EXISTS `leave_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `leave_from` date NOT NULL,
  `leave_to` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum(\'pending\',\'approved\',\'rejected\') NOT NULL DEFAULT \'pending\',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_leave_emp` (`emp_id`),
  CONSTRAINT `fk_leave_emp` FOREIGN KEY (`emp_id`) REFERENCES `emp_list` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'leave_types' => 'CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_name` varchar(255) NOT NULL,
  `num_of_leave` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'nda_forms' => 'CREATE TABLE IF NOT EXISTS `nda_forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `salary` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `notice_period` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'offer_letters' => 'CREATE TABLE IF NOT EXISTS `offer_letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `number` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `notice_period` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_budget_phases' => 'CREATE TABLE IF NOT EXISTS `project_budget_phases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `phase_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `expected_date` date DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT 0.00,
  `received_amount` decimal(15,2) DEFAULT 0.00,
  `received_date` date DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_documents' => 'CREATE TABLE IF NOT EXISTS `project_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `is_proposal` tinyint(1) NOT NULL DEFAULT 0,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_expenses' => 'CREATE TABLE IF NOT EXISTS `project_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` int(11) DEFAULT 1,
  `cost` decimal(15,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) DEFAULT 0.00,
  `expense_date` date DEFAULT NULL,
  `ordered_from` varchar(255) DEFAULT NULL,
  `ordered_from_url` varchar(500) DEFAULT NULL,
  `paid_by` varchar(255) DEFAULT NULL,
  `invoice_no` varchar(255) DEFAULT NULL,
  `invoice_file` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_links' => 'CREATE TABLE IF NOT EXISTS `project_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `link_name` varchar(255) NOT NULL,
  `link_url` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_phase_payments' => 'CREATE TABLE IF NOT EXISTS `project_phase_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `phase_name` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_team_todos' => 'CREATE TABLE IF NOT EXISTS `project_team_todos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` varchar(50) DEFAULT \'Medium\',
  `status` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_todo_attachments' => 'CREATE TABLE IF NOT EXISTS `project_todo_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `uploaded_by_admin` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_todo_comments' => 'CREATE TABLE IF NOT EXISTS `project_todo_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'system_notifications' => 'CREATE TABLE IF NOT EXISTS `system_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_type` varchar(20) NOT NULL,
  `recipient_id` int(11) DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT \'info\',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'users' => 'CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'vendors' => 'CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vendor_custom_id` VARCHAR(50) DEFAULT NULL,
  `category_section` VARCHAR(100) NOT NULL,
  `sub_group` VARCHAR(150) NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `project_name` VARCHAR(255) DEFAULT NULL,
  `projects_count` INT(11) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
  'project_sop_items' => 'CREATE TABLE IF NOT EXISTS `project_sop_items` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(100) NOT NULL,
  `item_text` TEXT NOT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_sop_checklist' => 'CREATE TABLE IF NOT EXISTS `project_sop_checklist` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT(11) NOT NULL,
  `sop_item_id` INT(11) NOT NULL,
  `is_checked` TINYINT(1) DEFAULT 0,
  `checked_by` VARCHAR(255) DEFAULT NULL,
  `checked_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `unique_project_sop` (`project_id`, `sop_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
  'project_media_assets' => 'CREATE TABLE IF NOT EXISTS `project_media_assets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `asset_name` VARCHAR(255) NOT NULL,
  `dimension_spec` VARCHAR(100) DEFAULT NULL,
  `file_path` TEXT DEFAULT NULL,
  `link_url` TEXT DEFAULT NULL,
  `file_type` VARCHAR(50) DEFAULT \'image\',
  `original_file_name` VARCHAR(255) DEFAULT NULL,
  `uploaded_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  KEY `idx_proj_cat` (`project_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
);

foreach ($tables as $name => $sql) {
  if (mysqli_query($con, $sql)) {
    record_migration($con, "create_" . $name . "_table", 1);
    echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Table <b>$name</b> ready & recorded in migrations.</div>";
  } else {
    echo "<div style='color: #ef4444; margin-bottom: 10px;'>✘ Error creating $name: " . mysqli_error($con) . "</div>";
  }
}

// Seed default SOP items if project_sop_items table is empty
$sop_check = mysqli_query($con, "SELECT COUNT(*) as cnt FROM project_sop_items");
if ($sop_check) {
  $sop_cnt = (int)mysqli_fetch_assoc($sop_check)['cnt'];
  if ($sop_cnt === 0) {
    $default_sop_items = [
      ['SETUP', 'Job Card created in Job Card Tracker', 1],
      ['SETUP', 'Drive Main Project Folder created', 2],
      ['SETUP', 'Social Media folder created (3d Cad Images) - PORTFOLIO SECTION', 3],
      ['SETUP', 'Canva Whiteboard created for design tracking', 4],
      ['EXECUTION', 'Daily work photos uploaded to CRM as work log', 5],
      ['EXECUTION', 'Design changes tracked on Canva Whiteboard (dated)', 6],
      ['EXECUTION', 'Phase-wise site images saved in portfolio folder', 7],
      ['EXECUTION', 'Changelog updated after every revision', 8],
      ['COMPLETION', 'All final files saved on Drive with Date', 9],
      ['COMPLETION', 'Photorealistic renders created and saved', 10],
      ['COMPLETION', 'BOM with vendors created', 11],
      ['COMPLETION', 'Vendors added to Vendor Sheet', 12],
      ['COMPLETION', 'Job Card updated with all final links (Fusion Link + Drive + Canva)', 13],
      ['COMPLETION', 'Project Folder downloaded locally on Master Computer', 14],
      ['MARKETING', 'Add Content of Portfolio on Sheet', 15],
      ['MARKETING', 'Add Content of Case Study on Sheet', 16],
      ['MARKETING', 'Portfolio images organised in Drive by phase', 17],
      ['MARKETING', 'Figma - Social Media content created (Insta, Case Study, etc.)', 18],
      ['MARKETING', 'Portfolio website updated FR + Case Study', 19],
      ['MARKETING', 'Social Media Posts published on all Social Media platforms', 20]
    ];
    $stmt = mysqli_prepare($con, "INSERT INTO project_sop_items (category, item_text, sort_order) VALUES (?, ?, ?)");
    if ($stmt) {
      foreach ($default_sop_items as $item) {
        mysqli_stmt_bind_param($stmt, 'ssi', $item[0], $item[1], $item[2]);
        mysqli_stmt_execute($stmt);
      }
      mysqli_stmt_close($stmt);
      echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Seeded default items into <b>project_sop_items</b>.</div>";
    }
  }
}

// Auto-hash plain-text admin & employee passwords if unhashed
$admins_res = mysqli_query($con, "SELECT admin_id, admin_pass FROM admins");
if ($admins_res) {
  while ($a = mysqli_fetch_assoc($admins_res)) {
    $p = $a['admin_pass'];
    if (!empty($p) && password_get_info($p)['algo'] === 0) {
      $hashed = password_hash($p, PASSWORD_DEFAULT);
      $aid = (int)$a['admin_id'];
      $stmt = mysqli_prepare($con, "UPDATE admins SET admin_pass=? WHERE admin_id=?");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $hashed, $aid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
      }
    }
  }
  echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Admin passwords verified & hashed.</div>";
}

$emps_res = mysqli_query($con, "SELECT id, password FROM emp_list");
if ($emps_res) {
  while ($e = mysqli_fetch_assoc($emps_res)) {
    $p = $e['password'];
    if (!empty($p) && password_get_info($p)['algo'] === 0) {
      $hashed = password_hash($p, PASSWORD_DEFAULT);
      $eid = (int)$e['id'];
      $stmt = mysqli_prepare($con, "UPDATE emp_list SET password=? WHERE id=?");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $hashed, $eid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
      }
    }
  }
  echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Employee passwords verified & hashed.</div>";
}

// Ensure clients mobile column is expanded to VARCHAR(50) for international phone numbers
@mysqli_query($con, "ALTER TABLE clients MODIFY mobile VARCHAR(50)");
@mysqli_query($con, "ALTER TABLE admins MODIFY admin_contact VARCHAR(50)");
@mysqli_query($con, "ALTER TABLE vendors MODIFY phone VARCHAR(50)");
@mysqli_query($con, "ALTER TABLE leads MODIFY phone VARCHAR(50)");
$chk_v_proj = mysqli_query($con, "SHOW COLUMNS FROM `vendors` LIKE 'project_name'");
if ($chk_v_proj && mysqli_num_rows($chk_v_proj) == 0) {
  @mysqli_query($con, "ALTER TABLE `vendors` ADD COLUMN `project_name` VARCHAR(255) NULL DEFAULT NULL AFTER `address`");
}

// Auto-sync any other existing DB tables into migrations table
$db_tables_res = mysqli_query($con, "SHOW TABLES");
if ($db_tables_res) {
  while ($row = mysqli_fetch_row($db_tables_res)) {
    $t_name = $row[0];
    if ($t_name !== "migrations") {
      record_migration($con, "create_" . $t_name . "_table", 1);
    }
  }
}

echo "<div style='margin-top: 40px; padding: 20px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0; color: #166534;'>";
echo "<strong>Success!</strong> All database tables are synchronized and registered in the <code>migrations</code> table.";
echo "</div>";
echo "</div>";
