-- ======================================================================
-- LEAVE POLICY MIGRATION SCRIPT
-- ======================================================================
-- Purpose: Set up Country-Based Leave Policy system for HR application
-- Author: Development Team
-- Date: 2026-02-03
-- Version: 4.0 (Tiered Sick Leave with Salary Deductions)
-- 
-- USAGE:
--   Run this script ONCE in both local and production databases.
--   Uses CREATE TABLE IF NOT EXISTS for new tables.
--   Uses stored procedures for safe column additions (MySQL 5.x compatible).
--
-- COUNTRIES COVERED:
--   Saudi Arabia (SA), Egypt (EG), Kuwait (KW), Qatar (QA)
-- ======================================================================


-- ======================================================================
-- SECTION 1: CREATE NEW TABLES
-- ======================================================================

CREATE TABLE IF NOT EXISTS `ci_leave_policy_countries` (
  `policy_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = System Default',
  `country_code` varchar(5) NOT NULL COMMENT 'SA, EG, KW, QA',
  `leave_type` varchar(50) NOT NULL COMMENT 'annual, sick, maternity, hajj, emergency',
  `tier_order` int(11) NOT NULL DEFAULT 1,
  `service_years_min` float NOT NULL DEFAULT 0,
  `service_years_max` float DEFAULT NULL,
  `entitlement_days` int(11) NOT NULL DEFAULT 0,
  `is_paid` tinyint(1) NOT NULL DEFAULT 1,
  `payment_percentage` int(11) NOT NULL DEFAULT 100,
  `max_consecutive_days` int(11) DEFAULT NULL,
  `requires_documentation` tinyint(1) NOT NULL DEFAULT 0,
  `documentation_after_days` int(11) DEFAULT NULL,
  `is_one_time` tinyint(1) NOT NULL DEFAULT 0,
  `deduct_from_annual` tinyint(1) NOT NULL DEFAULT 0,
  `policy_description_en` text DEFAULT NULL,
  `policy_description_ar` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`policy_id`),
  KEY `idx_country_leave` (`country_code`, `leave_type`, `company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `ci_employee_leave_balances` (
  `balance_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `year` int(4) NOT NULL,
  `total_entitled` float NOT NULL,
  `used_days` float NOT NULL DEFAULT 0,
  `pending_days` float NOT NULL DEFAULT 0,
  `remaining_days` float NOT NULL,
  `carried_forward` float NOT NULL DEFAULT 0,
  `last_calculated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY `uk_emp_leave_year` (`employee_id`, `leave_type`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `ci_leave_policy_mapping` (
  `mapping_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `system_leave_type` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `uk_company_leave_type` (`company_id`, `leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ======================================================================
-- NEW TABLE: Track one-time leave usage (Hajj, etc.)
-- ======================================================================
CREATE TABLE IF NOT EXISTS `ci_employee_onetime_leaves` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL COMMENT 'hajj, or other one-time leave types',
  `leave_application_id` int(11) DEFAULT NULL COMMENT 'Reference to ci_leave_applications',
  `taken_date` date NOT NULL COMMENT 'Date when the one-time leave was taken',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_emp_onetime_leave` (`employee_id`, `leave_type`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Tracks one-time leaves like Hajj';


-- ======================================================================
-- NEW TABLE: Track tiered sick leave salary deductions
-- NOTE: Obsolete. Using existing 'ci_payslip_statutory_deductions' instead.
-- ======================================================================
/*
CREATE TABLE IF NOT EXISTS `ci_sick_leave_deductions` (
  `deduction_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_application_id` int(11) NOT NULL COMMENT 'Reference to ci_leave_applications',
  `salary_month` varchar(10) NOT NULL COMMENT 'YYYY-MM format for payroll month',
  `tier_order` int(11) NOT NULL COMMENT '1=Days 1-30, 2=Days 31-90, 3=Days 91-120',
  `days_in_tier` decimal(5,2) NOT NULL COMMENT 'Number of sick days falling in this tier',
  `daily_rate` decimal(15,2) NOT NULL COMMENT 'basic_salary / 30',
  `deduction_percentage` int(11) NOT NULL COMMENT '25 for tier 2, 100 for tier 3',
  `deduction_amount` decimal(15,2) NOT NULL COMMENT 'days_in_tier * daily_rate * (deduction_percentage/100)',
  `pay_title` varchar(200) NOT NULL COMMENT 'Deduction label for payslip',
  `is_processed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Added to payslip',
  `payslip_id` int(11) DEFAULT NULL COMMENT 'Reference to ci_payslips when processed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`deduction_id`),
  KEY `idx_emp_month` (`employee_id`, `salary_month`),
  KEY `idx_leave_app` (`leave_application_id`),
  KEY `idx_unprocessed` (`is_processed`, `salary_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Tracks salary deductions for tiered sick leave (SA, QA)';
*/


-- ======================================================================
-- SECTION 2: MODIFY EXISTING TABLES (MySQL 5.x Compatible)
-- ======================================================================

DELIMITER //

DROP PROCEDURE IF EXISTS AddColumnIfNotExists//

CREATE PROCEDURE AddColumnIfNotExists(
    IN p_table VARCHAR(100),
    IN p_column VARCHAR(100),
    IN p_definition VARCHAR(500)
)
BEGIN
    SET @dbname = DATABASE();
    SET @tablename = p_table;
    SET @columnname = p_column;
    SET @preparedStatement = (SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = @dbname 
         AND TABLE_NAME = @tablename 
         AND COLUMN_NAME = @columnname) > 0,
        'SELECT 1',
        CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` ', p_definition)
    ));
    PREPARE alterIfNotExists FROM @preparedStatement;
    EXECUTE alterIfNotExists;
    DEALLOCATE PREPARE alterIfNotExists;
END//

DELIMITER ;

-- Company Settings: Add leave policy country
CALL AddColumnIfNotExists('ci_erp_company_settings', 'leave_policy_country', 
    "varchar(10) DEFAULT NULL COMMENT 'SA, EG, KW, QA'");

-- User Details: Add disability flag for Egypt policy
CALL AddColumnIfNotExists('ci_erp_users_details', 'is_disability', 
    "tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Yes, 0=No'");

-- Leave Applications: Add policy tracking fields
CALL AddColumnIfNotExists('ci_leave_applications', 'country_code', "varchar(5) DEFAULT NULL");
CALL AddColumnIfNotExists('ci_leave_applications', 'service_years', "float DEFAULT NULL");
CALL AddColumnIfNotExists('ci_leave_applications', 'policy_id', "int(11) DEFAULT NULL");
CALL AddColumnIfNotExists('ci_leave_applications', 'calculated_days', "float DEFAULT NULL");
CALL AddColumnIfNotExists('ci_leave_applications', 'payment_percentage', "int(11) DEFAULT 100");
CALL AddColumnIfNotExists('ci_leave_applications', 'documentation_provided', "tinyint(1) DEFAULT 0");

-- Leave Applications: Add tier tracking for tiered sick leave
CALL AddColumnIfNotExists('ci_leave_applications', 'tier_order', 
    "int(11) DEFAULT 1 COMMENT 'Which tier of the policy was applied (for sick leave)'");

-- Leave Applications: Add salary deduction tracking
CALL AddColumnIfNotExists('ci_leave_applications', 'salary_deduction_applied', 
    "tinyint(1) DEFAULT 0 COMMENT '1=Deduction processed in payroll'");

-- Leave Applications: Add holiday inclusion flag
CALL AddColumnIfNotExists('ci_leave_applications', 'include_holidays', 
    "tinyint(1) DEFAULT 0 COMMENT '1=Include holidays in leave duration calculation'");

DROP PROCEDURE IF EXISTS AddColumnIfNotExists;


-- ======================================================================
-- SECTION 3: SEED POLICY DATA
-- ======================================================================

DELETE FROM `ci_leave_policy_countries` WHERE `company_id` = 0;


-- ======================================================================
-- SAUDI ARABIA (SA) - نظام العمل السعودي
-- ======================================================================

-- Annual Leave - الإجازة السنوية
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'SA', 'annual', 1, 0, 1, 0, 100, 
   'No annual leave entitlement before completing 1 year of service', 
   'لا يستحق الموظف إجازة سنوية قبل إتمام سنة واحدة في الخدمة'),
  (0, 'SA', 'annual', 2, 1, 5, 21, 100, 
   '21 days fully paid for employees with 1-5 years of service', 
   '21 يوماً براتب كامل للموظفين الذين أتموا من 1 إلى 5 سنوات'),
  (0, 'SA', 'annual', 3, 5, NULL, 30, 100, 
   '30 days fully paid for employees with 5+ years of service', 
   '30 يوماً براتب كامل للموظفين الذين أتموا 5 سنوات فأكثر');

-- Sick Leave - الإجازة المرضية
-- TIERED: Each tier has different payment percentage
-- tier_order is crucial for tracking which tier applies to current leave
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'SA', 'sick', 1, 0, NULL, 30, 100, 
   'First 30 days: Full salary - no deductions from monthly salary', 
   'أول 30 يوماً: راتب كامل - بدون أي خصم من الراتب الشهري'),
  (0, 'SA', 'sick', 2, 0, NULL, 60, 75, 
   'Days 31-90: 75% salary - 25% deduction from monthly salary', 
   'من اليوم 31 إلى 90: 75% من الراتب - خصم 25% من الراتب الشهري'),
  (0, 'SA', 'sick', 3, 0, NULL, 30, 0, 
   'Days 91-120: Unpaid leave - full salary deduction', 
   'من اليوم 91 إلى 120: إجازة بدون راتب - خصم كامل');

-- Maternity Leave - إجازة الأمومة
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'SA', 'maternity', 1, 0, NULL, 70, 100, 
   'First 70 days: Full salary - no deductions', 
   'أول 70 يوماً: راتب كامل - بدون أي خصم'),
  (0, 'SA', 'maternity', 2, 0, NULL, 60, 0, 
   'Additional days beyond 70: Unpaid leave available', 
   'الأيام الإضافية بعد 70 يوماً: إجازة متاحة بدون راتب');

