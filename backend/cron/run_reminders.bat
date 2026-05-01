@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
set "PHP_BIN=C:\xampp\php\php.exe"
set "PHP_SCRIPT=%SCRIPT_DIR%run_reminders.php"
set "LOG_DIR=%SCRIPT_DIR%logs"
set "LOG_FILE=%LOG_DIR%\run_reminders.log"

if not exist "%PHP_BIN%" (
    echo PHP executable not found at "%PHP_BIN%"
    exit /b 1
)

if not exist "%LOG_DIR%" (
    mkdir "%LOG_DIR%"
)

echo ==== %date% %time% ==== >> "%LOG_FILE%"
"%PHP_BIN%" "%PHP_SCRIPT%" >> "%LOG_FILE%" 2>&1
echo. >> "%LOG_FILE%"

endlocal
