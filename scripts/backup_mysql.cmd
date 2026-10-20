
@echo off
setlocal EnableExtensions DisableDelayedExpansion

REM Usage: backup_mysql.cmd DB_NAME DB_USER BACKUP_FILE.sql

if "%~1"=="" goto usage
if "%~2"=="" goto usage
if "%~3"=="" goto usage

set "DB_NAME=%~1"
set "DB_USER=%~2"
set "BACKUP_FILE=%~f3"
set "TEMP_FILE=%BACKUP_FILE%.partial"

REM Use XAMPP or find mysqldump in PATH.
set "DUMP_EXE="
if exist "C:\xampp\mysql\bin\mysqldump.exe" (
    set "DUMP_EXE=C:\xampp\mysql\bin\mysqldump.exe"
) else (
    for %%I in (mysqldump.exe) do set "DUMP_EXE=%%~$PATH:I"
)

if not defined DUMP_EXE (
    echo ERROR: mysqldump.exe not found.
    exit /b 1
)

if exist "%BACKUP_FILE%" (
    echo ERROR: Backup already exists.
    exit /b 1
)

if exist "%TEMP_FILE%" (
    echo ERROR: Partial backup already exists.
    exit /b 1
)

"%DUMP_EXE%" --single-transaction --default-character-set=utf8mb4 --no-tablespaces -u "%DB_USER%" -p --result-file="%TEMP_FILE%" "%DB_NAME%"

if errorlevel 1 (
    echo ERROR: Backup failed.
    if exist "%TEMP_FILE%" del /q "%TEMP_FILE%"
    exit /b 1
)

if not exist "%TEMP_FILE%" (
    echo ERROR: No backup file created.
    exit /b 1
)

for %%F in ("%TEMP_FILE%") do (
    if %%~zF EQU 0 (
        echo ERROR: Empty backup.
        del /q "%TEMP_FILE%"
        exit /b 1
    )
)

if exist "%BACKUP_FILE%" (
    echo ERROR: Backup destination appeared during export.
    exit /b 1
)

move "%TEMP_FILE%" "%BACKUP_FILE%" >nul

if errorlevel 1 (
    echo ERROR: Cannot finalize backup.
    exit /b 1
)

echo Backup created: %BACKUP_FILE%
exit /b 0

:usage
echo Usage: backup_mysql.cmd DB_NAME DB_USER BACKUP_FILE.sql
exit /b 2
