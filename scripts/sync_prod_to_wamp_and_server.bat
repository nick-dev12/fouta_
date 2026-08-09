@echo off
setlocal
cd /d "%~dp0.."

set PHP_EXE=
for %%P in (
  "C:\wamp64\bin\php\php8.3.14\php.exe"
  "C:\wamp64\bin\php\php8.2.26\php.exe"
  "C:\wamp64\bin\php\php8.1.31\php.exe"
  "C:\wamp64\bin\php\php8.0.30\php.exe"
) do if exist %%P set PHP_EXE=%%~P

if "%PHP_EXE%"=="" (
  where php >nul 2>&1
  if errorlevel 1 (
    echo PHP introuvable. Installez WAMP ou ajoutez php au PATH.
    pause
    exit /b 1
  )
  set PHP_EXE=php
)

echo.
echo ========================================
echo  Fouta - Sync Production -^> WAMP -^> Serveur local
echo ========================================
echo.

"%PHP_EXE%" scripts\sync_prod_to_wamp_and_server.php %*

echo.
pause
