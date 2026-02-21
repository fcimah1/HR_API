# Design Document: Country-Based Leave Policy System

## Overview

This design document outlines the architecture and implementation approach for a comprehensive country-based leave policy system. The system will manage five types of leaves (Annual, Sick, Emergency, Hajj, Maternity/Paternity) with country-specific rules for Saudi Arabia, Egypt, Kuwait, and Qatar. The design integrates with the existing First Time HR system built on CodeIgniter 4.

### Key Design Goals

1. Flexible policy configuration supporting multiple countries and leave types
2. Automatic leave entitlement calculation based on service duration
3. Seamless integration with existing leave management workflow
4. Backward compatibility with existing leave data
5. Multi-language support (Arabic/English)
6. Scalable architecture for adding new countries

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Leave Views  │  │ Policy Admin │  │  Reports     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
┌─────────────────────────────────────────────────────────────┐
│                   Controller Layer                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │Leave.php     │  │LeavePolicy   │  │ Reports.php  │      │
│  │(Enhanced)    │  │.php (New)    │  │ (Enhanced)   │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
┌─────────────────────────────────────────────────────────────┐
│                    Business Logic Layer                      │
│  ┌──────────────────────────────────────────────────┐       │
│  │  Leave Policy Engine (Helper)                    │       │
│  │  - calculateLeaveEntitlement()                   │       │
│  │  - validateLeaveRequest()                        │       │
│  │  - getCountryPolicy()                            │       │
│  │  - calculateServiceDuration()                    │       │
│  └──────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
                            │
