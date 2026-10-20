
@echo off
setlocal EnableExtensions DisableDelayedExpansion

REM Usage: restore_mysql.cmd RESTORE_DB DB_USER BACKUP_FILE.sql
REM Safety: restore only to researchflow_restore.
REM Destination must exist and be empty.

if "%~1"=="" goto usage
if "%~2"=="" goto usage
if "%~3"=="" goto usage

set "DB_NAME=%~1"
set "DB_USER=%~2"
set "BACKUP_FILE=%~f3"

if /I not "%DB_NAME%"=="researchflow_restore" (
    echo ERROR: Restore allowed only into researchflow_restore.
    exit /b 1
)

if not exist "%BACKUP_FILE%" (
    echo Input file missing
    exit /b 1
)

set "MYSQL_EXE="
if exist "C:\xampp\mysql\bin\mysql.exe" (
    set "MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe"
) else (
    for %%I in (mysql.exe) do set "MYSQL_EXE=%%~$PATH:I"
)

if not defined MYSQL_EXE (
    echo ERROR: mysql.exe not found.
    exit /b 1
)


echo Checking destination database...

set "COUNT_FILE=%TEMP%\researchflow_restore_check_%RANDOM%_%RANDOM%.txt"

"%MYSQL_EXE%" -u "%DB_USER%" -p -N -B -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='researchflow_restore';" > "%COUNT_FILE%"

if errorlevel 1 (
    if exist "%COUNT_FILE%" del "%COUNT_FILE%"
    echo ERROR: Cannot verify destination.
    exit /b 1
)

set "TABLE_COUNT="
set /p TABLE_COUNT=<"%COUNT_FILE%"
del "%COUNT_FILE%"

if not "%TABLE_COUNT%"=="0" (
    echo ERROR: Destination database is not empty or does not exist.
    exit /b 1
)

echo Destination database is empty

echo IMPORTANT: Continue only if researchflow_restore is empty.
echo Restore will be performed into researchflow_restore only.
echo Type YES to continue:
set /p "CONFIRM="

if not "%CONFIRM%"=="YES" (
    echo Restore cancelled.
    exit /b 1
)

"%MYSQL_EXE%" --default-character-set=utf8mb4 -u "%DB_USER%" -p "%DB_NAME%" < "%BACKUP_FILE%"

if errorlevel 1 (
    echo ERROR: Restore failed. Destination may be partially populated.
    exit /b 1
)

echo Restore completed. Validate counts and checksums.
exit /b 0

:usage
echo Usage: restore_mysql.cmd RESTORE_DB DB_USER BACKUP_FILE.sql
exit /b 2
