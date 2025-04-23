CREATE TABLE purok (
    purok_id INT PRIMARY KEY AUTO_INCREMENT,
    purok_number VARCHAR(10) NOT NULL UNIQUE,
    purok_name VARCHAR(50),                   -- Optional: "Purok Malinis", "Purok Masagana"
    leader_id INT,                            -- Resident ID of purok leader (optional)
    date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES residents(resident_id)
);

CREATE TABLE residents (
    resident_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    birth_date DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other'),
    civil_status ENUM('Single', 'Married', 'Widowed', 'Divorced'),
    contact_number VARCHAR(15),
    email VARCHAR(100),
    purok_id INT NOT NULL, -- Links to purok table
    household_id INT,  -- Links to household table
    is_voter BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    date_registered DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purok_id) REFERENCES purok(purok_id),
    FOREIGN KEY (household_id) REFERENCES households(household_id)
);


CREATE TABLE households (
    household_id INT PRIMARY KEY AUTO_INCREMENT,
    address TEXT NOT NULL,
    purok_id INT NOT NULL,
    household_head_id INT,  -- Resident ID of the head
    date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purok_id) REFERENCES purok(purok_id),
    FOREIGN KEY (household_head_id) REFERENCES residents(resident_id)
);

CREATE TABLE documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    document_type ENUM('Barangay Clearance', 'Indigency Certificate', 'Residency Certificate', 'Business Permit'),
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed'),
    remarks TEXT,
    issued_by INT,  -- Barangay official ID
    fee DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (resident_id) REFERENCES residents(resident_id),
    FOREIGN KEY (issued_by) REFERENCES barangay_officials(official_id)
);

CREATE TABLE complaints (
    complaint_id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    complaint_type ENUM('Infrastructure', 'Noise', 'Dispute', 'Sanitation', 'Other'),
    description TEXT NOT NULL,
    date_reported DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Open', 'In Progress', 'Resolved', 'Closed'),
    resolved_by INT,  -- Official or tanod assigned
    resolution_notes TEXT,
    FOREIGN KEY (resident_id) REFERENCES residents(resident_id),
    FOREIGN KEY (resolved_by) REFERENCES barangay_officials(official_id)
);

CREATE TABLE barangay_officials (
    official_id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    position ENUM('Captain', 'Kagawad', 'Secretary', 'Treasurer', 'Tanod', 'SK Chair', 'Health Worker'),
    term_start DATE NOT NULL,
    term_end DATE,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (resident_id) REFERENCES residents(resident_id)
);

CREATE TABLE health_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT NOT NULL,
    record_type ENUM('Vaccination', 'PWD', 'Senior Citizen', 'Prenatal', 'Other'),
    details TEXT,
    date_recorded DATETIME DEFAULT CURRENT_TIMESTAMP,
    recorded_by INT,  -- Health worker ID
    FOREIGN KEY (resident_id) REFERENCES residents(resident_id),
    FOREIGN KEY (recorded_by) REFERENCES barangay_officials(official_id)
);

CREATE TABLE financial_transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    resident_id INT,
    transaction_type ENUM('Fee Payment', 'Budget Allocation', 'Expense'),
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    date_recorded DATETIME DEFAULT CURRENT_TIMESTAMP,
    received_by INT,  -- Official ID
    FOREIGN KEY (resident_id) REFERENCES residents(resident_id),
    FOREIGN KEY (received_by) REFERENCES barangay_officials(official_id)
);