-- Hajj Leave - إجازة الحج (ONE TIME)
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `is_one_time`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'SA', 'hajj', 1, 2, NULL, 15, 100, 1, 
   '10-15 days fully paid after 2 years service - ONE TIME ONLY during employment', 
   'من 10 إلى 15 يوماً براتب كامل بعد سنتين خدمة - مرة واحدة فقط خلال فترة العمل');

-- Emergency/Death Leave - إجازة الوفاة والطوارئ
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'SA', 'emergency', 1, 0, NULL, 5, 100, 
   'Death of spouse/parent/child: 5 days fully paid', 
   'وفاة الزوج/الزوجة أو الأب/الأم أو الأبناء: 5 أيام براتب كامل'),
  (0, 'SA', 'emergency', 2, 0, NULL, 3, 100, 
   'Death of sibling (brother/sister): 3 days fully paid', 
   'وفاة الأخ/الأخت: 3 أيام براتب كامل'),
  (0, 'SA', 'emergency', 3, 0, NULL, 3, 100, 
   'Paternity leave (new baby for father): 3 days fully paid', 
   'إجازة الأبوة (مولود جديد للأب): 3 أيام براتب كامل');


-- ======================================================================
-- EGYPT (EG) - قانون العمل المصري
-- ======================================================================

-- Annual Leave - الإجازة السنوية
-- Note: Disability handling (45 days) is done in application code by checking is_disability
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'EG', 'annual', 1, 0, 0.5, 0, 100, 
   'No annual leave entitlement before completing 6 months of service', 
   'لا يستحق الموظف إجازة سنوية قبل إتمام 6 أشهر في الخدمة'),
  (0, 'EG', 'annual', 2, 0.5, 1, 15, 100, 
   '15 days fully paid for 6 months to 1 year of service', 
   '15 يوماً براتب كامل للموظفين من 6 أشهر إلى سنة'),
  (0, 'EG', 'annual', 3, 1, 10, 21, 100, 
   '21 days fully paid for 1-10 years of service', 
   '21 يوماً براتب كامل للموظفين من سنة إلى 10 سنوات'),
  (0, 'EG', 'annual', 4, 10, NULL, 30, 100, 
   '30 days fully paid for 10+ years (45 days if employee has disability)', 
   '30 يوماً براتب كامل لأكثر من 10 سنوات (45 يوماً إذا كان الموظف من ذوي الإعاقة)');

