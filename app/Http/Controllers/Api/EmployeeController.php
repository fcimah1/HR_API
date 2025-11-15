<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CreateEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService
    ) {}
    /**
     * @OA\Get(
     *     path="/api/employees",
     *     summary="Get all employees in user's company",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or email",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employees retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Check if user has permission to view employees
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view employees'
            ], 403);
        }

        // Create DTO from request
        $filters = EmployeeFilterDTO::fromRequest([
            ...$request->all(),
            'company_id' => $user->company_id
        ]);

        // Get employees using service
        $result = $this->employeeService->getPaginatedEmployees($filters);

        return response()->json([
            'success' => true,
            ...$result
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}",
     *     summary="Get specific employee details",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions"
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployee($user, (int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this employee'
            ], 403);
        }

        $employee = $this->employeeService->getEmployeeWithDetails((int) $id, $user->company_id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $employee->toArray()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/stats",
     *     summary="Get employee statistics for the company",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_employees", type="integer", example=50),
     *                 @OA\Property(property="active_employees", type="integer", example=45),
     *                 @OA\Property(property="inactive_employees", type="integer", example=5),
     *                 @OA\Property(property="by_user_type", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view employee statistics'
            ], 403);
        }

        $stats = $this->employeeService->getEmployeeStats($user->company_id);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/search",
     *     summary="Search employees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term",
     *         required=true,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function search(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to search employees'
            ], 403);
        }

        $searchTerm = $request->get('q');
        if (empty($searchTerm)) {
            return response()->json([
                'success' => false,
                'message' => 'Search term is required'
            ], 400);
        }

        $employees = $this->employeeService->searchEmployees($user->company_id, $searchTerm);

        return response()->json([
            'success' => true,
            'data' => $employees
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/by-type/{type}",
     *     summary="Get employees by type",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         description="User type",
     *         required=true,
     *         @OA\Schema(type="string", example="employee")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employees by type",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getByType(Request $request, string $type)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view employees'
            ], 403);
        }

        $employees = $this->employeeService->getEmployeesByType($user->company_id, $type);

        return response()->json([
            'success' => true,
            'data' => $employees,
            'type' => $type
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/active",
     *     summary="Get active employees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Active employees",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getActiveEmployees(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view employees'
            ], 403);
        }

        $filters = EmployeeFilterDTO::fromRequest([
            'is_active' => true,
            'company_id' => $user->company_id,
            'per_page' => $request->get('per_page', 50)
        ]);

        $result = $this->employeeService->getPaginatedEmployees($filters);

        return response()->json([
            'success' => true,
            ...$result
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/inactive",
     *     summary="Get inactive employees",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Inactive employees",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getInactiveEmployees(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view employees'
            ], 403);
        }

        $filters = EmployeeFilterDTO::fromRequest([
            'is_active' => false,
            'company_id' => $user->company_id,
            'per_page' => $request->get('per_page', 50)
        ]);

        $result = $this->employeeService->getPaginatedEmployees($filters);

        return response()->json([
            'success' => true,
            ...$result
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/export/pdf",
     *     summary="Export employees to PDF",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="PDF file download",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     )
     * )
     */
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to export employees'
            ], 403);
        }

        $filters = EmployeeFilterDTO::fromRequest([
            'company_id' => $user->company_id,
            'per_page' => 1000 // Get all employees for export
        ]);

        $result = $this->employeeService->getPaginatedEmployees($filters);
        $employees = $result['data'];

        // Create PDF using TCPDF with Landscape orientation
        $pdf = new \TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('HR System');
        $pdf->SetAuthor($user->company_name);
        $pdf->SetTitle('Employees Report');
        $pdf->SetSubject('Employee List Export');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, $user->company_name, 'Employees Report - Generated on ' . date('Y-m-d H:i:s'));
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array('helvetica', '', 12));
        $pdf->setFooterFont(Array('helvetica', '', 8));
        
        // Set margins
        $pdf->SetMargins(15, 30, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font for content
        $pdf->SetFont('helvetica', '', 8);
        
        // Create improved HTML table
        $html = '<style>
            .report-title {
                text-align: center;
                color: #2c3e50;
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 20px;
                padding: 10px;
                border-bottom: 2px solid #3498db;
            }
            .employee-table {
                width: 100%;
                max-width: 580px;
                border-collapse: collapse;
                font-size: 8px;
                margin-top: 10px;
                table-layout: fixed;
                margin-left: auto;
                margin-right: auto;
            }
            .table-header {
                background-color: #3498db;
                color: white;
                font-weight: bold;
                text-align: center;
                padding: 8px 4px;
                border: 1px solid #2980b9;
            }
            .table-cell {
                padding: 6px 4px;
                border: 1px solid #bdc3c7;
                text-align: left;
                vertical-align: middle;
                word-wrap: break-word;
                overflow: hidden;
            }
            .table-cell-center {
                text-align: center;
            }
            .active-yes {
                color: #27ae60;
                font-weight: bold;
            }
            .active-no {
                color: #e74c3c;
                font-weight: bold;
            }
            .row-even {
                background-color: #f8f9fa;
            }
            .summary {
                text-align: center;
                color: #7f8c8d;
                font-size: 10px;
                margin-top: 20px;
                padding: 15px;
                border-top: 1px solid #bdc3c7;
            }
        </style>';
        
        $html .= '<div class="report-title">Employees Report</div>';
        
        $html .= '<table class="employee-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th class="table-header" style="width: 8%;">ID</th>';
        $html .= '<th class="table-header" style="width: 14%;">First Name</th>';
        $html .= '<th class="table-header" style="width: 14%;">Last Name</th>';
        $html .= '<th class="table-header" style="width: 20%;">Email</th>';
        $html .= '<th class="table-header" style="width: 14%;">Username</th>';
        $html .= '<th class="table-header" style="width: 8%;">Type</th>';
        $html .= '<th class="table-header" style="width: 6%;">Gender</th>';
        $html .= '<th class="table-header" style="width: 8%;">Status</th>';
        $html .= '<th class="table-header" style="width: 8%;">Phone</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $rowCount = 0;
        foreach ($employees as $employee) {
            $rowClass = ($rowCount % 2 == 0) ? '' : 'row-even';
            $html .= '<tr class="' . $rowClass . '">';
            
            $html .= '<td class="table-cell table-cell-center" style="width: 8%;">' . htmlspecialchars($employee['user_id']) . '</td>';
            $html .= '<td class="table-cell" style="width: 14%;">' . htmlspecialchars($employee['first_name']) . '</td>';
            $html .= '<td class="table-cell" style="width: 14%;">' . htmlspecialchars($employee['last_name']) . '</td>';
            
            // Email with smaller font and truncation
            $email = $employee['email'];
            if (strlen($email) > 25) {
                $email = substr($email, 0, 25) . '...';
            }
            $html .= '<td class="table-cell" style="width: 20%; font-size: 6px; line-height: 1.2;">' . htmlspecialchars($email) . '</td>';
            
            $html .= '<td class="table-cell" style="width: 14%;">' . htmlspecialchars($employee['username']) . '</td>';
            $html .= '<td class="table-cell table-cell-center" style="width: 8%;">' . htmlspecialchars(ucfirst($employee['user_type'])) . '</td>';
            $html .= '<td class="table-cell table-cell-center" style="width: 6%;">' . htmlspecialchars($employee['gender'] ?? '-') . '</td>';
            
            $statusClass = $employee['is_active'] ? 'active-yes' : 'active-no';
            $statusText = $employee['is_active'] ? 'Active' : 'Inactive';
            $html .= '<td class="table-cell table-cell-center ' . $statusClass . '" style="width: 8%;">' . $statusText . '</td>';
            
            // Phone with smaller font and truncation
            $phone = $employee['contact_number'] ?? '-';
            if (strlen($phone) > 8) {
                $phone = substr($phone, 0, 8) . '..';
            }
            $html .= '<td class="table-cell table-cell-center" style="width: 8%; font-size: 6px; line-height: 1.2;">' . htmlspecialchars($phone) . '</td>';
            
            $html .= '</tr>';
            $rowCount++;
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        // Add summary
        $activeCount = count(array_filter($employees, fn($emp) => $emp['is_active']));
        $inactiveCount = count($employees) - $activeCount;
        
        $html .= '<div class="summary">';
        $html .= '<strong>Report Summary</strong><br>';
        $html .= 'Total Employees: <strong>' . count($employees) . '</strong> | ';
        $html .= 'Active: <strong style="color: #27ae60;">' . $activeCount . '</strong> | ';
        $html .= 'Inactive: <strong style="color: #e74c3c;">' . $inactiveCount . '</strong><br>';
        $html .= 'Generated on: ' . date('Y-m-d H:i:s') . '<br>';
        $html .= 'Company: <strong>' . htmlspecialchars($user->company_name) . '</strong>';
        $html .= '</div>';
        
        // Print text using writeHTMLCell()
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'employees_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Output PDF
        $pdfContent = $pdf->Output($filename, 'S');
        
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * @OA\Get(
     *     path="/api/employees/export/pdf/detailed",
     *     summary="Export employees to detailed PDF",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Detailed PDF file download",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     )
     * )
     */
    public function exportDetailedPdf(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to export employees'
            ], 403);
        }

        $filters = EmployeeFilterDTO::fromRequest([
            'company_id' => $user->company_id,
            'per_page' => 1000 // Get all employees for export
        ]);

        $result = $this->employeeService->getPaginatedEmployees($filters);
        $employees = $result['data'];

        // Create PDF using TCPDF
        $pdf = new \TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false); // Portrait orientation for detailed view
        
        // Set document information
        $pdf->SetCreator('HR System');
        $pdf->SetAuthor($user->company_name);
        $pdf->SetTitle('Detailed Employees Report');
        $pdf->SetSubject('Employee Detailed List Export');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, $user->company_name, 'Detailed Employees Report - Generated on ' . date('Y-m-d H:i:s'));
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array('helvetica', '', 12));
        $pdf->setFooterFont(Array('helvetica', '', 8));
        
        // Set margins
        $pdf->SetMargins(15, 30, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font for content
        $pdf->SetFont('helvetica', '', 10);
        
        // Create detailed HTML table
        $html = '<h2 style="text-align: center; color: #333;">Detailed Employees Report</h2>';
        
        foreach ($employees as $employee) {
            $html .= '<div style="border: 1px solid #ccc; margin-bottom: 15px; padding: 10px; page-break-inside: avoid;">';
            $html .= '<h3 style="color: #2c3e50; margin-bottom: 10px;">' . htmlspecialchars($employee['full_name']) . ' (ID: ' . $employee['user_id'] . ')</h3>';
            
            $html .= '<table border="0" cellpadding="3" cellspacing="0" style="width: 100%;">';
            $html .= '<tr>';
            $html .= '<td style="width: 25%; font-weight: bold;">Email:</td>';
            $html .= '<td style="width: 25%;">' . htmlspecialchars($employee['email']) . '</td>';
            $html .= '<td style="width: 25%; font-weight: bold;">Username:</td>';
            $html .= '<td style="width: 25%;">' . htmlspecialchars($employee['username']) . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="font-weight: bold;">User Type:</td>';
            $html .= '<td>' . htmlspecialchars(ucfirst($employee['user_type'])) . '</td>';
            $html .= '<td style="font-weight: bold;">Gender:</td>';
            $html .= '<td>' . htmlspecialchars($employee['gender'] ?? 'Not specified') . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="font-weight: bold;">Contact:</td>';
            $html .= '<td>' . htmlspecialchars($employee['contact_number'] ?? 'Not provided') . '</td>';
            $html .= '<td style="font-weight: bold;">Status:</td>';
            $html .= '<td>' . ($employee['is_active'] ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>') . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="font-weight: bold;">Created:</td>';
            $html .= '<td>' . htmlspecialchars($employee['created_at']) . '</td>';
            $html .= '<td style="font-weight: bold;">Last Login:</td>';
            $html .= '<td>' . htmlspecialchars($employee['last_login_date'] ?? 'Never') . '</td>';
            $html .= '</tr>';
            
            // Address information
            if (!empty($employee['address']['address_1']) || !empty($employee['address']['city'])) {
                $html .= '<tr>';
                $html .= '<td colspan="4" style="font-weight: bold; padding-top: 10px;">Address:</td>';
                $html .= '</tr>';
                $html .= '<tr>';
                $html .= '<td colspan="4">';
                $address_parts = array_filter([
                    $employee['address']['address_1'],
                    $employee['address']['address_2'],
                    $employee['address']['city'],
                    $employee['address']['state'],
                    $employee['address']['country']
                ]);
                $html .= htmlspecialchars(implode(', ', $address_parts) ?: 'Not provided');
                $html .= '</td>';
                $html .= '</tr>';
            }
            
            // Employee details if available
            if (!empty($employee['details'])) {
                $details = $employee['details'];
                $html .= '<tr>';
                $html .= '<td colspan="4" style="font-weight: bold; padding-top: 10px; color: #2c3e50;">Employee Details:</td>';
                $html .= '</tr>';
                $html .= '<tr>';
                $html .= '<td style="font-weight: bold;">Employee ID:</td>';
                $html .= '<td>' . htmlspecialchars($details['employee_id'] ?? 'Not assigned') . '</td>';
                $html .= '<td style="font-weight: bold;">Department:</td>';
                $html .= '<td>' . htmlspecialchars($details['department_id'] ?? 'Not assigned') . '</td>';
                $html .= '</tr>';
                $html .= '<tr>';
                $html .= '<td style="font-weight: bold;">Basic Salary:</td>';
                $html .= '<td>' . htmlspecialchars($details['basic_salary'] ? number_format($details['basic_salary'], 2) : 'Not set') . '</td>';
                $html .= '<td style="font-weight: bold;">Date of Joining:</td>';
                $html .= '<td>' . htmlspecialchars($details['date_of_joining'] ?? 'Not set') . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table>';
            $html .= '</div>';
        }
        
        // Add summary
        $html .= '<div style="text-align: center; color: #666; font-size: 10px; margin-top: 20px;">';
        $html .= '<strong>Total Employees: ' . count($employees) . '</strong><br>';
        $html .= 'Generated on: ' . date('Y-m-d H:i:s') . '<br>';
        $html .= 'Company: ' . htmlspecialchars($user->company_name);
        $html .= '</div>';
        
        // Print text using writeHTMLCell()
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'employees_detailed_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Output PDF
        $pdfContent = $pdf->Output($filename, 'S');
        
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * @OA\Post(
     *     path="/api/employees",
     *     summary="Create new employee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name","email","username","password"},
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", example="john.doe@company.com"),
     *             @OA\Property(property="username", type="string", example="john.doe"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="user_type", type="string", example="company"),
     *             @OA\Property(property="contact_number", type="string", example="1234567890"),
     *             @OA\Property(property="gender", type="string", example="male"),
     *             @OA\Property(property="employee_id", type="string", example="EMP001"),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="designation_id", type="integer", example=1),
     *             @OA\Property(property="basic_salary", type="number", example=50000),
     *             @OA\Property(property="date_of_joining", type="string", example="2024-01-01"),
     *             @OA\Property(property="date_of_birth", type="string", example="1990-01-01")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions"
     *     )
     * )
     */
    public function store(CreateEmployeeRequest $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canManageEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create employees'
            ], 403);
        }

        // Validation is now handled in the Form Request

        try {
            $employeeData = CreateEmployeeDTO::fromRequest([
                ...$request->all(),
                'company_id' => $user->company_id,
                'company_name' => $user->company_name
            ]);

            $employee = $this->employeeService->createEmployee($employeeData);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee->toArray()
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}",
     *     summary="Update employee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", example="john.doe@company.com"),
     *             @OA\Property(property="contact_number", type="string", example="1234567890"),
     *             @OA\Property(property="basic_salary", type="number", example=55000),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions"
     *     )
     * )
     */
    public function update(UpdateEmployeeRequest $request, $id)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canManageEmployee($user, (int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this employee'
            ], 403);
        }

        // Check if employee exists
        $employee = $this->employeeService->getEmployeeById((int) $id, $user->company_id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        // Validation is now handled in the DTO

        try {
            $updateData = UpdateEmployeeDTO::fromRequest((int) $id, $request->all());
            
            $updated = $this->employeeService->updateEmployee($updateData);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee updated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No changes were made'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/employees/{id}",
     *     summary="Delete employee",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions"
     *     )
     * )
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canManageEmployee($user, (int) $id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this employee'
            ], 403);
        }

        // Check if employee exists
        $employee = $this->employeeService->getEmployeeById((int) $id, $user->company_id);
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        try {
            $deleted = $this->employeeService->deleteEmployee((int) $id, $user->company_id, $user);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete employee'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/export/pdf/arabic",
     *     summary="Export employees to Arabic PDF (RTL)",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Arabic PDF file download",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     )
     * )
     */
    public function exportArabicPdf(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to export employees'
            ], 403);
        }

        $filters = EmployeeFilterDTO::fromRequest([
            'company_id' => $user->company_id,
            'per_page' => 1000 // Get all employees for export
        ]);

        $result = $this->employeeService->getPaginatedEmployees($filters);
        $employees = $result['data'];

        // Create PDF using TCPDF with RTL support
        $pdf = new \TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('HR System');
        $pdf->SetAuthor($user->company_name);
        $pdf->SetTitle('Arabic Employees Report');
        $pdf->SetSubject('Employee List Export Arabic');
        
        // Set RTL
        $pdf->setRTL(true);
        
        // Set default header data
        $pdf->SetHeaderData('', 0, $user->company_name, 'Arabic Employees Report - Generated on ' . date('Y-m-d H:i:s'));
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array('helvetica', '', 12));
        $pdf->setFooterFont(Array('helvetica', '', 8));
        
        // Set margins
        $pdf->SetMargins(15, 30, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font for content - try different fonts for Unicode support
        try {
            $pdf->SetFont('dejavusans', '', 8);
        } catch (Exception $e) {
            try {
                $pdf->SetFont('freesans', '', 8);
            } catch (Exception $e2) {
                $pdf->SetFont('helvetica', '', 8);
            }
        }
        
        // Create Arabic RTL HTML table
        $html = '<style>
            body {
                direction: rtl;
                text-align: right;
                font-family: "dejavusans", Arial, sans-serif;
            }
            .report-title {
                text-align: center;
                color: #2c3e50;
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 20px;
                padding: 10px;
                border-bottom: 2px solid #3498db;
                direction: rtl;
            }
            .employee-table {
                width: 100%;
                max-width: 580px;
                border-collapse: collapse;
                font-size: 8px;
                margin-top: 10px;
                table-layout: fixed;
                margin-left: auto;
                margin-right: auto;
                direction: rtl;
            }
            .table-header {
                background-color: #3498db;
                color: white;
                font-weight: bold;
                text-align: center;
                padding: 8px 4px;
                border: 1px solid #2980b9;
                direction: rtl;
            }
            .table-cell {
                padding: 6px 4px;
                border: 1px solid #bdc3c7;
                text-align: right;
                vertical-align: middle;
                word-wrap: break-word;
                overflow: hidden;
                direction: rtl;
            }
            .table-cell-center {
                text-align: center;
            }
            .active-yes {
                color: #27ae60;
                font-weight: bold;
            }
            .active-no {
                color: #e74c3c;
                font-weight: bold;
            }
            .row-even {
                background-color: #f8f9fa;
            }
            .summary {
                text-align: center;
                color: #7f8c8d;
                font-size: 10px;
                margin-top: 20px;
                padding: 15px;
                border-top: 1px solid #bdc3c7;
                direction: rtl;
            }
        </style>';
        
        $html .= '<div class="report-title">Employees Report (RTL Layout)</div>';
        
        $html .= '<table class="employee-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th class="table-header" style="width: 8%;">Phone</th>';
        $html .= '<th class="table-header" style="width: 8%;">Status</th>';
        $html .= '<th class="table-header" style="width: 6%;">Gender</th>';
        $html .= '<th class="table-header" style="width: 8%;">Type</th>';
        $html .= '<th class="table-header" style="width: 14%;">Username</th>';
        $html .= '<th class="table-header" style="width: 20%;">Email</th>';
        $html .= '<th class="table-header" style="width: 14%;">Last Name</th>';
        $html .= '<th class="table-header" style="width: 14%;">First Name</th>';
        $html .= '<th class="table-header" style="width: 8%;">ID</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $rowCount = 0;
        foreach ($employees as $employee) {
            $rowClass = ($rowCount % 2 == 0) ? '' : 'row-even';
            $html .= '<tr class="' . $rowClass . '">';
            
            // Phone (first column in RTL)
            $phone = $employee['contact_number'] ?? '-';
            if (strlen($phone) > 8) {
                $phone = substr($phone, 0, 8) . '..';
            }
            $html .= '<td class="table-cell table-cell-center" style="width: 8%; font-size: 6px; line-height: 1.2;">' . htmlspecialchars($phone) . '</td>';
            
            // Status
            $statusClass = $employee['is_active'] ? 'active-yes' : 'active-no';
            $statusText = $employee['is_active'] ? 'Active' : 'Inactive';
            $html .= '<td class="table-cell table-cell-center ' . $statusClass . '" style="width: 8%;">' . $statusText . '</td>';
            
            // Gender
            $gender = '';
            if ($employee['gender'] == 'male') {
                $gender = 'Male';
            } elseif ($employee['gender'] == 'female') {
                $gender = 'Female';
            } else {
                $gender = '-';
            }
            $html .= '<td class="table-cell table-cell-center" style="width: 6%;">' . $gender . '</td>';
            
            // Type
            $userType = htmlspecialchars(ucfirst($employee['user_type']));
            $html .= '<td class="table-cell table-cell-center" style="width: 8%;">' . $userType . '</td>';
            
            // Username
            $html .= '<td class="table-cell" style="width: 14%;">' . htmlspecialchars($employee['username']) . '</td>';
            
            // Email
            $email = $employee['email'];
            if (strlen($email) > 25) {
                $email = substr($email, 0, 25) . '...';
            }
            $html .= '<td class="table-cell" style="width: 20%; font-size: 6px; line-height: 1.2;">' . htmlspecialchars($email) . '</td>';
            
            // Last Name
            $html .= '<td class="table-cell" style="width: 14%;">' . htmlspecialchars($employee['last_name']) . '</td>';
            
            // First Name
            $html .= '<td class="table-cell" style="width: 14%;">' . htmlspecialchars($employee['first_name']) . '</td>';
            
            // ID (last column in RTL)
            $html .= '<td class="table-cell table-cell-center" style="width: 8%;">' . htmlspecialchars($employee['user_id']) . '</td>';
            
            $html .= '</tr>';
            $rowCount++;
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        // Add Arabic summary
        $activeCount = count(array_filter($employees, fn($emp) => $emp['is_active']));
        $inactiveCount = count($employees) - $activeCount;
        
        $html .= '<div class="summary">';
        $html .= '<strong>Report Summary (RTL Layout)</strong><br>';
        $html .= 'Total Employees: <strong>' . count($employees) . '</strong> | ';
        $html .= 'Active: <strong style="color: #27ae60;">' . $activeCount . '</strong> | ';
        $html .= 'Inactive: <strong style="color: #e74c3c;">' . $inactiveCount . '</strong><br>';
        $html .= 'Generated on: ' . date('Y-m-d H:i:s') . '<br>';
        $html .= 'Company: <strong>' . htmlspecialchars($user->company_name) . '</strong>';
        $html .= '</div>';
        
        // Print text using writeHTMLCell()
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'employees_rtl_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Output PDF
        $pdfContent = $pdf->Output($filename, 'S');
        
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * @OA\Get(
     *     path="/api/employees/export/pdf/arabic-full",
     *     summary="Export employees to full Arabic PDF using mPDF",
     *     tags={"Employees"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Full Arabic PDF file download",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     )
     * )
     */
    public function exportFullArabicPdf(Request $request)
    {
        $user = $request->user();
        
        if (!$this->employeeService->canViewEmployees($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to export employees'
            ], 403);
        }

        $filters = EmployeeFilterDTO::fromRequest([
            'company_id' => $user->company_id,
            'per_page' => 1000 // Get all employees for export
        ]);

        // استخدام User model مع العلاقات بدلاً من Service - جلب الموظفين بناءً على company_name
        $employees = \App\Models\User::where('company_name', $user->company_name)
            ->with([
                'details',                      // علاقة UserDetails
                'details.department',           // علاقة Department من خلال UserDetails
                'details.designation',          // علاقة Designation من خلال UserDetails
                'details.officeShift',          // علاقة OfficeShift من خلال UserDetails
            ])
            ->get();

        // Create PDF using mPDF with full Arabic support
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L', // Landscape
            'orientation' => 'L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 30,
            'margin_bottom' => 25,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font' => 'dejavusans',
            'directionality' => 'rtl'
        ]);

        // Set document properties
        $mpdf->SetTitle('تقرير الموظفين');
        $mpdf->SetAuthor($user->company_name);
        $mpdf->SetCreator('نظام الموارد البشرية');

        // Create HTML content with full Arabic
        $html = '
        <style>
            body {
                font-family: "dejavusans", Arial, sans-serif;
                direction: rtl;
                text-align: right;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #3498db;
                padding-bottom: 15px;
            }
            .company-name {
                font-size: 18px;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 5px;
            }
            .report-title {
                font-size: 16px;
                color: #34495e;
                margin-bottom: 5px;
            }
            .report-date {
                font-size: 10px;
                color: #7f8c8d;
            }
            .employee-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-size: 9px;
            }
            .table-header {
                background-color: #3498db;
                color: white;
                font-weight: bold;
                text-align: center;
                padding: 8px 4px;
                border: 1px solid #2980b9;
            }
            .table-cell {
                padding: 6px 4px;
                border: 1px solid #bdc3c7;
                text-align: right;
                vertical-align: middle;
            }
            .table-cell-center {
                text-align: center;
            }
            .active-yes {
                color: #27ae60;
                font-weight: bold;
            }
            .active-no {
                color: #e74c3c;
                font-weight: bold;
            }
            .row-even {
                background-color: #f8f9fa;
            }
            .summary {
                text-align: center;
                margin-top: 30px;
                padding: 15px;
                border-top: 1px solid #bdc3c7;
                color: #7f8c8d;
                font-size: 10px;
            }
        </style>
        
        <div class="header">
            <div class="company-name">' . htmlspecialchars($user->company_name) . '</div>
            <div class="report-title">تقرير الموظفين</div>
            <div class="report-date">تم الإنشاء في: ' . date('Y-m-d H:i:s') . '</div>
        </div>
        
        <table class="employee-table">
            <thead>
                <tr>
                    <th class="table-header" style="width: 8%;">القسم</th>
                    <th class="table-header" style="width: 8%;">الراتب</th>
                    <th class="table-header" style="width: 6%;">الموظف</th>
                    <th class="table-header" style="width: 8%;">الموقع</th> 
                    <th class="table-header" style="width: 14%;">الحاله </th>
                    <th class="table-header" style="width: 20%;">تاريخ التعيين </th>
                    <th class="table-header" style="width: 14%;">سنوات الخبرة </th>

                </tr>
            </thead>
            <tbody>';

        $rowCount = 0;
        foreach ($employees as $employee) {
            $rowClass = ($rowCount % 2 == 0) ? '' : 'row-even';
            $html .= '<tr class="' . $rowClass . '">';
            
            // القسم - من علاقة department المحملة مسبقاً
            $department = 'غير محدد';
            if ($employee->details && $employee->details->department && $employee->details->department->department_name) {
                $department = $employee->details->department->department_name;
            }
            $html .= '<td class="table-cell table-cell-center">' . htmlspecialchars($department) . '</td>';
            
            // الراتب - من details أو قيمة افتراضية
            $salary = 'غير محدد';
            if ($employee->details && $employee->details->basic_salary) {
                $salary = $employee->details->basic_salary;
            } elseif (isset($employee->basic_salary)) {
                $salary = $employee->basic_salary;
            }
            if (is_numeric($salary)) {
                $salary = number_format($salary) . ' ريال';
            }
            $html .= '<td class="table-cell table-cell-center">' . htmlspecialchars($salary) . '</td>';
            
            // الموظف - رقم الموظف أو user_id   employee_name => first_name + last_name
            $employee_name = $employee->first_name && $employee->last_name ? $employee->first_name . ' ' . $employee->last_name : 'غير محدد';
            $html .= '<td class="table-cell table-cell-center">' . htmlspecialchars($employee_name) . '</td>';
            
            // الموقع (الوظيفة الحالية) - من علاقة designation أو user_type
            $position = '';
            if ($employee->details && $employee->details->designation && $employee->details->designation->designation_name) {
                $position = $employee->details->designation->designation_name;
            } else {
                // استخدام user_type كوظيفة افتراضية
                switch ($employee->user_type) {
                    case 'company':
                        $position = 'مدير الشركة';
                        break;
                    case 'admin':
                        $position = 'مدير عام';
                        break;
                    case 'manager':
                        $position = 'مدير قسم';
                        break;
                    case 'hr':
                        $position = 'أخصائي موارد بشرية';
                        break;
                    default:
                        $position = 'موظف';
                }
            }
            $html .= '<td class="table-cell table-cell-center">' . htmlspecialchars($position) . '</td>';
            
            // الحالة - نشط/غير نشط
            $statusClass = $employee->is_active ? 'active-yes' : 'active-no';
            $statusText = $employee->is_active ? 'نشط' : 'غير نشط';
            $html .= '<td class="table-cell table-cell-center ' . $statusClass . '">' . $statusText . '</td>';
            
            // تاريخ التعيين - من details.date_of_joining أو created_at
            $hire_date = 'غير محدد';
            if ($employee->details && $employee->details->date_of_joining) {
                $hire_date = $employee->details->date_of_joining;
            } elseif ($employee->created_at) {
                $hire_date = $employee->created_at;
            }
            
            if ($hire_date && $hire_date !== 'غير محدد') {
                try {
                    $hire_date = date('Y-m-d', strtotime($hire_date));
                } catch (\Exception $e) {
                    $hire_date = 'غير محدد';
                }
            }
            $html .= '<td class="table-cell table-cell-center">' . htmlspecialchars($hire_date) . '</td>';
            
            // سنوات الخبرة - حساب من تاريخ التعيين أو قيمة افتراضية
            $experience = 'غير محدد';
            if ($employee->details && $employee->details->date_of_joining) {
                try {
                    $hire_date_obj = new \DateTime($employee->details->date_of_joining);
                    $now = new \DateTime();
                    $diff = $now->diff($hire_date_obj);
                    $experience = $diff->y . ' سنة';
                    if ($diff->m > 0) {
                        $experience .= ' و ' . $diff->m . ' شهر';
                    }
                } catch (\Exception $e) {
                    $experience = 'غير محدد';
                }
            } elseif ($employee->details && $employee->details->experience) {
                $experience = $employee->details->experience . ' سنة';
            }
            $html .= '<td class="table-cell table-cell-center">' . htmlspecialchars($experience) . '</td>';
            
            $html .= '</tr>';
            $rowCount++;
        }

        $html .= '</tbody></table>';

        // Add Arabic summary
        $activeCount = $employees->where('is_active', 1)->count();
        $inactiveCount = $employees->count() - $activeCount;
        
        $html .= '
        <div class="summary">
            <strong>ملخص التقرير</strong><br>
            إجمالي الموظفين: <strong>' . $employees->count() . '</strong> | 
            نشط: <strong style="color: #27ae60;">' . $activeCount . '</strong> | 
            غير نشط: <strong style="color: #e74c3c;">' . $inactiveCount . '</strong><br>
            تم الإنشاء في: ' . date('Y-m-d H:i:s') . '<br>
            الشركة: <strong>' . htmlspecialchars($user->company_name) . '</strong>
        </div>';

        // Write HTML to PDF
        $mpdf->WriteHTML($html);

        $filename = 'employees_arabic_full_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Output PDF
        $pdfContent = $mpdf->Output($filename, 'S');
        
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

}
