@echo off
echo ========================================
echo WiFight ISP - XAMPP Deployment Script
echo ========================================
echo.

:: Set paths
set SOURCE_DIR=%CD%
set XAMPP_DIR=C:\xampp\htdocs\wifight

:: Stop services
echo Stopping XAMPP services...
"C:\xampp\xampp-control.exe" /stop

:: Backup existing installation
if exist "%XAMPP_DIR%" (
    echo Backing up existing installation...
    xcopy /E /I /Y "%XAMPP_DIR%" "C:\xampp\backups\wifight-%date:~-4,4%%date:~-10,2%%date:~-7,2%"
)

:: Copy files
echo Copying WiFight files...
xcopy /E /I /Y "%SOURCE_DIR%" "%XAMPP_DIR%"

:: Set environment file
echo Configuring environment...
copy "%XAMPP_DIR%\.env.example" "%XAMPP_DIR%\.env"

:: Create required directories
mkdir "%XAMPP_DIR%\storage\logs" 2>nul
mkdir "%XAMPP_DIR%\storage\cache" 2>nul
mkdir "%XAMPP_DIR%\storage\uploads" 2>nul

:: Install Composer dependencies
echo Installing Composer dependencies...
cd "%XAMPP_DIR%"
composer install --no-dev

:: Start services
echo Starting XAMPP services...
"C:\xampp\xampp-control.exe" /start

echo.
echo ========================================
echo Deployment Complete!
echo ========================================
echo.
echo Access WiFight at: http://localhost/wifight
echo Or: http://wifight.local
echo.
pause