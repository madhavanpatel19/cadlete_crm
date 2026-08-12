<?php
include("admin_area/includes/db.php");

echo "<div style='font-family: sans-serif; padding: 40px; line-height: 1.6; max-width: 800px; margin: 0 auto;'>";
echo "<h1 style='color: #1e293b;'>Cadlete Database Setup</h1>";
echo "<p style='color: #64748b;'>Initializing database synchronization...</p><hr style='border: 1px solid #f1f5f9; margin: 20px 0;'>";

$tables = [
    "leads" => "CREATE TABLE IF NOT EXISTS leads (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        client_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255),
        company_name VARCHAR(255),
        project_name VARCHAR(255),
        description TEXT,
        remark TEXT,
        budget VARCHAR(100),
        currency VARCHAR(10) DEFAULT 'INR',
        lead_source VARCHAR(255),
        status ENUM('active', 'future', 'expired') DEFAULT 'active',
        followup_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "lead_followups" => "CREATE TABLE IF NOT EXISTS lead_followups (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        lead_id INT(11) NOT NULL,
        followup_date DATE NOT NULL,
        followup_method VARCHAR(100),
        followup_type VARCHAR(100),
        remark TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "lead_sources" => "CREATE TABLE IF NOT EXISTS lead_sources (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        source_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "clients" => "CREATE TABLE IF NOT EXISTS clients (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        image VARCHAR(255),
        name VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        email VARCHAR(255),
        country VARCHAR(255),
        company_name VARCHAR(255),
        website VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "client_projects" => "CREATE TABLE IF NOT EXISTS client_projects (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        client_id INT(11) NOT NULL,
        project_name VARCHAR(255) NOT NULL,
        project_date DATE,
        budget DECIMAL(15,2) DEFAULT 0,
        currency VARCHAR(10) DEFAULT 'INR',
        status VARCHAR(50) DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "client_project_remarks" => "CREATE TABLE IF NOT EXISTS client_project_remarks (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        project_id INT(11) NOT NULL,
        remark TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "project_budget_phases" => "CREATE TABLE IF NOT EXISTS project_budget_phases (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        project_id INT(11) NOT NULL,
        phase_name VARCHAR(255) NOT NULL,
        description TEXT,
        cost DECIMAL(15,2) DEFAULT 0,
        received_amount DECIMAL(15,2) DEFAULT 0,
        received_date DATE,
        remark TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "project_phase_payments" => "CREATE TABLE IF NOT EXISTS project_phase_payments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        project_id INT(11) NOT NULL,
        phase_name VARCHAR(255) NULL,
        amount DECIMAL(15,2) DEFAULT 0,
        payment_date DATE,
        payment_method VARCHAR(100),
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "project_documents" => "CREATE TABLE IF NOT EXISTS project_documents (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        project_id INT(11) NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "project_links" => "CREATE TABLE IF NOT EXISTS project_links (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        project_id INT(11) NOT NULL,
        link_name VARCHAR(255) NOT NULL,
        link_url TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "leave_types" => "CREATE TABLE IF NOT EXISTS leave_types (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        leave_name VARCHAR(255) NOT NULL,
        num_of_leave INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "leave_applications" => "CREATE TABLE IF NOT EXISTS leave_applications (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        emp_id INT(11) NOT NULL,
        leave_type_id INT(11) NOT NULL,
        leave_from DATE NOT NULL,
        leave_to DATE NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "announcements" => "CREATE TABLE IF NOT EXISTS announcements (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "announcement_read" => "CREATE TABLE IF NOT EXISTS announcement_read (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT(11) NOT NULL,
        emp_id INT(11) NOT NULL
    )",
    "company_links" => "CREATE TABLE IF NOT EXISTS company_links (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        link_name VARCHAR(255) NOT NULL,
        link_url TEXT NOT NULL,
        category VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "experience_letters" => "CREATE TABLE IF NOT EXISTS experience_letters (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        number VARCHAR(20),
        designation VARCHAR(255),
        join_date DATE,
        relieve_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $name => $sql) {
    if (mysqli_query($con, $sql)) {
        echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Table <b>$name</b> ready.</div>";
    } else {
        echo "<div style='color: #ef4444; margin-bottom: 10px;'>✘ Error creating $name: " . mysqli_error($con) . "</div>";
    }
}

// Check for missing columns in existing tables
echo "<h3 style='margin-top: 30px; color: #1e293b;'>Checking columns...</h3>";

// 1. Add work_photos to attendance
$check_col = mysqli_query($con, "SHOW COLUMNS FROM attendance LIKE 'work_photos'");
if (mysqli_num_rows($check_col) == 0) {
    if (mysqli_query($con, "ALTER TABLE attendance ADD COLUMN work_photos TEXT DEFAULT NULL AFTER remarks")) {
        echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Column <b>work_photos</b> added to attendance.</div>";
    } else {
        echo "<div style='color: #ef4444; margin-bottom: 10px;'>✘ Error adding work_photos: " . mysqli_error($con) . "</div>";
    }
} else {
    echo "<div style='color: #64748b; margin-bottom: 10px;'>• Column <b>work_photos</b> already exists.</div>";
}

// 1b. Ensure remarks column in attendance is TEXT
mysqli_query($con, "ALTER TABLE attendance MODIFY COLUMN remarks TEXT");

// 2. Add currency to leads (just in case)
$check_col = mysqli_query($con, "SHOW COLUMNS FROM leads LIKE 'currency'");
if (mysqli_num_rows($check_col) == 0) {
    if (mysqli_query($con, "ALTER TABLE leads ADD COLUMN currency VARCHAR(10) DEFAULT 'INR' AFTER budget")) {
        echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Column <b>currency</b> added to leads.</div>";
    }
}

// 4. Add is_proposal to project_documents
$check_col = mysqli_query($con, "SHOW COLUMNS FROM project_documents LIKE 'is_proposal'");
if ($check_col && mysqli_num_rows($check_col) == 0) {
    if (mysqli_query($con, "ALTER TABLE project_documents ADD COLUMN is_proposal TINYINT(1) NOT NULL DEFAULT 0 AFTER document_name")) {
        echo "<div style='color: #10b981; margin-bottom: 10px;'>✔ Column <b>is_proposal</b> added to project_documents.</div>";
    } else {
        echo "<div style='color: #ef4444; margin-bottom: 10px;'>✘ Error adding is_proposal: " . mysqli_error($con) . "</div>";
    }
}


echo "<div style='margin-top: 40px; padding: 20px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0; color: #166534;'>";
echo "<strong>Success!</strong> Database synchronization complete. You can now delete this file and continue using the application.";
echo "</div>";
echo "</div>";