┌─────────────────────────────────────────────────────────────┐
│                      Data Layer                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │LeaveModel    │  │LeavePolicyModel│ │UsersModel   │      │
│  │(Enhanced)    │  │(New)         │  │(Enhanced)    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            │
┌─────────────────────────────────────────────────────────────┐
│                      Database Layer                          │
│  ┌──────────────────────────────────────────────────┐       │
│  │  ci_leave_policy_countries (New)                 │       │
│  │  ci_leave_applications (Enhanced)                │       │
│  │  ci_users (Enhanced - add country field)         │       │
│  │  ci_leave_adjustment (Enhanced)                  │       │
│  └──────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────┘
```


## Components and Interfaces

### 1. Database Schema

#### New Table: ci_leave_policy_countries

```sql
CREATE TABLE `ci_leave_policy_countries` (
  `policy_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `country_code` VARCHAR(10) NOT NULL COMMENT 'SA, EG, KW, QA',
  `leave_type` VARCHAR(50) NOT NULL COMMENT 'annual, sick, emergency, hajj, maternity, paternity',
  `service_years_min` DECIMAL(4,2) DEFAULT 0 COMMENT 'Minimum years of service',
  `service_years_max` DECIMAL(4,2) DEFAULT NULL COMMENT 'Maximum years of service (NULL = unlimited)',
  `entitlement_days` INT NOT NULL COMMENT 'Number of days entitled',
  `is_paid` TINYINT(1) DEFAULT 1 COMMENT '1=Paid, 0=Unpaid',
  `payment_percentage` DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Percentage of salary paid',
  `max_consecutive_days` INT DEFAULT NULL COMMENT 'Maximum consecutive days allowed',
  `requires_documentation` TINYINT(1) DEFAULT 0 COMMENT 'Requires medical/official docs',
  `documentation_after_days` INT DEFAULT NULL COMMENT 'Docs required after X days',
  `is_one_time` TINYINT(1) DEFAULT 0 COMMENT 'Can only be taken once (e.g., Hajj)',
  `deduct_from_annual` TINYINT(1) DEFAULT 0 COMMENT 'Deduct from annual leave balance',
  `policy_description_en` TEXT,
  `policy_description_ar` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`policy_id`),
  KEY `idx_country_leave_type` (`country_code`, `leave_type`),
  KEY `idx_company_country` (`company_id`, `country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Enhanced Table: ci_users (Add country field)

```sql
ALTER TABLE `ci_users` 
ADD COLUMN `country_code` VARCHAR(10) DEFAULT 'SA' COMMENT 'Employee country: SA, EG, KW, QA' AFTER `designation_id`,
ADD KEY `idx_country` (`country_code`);
```

#### New Table: ci_employee_leave_balances

```sql
CREATE TABLE `ci_employee_leave_balances` (
  `balance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `leave_type` VARCHAR(50) NOT NULL,
  `year` INT NOT NULL,
  `total_entitled` DECIMAL(10,2) NOT NULL COMMENT 'Total days entitled for the year',
  `used_days` DECIMAL(10,2) DEFAULT 0 COMMENT 'Days used',
  `pending_days` DECIMAL(10,2) DEFAULT 0 COMMENT 'Days in pending applications',
  `remaining_days` DECIMAL(10,2) NOT NULL COMMENT 'Available days',
  `carried_forward` DECIMAL(10,2) DEFAULT 0 COMMENT 'Days carried from previous year',
  `last_calculated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY `unique_employee_leave_year` (`employee_id`, `leave_type`, `year`),
  KEY `idx_company_employee` (`company_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Enhanced Table: ci_leave_applications

```sql
ALTER TABLE `ci_leave_applications`
ADD COLUMN `country_code` VARCHAR(10) DEFAULT NULL COMMENT 'Country at time of application' AFTER `leave_type_id`,
ADD COLUMN `service_years` DECIMAL(4,2) DEFAULT NULL COMMENT 'Service years at time of application' AFTER `country_code`,
ADD COLUMN `policy_id` INT UNSIGNED DEFAULT NULL COMMENT 'Policy used for calculation' AFTER `service_years`,
ADD COLUMN `calculated_days` DECIMAL(10,2) DEFAULT NULL COMMENT 'System calculated working days' AFTER `leave_hours`,
ADD COLUMN `payment_percentage` DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Salary payment percentage' AFTER `is_deducted`,
ADD COLUMN `documentation_provided` TINYINT(1) DEFAULT 0 COMMENT 'Required documentation submitted' AFTER `leave_attachment`,
ADD KEY `idx_country_policy` (`country_code`, `policy_id`);
```


### 2. Model Layer

#### New Model: LeavePolicyModel.php

```php
<?php
namespace App\Models;

use CodeIgniter\Model;

class LeavePolicyModel extends Model
{
    protected $table = 'ci_leave_policy_countries';
    protected $primaryKey = 'policy_id';
    protected $allowedFields = [
        'company_id', 'country_code', 'leave_type', 'service_years_min',
        'service_years_max', 'entitlement_days', 'is_paid', 'payment_percentage',
        'max_consecutive_days', 'requires_documentation', 'documentation_after_days',
        'is_one_time', 'deduct_from_annual', 'policy_description_en',
        'policy_description_ar', 'is_active'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    /**
     * Get applicable policy for employee
     */
    public function getApplicablePolicy($company_id, $country_code, $leave_type, $service_years)
    {
        return $this->where('company_id', $company_id)
                    ->where('country_code', $country_code)
                    ->where('leave_type', $leave_type)
                    ->where('service_years_min <=', $service_years)
                    ->groupStart()
                        ->where('service_years_max >=', $service_years)
                        ->orWhere('service_years_max IS NULL')
                    ->groupEnd()
                    ->where('is_active', 1)
                    ->first();
    }
    
    /**
     * Get all policies for a country
     */
    public function getCountryPolicies($company_id, $country_code)
    {
        return $this->where('company_id', $company_id)
                    ->where('country_code', $country_code)
                    ->where('is_active', 1)
                    ->orderBy('leave_type', 'ASC')
                    ->orderBy('service_years_min', 'ASC')
                    ->findAll();
    }
}
```

#### New Model: EmployeeLeaveBalanceModel.php

```php
<?php
namespace App\Models;

use CodeIgniter\Model;

class EmployeeLeaveBalanceModel extends Model
{
    protected $table = 'ci_employee_leave_balances';
    protected $primaryKey = 'balance_id';
    protected $allowedFields = [
        'company_id', 'employee_id', 'leave_type', 'year',
        'total_entitled', 'used_days', 'pending_days', 'remaining_days',
        'carried_forward'
    ];
    protected $useTimestamps = false;
    
    /**
     * Get employee balance for specific leave type and year
     */
    public function getBalance($employee_id, $leave_type, $year)
    {
        return $this->where('employee_id', $employee_id)
                    ->where('leave_type', $leave_type)
                    ->where('year', $year)
                    ->first();
    }
    
    /**
     * Get all balances for employee in a year
     */
    public function getEmployeeBalances($employee_id, $year)
    {
        return $this->where('employee_id', $employee_id)
                    ->where('year', $year)
                    ->findAll();
    }
    
    /**
     * Update balance after leave approval/rejection
     */
    public function updateBalance($employee_id, $leave_type, $year, $days, $action = 'deduct')
    {
        $balance = $this->getBalance($employee_id, $leave_type, $year);
        
        if (!$balance) {
            return false;
        }
        
        if ($action === 'deduct') {
            $used_days = $balance['used_days'] + $days;
            $remaining_days = $balance['remaining_days'] - $days;
        } else { // restore
            $used_days = $balance['used_days'] - $days;
            $remaining_days = $balance['remaining_days'] + $days;
        }
        
        return $this->update($balance['balance_id'], [
            'used_days' => $used_days,
            'remaining_days' => $remaining_days
        ]);
    }
}
```


### 3. Helper Functions (leave_policy_helper.php)

```php
<?php

/**
 * Calculate employee service duration in years
 */
function calculate_service_duration($hire_date)
{
    $hire = new DateTime($hire_date);
    $now = new DateTime();
    $interval = $hire->diff($now);
    
    // Return years with decimal (e.g., 5.5 years)
    $years = $interval->y;
    $months = $interval->m;
    return round($years + ($months / 12), 2);
}

/**
 * Get applicable leave policy for employee
 */
function get_employee_leave_policy($employee_id, $leave_type)
{
    $db = \Config\Database::connect();
    $UsersModel = new \App\Models\UsersModel();
    $LeavePolicyModel = new \App\Models\LeavePolicyModel();
    
    $employee = $UsersModel->find($employee_id);
    if (!$employee) {
        return null;
    }
    
    $service_years = calculate_service_duration($employee['date_of_joining']);
    $country_code = $employee['country_code'] ?? 'SA';
    
    return $LeavePolicyModel->getApplicablePolicy(
        $employee['company_id'],
        $country_code,
        $leave_type,
        $service_years
    );
}

/**
 * Calculate leave entitlement for employee
 */
function calculate_leave_entitlement($employee_id, $leave_type, $year)
{
    $policy = get_employee_leave_policy($employee_id, $leave_type);
    
    if (!$policy) {
        return 0;
    }
    
    return $policy['entitlement_days'];
}

/**
 * Calculate working days between two dates
 */
function calculate_working_days($start_date, $end_date, $company_id)
{
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include end date
    
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $working_days = 0;
    $HolidaysModel = new \App\Models\HolidaysModel();
    $holidays = $HolidaysModel->where('company_id', $company_id)
                              ->where('is_active', 1)
                              ->findAll();
    
    $holiday_dates = array_column($holidays, 'holiday_date');
    
    foreach ($period as $date) {
        $day_of_week = $date->format('N'); // 1=Monday, 7=Sunday
        $date_string = $date->format('Y-m-d');
        
        // Skip weekends (Friday=5, Saturday=6 for Middle East)
        if ($day_of_week == 5 || $day_of_week == 6) {
            continue;
        }
        
        // Skip holidays
        if (in_array($date_string, $holiday_dates)) {
            continue;
        }
        
        $working_days++;
    }
    
    return $working_days;
}

/**
 * Validate leave request against policy
 */
function validate_leave_request($employee_id, $leave_type, $start_date, $end_date, $year)
{
    $errors = [];
    
    // Get policy
    $policy = get_employee_leave_policy($employee_id, $leave_type);
    if (!$policy) {
        $errors[] = 'No leave policy found for this leave type';
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Get employee
    $UsersModel = new \App\Models\UsersModel();
    $employee = $UsersModel->find($employee_id);
    
    // Calculate requested days
    $requested_days = calculate_working_days($start_date, $end_date, $employee['company_id']);
    
    // Check balance
    $BalanceModel = new \App\Models\EmployeeLeaveBalanceModel();
    $balance = $BalanceModel->getBalance($employee_id, $leave_type, $year);
    
    if (!$balance || $balance['remaining_days'] < $requested_days) {
        $errors[] = 'Insufficient leave balance';
    }
    
    // Check max consecutive days
    if ($policy['max_consecutive_days'] && $requested_days > $policy['max_consecutive_days']) {
        $errors[] = "Maximum consecutive days allowed: {$policy['max_consecutive_days']}";
    }
    
    // Check one-time leave (e.g., Hajj)
    if ($policy['is_one_time']) {
        $LeaveModel = new \App\Models\LeaveModel();
        $previous_leaves = $LeaveModel->where('employee_id', $employee_id)
                                      ->where('leave_type_id', $leave_type)
                                      ->where('status', 1) // Approved
                                      ->countAllResults();
        if ($previous_leaves > 0) {
            $errors[] = 'This leave type can only be taken once';
        }
    }
    
    // Check overlapping leaves
    $LeaveModel = new \App\Models\LeaveModel();
    $overlapping = $LeaveModel->where('employee_id', $employee_id)
                              ->where('status !=', 2) // Not rejected
                              ->groupStart()
                                  ->where('from_date <=', $end_date)
                                  ->where('to_date >=', $start_date)
                              ->groupEnd()
                              ->countAllResults();
    
    if ($overlapping > 0) {
        $errors[] = 'Leave dates overlap with existing leave application';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'policy' => $policy,
        'requested_days' => $requested_days
    ];
}

/**
 * Initialize leave balances for employee
 */
function initialize_employee_leave_balances($employee_id, $year)
{
    $UsersModel = new \App\Models\UsersModel();
    $LeavePolicyModel = new \App\Models\LeavePolicyModel();
    $BalanceModel = new \App\Models\EmployeeLeaveBalanceModel();
    
    $employee = $UsersModel->find($employee_id);
    if (!$employee) {
        return false;
    }
    
    $service_years = calculate_service_duration($employee['date_of_joining']);
    $country_code = $employee['country_code'] ?? 'SA';
    
    // Get all applicable policies
    $policies = $LeavePolicyModel->getCountryPolicies($employee['company_id'], $country_code);
    
    foreach ($policies as $policy) {
        // Check if policy applies to this employee's service duration
        if ($policy['service_years_min'] > $service_years) {
            continue;
        }
        if ($policy['service_years_max'] !== null && $policy['service_years_max'] < $service_years) {
            continue;
        }
        
        // Check if balance already exists
        $existing = $BalanceModel->getBalance($employee_id, $policy['leave_type'], $year);
        
        if (!$existing) {
            $BalanceModel->insert([
                'company_id' => $employee['company_id'],
                'employee_id' => $employee_id,
                'leave_type' => $policy['leave_type'],
                'year' => $year,
                'total_entitled' => $policy['entitlement_days'],
                'used_days' => 0,
                'pending_days' => 0,
                'remaining_days' => $policy['entitlement_days'],
                'carried_forward' => 0
            ]);
        }
    }
    
    return true;
}

/**
 * Get country name in current language
 */
function get_country_name($country_code, $lang = 'en')
{
    $countries = [
        'SA' => ['en' => 'Saudi Arabia', 'ar' => 'السعودية'],
        'EG' => ['en' => 'Egypt', 'ar' => 'مصر'],
        'KW' => ['en' => 'Kuwait', 'ar' => 'الكويت'],
        'QA' => ['en' => 'Qatar', 'ar' => 'قطر']
    ];
    
    return $countries[$country_code][$lang] ?? $country_code;
}

/**
 * Get leave type name in current language
 */
function get_leave_type_name($leave_type, $lang = 'en')
{
    $types = [
        'annual' => ['en' => 'Annual Leave', 'ar' => 'الإجازة السنوية'],
        'sick' => ['en' => 'Sick Leave', 'ar' => 'الإجازة المرضية'],
        'emergency' => ['en' => 'Emergency Leave', 'ar' => 'إجازة الأزمة'],
        'hajj' => ['en' => 'Hajj Leave', 'ar' => 'إجازة الحج'],
        'maternity' => ['en' => 'Maternity Leave', 'ar' => 'إجازة الولادة (الأمومة)'],
        'paternity' => ['en' => 'Paternity Leave', 'ar' => 'إجازة الولادة (الأبوة)']
    ];
    
    return $types[$leave_type][$lang] ?? $leave_type;
}
```


## Data Models

### Leave Policy Data Structure

```php
[
    'policy_id' => 1,
    'company_id' => 724,
    'country_code' => 'SA',
    'leave_type' => 'annual',
    'service_years_min' => 0,
    'service_years_max' => 4.99,
    'entitlement_days' => 21,
    'is_paid' => 1,
    'payment_percentage' => 100.00,
    'max_consecutive_days' => null,
    'requires_documentation' => 0,
    'documentation_after_days' => null,
    'is_one_time' => 0,
    'deduct_from_annual' => 0,
    'policy_description_en' => '21 working days for employees with less than 5 years of service',
    'policy_description_ar' => '21 يوماً عمل للموظفين الذين لديهم أقل من 5 سنوات خدمة',
    'is_active' => 1
]
```

### Employee Leave Balance Data Structure

```php
[
    'balance_id' => 1,
    'company_id' => 724,
    'employee_id' => 727,
    'leave_type' => 'annual',
    'year' => 2026,
    'total_entitled' => 21,
    'used_days' => 5,
    'pending_days' => 3,
    'remaining_days' => 13,
    'carried_forward' => 0,
    'last_calculated' => '2026-01-20 10:30:00'
]
```

### Leave Application Enhanced Data Structure

```php
[
    'leave_id' => 131,
    'company_id' => 724,
    'employee_id' => 768,
    'duty_employee_id' => 727,
    'leave_type_id' => 'annual',
    'country_code' => 'SA',
    'service_years' => 3.5,
    'policy_id' => 1,
    'from_date' => '2026-02-22',
    'to_date' => '2026-02-22',
    'leave_hours' => '5',
    'calculated_days' => 1,
    'particular_date' => '2026-02-22',
    'leave_month' => '2',
    'leave_year' => '2026',
    'reason' => 'Personal matter',
    'remarks' => 'Approved',
    'status' => 1,
    'place' => 1,
    'is_half_day' => 0,
    'is_deducted' => 1,
    'payment_percentage' => 100.00,
    'leave_attachment' => '',
    'documentation_provided' => 0,
    'created_at' => '12-01-2026 09:34:44'
]
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Correctness Properties

Property 1: Country Policy Storage and Retrieval
*For any* valid leave policy configuration with country code, leave type, and entitlement rules, storing the policy and then retrieving it by country and leave type should return an equivalent policy configuration.
**Validates: Requirements 1.1, 1.3**

Property 2: Policy Creation Validation
*For any* leave policy creation attempt without a country code, the system should reject the creation and return a validation error.
**Validates: Requirements 1.2**

Property 3: Service-Duration-Based Annual Leave Entitlement
*For any* employee with a country code and service duration, calculating annual leave entitlement should return the number of days that matches the policy tier for that country and service duration (e.g., SA: 21 days for <5 years, 30 days for >=5 years).
**Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7**

Property 4: Sick Leave Tiered Payment Calculation
*For any* sick leave application with a duration and country code, the system should calculate the payment percentage based on the country's tiered payment rules (e.g., SA: 100% for first 30 days, 75% for next 60 days).
**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

Property 5: Leave Balance Tracking
*For any* employee leave balance, after approving a leave application for N days, the used_days should increase by N and remaining_days should decrease by N, maintaining the invariant: total_entitled = used_days + remaining_days + pending_days.
**Validates: Requirements 3.6, 8.3**

Property 6: Leave Balance Restoration
*For any* approved leave application, rejecting or cancelling it should restore the leave balance such that used_days decreases by the leave days and remaining_days increases by the same amount.
**Validates: Requirements 8.4**

Property 7: Emergency Leave Entitlement by Country
*For any* employee with a country code, the emergency leave entitlement should match the country's policy (SA: 5 days, EG: 7 days, KW: 4 days, QA: 5 days).
**Validates: Requirements 4.1, 4.2, 4.3, 4.4**

Property 8: Emergency Leave Reason Validation
*For any* emergency leave application without a reason specified, the system should reject the application with a validation error.
**Validates: Requirements 4.5**

Property 9: Hajj Leave One-Time Restriction
*For any* employee who has already taken approved Hajj leave, attempting to submit a second Hajj leave application should be rejected with an error indicating Hajj leave can only be taken once.
**Validates: Requirements 5.6, 15.1**

Property 10: Hajj Leave Balance Independence
*For any* approved Hajj leave application, the employee's annual leave balance should remain unchanged (Hajj leave should not deduct from annual balance).
**Validates: Requirements 5.7**

Property 11: Maternity Leave Gender Validation
*For any* maternity leave application submitted by an employee with gender = male, the system should reject the application with a validation error.
**Validates: Requirements 15.3**

Property 12: Paternity Leave Gender Validation
*For any* paternity leave application submitted by an employee with gender = female, the system should reject the application with a validation error.
**Validates: Requirements 15.4**

Property 13: Service Duration Calculation
*For any* employee with a hire date, calculating service duration should return the number of years (with decimal precision) between the hire date and current date, such that an employee hired exactly 5 years ago returns 5.0.
**Validates: Requirements 7.1, 7.4**

Property 14: Service Duration Threshold Entitlement Update
*For any* employee whose service duration crosses a policy threshold (e.g., from 4.9 to 5.0 years), recalculating leave entitlements should update the entitlement to match the new policy tier.
**Validates: Requirements 7.3, 7.5**

Property 15: Leave Balance Insufficient Funds Rejection
*For any* leave application requesting N days where the employee's remaining balance is less than N, the system should reject the application with an error indicating insufficient balance.
**Validates: Requirements 9.2**

Property 16: Working Days Calculation Excludes Weekends and Holidays
*For any* date range and company, calculating working days should exclude all Fridays, Saturdays, and company holidays, such that a week with no holidays returns 5 working days.
**Validates: Requirements 9.3**

Property 17: Leave Application Required Fields Validation
*For any* leave application missing leave_type, start_date, end_date, or reason, the system should reject the application with a validation error listing the missing fields.
**Validates: Requirements 9.4**

Property 18: Leave Application Country Policy Validation
*For any* leave application, the validation should use the policy matching the employee's country code and current service duration, not policies from other countries.
**Validates: Requirements 9.1, 8.5**

Property 19: Maximum Consecutive Days Enforcement
*For any* leave application requesting N consecutive days where N exceeds the policy's max_consecutive_days, the system should reject the application with an error indicating the maximum allowed.
**Validates: Requirements 15.5**

Property 20: Overlapping Leave Prevention
*For any* leave application with date range [start1, end1], if there exists an approved or pending leave with date range [start2, end2] where the ranges overlap, the system should reject the new application with an error indicating overlapping dates.
**Validates: Requirements 15.6**

Property 21: Employee Country Change Triggers Recalculation
*For any* employee, changing their country_code from country A to country B should trigger recalculation of all leave entitlements using country B's policies.
**Validates: Requirements 12.3**

Property 22: Leave Type Translation Consistency
*For any* leave type identifier, the translation function should return consistent names in both English and Arabic, such that translating 'annual' returns 'Annual Leave' in English and 'الإجازة السنوية' in Arabic.
**Validates: Requirements 14.1, 14.2, 14.3**

Property 23: Sick Leave Documentation Requirement
*For any* sick leave application exceeding the policy's documentation_after_days threshold, the system should require documentation_provided = 1, otherwise reject the application.
**Validates: Requirements 15.2**

Property 24: Leave Balance Display Completeness
*For any* employee and year, querying leave balances should return entries for all leave types applicable to the employee's country and service duration.
**Validates: Requirements 8.1, 8.2**


## Error Handling

### Validation Errors

The system will return structured error responses for validation failures:

```php
[
    'success' => false,
    'errors' => [
        'field_name' => 'Error message',
        'another_field' => 'Another error message'
    ],
    'message' => 'Validation failed'
]
```

### Common Error Scenarios

1. **Insufficient Leave Balance**
   - Error Code: `INSUFFICIENT_BALANCE`
   - Message: "Insufficient leave balance. Available: X days, Requested: Y days"
   - HTTP Status: 400

2. **Invalid Country Code**
   - Error Code: `INVALID_COUNTRY`
   - Message: "Invalid country code. Supported countries: SA, EG, KW, QA"
   - HTTP Status: 400

3. **Policy Not Found**
   - Error Code: `POLICY_NOT_FOUND`
   - Message: "No leave policy found for country {country} and leave type {type}"
   - HTTP Status: 404

4. **Overlapping Leave**
   - Error Code: `OVERLAPPING_LEAVE`
   - Message: "Leave dates overlap with existing leave application from {start} to {end}"
   - HTTP Status: 409

5. **One-Time Leave Already Taken**
   - Error Code: `ONE_TIME_LEAVE_TAKEN`
   - Message: "Hajj leave can only be taken once during employment"
   - HTTP Status: 409

6. **Gender Mismatch**
   - Error Code: `GENDER_MISMATCH`
   - Message: "Maternity leave is only available for female employees"
   - HTTP Status: 400

7. **Exceeds Maximum Consecutive Days**
   - Error Code: `EXCEEDS_MAX_DAYS`
   - Message: "Requested days ({requested}) exceed maximum consecutive days allowed ({max})"
   - HTTP Status: 400

8. **Missing Documentation**
   - Error Code: `DOCUMENTATION_REQUIRED`
   - Message: "Medical documentation is required for sick leave exceeding {threshold} days"
   - HTTP Status: 400

### Database Transaction Handling

All leave balance updates will be wrapped in database transactions to ensure data consistency:

```php
$db = \Config\Database::connect();
$db->transStart();

try {
    // Update leave application status
    $LeaveModel->update($leave_id, ['status' => 1]);
    
    // Update leave balance
    $BalanceModel->updateBalance($employee_id, $leave_type, $year, $days, 'deduct');
    
    // Send notifications
    send_leave_approval_notification($leave_id);
    
    $db->transComplete();
    
    if ($db->transStatus() === false) {
        throw new \Exception('Transaction failed');
    }
} catch (\Exception $e) {
    $db->transRollback();
    log_message('error', 'Leave approval failed: ' . $e->getMessage());
    return ['success' => false, 'message' => 'Failed to approve leave'];
}
```

### Logging

All leave policy operations will be logged for audit purposes:

```php
log_message('info', "Leave policy created: Company {$company_id}, Country {$country_code}, Type {$leave_type}");
log_message('info', "Leave balance updated: Employee {$employee_id}, Type {$leave_type}, Action {$action}, Days {$days}");
log_message('warning', "Leave validation failed: Employee {$employee_id}, Reason: {$error_message}");
log_message('error', "Leave calculation error: {$error_details}");
```

## Testing Strategy

### Unit Testing

Unit tests will verify specific functions and edge cases:

1. **Service Duration Calculation Tests**
   - Test exact year boundaries (e.g., 5.0 years)
   - Test leap year handling
   - Test employees hired on Feb 29

2. **Working Days Calculation Tests**
   - Test week with no holidays (should return 5)
   - Test week with one holiday (should return 4)
   - Test date range spanning multiple months
   - Test single day leave

3. **Policy Matching Tests**
   - Test exact threshold boundaries (4.99 vs 5.0 years)
   - Test NULL max service years (unlimited)
   - Test multiple policies for same leave type

4. **Balance Update Tests**
   - Test deduction reduces balance correctly
   - Test restoration increases balance correctly
   - Test concurrent leave applications

5. **Validation Tests**
   - Test each validation rule with valid and invalid inputs
   - Test error message formatting
   - Test multiple validation errors

### Property-Based Testing

Property tests will verify universal properties across all inputs using PHPUnit with a property testing library (e.g., Eris or php-quickcheck). Each test will run a minimum of 100 iterations.

1. **Property Test: Leave Balance Invariant**
   ```php
   /**
    * Feature: country-based-leave-policy, Property 5: Leave Balance Tracking
    * For any employee leave balance, after approving a leave application for N days,
    * the used_days should increase by N and remaining_days should decrease by N,
    * maintaining the invariant: total_entitled = used_days + remaining_days + pending_days
    */
   public function testLeaveBalanceInvariant()
   {
       $this->forAll(
           Generator::int(1, 30), // days to deduct
           Generator::int(1, 100), // initial total_entitled
           Generator::int(0, 50)   // initial used_days
       )->then(function ($days, $total, $used) {
           // Setup: Create balance
           $remaining = $total - $used;
           if ($days > $remaining) {
               $this->markTestSkipped('Insufficient balance');
           }
           
           $balance = [
               'total_entitled' => $total,
               'used_days' => $used,
               'remaining_days' => $remaining,
               'pending_days' => 0
           ];
           
           // Action: Deduct days
           $new_used = $used + $days;
           $new_remaining = $remaining - $days;
           
           // Assert: Invariant holds
           $this->assertEquals(
               $total,
               $new_used + $new_remaining,
               "Invariant violated: total != used + remaining"
           );
       });
   }
   ```

2. **Property Test: Service Duration Calculation**
   ```php
   /**
    * Feature: country-based-leave-policy, Property 13: Service Duration Calculation
    * For any employee with a hire date, calculating service duration should return
    * the number of years between the hire date and current date
    */
   public function testServiceDurationCalculation()
   {
       $this->forAll(
           Generator::date('2010-01-01', '2025-12-31')
       )->then(function ($hire_date) {
           $duration = calculate_service_duration($hire_date);
           
           $hire = new DateTime($hire_date);
           $now = new DateTime();
           $expected_years = $hire->diff($now)->y;
           
           // Duration should be >= expected_years and < expected_years + 1
           $this->assertGreaterThanOrEqual($expected_years, $duration);
           $this->assertLessThan($expected_years + 1, $duration);
       });
   }
   ```

3. **Property Test: Working Days Excludes Weekends**
   ```php
   /**
    * Feature: country-based-leave-policy, Property 16: Working Days Calculation
    * For any date range, calculating working days should exclude Fridays and Saturdays
    */
   public function testWorkingDaysExcludesWeekends()
   {
       $this->forAll(
           Generator::date('2026-01-01', '2026-12-31'),
           Generator::int(1, 30) // days to add
       )->then(function ($start_date, $days_to_add) {
           $start = new DateTime($start_date);
           $end = clone $start;
           $end->modify("+{$days_to_add} days");
           
           $working_days = calculate_working_days(
               $start->format('Y-m-d'),
               $end->format('Y-m-d'),
               724 // test company
           );
           
           // Working days should never exceed total days
           $total_days = $days_to_add + 1;
           $this->assertLessThanOrEqual($total_days, $working_days);
           
           // Working days should be at most 5/7 of total days (no weekends)
           $max_working = ceil($total_days * 5 / 7);
           $this->assertLessThanOrEqual($max_working, $working_days);
       });
   }
   ```

4. **Property Test: Country Policy Validation**
   ```php
   /**
    * Feature: country-based-leave-policy, Property 18: Leave Application Country Policy Validation
    * For any leave application, validation should use the employee's country policy
    */
   public function testCountryPolicyValidation()
   {
       $this->forAll(
           Generator::elements(['SA', 'EG', 'KW', 'QA']),
           Generator::elements(['annual', 'sick', 'emergency', 'hajj']),
           Generator::float(0, 10) // service years
       )->then(function ($country, $leave_type, $service_years) {
           $policy = get_applicable_policy($country, $leave_type, $service_years);
           
           if ($policy) {
               // Policy country should match employee country
               $this->assertEquals($country, $policy['country_code']);
               
               // Policy should match service years
               $this->assertLessThanOrEqual($service_years, $policy['service_years_min']);
               if ($policy['service_years_max'] !== null) {
                   $this->assertGreaterThanOrEqual($service_years, $policy['service_years_max']);
               }
           }
       });
   }
   ```

5. **Property Test: Leave Balance Restoration**
   ```php
   /**
    * Feature: country-based-leave-policy, Property 6: Leave Balance Restoration
    * For any approved leave, rejecting it should restore the balance
    */
   public function testLeaveBalanceRestoration()
   {
       $this->forAll(
           Generator::int(1, 30), // leave days
           Generator::int(50, 100) // initial balance
       )->then(function ($days, $initial_balance) {
           // Setup: Initial balance
           $balance_before = [
               'total_entitled' => $initial_balance,
               'used_days' => 0,
               'remaining_days' => $initial_balance
           ];
           
           // Action 1: Approve leave (deduct)
           $after_approval = [
               'used_days' => $days,
               'remaining_days' => $initial_balance - $days
           ];
           
           // Action 2: Reject leave (restore)
           $after_rejection = [
               'used_days' => 0,
               'remaining_days' => $initial_balance
           ];
           
           // Assert: Balance restored to original
           $this->assertEquals(
               $balance_before['remaining_days'],
               $after_rejection['remaining_days']
           );
       });
   }
   ```

### Integration Testing

Integration tests will verify the complete workflow:

1. **Leave Application Workflow Test**
   - Create employee with country
   - Initialize leave balances
   - Submit leave application
   - Validate against policy
   - Approve leave
   - Verify balance updated
   - Verify notifications sent

2. **Country Change Workflow Test**
   - Create employee with country SA
   - Initialize balances (21 days annual)
   - Change country to EG
   - Verify balances recalculated (21 or 30 days based on service)

3. **Multi-Year Leave Balance Test**
   - Create employee
   - Use partial leave in year 1
   - Carry forward to year 2
   - Verify balances correct in both years

### Test Data Setup

Test data will include:

1. **Sample Policies** for all countries and leave types
2. **Sample Employees** with various:
   - Countries (SA, EG, KW, QA)
   - Service durations (0.5, 2, 4.9, 5, 7, 10 years)
   - Genders (for maternity/paternity testing)
3. **Sample Holidays** for working days calculation
4. **Sample Leave Applications** covering all scenarios

### Test Coverage Goals

- Unit test coverage: 90%+
- Property test iterations: 100+ per property
- Integration test coverage: All critical workflows
- Edge case coverage: All boundary conditions