-- Sick Leave - الإجازة المرضية
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `requires_documentation`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'EG', 'sick', 1, 0, NULL, 90, 100, 1, 
   'First 3 months (90 days): Full salary + allowances with medical report', 
   'أول 3 أشهر (90 يوماً): راتب كامل + البدلات بتقرير طبي'),
  (0, 'EG', 'sick', 2, 0, NULL, 275, 0, 1, 
   'Days 91-365: Unpaid leave with medical report (up to 12 months total)', 
   'من اليوم 91 إلى 365: إجازة بدون راتب بتقرير طبي (حتى 12 شهراً إجمالي)');

-- Maternity Leave - إجازة الأمومة
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'EG', 'maternity', 1, 0, NULL, 120, 100, 
   '120 days (4 months) fully paid maternity leave', 
   '120 يوماً (4 أشهر) إجازة أمومة براتب كامل');

-- Hajj Leave - إجازة الحج (ONE TIME, requires 5 years)
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `is_one_time`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'EG', 'hajj', 1, 5, NULL, 30, 100, 1, 
   '30 days fully paid after 5 years service - ONE TIME ONLY during employment', 
   '30 يوماً براتب كامل بعد 5 سنوات خدمة - مرة واحدة فقط خلال فترة العمل');

-- Emergency Leave - إجازة طارئة
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'EG', 'emergency', 1, 0, NULL, 3, 100, 
   '3 days fully paid emergency leave', 
   '3 أيام إجازة طارئة براتب كامل');


