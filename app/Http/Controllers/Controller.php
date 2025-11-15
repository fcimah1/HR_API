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
 *     securityScheme="passport",
 *     type="oauth2",
 *     flows={
 *         @OA\Flow(
 *             flow="password",
 *             tokenUrl="/api/login",
 *             scopes={}
 *         )
 *     },
 *     description="OAuth2 Password Grant Authentication"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Bearer Token Authentication"
 * )
 */
abstract class Controller
{
    //
}
