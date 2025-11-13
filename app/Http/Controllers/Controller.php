<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="HR Management API",
 *     version="1.0.0",
 *     description="API for HR Management System with Employee and Leave Management",
 *     @OA\Contact(
 *         email="admin@hrapi.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum Token Authentication"
 * )
 */
abstract class Controller
{
    //
}