-- ======================================================================
-- KUWAIT (KW) - قانون العمل الكويتي
-- ======================================================================

-- Annual Leave - الإجازة السنوية
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'KW', 'annual', 1, 0, 0.75, 0, 100, 
   'No annual leave entitlement before completing 9 months of service', 
   'لا يستحق الموظف إجازة سنوية قبل إتمام 9 أشهر في الخدمة'),
  (0, 'KW', 'annual', 2, 0.75, NULL, 30, 100, 
   '30 days fully paid after 9 months of service', 
   '30 يوماً براتب كامل بعد إتمام 9 أشهر');

-- Sick Leave - الإجازة المرضية
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'KW', 'sick', 1, 0, NULL, 75, 100, 
   'Up to 75 days per year fully paid with medical certificate', 
   'حتى 75 يوماً سنوياً براتب كامل بشهادة طبية');

-- Maternity Leave - إجازة الأمومة
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'KW', 'maternity', 1, 0, NULL, 70, 100, 
   'First 70 days: Full salary', 
   'أول 70 يوماً: راتب كامل'),
  (0, 'KW', 'maternity', 2, 0, NULL, 120, 0, 
   'Additional 120 days (4 months) available as unpaid leave', 
   '120 يوماً إضافية (4 أشهر) متاحة كإجازة بدون راتب');

-- Hajj Leave - إجازة الحج (ONE TIME)
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `is_one_time`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'KW', 'hajj', 1, 2, NULL, 21, 100, 1, 
   '21 days fully paid after 2 years service - ONE TIME ONLY', 
   '21 يوماً براتب كامل بعد سنتين خدمة - مرة واحدة فقط');

-- Emergency/Death Leave - إجازة الوفاة والطوارئ
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'KW', 'emergency', 1, 0, NULL, 3, 100, 
   'General emergency leave: 3 days fully paid', 
   'إجازة طوارئ عامة: 3 أيام براتب كامل'),
  (0, 'KW', 'emergency', 2, 0, NULL, 130, 100, 
   'Female employee - husband death: 130 days (4 months + 10 days) fully paid (Iddah period)', 
   'الموظفة الأنثى - وفاة الزوج: 130 يوماً (4 أشهر و10 أيام) براتب كامل (عدة الوفاة)');


-- ======================================================================
-- QATAR (QA) - قانون العمل القطري
-- ======================================================================

-- Annual Leave - الإجازة السنوية
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'QA', 'annual', 1, 0, 5, 21, 100, 
   '21 days (3 weeks) fully paid for less than 5 years service', 
   '21 يوماً (3 أسابيع) براتب كامل لأقل من 5 سنوات خدمة'),
  (0, 'QA', 'annual', 2, 5, NULL, 28, 100, 
   '28 days (4 weeks) fully paid for 5+ years service', 
   '28 يوماً (4 أسابيع) براتب كامل لـ 5 سنوات خدمة فأكثر');

-- Sick Leave - الإجازة المرضية (TIERED)
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'QA', 'sick', 1, 0.25, NULL, 14, 100, 
   'First 2 weeks: Full salary (eligible after 3 months service)', 
   'أول أسبوعين: راتب كامل (بعد إتمام 3 أشهر خدمة)'),
  (0, 'QA', 'sick', 2, 0.25, NULL, 28, 50, 
   'Next 4 weeks: 50% salary (50% deducted)', 
   'الـ 4 أسابيع التالية: 50% من الراتب (خصم 50%)'),
  (0, 'QA', 'sick', 3, 0.25, NULL, 42, 0, 
   'Next 6 weeks: Unpaid (100% deducted)', 
   'الـ 6 أسابيع التالية: بدون راتب (خصم 100%)');

