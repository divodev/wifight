# WiFight ISP System - API Endpoints

Complete API documentation for the WiFight ISP System.

## Authentication
POST /api/v1/auth/login - User login
POST /api/v1/auth/register - User registration
POST /api/v1/auth/refresh - Refresh token

## User Management
GET /api/v1/users - List users
POST /api/v1/users - Create user
PUT /api/v1/users/{id} - Update user

## Controllers
GET /api/v1/controllers - List controllers
POST /api/v1/controllers - Add controller

## Plans
GET /api/v1/plans - List plans

## Sessions
GET /api/v1/sessions - List sessions
DELETE /api/v1/sessions/{id} - Disconnect session

## System
GET /api/v1/health - Health check