-- Maternity Leave - إجازة الأمومة
INSERT INTO `ci_leave_policy_countries` 
  (`company_id`, `country_code`, `leave_type`, `tier_order`, `service_years_min`, `service_years_max`, `entitlement_days`, `payment_percentage`, `policy_description_en`, `policy_description_ar`) 
VALUES
  (0, 'QA', 'maternity', 1, 1, NULL, 50, 100, 
   '50 days fully paid (after 1 year service)', 
   '50 يوماً براتب كامل (بعد إتمام سنة خدمة)');

-- NOTE: Qatar does not have explicit Hajj or Emergency leave policies


-- ======================================================================
-- SECTION 4: SEED MAPPING DATA (System Defaults)
-- ======================================================================

DELETE FROM `ci_leave_policy_mapping` WHERE `company_id` = 0;

-- SICK (Various IDs found in constants)
INSERT INTO `ci_leave_policy_mapping` (`company_id`, `leave_type_id`, `system_leave_type`) VALUES 
(0, 193, 'sick'), (0, 195, 'sick'), (0, 219, 'sick'), (0, 279, 'sick'), (0, 283, 'sick'), (0, 286, 'sick'), (0, 296, 'sick'), (0, 485, 'sick');

-- ANNUAL
INSERT INTO `ci_leave_policy_mapping` (`company_id`, `leave_type_id`, `system_leave_type`) VALUES 
(0, 190, 'annual'), (0, 282, 'annual'), (0, 285, 'annual'), (0, 295, 'annual'), (0, 302, 'annual'), (0, 481, 'annual');

-- MATERNITY
INSERT INTO `ci_leave_policy_mapping` (`company_id`, `leave_type_id`, `system_leave_type`) VALUES 
(0, 119, 'maternity'), (0, 484, 'maternity');

-- EMERGENCY
INSERT INTO `ci_leave_policy_mapping` (`company_id`, `leave_type_id`, `system_leave_type`) VALUES 
(0, 284, 'emergency'), (0, 287, 'emergency'), (0, 303, 'emergency'), (0, 482, 'emergency');

-- HAJJ
INSERT INTO `ci_leave_policy_mapping` (`company_id`, `leave_type_id`, `system_leave_type`) VALUES 
(0, 288, 'hajj'), (0, 483, 'hajj');

-- ======================================================================
-- END OF MIGRATION SCRIPT
-- ======================================================================
-- 
-- NEW TABLES CREATED:
--   - ci_leave_policy_countries: Country-specific leave policies
--   - ci_employee_leave_balances: Employee leave balance tracking
--   - ci_leave_policy_mapping: Maps company leave types to system types
--   - ci_employee_onetime_leaves: Tracks one-time leave usage (Hajj)
--   - ci_sick_leave_deductions: Tracks salary deductions for tiered sick leave
--
-- NEW COLUMNS ADDED TO ci_leave_applications:
--   - country_code: Country policy applied
--   - service_years: Employee service years at time of request
--   - policy_id: Reference to ci_leave_policy_countries
--   - calculated_days: Leave days calculated
--   - payment_percentage: Percentage of salary paid (100, 75, 50, 0)
--   - documentation_provided: Medical documentation flag
--   - tier_order: Which tier of tiered policy was applied
--   - salary_deduction_applied: Whether payroll has processed the deduction
--
-- TIERED SICK LEAVE EXPLANATION:
--   For countries with tiered sick leave (SA, QA):
--   
--   SAUDI ARABIA (SA):
--      - Tier 1: Days 1-30 = 100% paid (0% deduction)
--      - Tier 2: Days 31-90 = 75% paid (25% deduction) - "خصم إجازة مرضية (25%)"
--      - Tier 3: Days 91-120 = 0% paid (100% deduction) - "خصم إجازة مرضية (100%)"
--
--   QATAR (QA):
--      - Tier 1: Days 1-14 = 100% paid (0% deduction)
--      - Tier 2: Days 15-42 = 50% paid (50% deduction)
--      - Tier 3: Days 43-84 = 0% paid (100% deduction)
--
--   DEDUCTION CALCULATION:
--      daily_rate = basic_salary / 30
--      deduction_amount = days_in_tier * daily_rate * (deduction_percentage / 100)
--   
--   WORKFLOW:
--      1. Employee requests sick leave
--      2. System splits request across tiers if needed
--      3. On approval, deduction records created in ci_sick_leave_deductions
--      4. Payroll reads deductions for the month and adds to payslip
--      5. Mark is_processed = 1 after payslip generated
--
-- ======================================================================
